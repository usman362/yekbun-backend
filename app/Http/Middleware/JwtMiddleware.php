<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['error' => 'UnAuthorised User, Login again.', 'success' => false], 403);
            }

            // Enforce single active session per user (1 device at a time).
            // Each login issues a JWT with claim `sv` and bumps `users.session_version`.
            // If the claim doesn't match the user's current version, this token is stale.
            $payloadSv = JWTAuth::parseToken()->getPayload()->get('sv');
            $currentSv = (int) ($user->session_version ?? 0);
            if (!$payloadSv || (int) $payloadSv !== $currentSv) {
                return response()->json(['error' => 'Session expired. Login again.', 'success' => false], 403);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'UnAuthorised User, Login again.', 'success' => false], 403);
        }

        return $next($request);
    }
}
