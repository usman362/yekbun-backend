<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\NotificationCenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    public function index()
    {
        $notifications = NotificationCenter::with(['user', 'send_by'])->where('is_read', 0)->where('user_id', Auth::id())->get();
        return ResponseHelper::sendResponse($notifications, 'Notifications has been Fetch Successfully!');
    }

    public function store(Request $request)
    {
        $userImage = '';
        if ($request->hasFile('user_image')) {
            $userImage = Helpers::fileUpload($request->user_image, 'notification-users');
        }
        $notifications = NotificationCenter::create([
            'title' => $request->title,
            'description' => $request->description,
            'user_id' => $request->user_id,
            'send_by_id' => Auth::id(),
            'user_image' => $userImage,
            'type' => $request->type,
            'is_read' => 0,
        ]);
        return ResponseHelper::sendResponse($notifications, 'Notification has been Sent!');
    }

    public function read($id)
    {
        $notification = NotificationCenter::find($id);
        $notification->is_read = 1;
        $notification->read_at = Carbon::now();
        $notification->save();
        return ResponseHelper::sendResponse($notification, 'Notification has been Read Successfully!');
    }

    public function delete($id)
    {
        $notification = NotificationCenter::find($id);
        $notification->delete();
        return ResponseHelper::sendResponse([], 'Notification has been Deleted Successfully!');
    }

    public function getSystemSettings()
    {
        $notify = AdminNotification::select(['screenshots', 'recording'])->first();
        return ResponseHelper::sendResponse($notify, 'System Settings has fetch Successfully!');
    }
}
