<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\UserCode;
use App\Mail\SendCodeMail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AccountSettingController extends Controller
{
    public function change_password(Request $request)
    {
        $request->validate([
            'oldPassword' => 'required',
        ]);
        if (!Hash::check($request->oldPassword, Auth::user()->password)) {
            return ResponseHelper::sendResponse([], 'Current password is incorrect!', false, 403);
        } else {
            $user = User::find(Auth::id());
            $user->password = Hash::make($request->password);
            $user->save();
            return ResponseHelper::sendResponse([], 'Password successfully updated!');
        }
    }

    public function send_old_email_code(Request $request)
    {
        $request->validate([
            'oldEmail' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->oldEmail)->first();

        $code = rand(1000, 9999);
        UserCode::updateOrCreate(
            ['user_id' => $user->id, 'type' => 'email_change_old'],
            ['code' => $code, 'expires_at' => now()->addMinutes(10)]
        );

        try {
            Mail::to($request->oldEmail)->send(new SendCodeMail(['code' => $code]));
            return response()->json(['success' => true, 'message' => "OTP sent to old email."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "Failed to send OTP.", 'error' => $e->getMessage()]);
        }
    }

    public function send_new_email_code(Request $request)
    {
        $request->validate([
            'newEmail' => 'required|email|unique:users,email',
        ]);

        $code = rand(1000, 9999);

        UserCode::updateOrCreate(
            ['email' => $request->newEmail],
            ['code' => $code, 'expires_at' => now()->addMinutes(10)]
        );

        try {
            $details = [
                'title' => 'Mail from Yekbun.org',
                'code' => $code,
                'username' => 'User',
            ];
            Mail::to($request->newEmail)->send(new SendCodeMail($details));
            return response()->json(['success' => true, 'message' => "OTP sent to new email."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "Failed to send OTP.", 'error' => $e->getMessage()]);
        }
    }

    public function change_email(Request $request)
    {
        $request->validate([
            'oldEmail' => 'required|email|exists:users,email',
            'newEmail' => 'required|email|unique:users,email',
            'newOtp' => 'required|digits:4',
        ]);

        $user = User::where('email', $request->oldEmail)->first();

        $newOtp = UserCode::where('email', $request->oldEmail)->where('code', (int)$request->newOtp)->first();

        if (!$newOtp) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP for new email.']);
        }

        $user->update(['email' => $request->newEmail]);
        $newOtp->delete();

        return response()->json(['success' => true, 'message' => 'Email successfully updated.']);
    }

    public function resend_email_code(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
        ]);
        $user = UserCode::with('user')->where('user_id', $request->user_id)->first();
        $code = rand(1000, 9999);
        try {
            $details = [
                'title' => 'Mail from Yekbun.org',
                'code' => $code,
                'username' => $user->user->username ?? '',
            ];
            Mail::to($request->NewEmail)->send(new SendCodeMail($details));
            $user->code = $code;
            $user->save();
            return response()->json(['success' => true, 'message' => "Email successfully resent."]);
        } catch (\Exception $e) {
            info("Error: " . $e->getMessage());
        }
    }

    public function upgrade_account(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'level' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (isset($user)) {
            $user->level = $request->level;
            $user->save();
            $levels = [
                0 => 'Standard',
                1 => 'Premium',
                2 => 'VIP'
            ];
            return response()->json(['success' => true, 'message' => "User Upgrade to {$levels[$request->level]} Successfully."]);
        } else {
            return response()->json(['success' => false, 'message' => 'User not found.']);
        }
    }
}
