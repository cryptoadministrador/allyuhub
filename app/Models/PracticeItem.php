<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plantilla de ítem de práctica parametrizada, anclada a una destreza.
 * Se instancia con semilla determinista: nunca guarda números concretos,
 * solo rangos (`params`) y la expresión de solución (`solution_expr`).
 */
class PracticeItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'statement' => 'array',
        'params' => 'array',
        'attrs' => 'array',
        'tolerance' => 'float',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'objective_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PracticeAttempt::class, 'item_id');
    }
}
