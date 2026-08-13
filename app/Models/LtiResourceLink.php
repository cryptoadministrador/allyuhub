<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contexto AGS de un launch: qué lineitem de qué Platform recibe los scores
 * de qué alumno (y para qué destreza). Lo escribe el launch validado; lo lee
 * el job PushLtiScore.
 */
class LtiResourceLink extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'ags' => 'array',
        'last_launched_at' => 'datetime',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(LtiPlatform::class, 'platform_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'objective_id');
    }
}
