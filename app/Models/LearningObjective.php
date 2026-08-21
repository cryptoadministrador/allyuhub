<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La unidad atómica del sistema: destreza MINEDEC, learning objective Cambridge,
 * criterio IB. Todo (recursos, evaluación, analítica, crosswalk) se ancla aquí.
 */
class LearningObjective extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'statement' => 'array',
        'attrs' => 'array',
        'is_essential' => 'boolean',
        'spans_stages' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(CurNode::class, 'node_id');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_objectives', 'objective_id', 'resource_id')
            ->withPivot('role');
    }

    /** Alineaciones salientes (este objetivo → otros marcos). */
    public function alignments(): HasMany
    {
        return $this->hasMany(Alignment::class, 'source_id');
    }

    /**
     * Ítems de práctica anclados a esta destreza — SOLO LOS FIRMADOS.
     *
     * El filtro vive en la relación y no en cada consulta a propósito: de aquí
     * cuelgan `has_items` del catálogo y de la ficha, el `whereHas` del Deep
     * Linking de Moodle, los conteos del blueprint y la rotación del motor. Con
     * la puerta puesta solo en el selector, una destreza anunciaría «con
     * ejercicios» y luego daría 404 al entrar — un botón que lleva a ninguna
     * parte es peor que un botón ausente (regla de la casa).
     *
     * Para contar TODO —incluido lo pendiente de firma— está la relación de
     * abajo, que es la que usa el informe de la siembra.
     */
    public function practiceItems(): HasMany
    {
        return $this->hasMany(PracticeItem::class, 'objective_id')->whereNotNull('reviewed_at');
    }

    /** Todos los ítems, firmados o no. Solo para administrar e informar. */
    public function todosLosPracticeItems(): HasMany
    {
        return $this->hasMany(PracticeItem::class, 'objective_id');
    }

    /** Prerrequisitos: qué hay que dominar antes de esto. */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'alignments', 'source_id', 'target_id')
            ->wherePivot('relation', 'prerequisite');
    }

    /** Búsqueda full-text en español (pgsql) o LIKE (sqlite). */
    public function scopeSearch($query, string $term)
    {
        return $query->when(
            $query->getConnection()->getDriverName() === 'pgsql',
            fn ($q) => $q->whereRaw("search_vector @@ websearch_to_tsquery('spanish', ?)", [$term])
                ->orWhere('native_code', 'ilike', "%{$term}%"),
            fn ($q) => $q->where('statement', 'like', "%{$term}%")
                ->orWhere('native_code', 'like', "%{$term}%"),
        );
    }
}
