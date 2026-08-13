<?php

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // LTI 1.3: grupo propio SIN EncryptCookies (la cookie de state
            // lleva nombre dinámico) y sin CSRF (los POST llegan cross-site
            // desde Moodle; la protección del protocolo es state+nonce).
            Route::middleware('lti')
                ->prefix('lti')
                ->group(base_path('routes/lti.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('lti', [
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
