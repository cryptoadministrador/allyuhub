<?php

namespace App\Services\Practice;

use App\Models\Alignment;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use Illuminate\Support\Collection;

/**
 * Selección adaptativa sobre el grafo de prerrequisitos (fase B, plan v2 §Frente 1).
 *
 * Política, en este orden:
 *  1. RETROCESO («refuerzo de prerrequisito»): con ≥2 fallos seguidos
 *     (streak ≤ −2) y mastery < 0.4 en el objetivo pedido, se propone su
 *     prerrequisito NO dominado de menor mastery.
 *  2. AVANCE: con el objetivo pedido dominado (mastered_at sellado), se propone
 *     la destreza que lo tiene como prerrequisito, no dominada, de menor mastery.
 *  3. PRÁCTICA NORMAL: el objetivo pedido con la rotación v1 (ítem menos
 *     practicado, orden estable seq/created_at/id).
 *
 * Aristas admitidas: relation=prerequisite con method=manual O reviewed_at NOT
 * NULL. EXCEPCIÓN CONSAGRADA a la regla 5 de CLAUDE.md: `production()` exige
 * además confidence ≥ 0.8, pensado para el crosswalk de EQUIVALENCIAS que ven
 * los docentes; aquí una arista `manual` ya es de autoría humana (progresión
 * estructural del marco, no propuesta de IA) y solo se usa para NAVEGAR, no se
 * muestran como equivalencias. Auditar esta decisión cuando existan practice_items
 * sobre marcos internacionales. Desde la progresión INTERNA de EC-MINEDEC
 * (CrosswalkSeeder, sección «DENTRO de EC-MINEDEC») el retroceso y el avance sí
 * disparan en producción: antes todas las aristas prerequisite eran inter-marco y
 * ningún objetivo Cambridge/IB tiene ítems, así que el motor era estructuralmente
 * inerte. Lo vigila MineducProgressionTest sobre el grafo REAL del seeder.
 * Semántica (CrosswalkSeeder): alignment(source=A, target=B) = «para intentar A,
 * domina antes B»; retroceder = seguir targets, avanzar = seguir sources.
 *
 * El grafo cruza marcos (Cambridge/IB): se prefieren candidatos del marco del
 * objetivo de partida y solo se cruza de marco si no queda ninguno.
 * Todo desempate es un orden total (mastery, native_code, id): CERO azar.
 *
 * Nota de diseño: los candidatos se filtran por el hito PERMANENTE mastered_at,
 * no por el mastery vivo — un prerrequisito dominado hace meses cuyo mastery
 * decayó no vuelve a proponerse como refuerzo. El disparador del retroceso sí
 * usa el mastery vivo. Es deliberado (evita oscilaciones); revísalo si se
 * quiere repaso espaciado.
 */
class AdaptiveSelector
{
    public const RETREAT_FAIL_STREAK = 2;       // fallos seguidos que disparan refuerzo

    public const RETREAT_MASTERY_BELOW = 0.4;

    public const REASON_NORMAL = 'práctica normal';

    public const REASON_RETREAT = 'refuerzo de prerrequisito';

    public const REASON_ADVANCE = 'avance';

    /**
     * Elige el siguiente ítem para el alumno partiendo del objetivo pedido.
     *
     * @return array{item: PracticeItem, objective: LearningObjective, attempt_no: int, reason: string}|null
     *                                                                                                       null solo si el objetivo pedido no tiene ítems (contrato v1: 404).
     */
    public function next(LearningObjective $objective, int $userId): ?array
    {
        $own = $this->pickItem($objective, $userId);
        if ($own === null) {
            return null;
        }

        $state = ObjectiveMastery::query()
            ->where('user_id', $userId)
            ->where('objective_id', $objective->id)
            ->first();

        if ($state !== null) {
            if ($state->streak <= -self::RETREAT_FAIL_STREAK
                && $state->mastery < self::RETREAT_MASTERY_BELOW) {
                $retreat = $this->bestCandidate($this->edgeIds($objective, retreat: true), $objective, $userId);
                if ($retreat !== null) {
                    return $retreat + ['reason' => self::REASON_RETREAT];
                }
            } elseif ($state->mastered_at !== null) {
                $advance = $this->bestCandidate($this->edgeIds($objective, retreat: false), $objective, $userId);
                if ($advance !== null) {
                    return $advance + ['reason' => self::REASON_ADVANCE];
                }
            }
        }

        return [
            'item' => $own['item'],
            'objective' => $objective,
            'attempt_no' => $own['attempt_no'],
            'reason' => self::REASON_NORMAL,
        ];
    }

    /**
     * Los ítems de una destreza en el ORDEN ESTABLE de la rotación (seq,
     * created_at, id). Vive aquí para que el orden se defina una sola vez: lo
     * usan la rotación del alumno (abajo) y la del invitado, que rota por
     * número de intento porque no tiene historial que contar.
     *
     * @return Collection<int, PracticeItem>
     */
    public function itemsOf(LearningObjective $objective): Collection
    {
        return $objective->practiceItems()
            ->orderBy('seq')->orderBy('created_at')->orderBy('id')
            ->get();
    }

    /**
     * Rotación v1: el ítem menos practicado del objetivo, con orden estable.
     *
     * @return array{item: PracticeItem, attempt_no: int}|null
     */
    public function pickItem(LearningObjective $objective, int $userId): ?array
    {
        $items = $this->itemsOf($objective);
        if ($items->isEmpty()) {
            return null;
        }

        $counts = PracticeAttempt::query()
            ->whereIn('item_id', $items->pluck('id'))
            ->where('user_id', $userId)
            ->selectRaw('item_id, count(*) as total')
            ->groupBy('item_id')
            ->pluck('total', 'item_id');

        $minCount = $items->map(fn ($i) => $counts[$i->id] ?? 0)->min();
        $item = $items->first(fn ($i) => ($counts[$i->id] ?? 0) === $minCount);

        return ['item' => $item, 'attempt_no' => ($counts[$item->id] ?? 0) + 1];
    }

    /** Ids al otro lado de las aristas prerequisite admitidas (manual o revisada). */
    private function edgeIds(LearningObjective $objective, bool $retreat): Collection
    {
        return Alignment::query()
            ->where('relation', 'prerequisite')
            ->where(fn ($q) => $q->where('method', 'manual')->orWhereNotNull('reviewed_at'))
            ->when(
                $retreat,
                fn ($q) => $q->where('source_id', $objective->id),
                fn ($q) => $q->where('target_id', $objective->id),
            )
            ->pluck($retreat ? 'target_id' : 'source_id');
    }

    /**
     * El mejor candidato practicable: con ítems, no dominado, del marco del
     * objetivo de partida si es posible, y de menor mastery (empates por
     * native_code y luego id — determinista).
     *
     * @return array{item: PracticeItem, objective: LearningObjective, attempt_no: int}|null
     */
    private function bestCandidate(Collection $ids, LearningObjective $origin, int $userId): ?array
    {
        if ($ids->isEmpty()) {
            return null;
        }

        $candidates = LearningObjective::query()
            ->whereIn('id', $ids)
            ->whereHas('practiceItems')
            ->get();
        if ($candidates->isEmpty()) {
            return null;
        }

        $masteries = ObjectiveMastery::query()
            ->where('user_id', $userId)
            ->whereIn('objective_id', $candidates->pluck('id'))
            ->get()
            ->keyBy('objective_id');

        $pool = $candidates->reject(
            fn ($c) => ($masteries[$c->id] ?? null)?->mastered_at !== null,
        );
        if ($pool->isEmpty()) {
            return null;
        }

        // Mismo marco que el objetivo de partida, salvo que no quede ninguno.
        $frameworkOf = FrameworkVersion::query()
            ->whereIn('id', $pool->pluck('version_id')->push($origin->version_id)->unique())
            ->pluck('framework_id', 'id');
        $sameFramework = $pool->filter(
            fn ($c) => $frameworkOf[$c->version_id] === $frameworkOf[$origin->version_id],
        );
        $pool = $sameFramework->isNotEmpty() ? $sameFramework : $pool;

        $chosen = $pool->sort(function ($a, $b) use ($masteries) {
            $porMastery = (float) (($masteries[$a->id] ?? null)?->mastery ?? 0.0)
                <=> (float) (($masteries[$b->id] ?? null)?->mastery ?? 0.0);
            if ($porMastery !== 0) {
                return $porMastery;
            }

            // Desempate por código en orden NATURAL, no lexicográfico. Con dos
            // sucesores (CN.4.3.7 de 8.º y CN.4.3.10 de 10.º) el orden de
            // cadenas pone «CN.4.3.10» antes que «CN.4.3.7» porque compara
            // '1' < '7', y mandaba al alumno dos grados hacia adelante
            // saltándose la destreza del suyo. En EC-MINEDEC el código es
            // ÁREA.subnivel.bloque.n, así que el orden natural ES el orden
            // curricular. Ver MineducProgressionTest.
            $porCodigo = strnatcmp($a->native_code ?? '', $b->native_code ?? '');

            return $porCodigo !== 0 ? $porCodigo : ($a->id <=> $b->id);
        })->first();

        $pick = $this->pickItem($chosen, $userId);
        if ($pick === null) {
            return null;
        }

        return ['item' => $pick['item'], 'objective' => $chosen, 'attempt_no' => $pick['attempt_no']];
    }
}
