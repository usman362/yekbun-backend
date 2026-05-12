<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class SystemAdminController extends Controller
{
    public function logs(Request $request)
    {
        $limit = min((int) $request->get('limit', 300), 1000);

        // Primary source: structured admin activity logs (MongoDB)
        $rows = AdminActivityLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log, $i) {
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
                    'device'      => $log->user_agent ? $this->parseDevice($log->user_agent) : 'unknown',
                    'browser'     => $log->user_agent ? $this->parseBrowser($log->user_agent) : null,
                    'endpoint'    => $log->endpoint ?? null,
                    'session_id'  => $log->session_id ?? 'n/a',
                    'category'    => $log->category ?? 'system',
                    'payload'     => is_array($log->payload) ? $log->payload : null,
                ];
            });

        // Fall back to laravel.log lines only if no structured logs exist yet
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

    private function parseDevice(string $ua): string
    {
        if (str_contains($ua, 'iPhone')) return 'iPhone';
        if (str_contains($ua, 'iPad')) return 'iPad';
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'Macintosh')) return 'MacBook';
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Linux')) return 'Linux';
        return 'unknown';
    }

    private function parseBrowser(string $ua): ?string
    {
        if (str_contains($ua, 'Edg/')) return 'Edge';
        if (str_contains($ua, 'Chrome')) return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari')) return 'Safari';
        return null;
    }
}
