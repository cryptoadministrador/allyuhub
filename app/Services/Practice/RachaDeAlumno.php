<?php

namespace App\Services\Practice;

use App\Models\PracticeAttempt;
use Illuminate\Support\Carbon;

/**
 * La RACHA de un alumno: días naturales SEGUIDOS en los que ha practicado o
 * repasado.
 *
 * REGLA (misión curso 3, §2 — SUSTITUYE a la de la misión 1): la racha se rompe
 * si pasa UN día natural sin repaso ni práctica. La regla anterior daba tres
 * días de gracia («un fin de semana no castiga»); la nueva es la de Khan, y
 * viene con el repaso diario (`/corso/{lengua}/repaso`), que es justo lo que da
 * al alumno algo que hacer cada día en diez minutos. Sin ese sitio, la racha
 * estricta castigaba; con él, empuja.
 *
 * Los días son los de ECUADOR (America/Guayaquil), decidido: un alumno que
 * repasa a las diez de la noche de Quito repasa HOY, aunque en UTC ya sea
 * mañana. `created_at` se guarda en UTC y se convierte al contar.
 *
 * `viva` = el último día activo es hoy o ayer (hoy aún no ha terminado).
 * `dias` = cuántos días seguidos, contando hacia atrás desde el último activo.
 *
 * NO es la racha firmada de `ObjectiveMastery::streak` (aciertos seguidos de
 * UNA destreza). Esta es de días de actividad y sale de `practice_attempts`
 * — repaso y práctica por igual, porque las dos son intentos. El invitado no
 * tiene historia: cero.
 */
class RachaDeAlumno
{
    public const ZONA = 'America/Guayaquil';

    /**
     * @return array{dias: int, viva: bool}
     */
    public function calcular(?int $userId): array
    {
        if ($userId === null) {
            return ['dias' => 0, 'viva' => false];
        }

        // Los días naturales distintos con actividad, en hora de Ecuador, del
        // más reciente al más antiguo. Se agrupa en PHP y no en SQL para no
        // depender del motor: `date()` sobre un timestamp difiere entre pgsql
        // y sqlite, y la zona horaria ni la conocen.
        $dias = PracticeAttempt::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn ($t) => $t->copy()->setTimezone(self::ZONA)->toDateString())
            ->unique()
            ->values();

        if ($dias->isEmpty()) {
            return ['dias' => 0, 'viva' => false];
        }

        $hoy = now(self::ZONA)->startOfDay();
        $ultimo = Carbon::parse($dias->first(), self::ZONA)->startOfDay();

        // Viva si el último día activo es hoy o ayer. abs + int: Carbon nuevo
        // devuelve diffInDays con signo y como float (lo cazó un oráculo).
        $viva = (int) abs($hoy->diffInDays($ultimo)) <= 1;
        if (! $viva) {
            return ['dias' => 0, 'viva' => false];
        }

        // Hacia atrás, mientras los días activos sean CONSECUTIVOS.
        $cuenta = 1;
        $anterior = $ultimo;
        foreach ($dias->slice(1) as $dia) {
            $d = Carbon::parse($dia, self::ZONA)->startOfDay();
            if ((int) abs($anterior->diffInDays($d)) !== 1) {
                break;
            }
            $cuenta++;
            $anterior = $d;
        }

        return ['dias' => $cuenta, 'viva' => true];
    }

    /** ¿Hay actividad HOY (Ecuador)? Lo que decide si el repaso de hoy ya subió la racha. */
    public function activoHoy(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        $inicioHoyUtc = now(self::ZONA)->startOfDay()->setTimezone('UTC');

        return PracticeAttempt::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $inicioHoyUtc)
            ->exists();
    }
}
