<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFriends;
use App\Models\UserRequest;
use App\Models\UserImage;
use App\Models\UserVideo;
use App\Models\ReportUsers;
use App\Models\ProfileBanner;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class UsersController extends Controller
{
    public function users_list(Request $request)
    {
        $users = User::select('id', 'user_id', 'username', 'image', 'is_online')
            ->where('_id', '!=', Auth::id())
            ->where('is_admin_user', 0)
            ->get();
        return ResponseHelper::sendResponse($users, 'User Fetch Successfully');
    }

    public function users_may_you_know_list(Request $request)
    {
        $authId = Auth::id();
        $users = User::select('_id', 'user_id', 'username', 'image', 'is_online')
            ->where('_id', '!=', $authId)
            ->where('is_admin_user', 0)
            ->whereDoesntHave('relations', function ($q) use ($authId) {
                $q->whereIn('user_type', ['friends', 'family'])
                    ->where(function ($q) use ($authId) {
                        $q->where('user_id', $authId)
                            ->orWhere('friend_id', $authId);
                    });
            })
            ->get();
        return ResponseHelper::sendResponse($users, 'User Fetch Successfully');
    }

    public function users_details(Request $request, $id)
    {
        $user = User::with(['friends', 'family', 'user_requests'])->find($id);

        $friend = UserFriends::where('friend_id', $user->id)->where('user_type', 'friends')->where('user_id', Auth::id())->first();
        $family = UserFriends::where('friend_id', $user->id)->where('user_type', 'family')->where('user_id', Auth::id())->first();
        $requestfriend = UserRequest::where('request_id', $user->id)->where('user_id', Auth::id())->first();
        $comingrequest = UserRequest::where('user_id', $user->id)->where('request_id', Auth::id())->first();

        $is_request = $requestfriend ? 1 : 0;
        $is_coming = $comingrequest ? 1 : 0;
        $is_friend = $friend ? 1 : 0;
        $is_family = $family ? 1 : 0;

        $is_image = 0;
        if ($is_friend && $user->friends_image === 'true') {
            $is_image = 1;
        } elseif ($is_family && $user->family_image === 'true') {
            $is_image = 1;
        } elseif ($user->public_image === 'true') {
            $is_image = 1;
        }

        $data = ['user' => $user, 'is_friend' => $is_friend, 'is_family' => $is_family, 'is_request' => $is_request, 'is_coming' => $is_coming, 'is_image' => $is_image];
        return ResponseHelper::sendResponse($data, 'User Details Fetch Successfully');
    }

    public function sendRequest(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'status' => 'required'
        ]);

        $status = (int) $request->status;

        try {
            $user_request = UserRequest::updateOrCreate(
                ['request_id' => $request->user_id, 'user_id' => Auth::id()],
                ['request_id' => $request->user_id, 'user_id' => Auth::id(), 'status' => $status]
            );
            return ResponseHelper::sendResponse($user_request, 'User Request Sent Successfully');
        } catch (\Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to send request', false, 500);
        }
    }

    public function acceptRequest(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'user_type' => 'required'
        ]);

        try {
            $user_request = UserRequest::where('request_id', Auth::id())
                ->where('user_id', $request->user_id)
                ->first();

            if ($user_request) {
                UserFriends::updateOrCreate(
                    ['user_id' => $request->user_id, 'friend_id' => Auth::id()],
                    ['user_id' => $request->user_id, 'friend_id' => Auth::id(), 'user_type' => $request->user_type]
                );
                UserFriends::updateOrCreate(
                    ['user_id' => Auth::id(), 'friend_id' => $request->user_id],
                    ['user_id' => Auth::id(), 'friend_id' => $request->user_id, 'user_type' => $request->user_type]
                );
                $user_request->delete();
                return ResponseHelper::sendResponse([], 'Request Accepted Successfully');
            }
            return ResponseHelper::sendResponse([], 'Request not found', false, 404);
        } catch (\Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to accept request', false, 500);
        }
    }

    public function freind_list(Request $request, $id)
    {
        $friends = UserFriends::where('friend_id', $id)->where('user_type', 'friends')->with('user')->get();
        return ResponseHelper::sendResponse($friends, 'Friends Fetch Successfully');
    }

    public function family_list(Request $request, $id)
    {
        $family = UserFriends::where('friend_id', $id)->where('user_type', 'family')->with('user')->get();
        return ResponseHelper::sendResponse($family, 'Family Fetch Successfully');
    }

    public function block_list(Request $request, $id)
    {
        $blocked = UserFriends::where('friend_id', $id)->where('user_type', 'block')->with('user')->get();
        return ResponseHelper::sendResponse($blocked, 'Block List Fetch Successfully');
    }

    public function update_block_list(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
        ]);

        UserFriends::updateOrCreate(
            ['user_id' => Auth::id(), 'friend_id' => $request->user_id],
            ['user_id' => Auth::id(), 'friend_id' => $request->user_id, 'user_type' => 'block']
        );
        return ResponseHelper::sendResponse([], 'User Blocked Successfully');
    }

    public function unblock_user($id)
    {
        UserFriends::where('user_id', Auth::id())->where('friend_id', $id)->where('user_type', 'block')->delete();
        return ResponseHelper::sendResponse([], 'User Unblocked Successfully');
    }

    public function unfriend_user($id)
    {
        UserFriends::where('friend_id', $id)->where('user_id', Auth::id())->delete();
        UserFriends::where('friend_id', Auth::id())->where('user_id', $id)->delete();
        return ResponseHelper::sendResponse([], 'User Unfriended Successfully');
    }

    public function request_list(Request $request, $id)
    {
        $requests = UserRequest::where('request_id', $id)->where('status', 1)->get();
        return ResponseHelper::sendResponse($requests, 'Request List Fetch Successfully');
    }

    public function search_user(Request $request)
    {
        $request->validate([
            'search' => 'required',
        ]);
        $users = User::where('username', 'like', '%' . $request->search . '%')
            ->orWhere('name', 'like', '%' . $request->search . '%')
            ->orWhere('email', 'like', '%' . $request->search . '%')
            ->where('is_admin_user', 0)
            ->select('_id', 'user_id', 'username', 'image', 'name', 'last_name')
            ->limit(20)
            ->get();
        return ResponseHelper::sendResponse($users, 'User Search Results');
    }

    public function congrats_popup_off()
    {
        $user = User::find(Auth::id());
        $user->congrats_popup = 0;
        $user->save();
        return ResponseHelper::sendResponse([], 'Congrats popup turned off');
    }

    public function updateDeviceToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required',
        ]);
        $user = User::find(Auth::id());
        $user->fcm_token = $request->fcm_token;
        $user->save();
        return ResponseHelper::sendResponse([], 'Device token updated successfully');
    }

    public function user_images(Request $request)
    {
        $images = UserImage::where('user_id', Auth::id())->get();
        return ResponseHelper::sendResponse($images, 'User Images Fetch Successfully');
    }

    public function user_videos(Request $request)
    {
        $videos = UserVideo::where('user_id', Auth::id())->get();
        return ResponseHelper::sendResponse($videos, 'User Videos Fetch Successfully');
    }

    public function getLoginImage()
    {
        $setting = Setting::first();
        $data = $setting ? ($setting->login_image ?? null) : null;
        return ResponseHelper::sendResponse($data, 'Login Image Fetched');
    }

    public function getProfileBanners()
    {
        $banners = ProfileBanner::where('status', 1)->get();
        return ResponseHelper::sendResponse($banners, 'Profile Banners Fetched');
    }

    public function store_user_online(Request $request, $id)
    {
        $user = User::find($id);
        if ($user) {
            $user->is_online = $request->is_online ?? 0;
            $user->save();
        }
        return ResponseHelper::sendResponse([], 'User Online Status Updated');
    }
}
