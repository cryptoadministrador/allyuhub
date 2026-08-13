<?php

namespace App\Http\Middleware;

use App\Models\LtiPlatform;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La app vive dentro del iframe de Moodle: se permite el embebido SOLO desde
 * las Platforms LTI registradas y activas (frame-ancestors con sus orígenes,
 * jamás *). Sin platforms registradas queda 'self' a secas.
 */
class AllowLtiFrameEmbedding
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // rescue: si la tabla aún no existe (despliegue a medio migrar, tests
        // sin BD), la política queda en 'self' a secas — jamás un 500 y jamás
        // un fallback más permisivo.
        $origins = rescue(
            fn () => LtiPlatform::query()->active()
                ->pluck('issuer')
                ->map(fn (string $issuer) => preg_replace('~^(https?://[^/]+).*~', '$1', $issuer))
                ->unique()
                ->implode(' '),
            '',
            report: false,
        );

        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self'".($origins !== '' ? ' '.$origins : ''),
        );

        return $response;
    }
}
