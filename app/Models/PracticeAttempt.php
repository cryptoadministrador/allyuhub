<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un intento de un alumno sobre un ítem instanciado. Guarda la semilla y los
 * parámetros exactos que vio el alumno (reproducibilidad) y el veredicto
 * calculado en el servidor — el cliente nunca decide `is_correct`.
 */
class PracticeAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'params' => 'array',
        'respuesta' => 'array',
        'answer' => 'float',
        'expected' => 'float',
        'is_correct' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PracticeItem::class, 'item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
