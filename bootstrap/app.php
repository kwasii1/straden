<?php

use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\TrackProjectAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the proxy in front of the app (Caddy in Docker, or a user's own
        // reverse proxy) so HTTPS URLs and client IPs resolve correctly.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [EnsureSetupComplete::class]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'track.project' => TrackProjectAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
