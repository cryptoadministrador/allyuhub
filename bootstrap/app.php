<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // LTI 1.3 en el grupo `web` COMPLETO: la sesión que nace en el
            // launch es la MISMA (cookies cifradas) que usan las páginas de
            // la app y la API de práctica. Única excepción: sin CSRF de
            // Laravel (abajo) — los POST llegan cross-site desde Moodle y la
            // protección del protocolo es state+nonce.
            Route::middleware('web')
                ->prefix('lti')
                ->group(base_path('routes/lti.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['lti/*']);

        // Sin sesión, las páginas redirigen a /entrar («vuelve desde Moodle»);
        // las peticiones JSON reciben su 401 estándar.
        $middleware->redirectGuestsTo('/entrar');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
