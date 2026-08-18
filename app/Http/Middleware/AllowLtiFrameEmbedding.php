<?php

namespace App\Http\Middleware;

use App\Models\LtiPlatform;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        //
        // Cacheado porque este middleware corre en TODAS las respuestas web,
        // incluida la portada pública: una consulta por visita para una lista
        // que cambia cuando alguien ejecuta `lti:platform:add`. La invalidación
        // no depende del TTL — el modelo borra la clave al guardarse o
        // borrarse (LtiPlatform::booted), así que el header nunca va atrasado.
        $origins = rescue(
            fn () => Cache::remember(LtiPlatform::CACHE_ORIGENES, now()->addHour(),
                fn () => LtiPlatform::query()->active()
                    ->pluck('issuer')
                    ->map($this->toOrigin(...))
                    ->filter()
                    ->unique()
                    ->implode(' ')),
            '',
            report: false,
        );

        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self'".($origins !== '' ? ' '.$origins : ''),
        );

        return $response;
    }

    /**
     * Issuer → origen (esquema://host[:puerto]) apto para una directiva CSP.
     *
     * Con `parse_url`, no con regex: un issuer mal pegado tipo
     * «https://moodle.test *» o un `urn:` pasaban verbatim al header y podían
     * colar un `*` en frame-ancestors, abriendo la app a clickjacking desde
     * cualquier origen. Lo que no sea una URL http(s) se descarta.
     */
    private function toOrigin(string $issuer): ?string
    {
        $parts = parse_url(trim($issuer));

        if (! isset($parts['scheme'], $parts['host'])
            || ! in_array($parts['scheme'], ['http', 'https'], true)
            || preg_match('/[\s;\'"]/', $parts['host']) === 1) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
