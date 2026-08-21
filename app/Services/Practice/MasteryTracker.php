<?php

namespace App\Services\Practice;

use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
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
 * `mastered_at` se sella con racha ≥ 3, mastery ≥ 0.8 y aciertos en al menos
 * DOS ÍTEMS DISTINTOS, y NO se borra después: el selector adaptativo usa el
 * mastery vivo para decidir refuerzos.
 *
 * POR QUÉ LOS DOS ÍTEMS. Un ítem de opción múltiple no re-aleatoriza nada entre
 * intentos, y al fallar se revela cuál era la buena — así que con una sola
 * pregunta bastaban tres clics para sellar el dominio de una destreza y empujar
 * la nota al cuaderno del profesor. El camino numérico era inmune porque los
 * números cambian, pero la regla se aplica a los dos: el dominio de una destreza
 * con un único ítem no significa nada, haya trampa o no. Esconder la
 * explicación no habría servido —con cuatro opciones se fuerza bruta igual— y
 * habría costado la retroalimentación, que sí está bien puesta.
 */
class MasteryTracker
{
    public const ALPHA = 0.35;

    public const BETA = 0.30;

    public const MASTERY_THRESHOLD = 0.8;

    public const STREAK_TO_MASTER = 3;

    /** Ítems DISTINTOS que hay que haber acertado para que el dominio cuente. */
    public const ITEMS_TO_MASTER = 2;

    /**
     * Registra el resultado de un intento. Llamar SIEMPRE dentro de la
     * transacción del intento.
     *
     * `$itemsAcertados` lo calcula quien llama (con `itemsAcertados()`, después
     * de guardar el intento y antes de esto) y no este método: así el tracker
     * sigue siendo aritmética pura y comprobable en un test unitario, y la
     * cuenta se hace UNA vez por intento en lugar de dos —aquí y otra vez para
     * decidir si la nota viaja al aula.
     */
    public function apply(
        int $userId,
        string $objectiveId,
        bool $isCorrect,
        int $itemsAcertados = 0,
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
            && $m->mastery >= self::MASTERY_THRESHOLD
            && $itemsAcertados >= self::ITEMS_TO_MASTER) {
            $m->mastered_at = $m->last_attempt_at;
        }

        $m->save();

        return $m;
    }

    /**
     * Cuántos ítems DISTINTOS de esa destreza ha acertado ya el alumno.
     *
     * Distintos, no intentos: acertar veinte veces la misma pregunta sigue
     * siendo una pregunta. El intento en curso ya está en la tabla cuando esto
     * se llama —`persistAttempt` lo crea antes, en la misma transacción— así
     * que el acierto que acaba de ocurrir cuenta.
     */
    public function itemsAcertados(int $userId, string $objectiveId): int
    {
        return PracticeAttempt::query()
            ->where('user_id', $userId)
            ->where('is_correct', true)
            ->whereIn('item_id', PracticeItem::where('objective_id', $objectiveId)->select('id'))
            ->distinct()
            ->count('item_id');
    }

    /**
     * ¿Tiene sentido publicar una nota de esta destreza en el aula?
     *
     * Mismo listón que el dominio: mientras el alumno solo haya acertado un
     * ítem, la nota no diría nada del aprendizaje y sí ocuparía una casilla del
     * cuaderno del profesor. Se practica y se corrige con normalidad; lo único
     * que espera es la calificación.
     */
    public function califica(int $itemsAcertados): bool
    {
        return $itemsAcertados >= self::ITEMS_TO_MASTER;
    }
}
