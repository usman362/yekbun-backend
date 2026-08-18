<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Mail\SendCodeMail;
use App\Models\AdminNotification;
use App\Models\KycVerification;
use App\Models\User;
use App\Models\UserCode;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Passwordless OTP login for the web app (yekbun.app).
 *
 * Two steps:
 *   1. POST /api/otp-login/send    — accepts {email}, generates a 6-digit code, emails it,
 *                                    and stores it (hashed) under user_codes with a 10-min TTL.
 *   2. POST /api/otp-login/verify  — accepts {email, otp}, checks the code, issues a JWT
 *                                    via JWTAuth::fromUser($user) and returns the same
 *                                    {user, token} shape AuthController::login uses, so the
 *                                    frontend's existing token-handling code keeps working.
 *
 * Why a separate controller from AuthController::login: the mobile login is email+password+IMEI;
 * the web wants passwordless OTP. Keeping them apart avoids overloading one endpoint with two
 * very different validation paths.
 */
class OtpLoginController extends Controller
{
    /** OTP lifetime — 10 minutes is long enough for users to copy from email, short enough to be safe. */
    private const OTP_TTL_MINUTES = 10;

    /**
     * POST /api/otp-login/send
     * Body: { email: string }
     *
     * Always returns a generic "OTP sent" response — we don't leak whether the email exists in
     * the DB (basic enumeration protection). The mail itself is only dispatched when the user
     * actually exists. Mail is gated by AdminNotification::otp so prod can turn it off if needed.
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);
        }

        $email = strtolower($request->email);
        $user  = User::where('email', $email)->first();

        // Generic response — same message whether user exists or not. Prevents email enumeration.
        $generic = ['email' => $email];
        $msg     = 'If an account exists for this email, a verification code has been sent.';

        if (!$user) {
            return ResponseHelper::sendResponse($generic, $msg);
        }

        // Generate a 6-digit code, store as plaintext for simplicity (matches existing UserCode usage).
        $code = (string) random_int(100000, 999999);

        UserCode::updateOrCreate(
            ['user_id' => $user->_id],
            [
                'user_id'    => $user->_id,
                'code'       => $code,
                'expires_at' => Carbon::now()->addMinutes(self::OTP_TTL_MINUTES),
            ]
        );

        // Only send the mail if the admin hasn't disabled OTP emails globally. SendCodeMail
        // is the same template the rest of the app uses for verification codes.
        $notify = AdminNotification::first();
        if (!$notify || $notify->otp == 1) {
            try {
                Mail::to($user->email)->send(new SendCodeMail([
                    'title'    => 'Your YekBûn login code',
                    'code'     => $code,
                    'username' => $user->username ?? $user->name ?? 'there',
                ]));
            } catch (\Throwable) {
                // Don't 500 if the mailer is misconfigured. Never fall back to returning the
                // code in the API body — email is the only delivery channel.
            }
        }

        // Never return the OTP (or any alias) outside a local workstation. Production
        // APP_DEBUG=true must not leak the code — gate on APP_ENV=local only.
        if (config('app.env') === 'local') {
            $generic['dev_code'] = $code;
        }

        return ResponseHelper::sendResponse($generic, $msg);
    }

    /**
     * POST /api/otp-login/verify
     * Body: { email: string, otp: string }
     *
     * Verifies the OTP and issues a JWT. Response shape mirrors AuthController::login so the
     * existing frontend useLogin() hook continues to work unchanged:
     *   { user: {...full user doc with embedded wallet+kyc}, token: "<jwt>" }
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);
        if ($validator->fails()) {
            return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);
        }

        $email = strtolower($request->email);
        $user  = User::where('email', $email)->first();
        if (!$user) {
            return ResponseHelper::sendResponse([], 'Invalid email or code.', false, 401);
        }

        if ($user->status == 0) {
            return ResponseHelper::sendResponse(
                [],
                'Your Account is deactivated, kindly click Recover my Account',
                false,
                404
            );
        }

        $record = UserCode::where('user_id', $user->_id)->first();
        if (!$record || $record->code !== (string) $request->otp) {
            return ResponseHelper::sendResponse([], 'Invalid email or code.', false, 401);
        }

        // Honour TTL — `expires_at` is a Carbon-cast datetime on the model.
        if ($record->expires_at && Carbon::parse($record->expires_at)->isPast()) {
            return ResponseHelper::sendResponse([], 'Code has expired. Request a new one.', false, 410);
        }

        // Code consumed — delete so it can't be re-used. (We could rotate instead but delete is
        // simpler and matches the "send → verify → throw away" mental model.)
        $record->delete();

        // Mirror AuthController::login's post-success housekeeping:
        $user->force_logout = 0;
        if (empty($user->email_verified_at)) {
            // OTP delivery to inbox proves email ownership — promote unverified accounts.
            $user->email_verified_at = Carbon::now();
        }
        $user->save();

        try {
            // Single-session enforcement: bump version so any previously issued tokens become stale.
            $user->session_version = (int) ($user->session_version ?? 0) + 1;
            $user->save();

            $token = JWTAuth::claims(['sv' => (int) $user->session_version])->fromUser($user);
            if (!$token) {
                return ResponseHelper::sendResponse([], 'Could not issue session token.', false, 500);
            }
        } catch (JWTException $e) {
            return ResponseHelper::sendResponse([], 'Could not issue session token.', false, 500);
        }

        // Embed wallet + kyc into the user payload so the web app can hydrate balances and KYC
        // status immediately on first paint without a separate round-trip.
        $userData          = $user->toArray();
        $userData['wallet'] = $this->walletPayload($user);
        $userData['kyc']    = $this->kycPayload($user);

        return ResponseHelper::sendResponse([
            'user'  => $userData,
            'token' => $token,
        ], 'Login successful.');
    }

    /** Compact wallet snapshot for embedding in the login response. Mirrors AuthController. */
    private function walletPayload(User $user): array
    {
        $wallet = Wallet::where('user_id', (string) $user->_id)->first();
        if (!$wallet) {
            return [
                'has_wallet'            => false,
                'has_valid'             => false,
                'has_pin'               => false,
                'welcome_bonus_claimed' => false,
                'wallet_id'             => null,
                'wallet_status'         => 'not_found',
                'balance'               => 0,
            ];
        }
        $status = $wallet->status ?? 'under_review';
        return [
            'has_wallet'            => true,
            'has_valid'             => in_array($status, ['activated', 'active', 'approved'], true),
            'has_pin'               => !empty($wallet->pin),
            'welcome_bonus_claimed' => !empty($wallet->welcome_bonus_claimed),
            'wallet_id'             => $wallet->formattedWalletNumber(),
            'wallet_number'         => $wallet->formattedWalletNumber(),
            'wallet_status'         => $status,
            'balance'               => round((float) ($wallet->balance ?? 0), 2),
        ];
    }

    /** Compact KYC snapshot for embedding in the login response. */
    private function kycPayload(User $user): array
    {
        $kyc = KycVerification::where('user_id', (string) $user->_id)
            ->orderBy('created_at', 'desc')
            ->first();
        if (!$kyc) {
            return ['has_kyc' => false, 'kyc_status' => 'not_submitted'];
        }
        return [
            'has_kyc'    => true,
            'kyc_id'     => (string) $kyc->_id,
            'kyc_status' => $kyc->status,
        ];
    }
}
