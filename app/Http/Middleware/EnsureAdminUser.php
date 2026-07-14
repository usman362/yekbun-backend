<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ensures the JWT subject is an admin (is_admin_user / is_superadmin).
 * Login already checks this, but every /admin/* request must re-check —
 * otherwise any mobile JWT can hit team / feeds / etc.
 */
class EnsureAdminUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'UnAuthorised User, Login again.', 'success' => false], 403);
        }

        $isAdmin = (int) ($user->is_admin_user ?? 0) === 1
            || (int) ($user->is_superadmin ?? 0) === 1;

        if (!$isAdmin) {
            return response()->json(['error' => 'Admin access required.', 'success' => false], 403);
        }

        $status = $user->status ?? 1;
        if ($status === 0 || $status === '0' || $status === false) {
            return response()->json(['error' => 'Account disabled.', 'success' => false], 403);
        }

        return $next($request);
    }
}
