<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una prueba de unidad ENTREGADA. Lo que la prueba añade sobre la práctica:
 * sus diez respuestas ya son `practice_attempts`; aquí queda la nota, el
 * desglose por descriptor y si aprobó (≥ 8 / 10).
 */
class PruebaUnidad extends Model
{
    use HasUuids;

    protected $table = 'pruebas_unidad';

    protected $guarded = [];

    protected $casts = [
        'desglose' => 'array',
        'aprobada' => 'boolean',
        'completed_at' => 'datetime',
        'unidad' => 'integer',
        'intento' => 'integer',
        'nota' => 'integer',
        'total' => 'integer',
    ];

    /** El listón: 8 de 10. */
    public const APROBADO = 8;

    /** Cuántos ítems tiene una prueba cuando el banco da para ello. */
    public const TAMANO = 10;

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
