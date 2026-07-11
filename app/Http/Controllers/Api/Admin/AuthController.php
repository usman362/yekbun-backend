<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ActivityLogHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Helpers\Helpers;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation failed',
                false,
                422
            );
        }

        $email = strtolower($request->email);
        $user = User::where('email', $email)->first();

        if (!$user) {
            return ResponseHelper::sendResponse([], 'Invalid credentials.', false, 401);
        }

        if (!$user->is_admin_user && !$user->is_superadmin) {
            return ResponseHelper::sendResponse([], 'Access denied. Admin privileges required.', false, 403);
        }

        if ($user->status == 0) {
            return ResponseHelper::sendResponse([], 'Your account has been deactivated.', false, 403);
        }

        if (!Auth::attempt(['email' => $email, 'password' => $request->password])) {
            return ResponseHelper::sendResponse([], 'Invalid credentials.', false, 401);
        }

        try {
            // Enforce single active session per admin account as well.
            $user->session_version = (int) ($user->session_version ?? 0) + 1;
            $user->last_login_at = Carbon::now();
            $user->save();

            $token = JWTAuth::claims(['sv' => (int) $user->session_version])->fromUser($user);
        } catch (JWTException $e) {
            return ResponseHelper::sendResponse([], 'Could not create token.', false, 500);
        }

        ActivityLogHelper::record(
            'Admin Login',
            ($user->is_superadmin ? 'Super Admin' : 'Admin') . ' logged in successfully',
            'success',
            'auth',
            [
                'role' => $user->is_superadmin ? 'super_admin' : 'admin',
                'method' => 'password',
            ],
            $request,
            $user
        );

        return ResponseHelper::sendResponse([
            'token' => $token,
            'user'  => $this->profilePayload($user),
        ], 'Login successful.');
    }

    public function me(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        return ResponseHelper::sendResponse($this->profilePayload($user), 'User details fetched.');
    }

    /**
     * Update logged-in admin profile fields and optional avatar (multipart `image`).
     * Avatar is stored on the API public disk via fileUpload (same as mobile profile).
     */
    public function updateProfile(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'name'       => 'sometimes|nullable|string|max:255',
            'email'      => 'sometimes|nullable|email|max:255',
            'phone'      => 'nullable|string|max:50',
            'country'    => 'nullable|string|max:100',
            'language'   => 'nullable|string|max:100',
            'department' => 'nullable|string|max:150',
            'bio'        => 'nullable|string|max:2000',
            'image'      => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                $validator->errors()->first() ?: 'Validation failed',
                false,
                422
            );
        }

        if ($request->filled('email')) {
            $email = strtolower(trim((string) $request->email));
            $taken = User::where('email', $email)->where('_id', '!=', $user->_id)->exists();
            if ($taken) {
                return ResponseHelper::sendResponse([], 'Email already in use.', false, 422);
            }
            $user->email = $email;
        }

        if ($request->has('name')) {
            $user->name = trim((string) $request->name);
        }
        if ($request->has('phone')) {
            $user->phone = (string) $request->phone;
        }
        if ($request->has('country')) {
            $user->country = (string) $request->country;
        }
        if ($request->has('language')) {
            $user->is_language = (string) $request->language;
        }
        if ($request->has('department')) {
            $user->department = (string) $request->department;
        }
        if ($request->has('bio')) {
            $user->bio = (string) $request->bio;
        }

        if ($request->hasFile('image')) {
            try {
                $existing = public_path('storage/' . ltrim((string) ($user->image ?? ''), '/'));
                if (!empty($user->image) && is_file($existing)) {
                    @unlink($existing);
                }
            } catch (\Throwable $e) {
                // ignore cleanup errors
            }
            $user->image = Helpers::fileUpload($request->file('image'), 'images/user');
        }

        $user->save();

        return ResponseHelper::sendResponse($this->profilePayload($user->fresh()), 'Profile updated.');
    }

    /** Avatar-only upload — avoids multipart + field validation conflicts. */
    public function updateAvatar(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                $validator->errors()->first() ?: 'Validation failed',
                false,
                422
            );
        }

        if (!$request->hasFile('image')) {
            return ResponseHelper::sendResponse([], 'No image file received.', false, 422);
        }

        try {
            $existing = public_path('storage/' . ltrim((string) ($user->image ?? ''), '/'));
            if (!empty($user->image) && is_file($existing)) {
                @unlink($existing);
            }
        } catch (\Throwable $e) {
            // ignore cleanup errors
        }

        $user->image = Helpers::fileUpload($request->file('image'), 'images/user');
        $user->save();

        return ResponseHelper::sendResponse($this->profilePayload($user->fresh()), 'Avatar updated.');
    }

    public function changePassword(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse($validator->errors(), 'Validation failed', false, 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return ResponseHelper::sendResponse([], 'Current password is incorrect.', false, 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return ResponseHelper::sendResponse([], 'Password updated successfully.');
    }

    public function logout(Request $request)
    {
        $user = null;
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $e) {
            // No valid token — that's fine, still record logout attempt
        }

        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException $e) {
            // Token already invalid — that's fine
        }

        ActivityLogHelper::record(
            'Admin Logout',
            ($user?->is_superadmin ? 'Super Admin' : 'Admin') . ' session ended',
            'info',
            'auth',
            ['role' => $user?->is_superadmin ? 'super_admin' : 'admin'],
            $request,
            $user
        );

        return ResponseHelper::sendResponse([], 'Logged out successfully.');
    }

    public function refresh()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
        } catch (JWTException $e) {
            return ResponseHelper::sendResponse([], 'Could not refresh token.', false, 401);
        }

        return ResponseHelper::sendResponse(['token' => $newToken], 'Token refreshed.');
    }

    /** Shared shape for login / me / profile update. */
    private function profilePayload(User $user): array
    {
        $joined = $user->created_at ? Carbon::parse($user->created_at) : null;
        $lastLogin = !empty($user->last_login_at) ? Carbon::parse($user->last_login_at) : null;

        return [
            'id'         => (string) $user->_id,
            'name'       => $user->name ?? '',
            'email'      => $user->email ?? '',
            'username'   => $user->username ?? '',
            'image'      => Helpers::profileImageUrl($user->image),
            'role'       => $user->is_superadmin ? 'superadmin' : 'admin',
            'phone'      => (string) ($user->phone ?? ''),
            'country'    => (string) ($user->country ?? ''),
            'language'   => (string) ($user->is_language ?? $user->language ?? ''),
            'department' => (string) ($user->department ?? ''),
            'bio'        => (string) ($user->bio ?? ''),
            'joined_at'  => $joined ? $joined->format('d. M Y') : '',
            'last_login' => $lastLogin ? $lastLogin->diffForHumans() : '',
        ];
    }
}
