<?php

use App\Http\Middleware\AllowLtiFrameEmbedding;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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
        // En despliegue la app vive DETRÁS del nginx del host (deploy/):
        // el TLS termina ahí y a PHP le llega http. Sin confiar en el proxy,
        // Laravel cree que la petición es insegura, las cookies pierden
        // `Secure` y la sesión muere dentro del iframe de Moodle — justo lo
        // que el piloto tiene que probar. El proxy es NUESTRO nginx en la
        // misma máquina (127.0.0.1), por eso el '*' no abre nada a terceros.
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: ['lti/*']);

        // Sin sesión, las páginas redirigen a /entrar («vuelve desde Moodle»);
        // las peticiones JSON reciben su 401 estándar.
        $middleware->redirectGuestsTo('/entrar');

        // ANTES de SubstituteBindings, no después (auditoría del frente
        // visual). Cuando el binding de ruta no encuentra el modelo —el 404
        // MÁS COMÚN de la app: /catalogo/{uuid-que-no-existe}— lanza la
        // excepción desde DENTRO del pipeline, así que todo middleware
        // posterior no llega a ejecutarse nunca. Con los nuestros al final,
        // la página de error se pintaba sin `auth` (un alumno con sesión
        // recibía la salida del visitante: «entra desde tu aula virtual», la
        // pared) y sin la cabecera `frame-ancestors`, dejando una superficie
        // con enlaces embebible desde cualquier origen.
        $middleware->web(remove: [SubstituteBindings::class]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AllowLtiFrameEmbedding::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Un enlace muerto no puede echar al alumno a la pantalla blanca de
        // Symfony: 404 y 403 se pintan con la marca de la app, dentro del
        // mismo marco (y del mismo iframe de Moodle), con una salida clara.
        // Solo esos dos: un 500 con página bonita esconde el fallo real.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            // `expectsJson()` NO basta: es false sin cabecera `Accept` y con
            // `Accept: */*`, así que un `curl /api/v1/...` inexistente se comía
            // la página entera creyendo que era JSON (auditoría). El prefijo
            // manda, igual que en shouldRenderJsonWhen de arriba.
            if (! in_array($response->getStatusCode(), [403, 404], true)
                || $request->is('api/*')
                || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('error', ['status' => $response->getStatusCode()])
                ->toResponse($request)
                ->setStatusCode($response->getStatusCode());
        });
    })->create();
