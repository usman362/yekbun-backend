<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Clips;
use App\Models\Feed;
use App\Models\KycVerification;
use App\Models\User;
use App\Models\UserFriends;
use App\Models\UserImage;
use App\Models\UserVideo;
use App\Models\Wallet;
use App\Models\FeedComments;
use App\Services\BunnyCDNService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $tab     = $request->get('tab', 'male');
        $search  = $request->get('search', '');
        $level   = $request->get('level');

        $query = User::query();

        if ($tab === 'male') {
            $query->where('gender', 'male')->where('status', 1);
        } elseif ($tab === 'female') {
            $query->where('gender', 'female')->where('status', 1);
        } elseif ($tab === 'closed') {
            $query->where('status', 0);
        }

        if ($level && $level !== 'all') {
            $levelMap = ['cultivated' => 1, 'educated' => 2, 'academic' => 3, 'flagged' => 4];
            if (isset($levelMap[$level])) {
                $query->where('level', $levelMap[$level]);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $paginated = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, [
                'name', 'email', 'username', 'user_id', 'image',
                'gender', 'status', 'device_type', 'device_imei',
                'device_model', 'device_serial', 'created_at',
            ]);

        $users = collect($paginated->items())->map(function ($u) {
            return [
                'id'           => $u->_id,
                'name'         => $u->name ?? '',
                'email'        => $u->email ?? '',
                'username'     => $u->username ?? '',
                'userId'       => $u->user_id ?? '',
                'avatar'       => Helpers::mediaUrl($u->image) ?? '',
                'gender'       => $u->gender ?? 'male',
                'status'       => $u->status == 1 ? 'active' : 'closed',
                'deviceType'   => $u->device_type ?? 'android',
                'deviceImei'   => $u->device_imei ?? '',
                'deviceModel'  => $u->device_model ?? '',
                'serialNumber' => $u->device_serial ?? 'unknown',
                'joinDate'     => $u->created_at ? Carbon::parse($u->created_at)->format('d/m/Y') : '',
            ];
        });

        return ResponseHelper::sendResponse([
            'users'       => $users,
            'total'       => $paginated->total(),
            'page'        => $paginated->currentPage(),
            'last_page'   => $paginated->lastPage(),
            'per_page'    => $paginated->perPage(),
        ], 'Users fetched.');
    }

    public function stats()
    {
        $total  = User::count();
        $male   = User::where('gender', 'male')->where('status', 1)->count();
        $female = User::where('gender', 'female')->where('status', 1)->count();
        $closed = User::where('status', 0)->count();

        return ResponseHelper::sendResponse([
            'total'  => $total,
            'male'   => $male,
            'female' => $female,
            'closed' => $closed,
        ], 'User stats fetched.');
    }

    public function details($id)
    {
        $u = User::find($id);
        if (!$u) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }

        $levelLabels = [1 => 'Cultivated User', 2 => 'Educated User', 3 => 'Academic User', 4 => 'Flagged User'];
        $levelLabel = $levelLabels[(int) ($u->level ?? 0)] ?? 'User';

        // Counts
        $feedsCount  = Feed::where('user_id', $u->_id)->count();
        $friendsCount = UserFriends::where('user_id', $u->_id)
            ->whereIn('user_type', ['friends', 'family'])->count();
        $photosCount = UserImage::where('user_id', $u->_id)->count();
        $videosCount = UserVideo::where('user_id', $u->_id)->count();
        $reelsCount  = Clips::where('user_id', $u->_id)->count();

        // Recent feeds
        $posts = Feed::where('user_id', $u->_id)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($f) {
                $images = is_array($f->images) ? $f->images : [];
                $videos = is_array($f->videos) ? $f->videos : [];
                $firstImage = $images[0]['path'] ?? $f->image ?? null;
                // Feed videos are stored in the `videos` array ([{path,...}]) — NOT a `video`
                // field — so a video post was being mis-classified as "text".
                $video = $videos[0]['path'] ?? ($f->video ?? null);
                // Classify the feed so the card shows a video player / image / text
                // instead of a generic gradient with no hint of the content type.
                $type = $video ? 'video' : ($firstImage ? 'image' : 'text');
                return [
                    'id'       => $f->_id,
                    'title'    => $f->text ? Str::limit((string) $f->text, 40) : ucfirst($type) . ' Post',
                    'text'     => $f->text ?? '',
                    'image'    => $firstImage ? Helpers::mediaUrl($firstImage) : null,
                    'video'    => $video ? Helpers::mediaUrl($video) : null,
                    'type'     => $type,
                    'views'    => (int) ($f->views_count ?? 0),
                    'comments' => (int) ($f->comments_count ?? 0),
                    'time'     => $f->created_at ? Carbon::parse($f->created_at)->format('Y-m-d') : '',
                    'status'   => ($f->is_deleted ?? false) ? 'expired' : 'active',
                ];
            })->values();

        // Friends with their info
        $friendRows = UserFriends::where('user_id', $u->_id)
            ->whereIn('user_type', ['friends', 'family'])
            ->limit(8)
            ->get();
        $friendIds = $friendRows->pluck('friend_id')->filter()->toArray();
        $friendUsers = User::whereIn('_id', $friendIds)->get()->keyBy('_id');
        $friends = $friendRows->map(function ($fr) use ($friendUsers) {
            $fu = $friendUsers->get($fr->friend_id);
            if (!$fu) return null;
            return [
                'id'     => $fu->_id,
                'name'   => $fu->name ?? $fu->username ?? 'User',
                'role'   => $fr->user_type === 'family' ? 'Family' : 'Friend',
                'status' => ($fu->status ?? 1) == 1 ? 'online' : 'offline',
                'avatar' => Helpers::mediaUrl($fu->image) ?? '',
            ];
        })->filter()->values();

        // Photos
        $photos = UserImage::where('user_id', $u->_id)
            ->orderBy('created_at', 'desc')
            ->limit(12)
            ->get()
            ->map(function ($img) {
                return [
                    'id'    => $img->_id,
                    'image' => Helpers::mediaUrl($img->image) ?? '',
                ];
            })->values();

        // Videos
        $videos = UserVideo::where('user_id', $u->_id)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($v) {
                return [
                    'id'        => $v->_id,
                    'title'     => 'Video',
                    'video'     => Helpers::mediaUrl($v->video) ?? '',
                    'thumbnail' => Helpers::mediaUrl($v->thumbnail) ?? '',
                    'views'     => 0,
                    'duration'  => '',
                ];
            })->values();

        // Reels (clips)
        $reels = Clips::where('user_id', $u->_id)
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->map(function ($c) {
                return [
                    'id'        => $c->_id,
                    'title'     => $c->title ?? 'Clip',
                    'thumbnail' => Helpers::mediaUrl($c->thumbnail) ?? '',
                    'video'     => Helpers::mediaUrl($c->video ?? $c->video_path) ?? '',
                    'views'     => 0,
                    'duration'  => '',
                ];
            })->values();

        // Wallet — source of truth is `wallets` + `kyc_verifications` ONLY.
        //
        // We deliberately do NOT fall back to legacy user-table flags (kyc_otp_verified,
        // user.wallet_status, user.kyc_status) any more. Those caused stale state to linger when
        // the wallet/kyc rows themselves were deleted. The Wallet Request tab now shows up if and
        // only if a real wallet or KYC record exists for this user.
        $userIdStr = (string) $u->_id;
        $wallet = Wallet::where('user_id', $userIdStr)->first();
        $kyc = KycVerification::where(function ($q) use ($userIdStr, $u) {
            $q->where('user_id', $userIdStr)->orWhere('user_id', $u->_id);
        })->orderBy('created_at', 'desc')->first();

        $walletData = null;
        if ($wallet || $kyc) {
            // Status precedence: wallets.status > kyc.status > 'pending'.
            $statusRaw = $wallet?->status ?? $kyc?->status ?? 'pending';

            // Normalise legacy values into the 3 states the dashboard cares about.
            $status = in_array($statusRaw, ['active', 'activated', 'approved'], true)
                ? 'active'
                : (in_array($statusRaw, ['rejected', 'closed', 'suspended', 'deactivated'], true)
                    ? 'rejected'
                    : 'pending');

            $createdSource = $wallet?->created_at ?? $kyc?->submitted_at ?? $kyc?->created_at;
            $updatedSource = $wallet?->updated_at ?? $kyc?->reviewed_at;
            $sourceId = $wallet?->_id ?? $kyc?->_id;

            $walletData = [
                'exists'             => true,
                'status'             => $status,
                'request_id'         => $sourceId ? 'WR-' . strtoupper(substr((string) $sourceId, -8)) : '',
                'request_date'       => $createdSource ? Carbon::parse($createdSource)->format('Y-m-d') : '',
                'wallet_id'          => $wallet ? 'WLT-' . strtoupper(substr((string) $wallet->_id, -6)) : '',
                'balance'            => number_format((float) ($wallet?->balance ?? 0), 2) . ' ZER',
                'cashback_balance'   => number_format((float) ($wallet?->cashback_balance ?? 0), 2) . ' ZER',
                'wallet_status'      => $status === 'active' ? 'Active' : ucfirst($status),
                'verification_level' => $kyc && $kyc->status === 'approved' ? 'Plus' : 'Basic',
                'country'            => $u->country ?? '',
                'kyc_status'         => $kyc ? (string) ($kyc->status ?? 'Under Review') : 'Not Submitted',
                'activation_date'    => $status === 'active' && $updatedSource
                    ? Carbon::parse($updatedSource)->format('Y-m-d')
                    : '',
                'documents'          => $kyc ? [
                    'front'  => Helpers::mediaUrl($kyc->front_image) ?? null,
                    'back'   => Helpers::mediaUrl($kyc->back_image) ?? null,
                    'selfie' => Helpers::mediaUrl($kyc->selfie_image) ?? null,
                ] : null,
            ];
        }

        return ResponseHelper::sendResponse([
            'user' => [
                'id'           => $u->_id,
                'name'         => $u->name ?? $u->username ?? 'User',
                'username'     => $u->username ?? '',
                'user_id'      => $u->user_id ?? '',
                'email'        => $u->email ?? '',
                'phone'        => $u->phone ?? '',
                'country'      => $u->country ?? '',
                'origin'       => $u->origin ?? '',
                'image'        => Helpers::mediaUrl($u->image) ?? '',
                'level_label'  => $levelLabel,
                'status'       => ($u->status ?? 1) == 1 ? 'active' : 'closed',
                'gender'       => $u->gender ?? '',
                'member_since' => $u->created_at ? Carbon::parse($u->created_at)->format('d/m/Y') : '',
                'subscription' => $wallet ? 'Premium' : 'Standard',
                'device_type'  => $u->device_type ?? '',
                'device_model' => $u->device_model ?? '',
                'device_imei'  => $u->device_imei ?? '',
            ],
            'counts' => [
                'feeds'   => $feedsCount,
                'friends' => $friendsCount,
                'photos'  => $photosCount,
                'videos'  => $videosCount,
                'reels'   => $reelsCount,
            ],
            'posts'   => $posts,
            'friends' => $friends,
            'photos'  => $photos,
            'videos'  => $videos,
            'reels'   => $reels,
            'wallet'  => $walletData,
        ], 'User details loaded.');
    }

    /**
     * Approve the user's wallet + KYC.
     *
     * Source-of-truth design (per data-integrity feedback): wallet state lives in `wallets`,
     * KYC state lives in `kyc_verifications`. We do NOT mirror these onto the `users` document
     * any more — that caused stale flags to remain on the user when wallet/kyc rows were deleted.
     * Reads must derive status from the proper collections.
     *
     * As part of every admin action we also `$unset` the legacy wallet/kyc flags from the user
     * doc so old data submitted by mobile (kyc_status: 'pending', kyc_otp_verified, wallet_id,
     * wallet_status, etc.) doesn't linger as orphan state.
     */
    public function walletAccept($id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }

        $userIdStr = (string) $user->_id;

        // Wallet: ensure a row exists, then mark it activated with a friendly status message.
        // We use the canonical mobile status string `activated` (not `active`) so the mobile
        // `$walletStatusMessages['activated']` lookup actually matches and returns a message.
        //
        // NOTE: We deliberately DO NOT grant the welcome bonus here. The bonus is claimed by the
        // mobile user via the in-app popup after they set their wallet PIN — that flow lives in
        // `WalletApiController::verifyPin`. Auto-granting here breaks the popup (it never appears
        // because `welcome_bonus_claimed` is already true).
        $wallet = Wallet::where('user_id', $userIdStr)->first();
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = $userIdStr;
            $wallet->balance = 0;
        }
        $wallet->status = 'activated';
        $wallet->status_reason = null;
        $wallet->status_message = 'All wallet features are now available.';
        $wallet->save();

        // KYC: per-record save (reliable on MongoDB) with type-safe matching for the user_id field.
        $kycs = KycVerification::where(function ($q) use ($userIdStr, $user) {
            $q->where('user_id', $userIdStr)->orWhere('user_id', $user->_id);
        })->get();

        if ($kycs->isEmpty()) {
            // No KYC submission row exists yet (e.g. mobile user only verified OTP but never
            // uploaded documents). Create one so the mobile `/kyc/status` endpoint returns the
            // right state — otherwise it would still say `has_kyc: false`.
            $kyc = new KycVerification();
            $kyc->user_id = $userIdStr;
            $kyc->status = 'approved';
            $kyc->submitted_at = Carbon::now();
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();
            $kycs = collect([$kyc]);
        } else {
            foreach ($kycs as $kyc) {
                $kyc->status = 'approved';
                $kyc->reviewed_at = Carbon::now();
                $kyc->rejection_reason = null;
                $kyc->save();
            }
        }

        // Strip the legacy wallet/kyc flags from the user document so they don't linger as stale
        // duplicates of the now-authoritative wallets/kyc_verifications rows.
        $this->stripLegacyWalletFields($user);

        return ResponseHelper::sendResponse([
            'wallet_id'           => (string) $wallet->_id,
            'wallet_status'       => 'activated',
            'kyc_status'          => 'approved',
            'kyc_records_updated' => $kycs->count(),
            'balance'             => (float) $wallet->balance,
        ], 'Wallet activated.');
    }

    /**
     * Reject the wallet + KYC.
     *
     * Same design rule as walletAccept — no user-table writes. The wallet row is created if it
     * doesn't exist so the rejection is still recorded (KYC-only requests are valid).
     */
    public function walletReject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }

        $userIdStr = (string) $user->_id;

        $wallet = Wallet::where('user_id', $userIdStr)->first();
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = $userIdStr;
            $wallet->balance = 0;
        }
        $wallet->status = 'rejected';
        $wallet->status_reason = $request->reason;
        $wallet->save();

        $kycs = KycVerification::where(function ($q) use ($userIdStr, $user) {
            $q->where('user_id', $userIdStr)->orWhere('user_id', $user->_id);
        })->get();

        if ($kycs->isEmpty()) {
            // Synthesise a rejected KYC row so mobile sees consistent state (see walletAccept comment).
            $kyc = new KycVerification();
            $kyc->user_id = $userIdStr;
            $kyc->status = 'rejected';
            $kyc->rejection_reason = $request->reason;
            $kyc->submitted_at = Carbon::now();
            $kyc->reviewed_at = Carbon::now();
            $kyc->save();
            $kycs = collect([$kyc]);
        } else {
            foreach ($kycs as $kyc) {
                $kyc->status = 'rejected';
                $kyc->rejection_reason = $request->reason;
                $kyc->reviewed_at = Carbon::now();
                $kyc->save();
            }
        }

        // Clean up legacy user-table flags so the user record doesn't keep `kyc_status: pending`
        // or similar after we've moved the authoritative state to wallets/kyc_verifications.
        $this->stripLegacyWalletFields($user);

        return ResponseHelper::sendResponse([
            'wallet_status'       => 'rejected',
            'kyc_status'          => 'rejected',
            'reason'              => $request->reason,
            'kyc_records_updated' => $kycs->count(),
        ], 'Wallet rejected.');
    }

    /**
     * Remove legacy wallet/kyc duplicate fields from a user document via MongoDB `$unset`.
     *
     * Mobile flows (KycApiController::submit, WalletApiController::*) historically wrote a copy
     * of wallet/kyc status onto the user record. We're moving away from that — the wallets +
     * kyc_verifications collections are now the only source of truth. Running this on every
     * admin approval/rejection keeps the user document tidy as old data ages out.
     */
    private function stripLegacyWalletFields(User $user): void
    {
        User::raw(function ($collection) use ($user) {
            $collection->updateOne(
                ['_id' => $user->_id],
                ['$unset' => [
                    'wallet_status'         => '',
                    'wallet_status_reason'  => '',
                    'wallet_id'             => '',
                    'wallet_balance'        => '',
                    'wallet_pin'            => '',
                    'wallet_created_at'     => '',
                    'wallet_expire_at'      => '',
                    'kyc_status'            => '',
                    'kyc_otp'               => '',
                    'kyc_otp_expires_at'    => '',
                    'kyc_otp_verified'      => '',
                    'zer_balance'           => '',
                ]]
            );
        });
    }

    /**
     * DELETE /admin/users/{id} — permanently delete a user and EVERYTHING linked to them:
     * feeds, clips, comments, likes/views/shares, wallet, transactions, playlists, friendships,
     * KYC, reports, notifications, device rows — plus every uploaded media file from the CDN.
     *
     * Admin / superadmin accounts are refused. All deletes are best-effort (wrapped) so one
     * missing collection or a transient CDN error never leaves the account half-deleted.
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        // Safety rail: never delete an admin/superadmin from this endpoint.
        if ((int) ($user->is_admin_user ?? 0) === 1 || (int) ($user->is_superadmin ?? 0) === 1) {
            return ResponseHelper::sendResponse(null, 'Admin accounts cannot be deleted.', false, 403);
        }

        $uid = (string) $user->_id;
        $bunny = new BunnyCDNService();

        // Delete a single CDN file from its stored path (absolute URL or CDN-relative). Best-effort.
        $cdnBase = (string) env('BUNNY_CDN_URL');
        $del = function ($path) use ($bunny, $cdnBase) {
            if (empty($path) || !is_string($path)) return;
            $rel = ltrim($cdnBase !== '' ? Str::after($path, $cdnBase) : $path, '/');
            if ($rel === '' || Str::startsWith($rel, ['http://', 'https://'])) return;
            try { $bunny->delete($rel); } catch (\Throwable $e) {}
        };

        // ── 1) Remove uploaded media from the CDN ──
        $del($user->image);

        foreach (Feed::where('user_id', $uid)->get() as $f) {
            foreach ((array) ($f->images ?? []) as $img) $del(is_array($img) ? ($img['path'] ?? null) : null);
            foreach ((array) ($f->videos ?? []) as $vid) $del(is_array($vid) ? ($vid['path'] ?? null) : null);
        }
        foreach (Clips::where('user_id', $uid)->get() as $c) { $del($c->clip); $del($c->thumbnail); }
        foreach (UserImage::where('user_id', $uid)->get() as $ui) $del($ui->image);
        foreach (UserVideo::where('user_id', $uid)->get() as $uv) $del($uv->video);
        foreach (FeedComments::where('user_id', $uid)->get() as $fc) { $del($fc->audio); $del($fc->image); }
        foreach (KycVerification::where('user_id', $uid)->get() as $k) {
            $del($k->front_image); $del($k->back_image); $del($k->selfie_image);
        }

        // ── 2) Remove all DB rows keyed by this user_id ──
        $byUser = [
            Feed::class, Clips::class, UserImage::class, UserVideo::class, Wallet::class, KycVerification::class,
            FeedComments::class,
            \App\Models\Comment::class, \App\Models\FeedLikes::class, \App\Models\FeedViews::class,
            \App\Models\FeedShare::class, \App\Models\ClipsLikes::class, \App\Models\ClipsViews::class,
            \App\Models\CommentsLike::class, \App\Models\Transaction::class, \App\Models\UserPlaylist::class,
            \App\Models\UserPlaylistGroup::class, \App\Models\ArtistFavorite::class, \App\Models\MusicPlay::class,
            \App\Models\VideoPlay::class, \App\Models\SongViews::class, \App\Models\VideoClipViews::class,
            \App\Models\NotificationCenter::class, \App\Models\UserCode::class, \App\Models\UserImei::class,
            \App\Models\Report::class, \App\Models\ReportComments::class, \App\Models\ReportFeeds::class,
            \App\Models\ReportUsers::class, \App\Models\Media::class,
        ];
        foreach ($byUser as $model) {
            try { $model::where('user_id', $uid)->delete(); } catch (\Throwable $e) {}
        }

        // Friendships (both directions) + reports filed AGAINST this user.
        try { UserFriends::where('user_id', $uid)->orWhere('friend_id', $uid)->delete(); } catch (\Throwable $e) {}
        try { \App\Models\ReportUsers::where('reported_user_id', $uid)->delete(); } catch (\Throwable $e) {}

        // ── 3) Finally, the user ──
        $user->delete();

        return ResponseHelper::sendResponse(null, 'User and all related data have been deleted.');
    }
}
