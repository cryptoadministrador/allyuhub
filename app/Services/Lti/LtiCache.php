<?php

namespace App\Services\Lti;

use Illuminate\Support\Facades\Cache;
use Packback\Lti1p3\Interfaces\ICache;

/**
 * ICache de la librería sobre la cache de Laravel.
 *
 * SEGURIDAD (anti-replay): el nonce se consume con pull() — un solo uso.
 * Reenviar el mismo id_token (mismo nonce) muere en checkNonceIsValid.
 * En producción el driver de cache debe ser COMPARTIDO entre workers
 * (database/redis, no array/file por-proceso): ver docs/lti-moodle.md.
 */
class LtiCache implements ICache
{
    private const NONCE_TTL = 600;      // 10 min: la vida del apretón OIDC

    private const LAUNCH_TTL = 21600;   // 6 h: una jornada de clase con margen

    private const TOKEN_TTL = 3500;     // los access token de Moodle duran 3600 s

    public function getLaunchData(string $key): ?array
    {
        return Cache::get('lti1p3:launch:'.$key);
    }

    public function cacheLaunchData(string $key, array $jwtBody): void
    {
        Cache::put('lti1p3:launch:'.$key, $jwtBody, self::LAUNCH_TTL);
    }

    public function cacheNonce(string $nonce, string $state): void
    {
        Cache::put('lti1p3:nonce:'.$nonce, $state, self::NONCE_TTL);
    }

    public function checkNonceIsValid(string $nonce, string $state): bool
    {
        // El nonce vale UNA vez. pull() (get+forget) tiene una ventana de carrera
        // entre dos launches concurrentes del mismo id_token; un lock atómico la
        // cierra: solo el primero que adquiere el lock consume el nonce, el resto
        // ve el nonce ya borrado y falla. En stores sin lock nativo (array/file)
        // Laravel degrada a un lock de cache, suficiente en un solo proceso; en
        // producción el store DEBE ser compartido (ver assertSharedCacheInProduction).
        return Cache::lock('lti1p3:nonce-lock:'.$nonce, 5)->block(5, function () use ($nonce, $state) {
            return Cache::pull('lti1p3:nonce:'.$nonce) === $state;
        });
    }

    public function cacheAccessToken(string $key, string $accessToken): void
    {
        Cache::put('lti1p3:token:'.$key, $accessToken, self::TOKEN_TTL);
    }

    public function getAccessToken(string $key): ?string
    {
        return Cache::get('lti1p3:token:'.$key);
    }

    public function clearAccessToken(string $key): void
    {
        Cache::forget('lti1p3:token:'.$key);
    }
}
