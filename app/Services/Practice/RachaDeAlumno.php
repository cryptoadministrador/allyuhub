<?php

namespace App\Services\Practice;

use App\Models\PracticeAttempt;

/**
 * La RACHA de un alumno: días naturales seguidos en los que ha practicado.
 *
 * REGLA (fijada en la misión, no emergente): la racha se rompe con TRES días
 * naturales sin actividad, no con uno. Un fin de semana no castiga a quien
 * tiene vida — practicar viernes y lunes mantiene la racha, y solo tres días
 * de silencio seguidos la cortan.
 *
 * Es la mecánica que enseña —volver cada día ES como funciona la memoria— y por
 * eso importa que sea generosa: una racha que un lunes marca cero por no haber
 * tocado el sábado y el domingo es una que el alumno deja de mirar.
 *
 * NO es la racha firmada de `ObjectiveMastery::streak` (esa cuenta aciertos
 * seguidos de UNA destreza). Esta es de días de actividad, y sale de los
 * instantes de `practice_attempts`. El invitado no tiene: cero.
 */
class RachaDeAlumno
{
    /** Días de gracia: hasta 2 días naturales de hueco no rompen (el 3.º sí). */
    private const HUECO_MAXIMO = 2;

    /**
     * @return array{dias: int, viva: bool}
     */
    public function calcular(?int $userId): array
    {
        if ($userId === null) {
            return ['dias' => 0, 'viva' => false];
        }

        // Los días naturales distintos con actividad, del más reciente al más
        // antiguo. Se agrupa en PHP y no en SQL para no depender del motor:
        // `date()` sobre un timestamp difiere entre pgsql y sqlite.
        $dias = PracticeAttempt::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn ($t) => $t->toDateString())
            ->unique()
            ->values();

        if ($dias->isEmpty()) {
            return ['dias' => 0, 'viva' => false];
        }

        $hoy = now()->startOfDay();
        $ultimo = \Illuminate\Support\Carbon::parse($dias->first())->startOfDay();

        // Viva solo si el último día activo está dentro de la ventana de gracia
        // DESDE HOY: si el último fue hace 3+ días, la racha ya se rompió.
        // abs + int: Carbon nuevo devuelve diffInDays con signo y como
        // float — sin esto, «hace 3 días» daba -3 y -3 <= 2 mantenía viva
        // una racha ya rota (lo cazó el oráculo).
        $viva = (int) abs($hoy->diffInDays($ultimo)) <= self::HUECO_MAXIMO;
        if (! $viva) {
            return ['dias' => 0, 'viva' => false];
        }

        // Se camina hacia atrás mientras el salto entre días activos
        // consecutivos no pase de la ventana de gracia.
        $cuenta = 1;
        $anterior = $ultimo;
        foreach ($dias->slice(1) as $dia) {
            $d = \Illuminate\Support\Carbon::parse($dia)->startOfDay();
            if ((int) abs($anterior->diffInDays($d)) > self::HUECO_MAXIMO + 1) {
                break;
            }
            $cuenta++;
            $anterior = $d;
        }

        return ['dias' => $cuenta, 'viva' => true];
    }
}
