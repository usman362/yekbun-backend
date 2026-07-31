<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ActivityLogHelper;
use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\AdminPresence;
use App\Models\AdminRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Facades\JWTAuth;

class SystemAdminController extends Controller
{
    private const ONLINE_SECONDS = 180;

    private const AVATAR_COLORS = [
        'from-blue-500 to-indigo-500',
        'from-emerald-500 to-teal-500',
        'from-rose-500 to-pink-500',
        'from-amber-500 to-orange-500',
        'from-violet-500 to-purple-500',
        'from-cyan-500 to-sky-500',
        'from-fuchsia-500 to-pink-500',
        'from-teal-500 to-emerald-500',
    ];

    private const TASK_CATALOG = [
        'users' => [
            'label' => 'Users Approved',
            'icon'  => 'UserCheck',
            'color' => 'text-emerald-500',
            'bg'    => 'bg-emerald-500/10',
        ],
        'complaints' => [
            'label' => 'Complaints Reviewed',
            'icon'  => 'ShieldCheck',
            'color' => 'text-blue-500',
            'bg'    => 'bg-blue-500/10',
        ],
        'comments' => [
            'label' => 'Comments Deleted',
            'icon'  => 'Trash2',
            'color' => 'text-rose-500',
            'bg'    => 'bg-rose-500/10',
        ],
        'feeds' => [
            'label' => 'Feeds Published',
            'icon'  => 'FileText',
            'color' => 'text-violet-500',
            'bg'    => 'bg-violet-500/10',
        ],
        'videos' => [
            'label' => 'Videos Approved',
            'icon'  => 'Eye',
            'color' => 'text-amber-500',
            'bg'    => 'bg-amber-500/10',
        ],
        'wallet' => [
            'label' => 'Wallet Requests Approved',
            'icon'  => 'Wallet',
            'color' => 'text-teal-500',
            'bg'    => 'bg-teal-500/10',
        ],
        'reports' => [
            'label' => 'Reports Exported',
            'icon'  => 'Download',
            'color' => 'text-cyan-500',
            'bg'    => 'bg-cyan-500/10',
        ],
    ];

    /* ────────── Activity Center (System Log UI) ────────── */

    public function activityOverview(Request $request)
    {
        $todayStart = Carbon::today();
        $onlineCutoff = Carbon::now()->subSeconds(self::ONLINE_SECONDS);

        $admins = $this->adminUsers();
        $rolesById = $this->rolesById($admins);
        $adminIds = $admins->pluck('_id')->map(fn ($id) => (string) $id)->filter()->values()->all();
        $presences = empty($adminIds)
            ? collect()
            : AdminPresence::whereIn('user_id', $adminIds)->get()->keyBy('user_id');

        $todayLogs = AdminActivityLog::where('created_at', '>=', $todayStart)
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        $onlineAdmins = [];
        $performance = [];
        $allAdminsPayload = [];

        foreach ($admins as $admin) {
            $uid = (string) $admin->_id;
            $presence = $presences->get($uid);
            $isOnline = $presence
                && $presence->last_seen_at
                && Carbon::parse($presence->last_seen_at)->gte($onlineCutoff);

            $card = $this->adminCard($admin, $presence, $rolesById, $isOnline, $todayLogs);
            $allAdminsPayload[] = $card;
            if ($isOnline) {
                $onlineAdmins[] = $card;
            }
            $performance[] = [
                'id'           => $card['id'],
                'name'         => $card['name'],
                'role'         => $card['role'],
                'avatar'       => $card['avatar'],
                'color'        => $card['color'],
                'online'       => $isOnline,
                'tasksToday'   => $card['tasksToday'],
                'actionsToday' => $card['actionsToday'],
                'avgSession'   => $card['avgSession'],
                'loginTime'    => $card['loginTime'],
            ];
        }

        // Sort online by last activity desc
        usort($onlineAdmins, fn ($a, $b) => strcmp($b['lastActivityRaw'] ?? '', $a['lastActivityRaw'] ?? ''));

        $timeline = $todayLogs->take(80)->map(fn ($log) => $this->timelineEvent($log))->values()->all();

        $authLogs = AdminActivityLog::where('category', 'auth')
            ->orderBy('created_at', 'desc')
            ->limit(40)
            ->get();

        $recentLogins = [];
        $recentLogouts = [];
        foreach ($authLogs as $log) {
            $entry = [
                'id'       => (string) $log->_id,
                'name'     => $log->user_name ?? 'Admin',
                'avatar'   => $this->initials($log->user_name ?? 'A'),
                'color'    => $this->colorFor($log->user_id ?? $log->user_name ?? 'x'),
                'device'   => $log->device ?: ActivityLogHelper::parseDevice((string) ($log->user_agent ?? '')),
                'country'  => $log->country ?? '—',
                'time'     => $log->created_at ? Carbon::parse($log->created_at)->format('H:i') : '—',
                'duration' => is_array($log->payload) ? ($log->payload['duration'] ?? null) : null,
            ];
            $title = strtolower((string) $log->title);
            if (str_contains($title, 'login') && count($recentLogins) < 8) {
                $recentLogins[] = $entry;
            } elseif (str_contains($title, 'logout') && count($recentLogouts) < 8) {
                $recentLogouts[] = $entry;
            }
        }

        $taskCategories = $this->buildTaskCategories($todayLogs);
        $completedTasksToday = array_sum(array_column($taskCategories, 'count'));
        $actionsToday = $todayLogs->count();
        $offlineCount = max(0, $admins->count() - count($onlineAdmins));

        return ResponseHelper::sendResponse([
            'stats' => [
                'online'                => count($onlineAdmins),
                'total_accounts'        => $admins->count(),
                'offline'               => $offlineCount,
                'completed_tasks_today' => $completedTasksToday,
                'actions_today'         => $actionsToday,
                'active_sessions'       => count($onlineAdmins),
            ],
            'online_admins'    => $onlineAdmins,
            'admins'           => $allAdminsPayload,
            'timeline'         => $timeline,
            'recent_logins'    => $recentLogins,
            'recent_logouts'   => $recentLogouts,
            'task_categories'  => $taskCategories,
            'performance'      => $performance,
            'generated_at'     => Carbon::now()->toIso8601String(),
        ], 'Admin activity overview loaded.');
    }

    public function presence(Request $request)
    {
        $user = Auth::user() ?? JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return ResponseHelper::sendResponse([], 'Unauthorized.', false, 401);
        }

        $module = trim((string) $request->input('module', 'Dashboard'));
        $page = trim((string) $request->input('page', 'Overview'));
        $action = trim((string) $request->input('action', 'Browsing'));
        $ua = (string) $request->header('User-Agent', '');
        $uid = (string) $user->_id;
        $now = Carbon::now();

        $row = AdminPresence::where('user_id', $uid)->first() ?? new AdminPresence();
        $isNew = !$row->exists;

        $row->user_id     = $uid;
        $row->user_name   = trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? 'Admin');
        $row->module      = $module !== '' ? $module : 'Dashboard';
        $row->page        = $page !== '' ? $page : 'Overview';
        $row->action      = $action !== '' ? $action : 'Browsing';
        $row->ip          = $request->ip() ?? '—';
        $row->user_agent  = $ua;
        $row->device      = ActivityLogHelper::parseDevice($ua);
        $row->browser     = ActivityLogHelper::parseBrowser($ua);
        $row->os          = ActivityLogHelper::parseOs($ua);
        $row->country     = $user->country ?? ($row->country ?? '—');
        $row->city        = $row->city ?? '—';
        $row->last_seen_at = $now;
        if ($isNew || !$row->login_at) {
            $row->login_at = $user->last_login_at
                ? Carbon::parse($user->last_login_at)
                : $now;
        }
        $row->save();

        return ResponseHelper::sendResponse([
            'user_id'      => $uid,
            'module'       => $row->module,
            'page'         => $row->page,
            'action'       => $row->action,
            'last_seen_at' => Carbon::parse($row->last_seen_at)->toIso8601String(),
        ], 'Presence updated.');
    }

    public static function clearPresenceForUser(?string $userId): void
    {
        if (!$userId) return;
        try {
            AdminPresence::where('user_id', $userId)->delete();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /* ────────── Existing system endpoints ────────── */

    public function logs(Request $request)
    {
        $limit = min((int) $request->get('limit', 300), 1000);

        $rows = AdminActivityLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log, $i) {
                $ua = (string) ($log->user_agent ?? '');
                return [
                    'id'          => $log->_id ? (string) $log->_id : (string) $i,
                    'level'       => $log->level ?? 'info',
                    'timestamp'   => $log->created_at
                        ? Carbon::parse($log->created_at)->format('Y-m-d H:i:s')
                        : '',
                    'title'       => $log->title ?? 'Activity',
                    'description' => $log->description ?? '',
                    'user'        => $log->user_name ?? null,
                    'user_id'     => $log->user_id ?? null,
                    'ip'          => $log->ip ?? '—',
                    'device'      => $log->device ?: ($ua ? ActivityLogHelper::parseDevice($ua) : 'unknown'),
                    'browser'     => $log->browser ?: ($ua ? ActivityLogHelper::parseBrowser($ua) : null),
                    'endpoint'    => $log->endpoint ?? null,
                    'session_id'  => $log->session_id ?? 'n/a',
                    'category'    => $log->category ?? 'system',
                    'payload'     => is_array($log->payload) ? $log->payload : null,
                ];
            });

        if ($rows->isEmpty()) {
            $rows = collect($this->fileFallback($limit));
        }

        return ResponseHelper::sendResponse($rows->values()->toArray(), 'Logs loaded.');
    }

    public function clearLogs()
    {
        $deleted = AdminActivityLog::query()->delete();
        return ResponseHelper::sendResponse(['deleted' => $deleted], 'Logs cleared.');
    }

    public function health()
    {
        $dbOk = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        return ResponseHelper::sendResponse([
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'database' => $dbOk ? 'connected' : 'error',
            'disk_free_mb' => round(disk_free_space(base_path()) / 1024 / 1024, 1),
        ], 'Health status.');
    }

    public function backups()
    {
        $dir = storage_path('app/backups');
        $list = [];
        if (is_dir($dir)) {
            foreach (File::files($dir) as $file) {
                $list[] = [
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'modified' => date('c', $file->getMTime()),
                ];
            }
        }

        return ResponseHelper::sendResponse($list, 'Backups listed.');
    }

    public function apiStatus()
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            $routes[] = [
                'method' => implode('|', $route->methods()),
                'url' => '/' . $uri,
            ];
            if (count($routes) >= 80) {
                break;
            }
        }

        return ResponseHelper::sendResponse(['routes' => $routes], 'API routes sample.');
    }

    /* ────────── helpers ────────── */

    private function adminUsers()
    {
        return User::where(function ($q) {
            $q->where('is_admin_user', true)
                ->orWhere('is_admin_user', 1)
                ->orWhere('is_admin_user', '1')
                ->orWhere('is_superadmin', true)
                ->orWhere('is_superadmin', 1)
                ->orWhere('is_superadmin', '1')
                ->orWhere('user_type', 'team_member');
        })->get();
    }

    private function rolesById($admins)
    {
        $roleIds = $admins->pluck('role_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => (bool) preg_match('/^[0-9a-fA-F]{24}$/', $id))
            ->unique()
            ->values()
            ->all();

        if (empty($roleIds)) {
            return collect();
        }

        try {
            return AdminRole::whereIn('_id', $roleIds)->get()->keyBy(fn ($r) => (string) $r->_id);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function adminCard($admin, $presence, $rolesById, bool $isOnline, $todayLogs): array
    {
        $uid = (string) $admin->_id;
        $name = trim(($admin->name ?? '') . ' ' . ($admin->last_name ?? '')) ?: ($admin->username ?? 'Admin');
        $roleName = 'Admin';
        if ($admin->is_superadmin) {
            $roleName = 'Super Admin';
        } elseif ($admin->role_id && $rolesById->has((string) $admin->role_id)) {
            $roleName = $rolesById->get((string) $admin->role_id)->name ?? 'Admin';
        }

        $userLogs = $todayLogs->where('user_id', $uid);
        $taskCount = $userLogs->filter(fn ($l) => !empty($l->task_category))->count();
        $actionCount = $userLogs->count();

        $loginAt = null;
        if ($presence?->login_at) {
            $loginAt = Carbon::parse($presence->login_at);
        } elseif ($admin->last_login_at) {
            $loginAt = Carbon::parse($admin->last_login_at);
        }

        $lastSeen = $presence?->last_seen_at ? Carbon::parse($presence->last_seen_at) : null;
        $duration = '—';
        if ($isOnline && $loginAt) {
            $duration = $this->humanDuration((int) $loginAt->diffInSeconds(Carbon::now()));
        } elseif ($loginAt && $lastSeen) {
            $duration = $this->humanDuration((int) $loginAt->diffInSeconds($lastSeen));
        }

        $ua = (string) ($presence->user_agent ?? '');
        $image = null;
        if (!empty($admin->image)) {
            try {
                $image = Helpers::mediaUrl($admin->image);
            } catch (\Throwable $e) {
                $image = $admin->image;
            }
        }

        return [
            'id'              => $uid,
            'name'            => $name,
            'role'            => $roleName,
            'avatar'          => $this->initials($name),
            'image'           => $image,
            'online'          => $isOnline,
            'loginTime'       => $loginAt ? $loginAt->format('H:i') : '—',
            'logoutTime'      => (!$isOnline && $lastSeen) ? $lastSeen->format('H:i') : null,
            'duration'        => $duration,
            'lastActivity'    => $lastSeen ? $this->relativeTime($lastSeen) : '—',
            'lastActivityRaw' => $lastSeen ? $lastSeen->toIso8601String() : '',
            'module'          => $isOnline ? ($presence->module ?? '—') : '—',
            'page'            => $isOnline ? ($presence->page ?? '—') : '—',
            'action'          => $isOnline ? ($presence->action ?? '—') : '—',
            'device'          => $presence->device ?? ($ua ? ActivityLogHelper::parseDevice($ua) : '—'),
            'browser'         => $presence->browser ?? ($ua ? ActivityLogHelper::parseBrowser($ua) : '—'),
            'os'              => $presence->os ?? ($ua ? ActivityLogHelper::parseOs($ua) : '—'),
            'ip'              => $presence->ip ?? '—',
            'country'         => $presence->country ?? ($admin->country ?? '—'),
            'city'            => $presence->city ?? '—',
            'tasksToday'      => $taskCount,
            'actionsToday'    => $actionCount,
            'avgSession'      => $duration !== '—' ? $duration : '—',
            'color'           => $this->colorFor($uid),
        ];
    }

    private function timelineEvent($log): array
    {
        $level = strtolower((string) ($log->level ?? 'info'));
        $status = match ($level) {
            'success' => 'success',
            'warning' => 'warning',
            'error', 'critical' => 'error',
            default => 'info',
        };
        $ua = (string) ($log->user_agent ?? '');
        $name = $log->user_name ?? 'Admin';

        return [
            'id'      => (string) $log->_id,
            'time'    => $log->created_at ? Carbon::parse($log->created_at)->format('H:i') : '—',
            'adminId' => $log->user_id ?? '',
            'admin'   => $name,
            'avatar'  => $this->initials($name),
            'color'   => $this->colorFor($log->user_id ?? $name),
            'module'  => $log->module ?? ucfirst((string) ($log->category ?? 'System')),
            'action'  => $log->action_label ?? $log->title ?? 'Activity',
            'device'  => $log->device ?: ($ua ? ActivityLogHelper::parseDevice($ua) : '—'),
            'ip'      => $log->ip ?? '—',
            'status'  => $status,
            'description' => $log->description ?? '',
            'target'  => $log->target ?? null,
        ];
    }

    private function buildTaskCategories($todayLogs): array
    {
        $grouped = [];
        foreach (self::TASK_CATALOG as $key => $meta) {
            $grouped[$key] = [
                'id'      => $key,
                'label'   => $meta['label'],
                'icon'    => $meta['icon'],
                'color'   => $meta['color'],
                'bg'      => $meta['bg'],
                'count'   => 0,
                'details' => [],
            ];
        }

        foreach ($todayLogs as $log) {
            $cat = $log->task_category ?? null;
            if (!$cat || !isset($grouped[$cat])) {
                continue;
            }
            $grouped[$cat]['count']++;
            if (count($grouped[$cat]['details']) < 40) {
                $grouped[$cat]['details'][] = [
                    'time'   => $log->created_at ? Carbon::parse($log->created_at)->format('H:i') : '—',
                    'admin'  => $log->user_name ?? 'Admin',
                    'target' => $log->target ?: ($log->description ?: $log->title),
                ];
            }
        }

        // Only return categories that have activity (keep empty ones with 0 for UI consistency)
        return array_values($grouped);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $a = strtoupper(substr($parts[0] ?? 'A', 0, 1));
        $b = strtoupper(substr($parts[1] ?? ($parts[0] ?? 'A'), 0, 1));
        return $a . $b;
    }

    private function colorFor(string $seed): string
    {
        $idx = abs(crc32($seed)) % count(self::AVATAR_COLORS);
        return self::AVATAR_COLORS[$idx];
    }

    private function humanDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) return "{$h}h " . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . 'm';
        if ($m > 0) return "{$m}m";
        return max(1, $seconds) . 's';
    }

    private function relativeTime(Carbon $at): string
    {
        $secs = (int) $at->diffInSeconds(Carbon::now());
        if ($secs < 10) return 'just now';
        if ($secs < 60) return $secs . 's ago';
        if ($secs < 3600) return intdiv($secs, 60) . 'm ago';
        if ($secs < 86400) return intdiv($secs, 3600) . 'h ago';
        return $at->diffForHumans();
    }

    private function fileFallback(int $limit): array
    {
        $path = storage_path('logs/laravel.log');
        $entries = [];
        if (!is_readable($path)) return $entries;

        $lines = @file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $slice = array_slice($lines, -$limit);
        $i = 0;
        foreach ($slice as $line) {
            $level = str_contains($line, '.ERROR') || str_contains($line, ' ERROR ') ? 'error'
                : (str_contains($line, '.WARNING') || str_contains($line, ' WARNING ') ? 'warning' : 'info');
            $entries[] = [
                'id'          => 'file_' . ++$i,
                'level'       => $level,
                'timestamp'   => now()->toDateTimeString(),
                'title'       => 'Server Log',
                'description' => $line,
                'user'        => null,
                'ip'          => '—',
                'device'      => 'server',
                'browser'     => null,
                'endpoint'    => null,
                'session_id'  => 'laravel',
                'category'    => 'system',
                'payload'     => null,
            ];
        }
        return $entries;
    }
}
