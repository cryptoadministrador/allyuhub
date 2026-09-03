<?php

namespace App\Models;

use App\Services\Produccion\AlmacenDeProducciones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Lo que un alumno escribe o graba para que su docente lo corrija.
 *
 * El motor NO corrige producción: aquí solo se guarda y se encola para el
 * docente. La corrección (`rubrica`, `comentario`) la pone una persona.
 *
 * INVARIANTE de contenido, como en `practice_attempts` (una vía por kind):
 * exactamente UNA de `texto`/`archivo` poblada según `tipo` — hasta la purga,
 * que las deja las dos nulas y conserva la nota. El guardián lo impone al
 * guardar; rellenar la otra con '' escondería un bug de bifurcación.
 */
class Produccion extends Model
{
    use HasUuids;

    protected $table = 'producciones';

    protected $guarded = [];

    protected $casts = [
        'rubrica' => 'array',
        'corregida_en' => 'datetime',
        'purgada_en' => 'datetime',
        'unidad' => 'integer',
    ];

    public const PENDIENTE = 'pendiente';

    public const CORREGIDA = 'corregida';

    public const ESCRITURA = 'escritura';

    public const VOZ = 'voz';

    protected static function booted(): void
    {
        static::saving(function (Produccion $p) {
            // Tras la purga, las dos vías van nulas: la nota vive, la grabación
            // no. Ese estado es legítimo y no lo toca el guardián.
            if ($p->purgada_en !== null) {
                return;
            }

            $tieneTexto = $p->texto !== null && $p->texto !== '';
            $tieneArchivo = $p->archivo !== null && $p->archivo !== '';

            if ($p->tipo === self::ESCRITURA && (! $tieneTexto || $tieneArchivo)) {
                throw new RuntimeException('Una producción de escritura lleva SOLO texto.');
            }
            if ($p->tipo === self::VOZ && (! $tieneArchivo || $tieneTexto)) {
                throw new RuntimeException('Una producción de voz lleva SOLO archivo.');
            }
        });
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'objective_id');
    }

    public function corrigio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corregida_por');
    }

    public function estaCorregida(): bool
    {
        return $this->estado === self::CORREGIDA;
    }

    /**
     * ¿Comparte curso este usuario con el ALUMNO que produjo? La regla de
     * visibilidad del docente, escrita UNA vez: el alumno está entre los que
     * este docente enseña. La policy (ver, corregir) y la cola tiran de la
     * MISMA derivación (`alumnosDeDocente`) desde puntas opuestas — si
     * divergieran, la cola mostraría algo que la policy prohíbe.
     */
    public function docenteComparteCurso(int $docenteId): bool
    {
        return self::alumnosDeDocente($docenteId)->contains($this->user_id);
    }

    /**
     * Producciones PENDIENTES de los alumnos a los que este docente da clase —
     * la cola. Vacía si no es instructor de ningún curso.
     */
    public function scopePendientesDeDocente(Builder $q, int $docenteId): Builder
    {
        return $q->where('estado', self::PENDIENTE)
            ->whereIn('user_id', self::alumnosDeDocente($docenteId))
            // Nullable NUNCA se ordena implícito (lección #29: NULL ordena
            // distinto en PostgreSQL); created_at no es nullable, pero se deja
            // explícito el sentido: lo más viejo primero, que es lo que urge.
            ->orderBy('created_at');
    }

    /**
     * Los ids de los alumnos (learners) a los que un docente da clase: los
     * learners de los contextos donde el docente es instructor. LA FUENTE ÚNICA
     * de la relación docente↔alumno para esta tabla.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function alumnosDeDocente(int $docenteId): \Illuminate\Support\Collection
    {
        $contextos = LtiContextMembership::query()
            ->where('user_id', $docenteId)
            ->where('role', 'instructor')
            ->pluck('lti_context_id');

        return LtiContextMembership::query()
            ->whereIn('lti_context_id', $contextos)
            ->where('role', 'learner')
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /** Borra la grabación de disco y deja la fila (nota incluida) en su sitio. */
    public function purgarGrabacion(AlmacenDeProducciones $almacen): void
    {
        if ($this->archivo !== null) {
            $almacen->borrar($this->archivo);
        }

        $this->forceFill([
            'texto' => null,
            'archivo' => null,
            'purgada_en' => now(),
        ])->save();
    }
}
