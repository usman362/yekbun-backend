<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class SystemAdminController extends Controller
{
    public function logs()
    {
        $path = storage_path('logs/laravel.log');
        $entries = [];
        if (is_readable($path)) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES) ?: [];
            $slice = array_slice($lines, -300);
            $i = 0;
            foreach ($slice as $line) {
                $entries[] = [
                    'id' => ++$i,
                    'level' => str_contains($line, '.ERROR') || str_contains($line, ' ERROR ') ? 'error'
                        : (str_contains($line, '.WARNING') || str_contains($line, ' WARNING ') ? 'warning' : 'info'),
                    'timestamp' => now()->toDateTimeString(),
                    'title' => 'Log',
                    'description' => $line,
                ];
            }
        }

        return ResponseHelper::sendResponse($entries, 'Logs loaded.');
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
}
