<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Feed;
use App\Models\News;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

class UserProfileController extends Controller
{
    public function index()
    {
        $activity = Auth::user()->actions()->orderBy('created_at', 'DESC')->paginate(20);
        // return view('content.admin_profile.index', compact("activity"));
        return view('content.pages.pages-account-settings-account', compact('activity'));
    }

    public function welcome()
    {
        return view('content.welcome');
    }

    public function admin_activity()
    {
        $events = Event::all();
        $news = News::all();
        $feeds = Feed::all();
        return view('content.pages.admin_activity', compact('events', 'news', 'feeds'));
    }

    public function store(Request $request)
    {

        $profile = User::find(Auth::id());
        if (!empty($request->name) && $request->name !== "") {
            $profile->name = $request->name;
        }
        if (!empty($request->nickname) && $request->nickname !== "") {
            $profile->nickname = $request->nickname;
        }
        if (!empty($request->username) && $request->username !== "") {
            $profile->username = $request->username;
        }
        if (!empty($request->last_name) && $request->last_name !== "") {
            $profile->last_name = $request->last_name;
        }
        if (!empty($request->email) && $request->email !== "") {
            if ($request->old_email != $profile->email) {
                return response()->json(['message', 'Invalid Old Email Address'], 403);
            }
            $profile->email  = $request->email;
        }
        if (!empty($request->phone) && $request->phone !== "") {
            $profile->phone  = $request->phone;
        }
        if (!empty($request->public_image) && $request->public_image !== "") {
            $profile->public_image  = $request->public_image;
        }
        if (!empty($request->friends_image) && $request->friends_image !== "") {
            $profile->friends_image  = $request->friends_image;
        }
        if (!empty($request->family_image) && $request->family_image !== "") {
            $profile->family_image  = $request->family_image;
        }
        if (!empty($request->friends_request) && $request->friends_request !== "") {
            $profile->friends_request  = $request->friends_request;
        }
        if (!empty($request->get_greetings) && $request->get_greetings !== "") {
            $profile->get_greetings  = $request->get_greetings;
        }
        if (!empty($request->search_option) && $request->search_option !== "") {
            $profile->search_option  = $request->search_option;
        }
        if (!empty($request->origin) && $request->origin !== "") {
            $profile->origin  = $request->origin;
        }
        if (!empty($request->city) && $request->city !== "") {
            $profile->city  = $request->city;
        }
        if (!empty($request->province) && $request->province !== "") {
            $profile->province  = $request->province;
        }
        if (!empty($request->country) && $request->country !== "") {
            $profile->country  = $request->country;
        }
        if (!empty($request->maritalStatus) && $request->maritalStatus !== "") {
            $profile->maritalStatus  = $request->maritalStatus;
        }
        if (!empty($request->is_language) && $request->is_language !== "") {
            $profile->is_language  = $request->is_language;
        }
        if (!empty($request->is_music) && $request->is_music !== "") {
            $profile->is_music  = $request->is_music;
        }
        if (!empty($request->is_else) && $request->is_else !== "") {
            $profile->is_else  = $request->is_else;
        }

        if (!empty($request->is_disabled) && $request->is_disabled !== "") {
            $profile->is_disabled  = $request->is_disabled;
        }

        if (!empty($request->password) && $request->password !== "") {
            if (!Hash::check($request->old_password, $profile->password)) {
                return response()->json(['message', 'Invalid Old Password'], 403);
            }
            $profile->password  = Hash::make($request->password);
        }
        // unlink($profile->image);
        if ($request->hasFile('image')) {
            $image_path = Helpers::fileUpload($request->image, 'images/user');
            try {
                $existing_image = public_path('storage/' . $profile->image);
                if (file_exists($existing_image)) {
                    unlink($existing_image);
                }
            } catch (Exception $e) {
                //
            }
            $profile->image = $image_path;
        }

        if ($request->has('banner_image')) {
            $banner_path = '';
            if ($request->hasFile('banner_image')) {
                $banner_path = Helpers::fileUpload($request->banner_image, 'images/user');
                try {
                    $existing_banner = public_path('storage/' . $profile->banner_image);
                    if (
                        !empty($profile->banner_image) &&
                        file_exists($existing_banner) &&
                        strpos($profile->banner_image, 'images/user/') !== false
                    ) {
                        unlink($existing_banner);
                    }
                } catch (Exception $e) {
                    //
                }
            } else {
                $banner_path = $request->banner_image;
            }
            $profile->banner_image = $banner_path;
        }

        if ($profile->update()) {
            return ResponseHelper::sendResponse($profile, 'Your profile has been updated');
        } else {
            return ResponseHelper::sendResponse($profile, 'Failed to Update your profile', false, 403);
        }
    }

    public function security()
    {
        return view('content.pages.pages-account-settings-security');
    }

    public function enable(Request $request)
    {

        if ($request->has('enable')) {
            Auth::user()->enable_2fa  = true;
            Auth::user()->save();
            return back()->with('success', 'Two Factor Authentication Enabled');
        } else {
            Auth::user()->enable_2fa  = false;
            Auth::user()->save();
            return back()->with('error', 'Two Factor Authentication Disabled');
        }
    }

    public function account()
    {
        $activity = Auth::user()->actions()->orderBy('created_at', 'DESC')->paginate(20);

        return view('content.pages.pages-account-settings-account', compact('activity'));
    }
    public function billing()
    {
        return view('content.pages.pages-account-settings-billing');
    }
    public function notification()
    {
        return view('content.pages.pages-account-settings-notifications');
    }
    public function connection()
    {
        return view('content.pages.pages-account-settings-connections');
    }

    public function change_password(Request $request)
    {
        $request->validate([
            'currentPassword' => 'required',
            'newPassword' => 'required',
            'confirmPassword' => 'required'
        ]);
        if (!Hash::check($request->currentPassword, Auth::user()->password)) {
            return back()->with("error", "Old Password Doesn't match!");
        } else {
            if ($request->newPassword == $request->confirmPassword) {

                User::whereId(Auth::id())->update([
                    'password' => Hash::make($request->newPassword)
                ]);
                return back()->with("success", "Password changed successfully!");
            } else {
                return back()->with('error', 'Your New Password  and Confirm Password  is not matched');
            }
        }
    }

    public function store_news(Request $request)
    {
        $news = News::create([
            'title' => $request->title,
            'description' => $request->description,
            'user_type' => $request->user_type,
            'image' => $request->image,
            'image_type' => (int)$request->image_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'comments' => (int)$request->comments ?? 0,
            'voice_comments' => (int)$request->voice_comments ?? 0,
            'share' => (int)$request->share ?? 0,
            'emotion' => (int)$request->emotion ?? 0,
            'status' => $request->status,
        ]);
        return back();
    }

    public function store_event(Request $request)
    {
        // $request->dd();
        $event = Event::create([
            'title' => $request->title,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);
        return back();
    }

    public function store_feeds(Request $request)
    {
        // $request->dd();
        $feeds = Feed::create([
            'feed_background_image' => $request->feed_background_image,
            'feed_text_color' => $request->feed_text_color,
            'grid_style' => $request->grid_style,
            'image_type' => (int)$request->image_type,
            'description' => $request->description,
            'user_type' => $request->user_type,
            'feed_type' => $request->feed_type,
            'image' => $request->image,
            'image_file_name' => $request->image_file_name,
            'image_file_length' => $request->image_file_length,
            'image_file_size' => $request->image_file_size,
            'video' => $request->video,
            'video_file_name' => $request->video_file_name,
            'video_file_length' => $request->video_file_length,
            'video_file_size' => $request->video_file_size,
        ]);
        return back();
    }
}
