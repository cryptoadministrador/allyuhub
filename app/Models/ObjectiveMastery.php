<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado de dominio de una destreza por alumno (mastery learning).
 * Lo escribe MasteryTracker en la misma transacción que el intento;
 * nadie más debería mutarlo.
 */
class ObjectiveMastery extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'mastery' => 'float',
        'attempts_count' => 'integer',
        'streak' => 'integer',
        'mastered_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'repaso_en' => 'datetime',
        'repaso_intervalo' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'objective_id');
    }

    /** Dominada = el hito mastered_at está sellado. */
    protected function isMastered(): Attribute
    {
        return Attribute::get(fn () => $this->mastered_at !== null);
    }
}
