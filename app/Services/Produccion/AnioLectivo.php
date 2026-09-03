<?php

namespace App\Services\Produccion;

use Illuminate\Support\Carbon;

/**
 * El AÑO LECTIVO al que pertenece una producción — la cita con la purga.
 *
 * Régimen Sierra-Amazonía (el de Neue Schule): el curso arranca en septiembre y
 * cierra en julio. Se toma AGOSTO como frontera para que las vacaciones caigan
 * del lado del curso que empieza: una grabación de agosto de 2026 ya cuenta
 * para 2026-2027. No es una regla de matrícula, es solo la etiqueta que agrupa
 * lo que se borra junto al cierre.
 *
 * Decisión (misión, «decidí yo»): un único régimen, no dos. La Costa arranca en
 * mayo; el día que haya colegios de Costa se añade el régimen como parámetro,
 * pero meter dos calendarios hoy sería configurar algo que nadie usa.
 */
final class AnioLectivo
{
    /** El mes en que empieza el año lectivo (y frontera de la etiqueta). */
    private const MES_INICIO = 8;   // agosto

    /** La etiqueta 'YYYY-YYYY' del año lectivo de una fecha. */
    public static function de(Carbon $fecha): string
    {
        $y = (int) $fecha->year;

        return $fecha->month >= self::MES_INICIO
            ? "{$y}-".($y + 1)
            : ($y - 1)."-{$y}";
    }

    /** El año lectivo en curso. */
    public static function actual(): string
    {
        return self::de(Carbon::now());
    }
}
