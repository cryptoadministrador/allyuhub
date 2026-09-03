<?php

namespace App\Services\Practice;

use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use Illuminate\Support\Carbon;

/**
 * REPASO ESPACIADO — lo que convierte «30 palabras vistas» en «30 palabras
 * sabidas». Sin él, un alumno aprende la U1 y la ha perdido en la U4.
 *
 * Tres decisiones fijadas (misión §2):
 *
 *  1. Se repasa el DESCRIPTOR, no el ítem: la cola solo trae descriptores con
 *     ≥2 ítems firmados de la lengua, porque repasar es practicar OTRO ítem
 *     del mismo descriptor. Con uno solo se repetiría el mismo y se aprendería
 *     el ítem, no la destreza.
 *  2. El repaso NO cuenta para AGS (lo aplica el controlador leyendo el flag
 *     del billete), SÍ para el dominio.
 *  3. Techo por sesión: 12. Una cola de 200 el lunes se abandona.
 *
 * Algoritmo: el mínimo que funciona. Intervalo ×2 con el acierto (1,2,4,8,16,
 * 32 días), vuelve a 1 con el fallo. Se mide, luego se sofistica — nada de
 * importar SM-2 con sus factores de facilidad antes de tener datos.
 */
class RepasoService
{
    private const INTERVALO_MAXIMO = 32;

    private const TECHO_SESION = 12;

    /**
     * Reprograma el repaso de un descriptor tras un intento del alumno.
     * Acierto: el próximo repaso cae al intervalo actual y este dobla (hasta
     * 32). Fallo: vuelve a un día. Se llama tras aplicar el dominio.
     */
    public function programar(int $userId, string $objectiveId, bool $acierto): void
    {
        $m = ObjectiveMastery::firstOrNew([
            'user_id' => $userId, 'objective_id' => $objectiveId,
        ]);

        $intervalo = $m->repaso_intervalo ?? 1;

        if ($acierto) {
            // El repaso cae al intervalo ACTUAL, y el siguiente dobla.
            $m->repaso_en = now()->addDays($intervalo);
            $m->repaso_intervalo = min($intervalo * 2, self::INTERVALO_MAXIMO);
        } else {
            $m->repaso_en = now()->addDay();
            $m->repaso_intervalo = 1;
        }

        // La fila ya existe si el dominio se aplicó antes; si no (no debería en
        // el flujo real), se guarda con lo mínimo. No se toca `mastery` aquí:
        // el dominio lo lleva MasteryTracker.
        $m->save();
    }

    /**
     * La cola de repaso del alumno para UNA lengua, con techo de 12.
     *
     * @return array{pendientes: int, siguiente: array{descriptor_id: string, code: string, url: string}|null}
     */
    public function cola(?int $userId, string $lengua): array
    {
        if ($userId === null) {
            return ['pendientes' => 0, 'siguiente' => null];
        }

        // Descriptores con ≥2 ítems FIRMADOS de esta lengua: los únicos
        // repasables (hay «otro» ítem que servir). La lengua es cerrada.
        $repasables = PracticeItem::query()
            ->where('lengua', $lengua)
            ->whereNotNull('reviewed_at')
            ->selectRaw('objective_id, count(*) as total')
            ->groupBy('objective_id')
            ->havingRaw('count(*) >= 2')
            ->pluck('objective_id');

        if ($repasables->isEmpty()) {
            return ['pendientes' => 0, 'siguiente' => null];
        }

        // Vencidos (repaso_en <= now), el más atrasado primero, con techo.
        $vencidos = ObjectiveMastery::query()
            ->where('user_id', $userId)
            ->whereIn('objective_id', $repasables)
            ->whereNotNull('repaso_en')
            ->where('repaso_en', '<=', now())
            ->orderBy('repaso_en')
            ->limit(self::TECHO_SESION)
            ->pluck('objective_id');

        if ($vencidos->isEmpty()) {
            return ['pendientes' => 0, 'siguiente' => null];
        }

        $primero = LearningObjective::find($vencidos->first());

        return [
            'pendientes' => $vencidos->count(),
            'siguiente' => $primero === null ? null : [
                'descriptor_id' => $primero->id,
                'code' => $primero->native_code,
                // A practicar el descriptor, en repaso (el flag va al billete)
                // y con la lengua cerrada.
                'url' => "/practicar/{$primero->id}?lengua={$lengua}&repaso=1",
            ],
        ];
    }
}
