<?php

namespace App\Services\Curso;

use App\Models\Tarjeta;
use App\Models\VocabEstado;
use App\Services\Practice\RachaDeAlumno;
use Illuminate\Support\Collection;

/**
 * EL MAZO DE VOCABULARIO de una unidad para un alumno (PR 10).
 *
 * Tarjetas FIRMADAS de (lengua, unidad), en el orden del banco, con lo que el
 * alumno ha dicho de cada una:
 *
 *  - `conocida`: la última vez dijo «la sé» (`conocida_at` con fecha). Es lo
 *    que cuenta «Vocabulario: 12 / 17 palabras» en la unidad.
 *  - `hoy`: está en el mazo de HOY. «La sé» la saca del mazo hasta el día
 *    siguiente (día de Ecuador, como la racha); «todavía no» la deja. Mañana
 *    vuelve, conocida o no: el vocabulario se REPASA, no se aprueba.
 *
 * El invitado ve el mazo entero, nada conocido, y todo lo que diga vive en la
 * memoria de la página: aquí no tiene filas y no las tendrá.
 *
 * No es un tipo de ítem ni da dominio, a propósito: es material de apoyo.
 */
final class MazoDeVocabulario
{
    /**
     * Las tarjetas serializadas para la página, en el orden del banco.
     *
     * @return Collection<int, array>
     */
    public function tarjetas(string $lengua, int $unidad, ?int $userId): Collection
    {
        $tarjetas = Tarjeta::published()->deUnidad($lengua, $unidad)->get();
        $estados = $this->estados($tarjetas, $userId);
        $inicioHoy = now(RachaDeAlumno::ZONA)->startOfDay();

        return $tarjetas->map(function (Tarjeta $t) use ($estados, $inicioHoy) {
            $conocidaAt = $estados[$t->id] ?? null;

            return self::serializar(
                $t,
                conocida: $conocidaAt !== null,
                hoy: $conocidaAt === null || $conocidaAt->lt($inicioHoy),
            );
        })->values();
    }

    /**
     * «Vocabulario: 12 / 17 palabras» — solo FIRMADAS, y conocidas solo del
     * alumno. Dos consultas acotadas, ninguna por tarjeta.
     *
     * @return array{total: int, conocidas: int}
     */
    public function cuenta(string $lengua, int $unidad, ?int $userId): array
    {
        $firmadas = Tarjeta::published()->where('lengua', $lengua)->where('unidad', $unidad);
        $total = (clone $firmadas)->count();

        $conocidas = $userId === null || $total === 0 ? 0 : VocabEstado::query()
            ->where('user_id', $userId)
            ->whereNotNull('conocida_at')
            ->whereIn('tarjeta_id', (clone $firmadas)->select('id'))
            ->count();

        return ['total' => $total, 'conocidas' => $conocidas];
    }

    /** La MISMA forma para la página del alumno y para la revisión docente. */
    public static function serializar(Tarjeta $t, bool $conocida, bool $hoy): array
    {
        return [
            'id' => $t->id,
            'palabra' => $t->palabra,
            'lectura' => $t->lectura,
            'significado' => $t->significado,
            'ejemplo' => [
                'texto' => $t->ejemplo[$t->lengua] ?? '',
                'es' => $t->ejemplo['es'] ?? '',
            ],
            // Solo la ruta pública, y solo si el fichero existe: nunca un
            // reproductor apuntando a un clip que no está.
            'audio' => $t->audio,
            'conocida' => $conocida,
            'hoy' => $hoy,
        ];
    }

    /**
     * `conocida_at` por tarjeta del alumno, en una consulta (nulo = «todavía no»).
     *
     * @return array<string, \Illuminate\Support\Carbon|null>
     */
    private function estados(Collection $tarjetas, ?int $userId): array
    {
        if ($userId === null || $tarjetas->isEmpty()) {
            return [];
        }

        return VocabEstado::query()
            ->where('user_id', $userId)
            ->whereIn('tarjeta_id', $tarjetas->pluck('id'))
            ->get(['tarjeta_id', 'conocida_at'])
            ->mapWithKeys(fn (VocabEstado $e) => [$e->tarjeta_id => $e->conocida_at])
            ->all();
    }
}
