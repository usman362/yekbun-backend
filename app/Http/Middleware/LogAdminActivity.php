<?php

namespace App\Http\Middleware;

use App\Helpers\ActivityLogHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs successful mutating admin API calls into admin_activity_logs.
 * Skips noisy / presence / auth-refresh endpoints (login/logout already log explicitly).
 */
class LogAdminActivity
{
    private const SKIP_PATHS = [
        'api/admin/logout',
        'api/admin/refresh',
        'api/admin/me',
        'api/admin/system/presence',
        'api/admin/system/activity-overview',
        'api/admin/system/logs',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->shouldLog($request, $response)) {
            return $response;
        }

        $mapped = $this->mapRoute($request);
        $method = strtoupper($request->method());
        $title = $mapped['title'];
        $level = $method === 'DELETE' ? 'warning' : 'success';

        ActivityLogHelper::record(
            $title,
            $mapped['description'],
            $level,
            $mapped['category'],
            [
                'method' => $method,
                'path'   => '/' . ltrim($request->path(), '/'),
                'status' => $response->getStatusCode(),
            ],
            $request,
            null,
            [
                'module'        => $mapped['module'],
                'page'          => $mapped['page'],
                'action'        => $title,
                'task_category' => $mapped['task_category'],
                'target'        => $mapped['target'],
            ]
        );

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        $method = strtoupper($request->method());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        $path = trim($request->path(), '/');
        foreach (self::SKIP_PATHS as $skip) {
            if ($path === $skip || str_starts_with($path, $skip . '/')) {
                return false;
            }
        }

        return str_starts_with($path, 'api/admin');
    }

    /**
     * @return array{module:string,page:string,title:string,description:string,category:string,task_category:?string,target:?string}
     */
    private function mapRoute(Request $request): array
    {
        $path = trim($request->path(), '/'); // api/admin/...
        $relative = preg_replace('#^api/admin/?#', '', $path) ?? '';
        $method = strtoupper($request->method());
        $verb = match ($method) {
            'POST' => 'Created',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => 'Action',
        };

        $segments = array_values(array_filter(explode('/', $relative)));
        $id = null;
        foreach ($segments as $seg) {
            if (preg_match('/^[0-9a-fA-F]{24}$/', $seg) || ctype_digit($seg)) {
                $id = $seg;
                break;
            }
        }

        $rules = [
            ['users',           'Users',           'User Management',     'users',      'task'],
            ['complaints',      'Complaints',      'Complaint Review',    'complaints', 'task'],
            ['feeds',           'Feeds',           'Feed Management',     'feeds',      'task'],
            ['content/history', 'History',         'History Content',     null,         'content'],
            ['content/ai-videos','Videos',         'AI Videos',           'videos',     'task'],
            ['user-clips',      'Videos',          'User Clips',          'videos',     'task'],
            ['user-videos',     'Videos',          'User Videos',         'videos',     'task'],
            ['zercash',         'Zercash',         'Wallet Requests',     'wallet',     'task'],
            ['transactions',    'Zercash',         'Transactions',        'wallet',     'task'],
            ['products',        'Zercash',         'Products',            null,         'task'],
            ['team/members',    'Team',            'Team Members',        null,         'role'],
            ['team/roles',      'Team',            'Roles',               null,         'role'],
            ['content/admin-activity', 'Admin Activity', 'Admin Activity', null,      'content'],
            ['content/votings', 'Surveys',         'Surveys',             null,         'content'],
            ['music',           'Music',           'Music Library',       null,         'content'],
            ['ringtone',        'Ringtone',        'Ringtones',           null,         'content'],
            ['policy',          'Policy',          'App Policy',          null,         'system'],
            ['languages',       'Languages',       'Languages',           null,         'system'],
            ['settings',        'Settings',        'Settings',            null,         'system'],
            ['device-control',  'Device Control',  'Device Control',      null,         'system'],
            ['officials',       'Officials',       'Officials',           null,         'task'],
            ['files/upload',    'Files',           'File Upload',         null,         'system'],
            ['portal',          'Portal',          'Portal',              null,         'system'],
        ];

        $module = 'Admin';
        $page = 'Dashboard';
        $taskCategory = null;
        $category = 'api';

        foreach ($rules as [$prefix, $mod, $pg, $task, $cat]) {
            if ($relative === $prefix || str_starts_with($relative, $prefix . '/')) {
                $module = $mod;
                $page = $pg;
                $taskCategory = $task;
                $category = $cat;
                break;
            }
        }

        // Soften generic titles for known approve/reject style paths
        $title = "{$verb} {$module}";
        if (str_contains($relative, 'approve')) {
            $title = "Approved {$module}";
            $taskCategory = $taskCategory ?: 'users';
        } elseif (str_contains($relative, 'reject')) {
            $title = "Rejected {$module}";
        } elseif (str_contains($relative, 'publish')) {
            $title = "Published {$module}";
            $taskCategory = $taskCategory ?: 'feeds';
        }

        $target = $id ? "{$module} #{$id}" : null;
        $description = $target
            ? "{$title} — {$target}"
            : "{$method} /{$relative}";

        return [
            'module'         => $module,
            'page'           => $page,
            'title'          => $title,
            'description'    => $description,
            'category'       => $category,
            'task_category'  => $taskCategory,
            'target'         => $target,
        ];
    }
}
