<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\NotificationHelper;
use App\Helpers\PermissionHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\NotificationCenter;
use App\Models\ProfileBanner;
use App\Models\ReportUsers;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserFriends;
use App\Models\UserRequest;
use App\Models\UserImage;
use App\Models\UserVideo;
use App\Models\UserVisitor;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Google\Client as GoogleClient;
use Carbon\Carbon;

class UsersController extends Controller
{
    public function users_list(Request $request)
    {
        $users = User::select('id', 'user_id', 'username', 'image', 'is_online')->where('_id', '!=', Auth::id())->where('is_admin_user', 0)->get();
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
        $user = User::with(['user_feeds', 'friends', 'family', 'user_requests'])->find($id);

        $friend = UserFriends::where('friend_id', $user->id)->where('type', 'friends')->where('user_id', Auth::id())->first();
        $family = UserFriends::where('friend_id', $user->id)->where('type', 'family')->where('user_id', Auth::id())->first();
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
        $friendUser = User::find($request->user_id);
        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'friends_allow_request');
        $allowRequest2 = PermissionHelper::checkPermission($friendUser->level, 'friends_allow_request');
        $familyLimit = PermissionHelper::checkPermission(Auth::user()->level, 'friends_total_family');
        $friendLimit = PermissionHelper::checkPermission(Auth::user()->level, 'friends_total_friends');

        $totalFamily = UserFriends::where('friend_id', Auth::id())->where('user_type', 'family')->count();
        $totalFriends = UserFriends::where('friend_id', Auth::id())->where('user_type', 'friends')->count();

        try {
            $user_request = UserRequest::updateOrCreate(
                ['request_id' => $request->user_id, 'user_id' => Auth::id()],
                ['request_id' => $request->user_id, 'user_id' => Auth::id(), 'status' => $status]
            );
            $user = User::select('name', 'last_name', 'username', 'image', 'is_online', 'fcm_token')->find($request->user_id);
            $current_user = User::find(Auth::id());
            if ($request->status == 1) {
                // After successful save only — PDF: "{name} sent you a friend request."
                $actorName = NotificationHelper::actorName($current_user);
                NotificationHelper::notifyUser(
                    $request->user_id,
                    $actorName,
                    $actorName . ' sent you a friend request.',
                    'friend_request',
                    Auth::id(),
                    $current_user->image ?? null,
                    'friend_request:' . Auth::id() . ':' . $request->user_id,
                    $user_request->id ?? null,
                    'user_request'
                );
                $data = [
                    "to" => $user->fcm_token ?? null,
                    "notification" => [
                        "title" => $actorName,
                        "body" => $actorName . ' sent you a friend request.',
                    ],
                    "data" => [
                        "type" => "friend_request",
                        "user_id" => $request->user_id,
                        "sender" => $current_user,
                    ],
                ];
                return ResponseHelper::sendResponse($data, 'User Request Send Successfully');
            } else {
                return ResponseHelper::sendResponse([], 'User Request Cancelled Successfully');
            }
        } catch (Exception $e) {
            Log::error('UserRequest Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return ResponseHelper::sendResponse([], 'Error to Send Request', false, 403);
        }
    }

    public function acceptRequest(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'user_type' => 'required'
        ]);

        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'friends_allow_request');
        $familyLimit = PermissionHelper::checkPermission(Auth::user()->level, 'friends_total_family');
        $friendLimit = PermissionHelper::checkPermission(Auth::user()->level, 'friends_total_friends');

        $totalFamily = UserFriends::where('friend_id', Auth::id())->where('user_type', 'family')->count();
        $totalFriends = UserFriends::where('friend_id', Auth::id())->where('user_type', 'friends')->count();

        if ($allowRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not allowed to accept friend requests.', false, 409);
        }
        // Only enforce a limit when one is actually configured as a number. `checkPermission`
        // returns true (unlimited), a number (the cap), or false (unset). Comparing against a
        // `false` cap coerces to `>= 0`, which would block EVERY accept — so guard with is_numeric.
        if ($request->user_type == 'family') {
            if (is_numeric($familyLimit) && $totalFamily >= (int) $familyLimit) {
                return ResponseHelper::sendResponse([], 'Your limit for family requests has been exceeded.', false, 409);
            }
        }

        if ($request->user_type == 'friends') {
            if (is_numeric($friendLimit) && $totalFriends >= (int) $friendLimit) {
                return ResponseHelper::sendResponse([], 'Your limit for friend requests has been exceeded.', false, 409);
            }
        }
        try {
            // Capture before any loop that could shadow $request (legacy foreach bug).
            $senderId = $request->user_id;
            $userType = $request->user_type;

            if ($userType !== 'rejected') {
                $user_request = UserFriends::updateOrCreate(
                    ['friend_id' => $senderId, 'user_id' => Auth::id()],
                    ['friend_id' => $senderId, 'user_id' => Auth::id(), 'user_type' => $userType]
                );

                UserFriends::updateOrCreate(
                    ['user_id' => $senderId, 'friend_id' => Auth::id()],
                    ['user_id' => $senderId, 'friend_id' => Auth::id(), 'user_type' => $userType]
                );
                $oldRequests = UserRequest::where('request_id', Auth::id())->where('user_id', $senderId)->get();
                foreach ($oldRequests as $oldRequest) {
                    $oldRequest->delete();
                }

                // After successful accept — PDF: "{name} accepted your friend request."
                $accepter = User::find(Auth::id());
                $actorName = NotificationHelper::actorName($accepter);
                NotificationHelper::notifyUser(
                    $senderId,
                    $actorName,
                    $actorName . ' accepted your friend request.',
                    'friend_accept',
                    Auth::id(),
                    $accepter->image ?? null,
                    'friend_accept:' . Auth::id() . ':' . $senderId,
                    $user_request->id ?? null,
                    'user_friends'
                );

                $sender = User::find($senderId);
                if ($sender) {
                    $sender->is_online = 1;
                    $sender->save();
                }
                if ($accepter) {
                    $accepter->is_online = 1;
                    $accepter->save();
                }
                return ResponseHelper::sendResponse($user_request, 'Request Accept Successfully');
            } else {
                $oldRequests = UserRequest::where('request_id', Auth::id())->where('user_id', $senderId)->get();
                foreach ($oldRequests as $oldRequest) {
                    $oldRequest->delete();
                }
                return ResponseHelper::sendResponse([], 'Request Rejected Successfully');
            }
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Error to Accept Request', false, 403);
        }
    }

    public function unblock_user(Request $request, $id)
    {
        try {
            $friend = UserFriends::where('user_id', $id)->where('friend_id', Auth::id())->first();
            $friend_to = UserFriends::where('friend_id', $id)->where('user_id', Auth::id())->first();
            $friend->delete();
            $friend_to->delete();
            return ResponseHelper::sendResponse([], 'Unblock has been Successfully');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Unblock!', false, 403);
        }
    }

    public function unfriend_user(Request $request, $id)
    {
        try {
            $friend = UserFriends::where('user_id', $id)->where('friend_id', Auth::id())->first();
            $friend_to = UserFriends::where('friend_id', $id)->where('user_id', Auth::id())->first();
            $friend->delete();
            $friend_to->delete();
            return ResponseHelper::sendResponse([], 'Unfriend has been Successfully');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Unfriend!', false, 403);
        }
    }

    public function block_list(Request $request, $id)
    {
        try {
            $user = User::with(['block' => fn ($q) => $q->with('user')])->find($id);
            if (!$user) {
                return ResponseHelper::sendResponse([], 'User Not Found!', false, 404);
            }
            return ResponseHelper::sendResponse(['block_list' => $user->block ?? []], 'Block List Fetch Successfully');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Error to Fetch Block List: ' . $e->getMessage(), false, 403);
        }
    }

    public function freind_list(Request $request, $id)
    {
        try {
            $user = User::with([
                'family' => fn ($q) => $q->with('user'),
                'friends' => fn ($q) => $q->with('user'),
            ])->find($id);

            if (!$user) {
                return ResponseHelper::sendResponse([], 'User Not Found!', false, 404);
            }

            return ResponseHelper::sendResponse([
                'friends_list' => $user->friends ?? [],
                'family_list' => $user->family ?? [],
            ], 'Friends List Fetch Successfully');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Error to Fetch Friends List: ' . $e->getMessage(), false, 403);
        }
    }

    public function update_freind_list(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'user_type' => 'required'
        ]);
        try {
            $user_request = UserFriends::updateOrCreate(
                ['friend_id' => $request->user_id, 'user_id' => Auth::id()],
                ['friend_id' => $request->user_id, 'user_id' => Auth::id(), 'user_type' => $request->user_type]
            );

            $user_request_to = UserFriends::updateOrCreate(
                ['user_id' => $request->user_id, 'friend_id' => Auth::id()],
                ['user_id' => $request->user_id, 'friend_id' => Auth::id(), 'user_type' => $request->user_type]
            );

            return ResponseHelper::sendResponse($user_request, 'Friends/Family Updated Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Fail to Update Friends/Family!', false, 403);
        }
    }

    public function update_block_list(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
        ]);
        try {
            $user_request = UserFriends::updateOrCreate(
                ['friend_id' => $request->user_id, 'user_id' => Auth::id()],
                ['friend_id' => $request->user_id, 'user_id' => Auth::id(), 'user_type' => 'block']
            );

            $user_request_to = UserFriends::updateOrCreate(
                ['user_id' => $request->user_id, 'friend_id' => Auth::id()],
                ['user_id' => $request->user_id, 'friend_id' => Auth::id(), 'user_type' => 'block']
            );

            return ResponseHelper::sendResponse($user_request, 'Blocked Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Fail to Update Block!', false, 403);
        }
    }

    public function family_list(Request $request, $id)
    {
        try {
            $user = User::with(['family' => fn ($q) => $q->with('user')])->find($id);
            if (!$user) {
                return ResponseHelper::sendResponse([], 'User Not Found!', false, 404);
            }
            $family_list = $user->family;
            return ResponseHelper::sendResponse($family_list, 'Family List Fetch Successfully');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Error to Fetch Family List', false, 403);
        }
    }

    public function request_list(Request $request, $id)
    {
        try {
            $user = User::with(['user_requests' => function ($q) {
                $q->with('user');
            }])->find($id);

            if (!$user) {
                return ResponseHelper::sendResponse([], 'User Not Found!', false, 404);
            }

            $friends_list = $user->user_requests;
            return ResponseHelper::sendResponse($friends_list, 'Requests List Fetch Successfully');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Error to Fetch Requests List: ' . $e->getMessage(), false, 403);
        }
    }

    public function store_user_online(Request $request, $id)
    {
        $request->validate([
            'value' => 'required'
        ]);

        $user = User::find($id);
        if ($user) {
            $user->is_online = (int)$request->value;
            $user->save();
            (int)$request->value == 1 ? $status = 'Online' : $status = 'Offline';
            return ResponseHelper::sendResponse($user, 'User ' . $status . ' Successfully!');
        } else {
            return ResponseHelper::sendResponse([], 'User Not Found!', false, 403);
        }
    }

    public function vistor(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
        ]);

        try {
            $visitor = UserVisitor::updateOrCreate(
                ['user_id' => $request->user_id, 'visitor_id' => Auth::id()],
                ['user_id' => $request->user_id, 'visitor_id' => Auth::id()]
            );
            $totalVisitors = UserVisitor::where('user_id', $request->user_id)->count();
            $user = User::find($request->user_id);
            $user->total_views = $totalVisitors;
            $user->save();
            return ResponseHelper::sendResponse($visitor, 'User Visit has been Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Visit User!', false, 403);
        }
    }

    public function get_vistor()
    {
        try {
            $visitors = UserVisitor::with(['visitor'])->where('user_id', Auth::id())->get();
            $totalVisitors = UserVisitor::where('user_id', Auth::id())->count();
            $data = ['visitors' => $visitors, 'total_views' => $totalVisitors];
            return ResponseHelper::sendResponse($data, 'User Visit has been Successfully!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Visit User!', false, 403);
        }
    }

    public function updateDeviceToken(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,_id',
            'fcm_token' => 'required|string',
        ]);

        $request->user()->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'Device token updated successfully']);
    }

    public function sendNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,_id',
            'title' => 'required|string',
            'body' => 'required|string',
        ]);

        $user = \App\Models\User::find($request->user_id);
        $fcm = $user->fcm_token;

        if (!$fcm) {
            return response()->json(['message' => 'User does not have a device token'], 400);
        }

        $title = $request->title;
        $description = $request->body;
        $projectId = env('FCM_PROJECT_ID');

        $credentialsFilePath = Storage::path('json/services.json');
        $client = new GoogleClient();
        $client->setAuthConfig($credentialsFilePath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $token = $client->getAccessToken();

        $access_token = $token['access_token'];

        $headers = [
            "Authorization: Bearer $access_token",
            'Content-Type: application/json'
        ];

        $data = [
            "message" => [
                "token" => $fcm,
                "notification" => [
                    "title" => $title,
                    "body" => $description,
                ],
            ]
        ];
        $payload = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return response()->json([
                'message' => 'Curl Error: ' . $err
            ], 500);
        } else {
            return response()->json([
                'message' => 'Notification has been sent',
                'response' => json_decode($response, true)
            ]);
        }
    }

    public function testNotification(Request $request)
    {
        $user = \App\Models\User::find($request->user_id);
        $fcm = $user->fcm_token;

        if (!$fcm) {
            return response()->json(['message' => 'User does not have a device token'], 400);
        }

        $projectId = env('FCM_PROJECT_ID');

        $credentialsFilePath = Storage::path('json/services.json');
        $client = new GoogleClient();
        $client->setAuthConfig($credentialsFilePath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $token = $client->getAccessToken();

        $access_token = $token['access_token'];

        $headers = [
            "Authorization: Bearer $access_token",
            'Content-Type: application/json'
        ];

        $data = [
            "message" => [
                "token" => $fcm,
                "notification" => [
                    "title" => $request->title,
                    "body" => $request->body,
                ],
            ]
        ];
        $payload = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return response()->json([
                'message' => 'Curl Error: ' . $err
            ], 500);
        } else {
            return response()->json([
                'message' => 'Notification has been sent',
                'response' => json_decode($response, true)
            ]);
        }
    }

    public function storeMyService(Request $request)
    {
        $user = User::find(Auth::id());
        $user->my_service = (int)$request->my_service;
        $user->save();
        return ResponseHelper::sendResponse($user, 'Service Updated Successfully!');
    }

    public function storeMyNetwork(Request $request)
    {
        $user = User::find(Auth::id());
        if (!empty($request->live_stream) && $request->live_stream !== "") {
            $user->live_stream  = $request->live_stream;
        }
        if (!empty($request->upload_video) && $request->upload_video !== "") {
            $user->upload_video  = $request->upload_video;
        }
        if (!empty($request->play_music) && $request->play_music !== "") {
            $user->play_music  = $request->play_music;
        }
        if (!empty($request->play_video) && $request->play_video !== "") {
            $user->play_video  = $request->play_video;
        }
        if (!empty($request->interview) && $request->interview !== "") {
            $user->interview  = $request->interview;
        }
        if (!empty($request->video_call) && $request->video_call !== "") {
            $user->video_call  = $request->video_call;
        }
        $user->save();
        return ResponseHelper::sendResponse($user, 'My Network Updated Successfully!');
    }

    public function storeMyNotification(Request $request)
    {
        $user = User::find(Auth::id());
        if (!empty($request->new_news) && $request->new_news !== "") {
            $user->new_news  = $request->new_news;
        }
        if (!empty($request->new_music) && $request->new_music !== "") {
            $user->new_music  = $request->new_music;
        }
        if (!empty($request->new_history) && $request->new_history !== "") {
            $user->new_history  = $request->new_history;
        }
        if (!empty($request->new_votes) && $request->new_votes !== "") {
            $user->new_votes  = $request->new_votes;
        }
        if (!empty($request->new_videos) && $request->new_videos !== "") {
            $user->new_videos  = $request->new_videos;
        }
        if (!empty($request->new_events) && $request->new_events !== "") {
            $user->new_events  = $request->new_events;
        }
        if (!empty($request->new_donation) && $request->new_donation !== "") {
            $user->new_donation  = $request->new_donation;
        }
        if (!empty($request->info_banner) && $request->info_banner !== "") {
            $user->info_banner  = $request->info_banner;
        }
        $user->save();
        return ResponseHelper::sendResponse($user, 'My Notifcation Updated Successfully!');
    }

    public function user_images(Request $request)
    {
        $images = UserImage::with('user')->where('user_id', $request->user_id)->get();
        return ResponseHelper::sendResponse($images, 'User Images Fetched Successfully');
    }

    public function user_videos(Request $request)
    {
        $videos = UserVideo::with('user')->where('user_id', $request->user_id)->get();
        return ResponseHelper::sendResponse($videos, 'User Videos Fetched Successfully');
    }

    public function getLoginImage(Request $request)
    {
        if (empty($request->device_imei)) {
            return ResponseHelper::sendResponse(null, 'Device Imei Not Found!', false, 404);
        }
        $user = User::where('device_imei', $request->device_imei)->first();
        if ($user) {
            $data = [
                'image' => Helpers::cdnRelativePath($user->image ?? null) ?? '',
                'status' => $user->status,
                'email' => $user->email,
            ];
            return ResponseHelper::sendResponse($data, 'User Image Fetched!');
        } else {
            return ResponseHelper::sendResponse(null, 'User Not Found!', false, 404);
        }
    }

    public function search_user(Request $request)
    {
        if ($request->origin) {
            if ($request->origin !== 'kurdish' && $request->origin !== 'non-kurdish') {
                return ResponseHelper::sendResponse([], 'Origin Not Found!', false, 404);
            }
        }
        $users = User::with('user_country')
            ->where('_id', '!=', Auth::id())
            ->where('status', 1)
            ->where(function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $request->search . '%');
            })
            ->when($request->origin, function ($query) use ($request) {
                $query->where('origin', $request->origin);
            })
            ->get();

        return ResponseHelper::sendResponse($users, 'Users has been Fetched Successfully!');
    }

    public function getProfileBanners()
    {
        $banner = ProfileBanner::get();
        return ResponseHelper::sendResponse($banner, 'User Banners Fetched!');
    }

    public function reportstore(Request $request, $id)
    {
        $request->validate([
            'report_type' => 'required|string|max:255',
        ]);

        $userId = Auth::id();

        $exists = ReportUsers::where('user_id', $id)
            ->where('report_by', $userId)
            ->first();

        if ($exists) {
            if ($exists->status == 1) {
                return ResponseHelper::sendResponse([], 'You have already reported this User.', false, 400);
            }
            $exists->delete();
        }

        $report = ReportUsers::create([
            'user_id' => $id,
            'report_type' => Str::slug($request->report_type),
            'report_by' => $userId,
            'status' => 1
        ]);

        $owner = User::where('_id', $id)
            ->whereIn('info_banner', ['banner', 'alert'])
            ->first();

        if ($owner) {
            NotificationHelper::sendNotification(
                $owner->_id,
                'Feed Reported',
                "You have been reported"
            );

            NotificationCenter::create([
                'title' => 'User Reported',
                'description' => "You have been reported",
                'user_id' => $owner->_id,
                'user_image' => $owner->image ?? null,
                'type' => 'user_reports',
                'is_read' => 0,
            ]);
        }

        return ResponseHelper::sendResponse($report, 'User reported successfully');
    }

    public function getReport()
    {
        $reports = ReportUsers::select([
            'user_id',
            'report_type',
            'status'
        ])->where('user_id', Auth::id())->get();
        return ResponseHelper::sendResponse($reports, 'Reports fetch successfully');
    }

    public function congrats_popup_off()
    {
        $current = Carbon::now();
        $newExpiry = Carbon::parse($current->copy()->addMonth())->format('Y-m-d');
        $user = User::find(Auth::id());
        $user->congrats_popup = 0;
        $user->level = (int)'1';
        $user->user_type = 'educated';
        $user->expired_at = $newExpiry;
        $user->subscription_type = 'trial';
        $user->save();
        return ResponseHelper::sendResponse($user, 'Congrats Popup Disabled Successfully');
    }
}
