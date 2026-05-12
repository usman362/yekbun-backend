<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ActivityLogHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Helpers\Helpers;
use Illuminate\Support\Facades\Auth;
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
            $token = JWTAuth::fromUser($user);
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
            'user'  => [
                'id'        => $user->_id,
                'name'      => $user->name,
                'email'     => $user->email,
                'username'  => $user->username,
                'image'     => Helpers::mediaUrl($user->image),
                'role'      => $user->is_superadmin ? 'superadmin' : 'admin',
            ],
        ], 'Login successful.');
    }

    public function me(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        return ResponseHelper::sendResponse([
            'id'        => $user->_id,
            'name'      => $user->name,
            'email'     => $user->email,
            'username'  => $user->username,
            'image'     => Helpers::mediaUrl($user->image),
            'role'      => $user->is_superadmin ? 'superadmin' : 'admin',
        ], 'User details fetched.');
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
}
