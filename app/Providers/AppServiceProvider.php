<?php

namespace App\Providers;

use App\Services\Lti\LtiCache;
use Illuminate\Support\ServiceProvider;
use Packback\Lti1p3\Interfaces\ICache;

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
        //
    }
}
