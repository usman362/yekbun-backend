<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\UserRequest;
use App\Models\UserFriends;
use App\Models\UserCode;
use App\Mail\SendCodeMail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Models\UserImei;
use App\Models\AdminNotification;
use App\Models\Wallet;
use App\Models\KycVerification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Traits\UploadMedia;
use Carbon\Carbon;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use UploadMedia;

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required',
            'password' => 'required',
            'device_imei' => 'required'
        ]);

        (int)$credentials['is_admin_user'] = 0;
        (int)$credentials['is_superadmin'] = 0;
        (int)$credentials['status'] = 1;

        $email = strtolower($request->email);
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->force_logout = 0;
            $user->save();

            if ($user->email !== 'test_yekbun@gmail.com') {
                if ($user->device_imei != $request->device_imei) {
                    $imeis = UserImei::where('user_id', $user->id)->pluck('device_imei');
                    if ($imeis) {
                        return ResponseHelper::sendResponse(['imeis' => $imeis], 'Device IMEI is not registered', false, 404);
                    } else {
                        return ResponseHelper::sendResponse([], 'Invalid Creadentials!', false, 404);
                    }
                }
            }

            if ($user->status == 0) {
                return ResponseHelper::sendResponse([], 'Your Account is deactivated, kindly click Recover my Account', false, 404);
            }
        } else {
            return ResponseHelper::sendResponse([], 'User not Found!', false, 404);
        }

        if (Auth::attempt(['email' => $email, 'password' => $request->password], true)) {
            $user = Auth::user();

            if ($user->action_type === 'suspend') {
                if (Carbon::now()->lt($user->action_duration)) {
                    $remainingDays = Carbon::now()->diffInDays($user->action_duration);
                    $remainingHours = Carbon::now()->diffInHours($user->action_duration) % 24;
                    $remainingText = $remainingDays > 0
                        ? "{$remainingDays} day(s) and {$remainingHours} hour(s)"
                        : "{$remainingHours} hour(s)";
                    return ResponseHelper::sendResponse([], "Your account is suspended for another {$remainingText}.", false, 403);
                } else {
                    $user->status = 1;
                    $user->action_type = null;
                    $user->action_duration = null;
                    $user->save();
                }
            }

            if ($user->action_type === 'downgrade') {
                if (Carbon::now()->gte($user->action_duration)) {
                    $user->level = $user->old_level ?? $user->level;
                    $user->user_type = $user->old_user_type ?? $user->user_type;
                    $user->action_type = null;
                    $user->action_duration = null;
                    $user->save();
                }
            }

            if ($user->email_verified_at == null || $user->email_verified_at == '') {
                if ($user->email) {
                    $code = rand(1000, 9999);
                    UserCode::updateOrCreate(
                        ['user_id' => $user->_id],
                        ['code' => $code]
                    );
                    $details = [
                        'title' => 'Mail from Yekbun.org',
                        'code' => $code,
                        'username' => $user->username,
                    ];
                    $notify = AdminNotification::first();
                    if ($notify && $notify->otp == 1) {
                        Mail::to($user->email)->send(new SendCodeMail($details));
                    }
                }
                return ResponseHelper::sendResponse([], "You're Email is not verified!", false, 403);
            }

            try {
                if (!$token = JWTAuth::fromUser($user)) {
                    return ResponseHelper::sendResponse([], 'Invalid Creadentials!', false, 400);
                }
            } catch (JWTException $e) {
                return ResponseHelper::sendResponse([], 'Invalid Creadentials!', false, 500);
            }

            // Wallet details
            $wallet = Wallet::where('user_id', $user->_id)->first();
            $walletStatusMessages = [
                'not_found'    => 'No wallet found. Please create one.',
                'under_review' => 'We will review your request. We will get back soon.',
                'activated'    => 'Wallet is activated. Enjoy...',
                'on_hold'      => 'Wallet is on Hold. See the reason here.',
                'closed'       => 'Wallet is Closed. The account will be removed after 90 Days.',
            ];

            if ($wallet) {
                $wStatus = $wallet->status ?? 'under_review';
                $walletNumber = $wallet->formattedWalletNumber();

                $walletData = [
                    'has_wallet'            => true,
                    'has_valid'             => ($wStatus === 'activated'),
                    'has_pin'               => !empty($wallet->pin),
                    'welcome_bonus_claimed' => !empty($wallet->welcome_bonus_claimed),
                    'wallet_id'             => $walletNumber,
                    'wallet_number'         => $walletNumber,
                    'wallet_status'         => $wStatus,
                    'status_message'        => $walletStatusMessages[$wStatus] ?? 'Unknown status.',
                    'hold_reason'           => $wallet->status_reason ?? null,
                    'balance'               => round($wallet->balance ?? 0, 2),
                    'expire_at'             => $wallet->expire_at ?? null,
                    'created_at'            => $wallet->created_at ?? null,
                ];
            } else {
                $walletData = [
                    'has_wallet'            => false,
                    'has_valid'             => false,
                    'has_pin'               => false,
                    'welcome_bonus_claimed' => false,
                    'wallet_id'             => null,
                    'wallet_status'         => 'not_found',
                    'status_message'        => $walletStatusMessages['not_found'],
                    'hold_reason'           => null,
                    'balance'               => 0,
                    'expire_at'             => null,
                    'created_at'            => null,
                ];
            }

            // KYC details
            $kyc = KycVerification::where('user_id', $user->_id)->orderBy('created_at', 'desc')->first();
            $kycStatusMessages = [
                'not_submitted' => 'KYC not submitted yet.',
                'pending'       => 'Your documents are submitted and waiting for review.',
                'under_review'  => 'Our team is currently reviewing your documents.',
                'approved'      => 'All wallet features are now available.',
                'rejected'      => 'Your KYC was rejected. Please resubmit.',
            ];

            if ($kyc) {
                $kycData = [
                    'has_kyc'          => true,
                    'kyc_id'           => $kyc->_id,
                    'kyc_status'       => $kyc->status,
                    'status_message'   => $kycStatusMessages[$kyc->status] ?? 'Unknown status.',
                    'document_type'    => $kyc->document_type,
                    'rejection_reason' => $kyc->rejection_reason ?? null,
                    'submitted_at'     => $kyc->submitted_at ? Carbon::parse($kyc->submitted_at)->format('d M Y H:i') : null,
                    'reviewed_at'      => $kyc->reviewed_at ? Carbon::parse($kyc->reviewed_at)->format('d M Y H:i') : null,
                ];
            } else {
                $kycData = [
                    'has_kyc'          => false,
                    'kyc_id'           => null,
                    'kyc_status'       => 'not_submitted',
                    'status_message'   => $kycStatusMessages['not_submitted'],
                    'document_type'    => null,
                    'rejection_reason' => null,
                    'submitted_at'     => null,
                    'reviewed_at'      => null,
                ];
            }

            $userData = $user->toArray();
            $userData['wallet'] = $walletData;
            $userData['kyc'] = $kycData;

            return ResponseHelper::sendResponse([
                'user'  => $userData,
                'token' => $token,
            ], 'You have logged in successfully!');
        } else {
            return ResponseHelper::sendResponse([], 'Email or Password is Incorrect!', false, 403);
        }
    }

    public function signup(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'fname' => 'required|max:100',
                'lname' => 'nullable|max:100',
                'email' => 'required|email',
                'password' => 'required|min:6',
            ]);

            $email = strtolower($request->email);
            $emailTaken = User::where('email', $email)->first();

            $unverifyEmails = User::where('email', $email)->where('is_verfied', 0)->get();
            if ($unverifyEmails) {
                foreach ($unverifyEmails as $unverified) {
                    $unverified->delete();
                }
            }

            if ($emailTaken) {
                return response()->json([
                    'success' => false,
                    'message' => 'This Email is already taken!',
                ]);
            }

            $usernameTaken = User::where('username', $request->username)->first();
            if ($usernameTaken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username is already taken!',
                ]);
            }

            $deviceImei = User::where('device_imei', $request['device_imei'])->first();
            if ($deviceImei) {
                return response()->json([
                    'success' => false,
                    'message' => 'Imei is already taken!',
                ]);
            }

            $current = Carbon::now();
            $newExpiry = Carbon::parse($current->copy()->addMonth())->format('Y-m-d');

            $user = User::create([
                'name' => $request['fname'],
                'username' => $request['username'],
                'email' => $email,
                'password' => Hash::make(trim($request->password)),
                'status' => (int)'1',
                'is_admin_user' => (int)'0',
                'is_verfied' => (int)'0',
                'is_superadmin' => (int)'0',
                'congrats_popup' => (int)'1',
                'level' => (int)'0',
                'user_type' => 'cultivated',
                'last_name' => $request['lname'],
                'language' => $request['language'],
                'gender' => $request['gender'],
                'origin' => $request['origin'],
                'location' => $request['location'],
                'country' => $request['country'],
                'maritalStatus' => $request['maritalStatus'] ?? $request['marital_status'],
                'dob' => $request['dob'],
                'province' => $request['province'],
                'city' => $request['city'],
                'phone' => $request['phone'],
                'expired_at' => $newExpiry,
                'subscription_type' => 'basic',
                'device_type' => $request['device_type'],
                'device_imei' => $request['device_imei'],
                'device_name' => $request['device_name'],
                'device_model' => $request['device_model'],
                'device_serial' => $request['device_serial'],
                'is_else' => 'true',
                'is_language' => 'true',
                'is_music' => 'true',
                'family_image' => 'true',
                'friends_image' => 'true',
                'friends_request' => 'true',
                'get_greetings' => 'true',
                'public_image' => 'true',
                'search_option' => 'true',
                'info_banner' => 'banner',
                'new_donation' => 'true',
                'new_events' => 'true',
                'new_history' => 'true',
                'new_music' => 'true',
                'new_news' => 'true',
                'new_videos' => 'true',
                'new_votes' => 'true',
                'live_stream' => '1',
                'play_video' => '1',
                'video_call' => '1',
                'interview' => '1',
                'upload_video' => '1',
                'play_music' => '1',
                'app_status' => 'online'
            ]);

            if ($request->has('image')) {
                $image_path = Helpers::fileUpload($request->image, 'images/user');
                $user->image = $image_path;
                $user->save();
            }

            $userId = '';
            $randomId = $user->user_id;
            if ($user->origin === 'kurdish') {
                $prV = '';
                if ($user->province === 'Rojava') {
                    $prV = 'RA';
                } elseif ($user->province === 'Bakûr') {
                    $prV = 'BK';
                } elseif ($user->province === 'Başûr') {
                    $prV = 'BŞ';
                } else {
                    $prV = 'RH';
                }
                $userId = 'YB-KU' . $randomId . $prV;
            } else {
                $userId = 'YB-US' . $randomId . 'NK';
            }
            $user->user_id = $userId;
            $user->save();

            if ($user->id) {
                $code = rand(1000, 9999);
                UserCode::updateOrCreate(
                    ['user_id' => $user->id],
                    ['code' => $code]
                );

                UserImei::create([
                    'user_id' => $user->id,
                    'device_imei' => $request['device_imei'],
                ]);

                try {
                    $details = [
                        'title' => 'Mail from Yekbun.org',
                        'code' => $code,
                        'username' => $request->username,
                    ];
                    $notify = AdminNotification::first();
                    if ($notify && $notify->otp == 1) {
                        Mail::to($request['email'])->send(new SendCodeMail($details));
                    }
                    return response()->json(['success' => true, "message" => "Verification Code has been sent to your email!", 'user' => $user->id], 201);
                } catch (\Exception $e) {
                    return response()->json(['success' => false, 'message' => 'Something went wrong'], 500);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => 'Something went wrong',
            ], 422);
        }
    }

    public function reactivateAccount(Request $request)
    {
        try {
            if (!$request->email) {
                return response()->json(['success' => false, 'message' => 'Email is Requred!'], 404);
            }
            $email = strtolower($request->email);
            $user = User::where('email', $email)->first();
            if ($user) {
                $code = rand(1000, 9999);
                UserCode::updateOrCreate(
                    ['user_id' => $user->id],
                    ['code' => $code]
                );
                try {
                    $details = [
                        'title' => 'Mail from Yekbun.org',
                        'code' => $code,
                        'username' => $request->username,
                    ];
                    $notify = AdminNotification::first();
                    if ($notify && $notify->otp == 1) {
                        Mail::to($request['email'])->send(new SendCodeMail($details));
                    }
                    return response()->json(['success' => true, "message" => "Verification Code has been sent to your email!", 'user' => $user->id], 201);
                } catch (\Exception $e) {
                    return response()->json(['success' => false, 'message' => 'Something went wrong'], 500);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => 'Something went wrong',
            ], 422);
        }
    }

    public function deactivateAccount()
    {
        $user = User::where('email', Auth::user()->email)->first();
        if ($user) {
            $user->status = (int)0;
            $user->deactivated_at = Carbon::now();
            $user->save();
        }
        return ResponseHelper::sendResponse([], 'Your account is deactived and will be deleted in 90 days. You can reactivate any time in Singin');
    }

    public function userImei(Request $request)
    {
        $deviceImei = User::where('device_imei', $request['device_imei'])->first();

        if ($deviceImei) {
            return response()->json([
                'success' => false,
                'message' => 'Imei is already taken.',
                'status' => $deviceImei->status ?? 0,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Imei not found!.',
            'status' => 0,
        ], 200);
    }

    public function verifyDevice(Request $request)
    {
        $request->validate([
            'email' => 'required',
        ]);
        $email = strtolower($request->email);
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not Found!'], 404);
        }
        if ($user->id) {
            $code = rand(1000, 9999);
            UserCode::updateOrCreate(
                ['user_id' => $user->id],
                ['code' => $code]
            );

            try {
                $details = [
                    'title' => 'Mail from Yekbun.org',
                    'code' => $code,
                    'username' => $user->username ?? 'User',
                ];
                $notify = AdminNotification::first();
                if ($notify && $notify->otp == 1) {
                    Mail::to($request['email'])->send(new SendCodeMail($details));
                }
                $data = ['user' => $user, 'otp' => $code];
                return ResponseHelper::sendResponse($data, 'Verification Code has been sent to your email!');
            } catch (\Exception $e) {
                return ResponseHelper::sendResponse([], 'Something went wrong', false, 505);
            }
        }
    }

    public function registerDevice(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'device_serial' => 'required',
            'device_model' => 'required',
            'device_type' => 'required',
            'device_imei' => 'required',
            'device_name' => 'required',
        ]);
        $email = strtolower($request->email);
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not Found!'], 404);
        }
        try {
            $createdUser = User::updateOrCreate(
                ['email' => $email],
                [
                    'device_serial' => $request->device_serial,
                    'device_type' => $request->device_type,
                    'device_model' => $request->device_model,
                    'device_imei' => $request->device_imei,
                    'device_name' => $request->device_name,
                ]
            );
            UserImei::updateOrCreate(['user_id' => $createdUser->id], [
                'user_id' => $createdUser->id,
                'device_imei' => $request['device_imei'],
            ]);
            return ResponseHelper::sendResponse($createdUser, 'New device has been registered successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to register device', false, 403);
        }
    }

    public function getCode(Request $request)
    {
        if (empty($request->email)) {
            return ResponseHelper::sendResponse([], 'Email is Required!', false, 404);
        }
        if (empty($request->otp)) {
            return ResponseHelper::sendResponse([], 'OTP is Required!', false, 404);
        }
        $email = strtolower($request->email);
        $user = User::where('email', $email)->first();

        if (!$user) {
            return ResponseHelper::sendResponse([], 'User not found!', false, 404);
        }

        $code = UserCode::where('user_id', $user->id)->first();

        if (!$code) {
            return ResponseHelper::sendResponse([], 'Code not found!', false, 404);
        }

        if ((int)$code->code == (int)$request->otp) {
            $user->email = $email;
            $user->email_verified_at = Carbon::now();
            $user->is_verfied = (int)1;
            $user->status = (int)1;
            $user->save();
            return ResponseHelper::sendResponse($user, 'Valid Code!');
        } else {
            return ResponseHelper::sendResponse([], 'Invalid Code!', false, 403);
        }
    }

    public function logout(Request $request)
    {
        if (!empty($request->status)) {
            $user = User::find(Auth::id());
            $user->app_status = $request->status;
            $user->save();
        }
        JWTAuth::parseToken()->invalidate(true);
        return ResponseHelper::sendResponse([], 'Logout successful!');
    }

    public function forgot_password(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        $email = strtolower($request->email);
        $user = User::where('email', '=', $email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found!']);
        }

        $code = rand(1000, 9999);
        $token  = Str::random(20);
        $tempreset = ResetUserPassword::where('user_id', $user->id)->get();
        if ($tempreset) {
            foreach ($tempreset as $userDel) {
                $userDel->delete();
            }
        }
        ResetUserPassword::updateorCreate(['code' => $code, 'user_id' => $user->id, 'token' => $token, 'email' => $user->email]);
        try {
            $details = [
                'title' => 'Mail from Yekbun.org',
                'code' => $code,
                'username' => $user->username
            ];
            $notify = AdminNotification::first();
            if ($notify && $notify->otp == 1) {
                Mail::to($user->email)->send(new SendCodeMail($details));
            }
            return response()->json(['success' => true, 'message' => 'Verification Code has been sent to your email!', 'data' => ['user_id' => $user->id, 'email' => $user->email, 'token' => $token]], 201);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function resetpassword(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'token' => 'required',
            'user_id' => 'required'
        ]);

        $resetEntry = ResetUserPassword::where('user_id', $request->user_id)->first();
        if (!$resetEntry || $resetEntry->password_token != $request->token) {
            return response()->json(['success' => false, 'message' => 'Invalid token or user.'], 400);
        }

        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found!'], 404);
        }

        $user->password = Hash::make(trim($request->password));
        $user->save();

        ResetUserPassword::where('user_id', $request->user_id)->delete();

        return response()->json(['success' => true, 'message' => 'Your password has been changed.'], 200);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'code' => 'required',
            'token' => 'required',
            'user_id' => 'required'
        ]);

        $resetEntry = ResetUserPassword::where('user_id', $request->user_id)->first();
        if (!$resetEntry) {
            return response()->json(['success' => false, 'message' => 'User not found!'], 404);
        }

        if ($request->token != $resetEntry->token) {
            return response()->json(['success' => false, 'message' => 'Invalid token.'], 400);
        }

        if ($resetEntry->code == $request->code) {
            $password_token = Str::random(50);
            $resetEntry->password_token = $password_token;
            $resetEntry->save();
            return response()->json(['success' => true, 'data' => ['token' => $password_token, 'user_id' => $resetEntry->user_id]], 200);
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid OTP. Please try again.'], 400);
        }
    }

    public function reset_resend(Request $request)
    {
        $user = ResetUserPassword::where('user_id', $request->user_id)->first();
        $code = rand(1000, 9999);

        try {
            $details = [
                'title' => 'Mail from Yekbun.com',
                'code' => $code,
                'username' => $user->username
            ];
            $notify = AdminNotification::first();
            if ($notify && $notify->otp == 1) {
                Mail::to($user->email)->send(new SendCodeMail($details));
            }
            $user->code = $code;
            $user->save();

            return response()->json(['success' => true, "message" => "Email successfully resent."]);
        } catch (\Exception $e) {
            info("Error: " . $e->getMessage());
        }
    }

    public function existsEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = User::where('email', $request->email)->first();

        if (!empty($email)) {
            return response()->json(['email' => $request->email, 'success' => true], 200);
        } else {
            return response()->json(['email' => null, 'success' => false], 404);
        }
    }

    public function acceptPrivacyPolicy(Request $request)
    {
        $request->validate([
            'privacy_policy' => 'required',
        ]);

        try {
            User::where('_id', Auth::id())->update([
                'isPrivacyPolicyAccepted' => $request->privacy_policy,
            ]);
            $user = User::find(Auth::id());
            if ($user->isPrivacyPolicyAccepted == true) {
                return response()->json(['message' => 'Privacy Policy has been Accepted!', 'user' => $user, 'success' => true], 200);
            } else {
                return response()->json(['message' => 'Privacy Policy has been Rejected!', 'user' => $user, 'success' => true], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'success' => false], 403);
        }
    }

    public function getMyDetails()
    {
        $user = User::find(Auth::id());
        return ResponseHelper::sendResponse($user, 'My Details has been Fetched Successfully!');
    }

    public function deleteMyAccount()
    {
        $user = User::find(Auth::id());
        $user->delete();
        return ResponseHelper::sendResponse([], 'Account has been Deleted Successfully!');
    }

    public function currenceyLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = strtolower($request->email);
        $user = User::where('email', $email)->first();

        if (!$user) {
            return ResponseHelper::sendResponse([], 'User not found!', false, 404);
        }

        if ($user->status == 0) {
            return ResponseHelper::sendResponse([], 'Your account is deactivated.', false, 403);
        }

        $otp = rand(100000, 999999);

        UserCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code' => $otp,
                'expires_at' => now()->addMinutes(5)
            ]
        );

        $details = [
            'code' => $otp,
            'username' => $user->username,
        ];

        Mail::to($user->email)->send(new SendCodeMail($details));

        return ResponseHelper::sendResponse([], 'OTP has been sent to your email.', true, 200);
    }

    public function verifyCurrenceyLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required'
        ]);

        $user = User::where('email', strtolower($request->email))->first();

        if (!$user) {
            return ResponseHelper::sendResponse([], 'User not found!', false, 404);
        }

        $userCode = UserCode::where('user_id', $user->_id)
            ->where('code', (int)$request->otp)
            ->first();

        if (!$userCode) {
            return ResponseHelper::sendResponse([], 'Invalid OTP!', false, 403);
        }

        try {
            if (!$token = JWTAuth::fromUser($user)) {
                return ResponseHelper::sendResponse([], 'Login failed!', false, 400);
            }
        } catch (JWTException $e) {
            return ResponseHelper::sendResponse([], 'Token error!', false, 500);
        }

        $userCode->delete();

        return ResponseHelper::sendResponse(
            ['user' => $user, 'token' => $token],
            'Login successful!',
            true,
            200
        );
    }
}
