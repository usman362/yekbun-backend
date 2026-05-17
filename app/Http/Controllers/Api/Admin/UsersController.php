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
                $firstImage = $images[0]['path'] ?? $f->image ?? null;
                return [
                    'id'       => $f->_id,
                    'title'    => $f->text ? Str::limit((string) $f->text, 40) : 'Post',
                    'text'     => $f->text ?? '',
                    'image'    => $firstImage ? Helpers::mediaUrl($firstImage) : null,
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

        // Wallet — show the Wallet Request tab whenever ANY of these is true:
        //   1. A `wallets` record exists for this user (full wallet flow).
        //   2. A `kyc_verifications` submission exists (KYC submitted, wallet not yet created).
        //   3. The user document itself carries a `wallet_status` / `kyc_status` (legacy mobile path
        //      where mobile updates the user record directly without creating a Wallet row first).
        // This guarantees admins always see the request as soon as the user starts the flow on mobile.
        $wallet = Wallet::where('user_id', $u->_id)->first();
        $kyc = KycVerification::where('user_id', $u->_id)->orderBy('created_at', 'desc')->first();

        $userWalletStatus = (string) ($u->wallet_status ?? '');
        $userKycStatus    = (string) ($u->kyc_status ?? '');
        $hasUserKycSignal = $userKycStatus !== '' && $userKycStatus !== 'not_submitted';
        $hasUserWalletSignal = $userWalletStatus !== '' && $userWalletStatus !== 'not_found';
        // Mobile sets `kyc_otp_verified` once the user receives + confirms the OTP, even before they
        // upload any documents. Admins should still see this row so they know a request is in progress.
        $hasOtpSignal = !empty($u->kyc_otp_verified);

        $walletData = null;
        if ($wallet || $kyc || $hasUserKycSignal || $hasUserWalletSignal || $hasOtpSignal) {
            // Resolve a single status precedence:
            //   wallets.status > users.wallet_status > kyc.status > users.kyc_status > 'pending'
            $statusRaw = $wallet?->status
                ?? ($hasUserWalletSignal ? $userWalletStatus : null)
                ?? $kyc?->status
                ?? ($hasUserKycSignal ? $userKycStatus : null)
                ?? 'pending';

            // Normalise the various legacy values into the 3 states the dashboard cares about.
            $status = in_array($statusRaw, ['active', 'activated', 'approved'], true)
                ? 'active'
                : (in_array($statusRaw, ['rejected', 'closed', 'suspended', 'deactivated'], true)
                    ? 'rejected'
                    : 'pending');

            $createdSource = $wallet?->created_at ?? $kyc?->submitted_at ?? $kyc?->created_at ?? $u->created_at;
            $updatedSource = $wallet?->updated_at ?? $kyc?->reviewed_at ?? $u->updated_at;
            $sourceId = $wallet?->_id ?? $kyc?->_id ?? $u->_id;

            $walletData = [
                'exists'             => true,
                'status'             => $status,
                'request_id'         => 'WR-' . strtoupper(substr((string) $sourceId, -8)),
                'request_date'       => $createdSource ? Carbon::parse($createdSource)->format('Y-m-d') : '',
                'wallet_id'          => $wallet
                    ? 'WLT-' . strtoupper(substr((string) $wallet->_id, -6))
                    : ((string) ($u->wallet_id ?? '')),
                'balance'            => number_format((float) ($wallet?->balance ?? $u->wallet_balance ?? 0), 2) . ' ZER',
                'cashback_balance'   => number_format((float) ($u->cashback_balance ?? 0), 2) . ' ZER',
                'wallet_status'      => $status === 'active' ? 'Active' : ucfirst($status),
                'verification_level' => $kyc && $kyc->status === 'approved' ? 'Plus' : 'Basic',
                'country'            => $u->country ?? '',
                'kyc_status'         => $kyc
                    ? (string) ($kyc->status ?? 'Under Review')
                    : ($userKycStatus !== '' ? $userKycStatus : 'Not Submitted'),
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
     * Approve the user's wallet + KYC. This is the dashboard counterpart of the user-driven KYC
     * flow on mobile — we need to update everything the mobile/api layer reads so the user sees
     * their wallet active immediately:
     *
     *   - `wallets.status`               → `active`   (created if missing — admins sometimes
     *                                                 approve users who only verified OTP and
     *                                                 never had a wallets row written by mobile)
     *   - `users.wallet_status`          → `activated` (legacy field used by mobile profile API)
     *   - `users.wallet_id`              → assign a short ID if missing
     *   - `users.kyc_status`             → `approved`
     *   - `kyc_verifications.status`     → `approved` + `reviewed_at` (used by KycApiController)
     */
    public function walletAccept($id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }

        // Find or create the wallet record. Mobile usually creates this on first wallet activation,
        // but if the admin is approving from the dashboard before mobile got that far we still want
        // to flip the status correctly.
        $wallet = Wallet::where('user_id', (string) $user->_id)->first();
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = (string) $user->_id;
            $wallet->balance = 0;
        }
        $wallet->status = 'active';
        $wallet->status_reason = null;
        $wallet->save();

        // Reflect on the user record so the mobile profile + admin lists agree.
        $user->wallet_status = 'activated';
        $user->kyc_status = 'approved';
        if (empty($user->wallet_id)) {
            $user->wallet_id = strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
        }
        $user->save();

        // Mark the underlying KYC submission(s) approved so KycApiController returns the right
        // status. Mobile sometimes stores `user_id` as ObjectId, the dashboard sometimes as a
        // plain string — match both forms and update every pending row to be safe.
        $userIdStr = (string) $user->_id;
        $kycQuery = KycVerification::where(function ($q) use ($userIdStr, $user) {
            $q->where('user_id', $userIdStr)->orWhere('user_id', $user->_id);
        });
        $kycQuery->update([
            'status'           => 'approved',
            'reviewed_at'      => Carbon::now(),
            'rejection_reason' => null,
        ]);

        return ResponseHelper::sendResponse([
            'status'       => 'active',
            'wallet_id'    => $user->wallet_id,
            'kyc_status'   => 'approved',
        ], 'Wallet activated.');
    }

    /**
     * Reject the wallet + KYC. Mirrors walletAccept but pushes everything to the rejected state
     * and persists the admin-supplied reason on both the wallet and the KYC record.
     *
     * As with walletAccept, we don't require an existing wallet record — KYC-only requests
     * (mobile user verified OTP but never got far enough to create a wallets row) are still
     * valid requests an admin should be able to decline.
     */
    public function walletReject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found', false, 404);
        }

        $wallet = Wallet::where('user_id', (string) $user->_id)->first();
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->user_id = (string) $user->_id;
            $wallet->balance = 0;
        }
        $wallet->status = 'rejected';
        $wallet->status_reason = $request->reason;
        $wallet->save();

        $user->wallet_status = 'rejected';
        $user->wallet_status_reason = $request->reason;
        $user->kyc_status = 'rejected';
        $user->save();

        // Bulk-update all KYC rows for this user (handles ObjectId vs string user_id variants).
        $userIdStr = (string) $user->_id;
        KycVerification::where(function ($q) use ($userIdStr, $user) {
            $q->where('user_id', $userIdStr)->orWhere('user_id', $user->_id);
        })->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
            'reviewed_at'      => Carbon::now(),
        ]);

        return ResponseHelper::sendResponse([
            'status'     => 'rejected',
            'kyc_status' => 'rejected',
            'reason'     => $request->reason,
        ], 'Wallet rejected.');
    }
}
