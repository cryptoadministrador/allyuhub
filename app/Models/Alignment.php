<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Arista del crosswalk: objetivo A ←(relation)→ objetivo B.
 * relation: exact | narrower | broader | related | prerequisite.
 * Flujo: la IA propone (method=llm-assisted, confidence), un docente revisa
 * (reviewed_by/reviewed_at); a producción solo lo revisado con confidence ≥ 0.8.
 */
class Alignment extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = ['confidence' => 'float', 'reviewed_at' => 'datetime'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'source_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'target_id');
    }

    public function scopeProduction($query)
    {
        return $query->whereNotNull('reviewed_at')->where('confidence', '>=', 0.8);
    }
}
