<?php

namespace App\Helpers;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogHelper
{
    /**
     * Record an admin activity log entry.
     *
     * @param  string       $title        Short headline ("Admin Login", "Role Created")
     * @param  string       $description  Longer detail
     * @param  string       $level        info | success | warning | error | critical
     * @param  string       $category     auth | task | content | role | api | security | system
     * @param  array|null   $payload      Arbitrary JSON details
     * @param  Request|null $request      Pass request for IP/user-agent extraction
     */
    public static function record(
        string $title,
        string $description = '',
        string $level = 'info',
        string $category = 'system',
        ?array $payload = null,
        ?Request $request = null,
        ?User $causer = null
    ): ?AdminActivityLog {
        try {
            $req = $request ?? request();
            $user = $causer ?? Auth::user();

            $log = new AdminActivityLog();
            $log->title       = $title;
            $log->description = $description;
            $log->level       = $level;
            $log->category    = $category;
            $log->payload     = $payload;

            if ($user) {
                $log->user_id   = (string) $user->_id;
                $log->user_name = $user->name ?? $user->username ?? 'Admin';
            }

            if ($req) {
                $log->ip         = $req->ip() ?? '—';
                $log->user_agent = (string) $req->header('User-Agent', '');
                $log->endpoint   = $req->method() . ' ' . ($req->path() ? '/' . $req->path() : '');
                $log->session_id = $req->header('X-Session-Id') ?? substr(hash('sha256', $req->ip() . ($user?->_id ?? '')), 0, 12);
            }

            $log->save();
            return $log;
        } catch (\Throwable $e) {
            // Don't let logging failures break the request
            return null;
        }
    }
}
