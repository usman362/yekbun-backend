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

        // Wallet
        $wallet = Wallet::where('user_id', $u->_id)->first();
        $kyc = KycVerification::where('user_id', $u->_id)->orderBy('created_at', 'desc')->first();
        $walletData = null;
        if ($wallet) {
            $walletStatus = (string) ($wallet->status ?? 'pending');
            $walletData = [
                'exists'             => true,
                'status'             => $walletStatus,
                'request_id'         => 'WR-' . substr((string) $wallet->_id, -8),
                'request_date'       => $wallet->created_at ? Carbon::parse($wallet->created_at)->format('Y-m-d') : '',
                'wallet_id'          => 'WLT-' . substr((string) $wallet->_id, -6),
                'balance'            => number_format((float) ($wallet->balance ?? 0), 2) . ' ZER',
                'cashback_balance'   => '0.00 ZER',
                'wallet_status'      => $walletStatus === 'active' ? 'Active' : ucfirst($walletStatus),
                'verification_level' => $kyc ? 'Plus' : 'Basic',
                'country'            => $u->country ?? '',
                'kyc_status'         => $kyc ? (string) ($kyc->status ?? 'Under Review') : 'Not Submitted',
                'activation_date'    => $walletStatus === 'active' && $wallet->updated_at
                    ? Carbon::parse($wallet->updated_at)->format('Y-m-d') : '',
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

    public function walletAccept($id)
    {
        $wallet = Wallet::where('user_id', $id)->first();
        if (!$wallet) {
            return ResponseHelper::sendResponse(null, 'Wallet not found', false, 404);
        }
        $wallet->status = 'active';
        $wallet->status_reason = null;
        $wallet->save();
        return ResponseHelper::sendResponse(['status' => 'active'], 'Wallet activated.');
    }

    public function walletReject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $wallet = Wallet::where('user_id', $id)->first();
        if (!$wallet) {
            return ResponseHelper::sendResponse(null, 'Wallet not found', false, 404);
        }
        $wallet->status = 'rejected';
        $wallet->status_reason = $request->reason;
        $wallet->save();
        return ResponseHelper::sendResponse(['status' => 'rejected'], 'Wallet rejected.');
    }
}
