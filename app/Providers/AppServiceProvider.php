<?php

namespace App\Providers;

use App\Services\Lti\LtiCache;
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
}
