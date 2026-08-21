<?php

namespace App\Providers;

use App\Services\Lti\LtiCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Packback\Lti1p3\Interfaces\ICache;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // LTI 1.3: la cache de la librería (nonces, launch data, tokens AGS)
        // es la nuestra sobre la cache de Laravel.
        $this->app->bind(ICache::class, LtiCache::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->limitarLaPracticaAbierta();

        // Fail-closed: la protección anti-replay de LTI (nonces) solo es sólida con
        // una cache COMPARTIDA entre workers. Con array/file, un replay que cae en
        // otro proceso no ve el nonce consumido y pasa. En producción se aborta el
        // arranque antes de aceptar un solo launch inseguro.
        if ($this->app->isProduction()
            && in_array(config('cache.default'), ['array', 'file'], true)) {
            throw new RuntimeException(
                'LTI 1.3 exige una cache compartida (database/redis) para el anti-replay '
                .'de nonces. CACHE_STORE='.config('cache.default').' no lo garantiza. '
                .'Ver docs/lti-moodle.md §0.3.'
            );
        }
    }

    /**
     * El límite de la práctica ABIERTA.
     *
     * Desde que el motor sirve ítems sin sesión, cualquiera puede pedirlos: sin
     * tope, un script martillea el generador (que evalúa expresiones y hashea)
     * gratis.
     *
     * 120 por minuto y por IP, no 60 (auditoría): un aula entera sale a
     * internet por la MISMA IP, y el bucle gasta dos peticiones por ejercicio
     * —pedirlo y responderlo—, así que 60 dejaba a todo el colegio en 30
     * ejercicios por minuto entre todos. Con 120 son 60, holgado para una clase
     * probando la plataforma y sigue siendo ridículo para un raspador.
     *
     * Que la clave sea de verdad la IP del cliente depende de que no se confíe
     * en cualquier X-Forwarded-For: ver `trustProxies` en bootstrap/app.php.
     *
     * Al ALUMNO no se le pone tope: entró por LTI, está identificado, y el
     * límite por IP lo dejaría fuera junto a toda su clase.
     */
    private function limitarLaPracticaAbierta(): void
    {
        RateLimiter::for('practica', fn (Request $request) => $request->user()
            ? Limit::none()
            : Limit::perMinute(120)->by($request->ip()));
    }
}
