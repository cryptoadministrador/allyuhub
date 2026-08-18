<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Una Platform LTI 1.3 (Moodle) autorizada a lanzar la Tool.
 * Se administra con `php artisan lti:platform:add`, nunca por API.
 */
class LtiPlatform extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'deployment_ids' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Los orígenes de `frame-ancestors` se cachean (el middleware corre en cada
     * respuesta web). La clave se borra AQUÍ, en el modelo, y no en el comando:
     * así da igual quién registre la Platform — `lti:platform:add`, un seeder o
     * un test — la CSP nunca se queda con una lista vieja.
     *
     * OJO: `saved`/`deleted` son eventos de MODELO. Un `update()` por query
     * builder o un UPDATE a mano en psql NO los disparan; para esos casos la
     * red de seguridad es el TTL corto del middleware, no este gancho.
     */
    public const CACHE_ORIGENES = 'lti.frame_ancestors';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_ORIGENES));
        static::deleted(fn () => Cache::forget(self::CACHE_ORIGENES));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
