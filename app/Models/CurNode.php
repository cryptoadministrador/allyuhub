<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nodo genérico del grafo curricular (Capa 1).
 * node_type es abierto: nivel|subnivel|grado|area|asignatura|bloque (MINEDEC),
 * programme|subject|stage|strand|substrand (Cambridge FW),
 * syllabus|topic|subtopic (Cambridge syllabus), etc.
 */
class CurNode extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'title' => 'array',
        'attrs' => 'array',
        'age_min' => 'float',
        'age_max' => 'float',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(FrameworkVersion::class, 'version_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('seq');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class, 'node_id');
    }

    /** Todo el subárbol bajo este nodo (usa ltree en pgsql, LIKE en sqlite). */
    public function scopeDescendantsOf($query, self $node)
    {
        return $query->where('version_id', $node->version_id)
            ->when(
                $query->getConnection()->getDriverName() === 'pgsql',
                fn ($q) => $q->whereRaw('path <@ ?::ltree', [$node->path])
                    ->whereRaw('path <> ?::ltree', [$node->path]),
                fn ($q) => $q->where('path', 'like', $node->path.'.%'),
            );
    }
}
