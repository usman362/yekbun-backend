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
     * @param  User|null    $causer       Acting admin
     * @param  array        $meta         module, page, action, task_category, target
     */
    public static function record(
        string $title,
        string $description = '',
        string $level = 'info',
        string $category = 'system',
        ?array $payload = null,
        ?Request $request = null,
        ?User $causer = null,
        array $meta = []
    ): ?AdminActivityLog {
        try {
            $req = $request ?? request();
            $user = $causer ?? Auth::user();
            $ua = $req ? (string) $req->header('User-Agent', '') : '';

            $log = new AdminActivityLog();
            $log->title       = $title;
            $log->description = $description;
            $log->level       = $level;
            $log->category    = $category;
            $log->payload     = $payload;

            $log->module         = $meta['module'] ?? ($payload['module'] ?? null);
            $log->page           = $meta['page'] ?? ($payload['page'] ?? null);
            $log->action_label   = $meta['action'] ?? ($payload['action'] ?? $title);
            $log->task_category  = $meta['task_category'] ?? ($payload['task_category'] ?? null);
            $log->target         = $meta['target'] ?? ($payload['target'] ?? null);

            if ($user) {
                $log->user_id   = (string) $user->_id;
                $log->user_name = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''))
                    ?: ($user->username ?? 'Admin');
            }

            if ($req) {
                $log->ip         = $req->ip() ?? '—';
                $log->user_agent = $ua;
                $log->device     = self::parseDevice($ua);
                $log->browser    = self::parseBrowser($ua);
                $log->os         = self::parseOs($ua);
                $log->endpoint   = $req->method() . ' ' . ($req->path() ? '/' . $req->path() : '');
                $log->session_id = $req->header('X-Session-Id')
                    ?? substr(hash('sha256', $req->ip() . ($user?->_id ?? '')), 0, 12);
            }

            $log->save();
            return $log;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function parseDevice(string $ua): string
    {
        if ($ua === '') return 'unknown';
        if (str_contains($ua, 'iPhone')) return 'iPhone';
        if (str_contains($ua, 'iPad')) return 'iPad';
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'Macintosh')) return 'MacBook';
        if (str_contains($ua, 'Windows')) return 'Windows Desktop';
        if (str_contains($ua, 'Linux')) return 'Linux';
        return 'unknown';
    }

    public static function parseBrowser(string $ua): string
    {
        if ($ua === '') return '—';
        if (str_contains($ua, 'Edg/')) return 'Edge';
        if (preg_match('/Chrome\/([\d.]+)/', $ua, $m) && !str_contains($ua, 'Edg/')) {
            return 'Chrome ' . explode('.', $m[1])[0];
        }
        if (preg_match('/Firefox\/([\d.]+)/', $ua, $m)) {
            return 'Firefox ' . explode('.', $m[1])[0];
        }
        if (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')) return 'Safari';
        return '—';
    }

    public static function parseOs(string $ua): string
    {
        if ($ua === '') return '—';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
            return str_contains($ua, 'iPad') ? 'iPadOS' : 'iOS';
        }
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh')) return 'macOS';
        if (str_contains($ua, 'Windows NT 10')) return 'Windows 10/11';
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Linux')) return 'Linux';
        return '—';
    }
}
