<?php

namespace App\Services\Practice;

use App\Models\ObjectiveMastery;
use DateTimeInterface;

/**
 * Actualiza el estado de dominio (mastery learning) tras cada intento.
 *
 * Media móvil exponencial, determinista y sin azar:
 *   acierto: mastery ← mastery + α(1 − mastery)
 *   fallo:   mastery ← mastery(1 − β)
 *
 * α = 0.35: con mastery inicial 0 hacen falta 4 aciertos seguidos para cruzar
 * el umbral 0.8 (0.35 → 0.578 → 0.725 → 0.821), un ritmo razonable para ítems
 * parametrizados donde cada acierto es sobre números distintos.
 * β = 0.30: un fallo resta ~un tercio del dominio acumulado — castiga menos de
 * lo que premia un acierto sobre dominio bajo, para no hundir a quien explora.
 *
 * `streak` es FIRMADA: >0 = aciertos seguidos, <0 = fallos seguidos. El hito
 * `mastered_at` se sella con racha ≥ 3 y mastery ≥ 0.8, y NO se borra después:
 * el selector adaptativo usa el mastery vivo para decidir refuerzos.
 */
class MasteryTracker
{
    public const ALPHA = 0.35;

    public const BETA = 0.30;

    public const MASTERY_THRESHOLD = 0.8;

    public const STREAK_TO_MASTER = 3;

    /** Registra el resultado de un intento. Llamar SIEMPRE dentro de la transacción del intento. */
    public function apply(
        int $userId,
        string $objectiveId,
        bool $isCorrect,
        ?DateTimeInterface $at = null,
    ): ObjectiveMastery {
        // Read-modify-write protegido: sin el lock, dos intentos simultáneos del
        // mismo alumno sobre el mismo objetivo (dos pestañas, dos ítems) leerían la
        // misma base y el último UPDATE se comería al otro en silencio — attempts_count
        // quedaría en N-1 y la EMA saltaría un paso. lockForUpdate es no-op en SQLite
        // (los tests no lo ejercitan) pero en PostgreSQL serializa la carrera.
        $m = ObjectiveMastery::query()
            ->where('user_id', $userId)
            ->where('objective_id', $objectiveId)
            ->lockForUpdate()
            ->first()
            ?? new ObjectiveMastery([
                'user_id' => $userId,
                'objective_id' => $objectiveId,
            ]);

        $mastery = (float) ($m->mastery ?? 0.0);
        $streak = (int) ($m->streak ?? 0);

        $m->mastery = round(
            $isCorrect
                ? $mastery + self::ALPHA * (1 - $mastery)
                : $mastery * (1 - self::BETA),
            5,
        );
        $m->streak = $isCorrect ? max($streak, 0) + 1 : min($streak, 0) - 1;
        $m->attempts_count = (int) ($m->attempts_count ?? 0) + 1;
        $m->last_attempt_at = $at ?? now();

        if ($m->mastered_at === null
            && $m->streak >= self::STREAK_TO_MASTER
            && $m->mastery >= self::MASTERY_THRESHOLD) {
            $m->mastered_at = $m->last_attempt_at;
        }

        $m->save();

        return $m;
    }
}
