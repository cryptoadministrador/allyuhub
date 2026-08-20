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
     * gratis. 60 por minuto y por IP es holgado para practicar de verdad —un
     * ejercicio cada segundo durante un minuto— y ridículo para un raspador.
     *
     * Al ALUMNO no se le pone tope: entró por LTI, está identificado, y un
     * límite por IP castigaría a un aula entera detrás del mismo NAT.
     */
    private function limitarLaPracticaAbierta(): void
    {
        RateLimiter::for('practica', fn (Request $request) => $request->user()
            ? Limit::none()
            : Limit::perMinute(60)->by($request->ip()));
    }
}
