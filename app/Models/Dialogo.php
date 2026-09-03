<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EL INTERLOCUTOR GUIONIZADO: un grafo de nodos por unidad. No evalúa —
 * registra que se completó, y de ahí sube el dominio del descriptor de
 * interacción una vez.
 *
 * NO se sirve hasta que un docente lo FIRMA (`reviewed_at`): el guion contiene
 * lengua que alguien que la sabe tiene que revisar. Misma puerta que lecciones
 * e ítems, y el filtro vive en el scope `published()`, no copiado en cada
 * consulta.
 */
class Dialogo extends Model
{
    use HasUuids;

    protected $table = 'dialogos';

    protected $guarded = [];

    protected $casts = [
        'nodos' => 'array',
        'reviewed_at' => 'datetime',
        'unidad' => 'integer',
    ];

    /** Firmado = servible. Toda ruta que sirva un diálogo pasa por aquí. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('reviewed_at');
    }

    /**
     * El gemelo por-instancia de `published()`: la MISMA noción de «firmado»
     * para cuando ya se tiene el modelo (completar). Escrita una vez para que
     * la puerta de servir y la de completar no puedan divergir.
     */
    public function estaFirmado(): bool
    {
        return $this->reviewed_at !== null;
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'objective_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(DialogoCompletion::class, 'dialogo_id');
    }

    /** El primer nodo del guion (por donde arranca la conversación). */
    public function nodoInicial(): ?array
    {
        return $this->nodos[0] ?? null;
    }
}
