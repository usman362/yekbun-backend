<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.custom'     => \App\Http\Middleware\JwtMiddleware::class,
            'admin.user'     => \App\Http\Middleware\EnsureAdminUser::class,
            'maintenance'    => \App\Http\Middleware\CheckMaintenance::class,
            'admin.activity' => \App\Http\Middleware\LogAdminActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Production/staging: never leak exception class, file, line, or stack in JSON,
        // even if APP_DEBUG is accidentally left on. Local env keeps full traces.
        $exceptions->respond(function ($response, \Throwable $e, $request) {
            if (config('app.env') === 'local') {
                return $response;
            }
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return $response;
            }
            $payload = json_decode($response->getContent(), true);
            if (! is_array($payload)) {
                return $response;
            }
            unset($payload['exception'], $payload['file'], $payload['line'], $payload['trace']);
            return response()->json($payload, $response->getStatusCode());
        });
    })->create();
