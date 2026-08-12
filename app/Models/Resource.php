<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un recurso se publica UNA vez y se alinea con N objetivos de N marcos:
 * sirve al niño de ordinaria, al adulto PCEI y al colegio Cambridge/IB sin duplicarse.
 */
class Resource extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = ['title' => 'array', 'summary' => 'array', 'a11y' => 'array'];

    public function versions(): HasMany
    {
        return $this->hasMany(ResourceVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ResourceVersion::class, 'current_version_id');
    }

    public function objectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'resource_objectives',
            'resource_id', 'objective_id')->withPivot('role');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
