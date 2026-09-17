<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UNA TARJETA DE VOCABULARIO: una palabra de una unidad y una lengua, con su
 * significado y una frase de ejemplo de la lección. Material de apoyo, no un
 * tipo de ítem: no da dominio.
 *
 * NO se sirve hasta que un docente la FIRMA (`reviewed_at`): es lengua que
 * alguien que la sabe tiene que revisar. Misma puerta que ítems, lecciones y
 * diálogos, y el filtro vive en `published()`, no copiado en cada consulta.
 */
class Tarjeta extends Model
{
    use HasUuids;

    protected $table = 'vocabulario';

    protected $guarded = [];

    protected $casts = [
        'ejemplo' => 'array',
        'reviewed_at' => 'datetime',
        'unidad' => 'integer',
        'orden' => 'integer',
    ];

    /** Firmada = servible. Toda ruta que sirva o CUENTE tarjetas pasa por aquí. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('reviewed_at');
    }

    /** El gemelo por-instancia de `published()`: la misma noción de «firmada». */
    public function estaFirmada(): bool
    {
        return $this->reviewed_at !== null;
    }

    /** El mazo de una unidad, en el orden del banco. */
    public function scopeDeUnidad(Builder $q, string $lengua, int $unidad): Builder
    {
        return $q->where('lengua', $lengua)->where('unidad', $unidad)->orderBy('orden')->orderBy('id');
    }

    public function estados(): HasMany
    {
        return $this->hasMany(VocabEstado::class, 'tarjeta_id');
    }
}
