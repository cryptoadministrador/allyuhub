<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TrackPhase extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = ['label' => 'array', 'attrs' => 'array', 'is_propedeutic' => 'boolean',
                        'duration_months' => 'float'];

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /** En ORD, la fase apunta al nodo-grado del grafo. */
    public function gradeNode(): BelongsTo
    {
        return $this->belongsTo(CurNode::class, 'grade_node_id');
    }

    public function objectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'track_phase_objectives',
            'phase_id', 'objective_id')->withPivot(['weight', 'source']);
    }
}
