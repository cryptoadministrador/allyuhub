<?php

namespace App\Services\Practice\Tipos;

/**
 * La normalización de una respuesta escrita, POR LENGUA y no global.
 *
 * Qué se perdona y qué no, y por qué:
 *
 *  - MAYÚSCULAS y ESPACIOS sobrantes se perdonan en todas las lenguas: son
 *    ruido de teclado, no conocimiento. Escribir «  OÙ  » es saber la palabra.
 *  - El APÓSTROFO TIPOGRÁFICO (’ y ʼ frente a ') se perdona en todas: lo mete
 *    el teclado del teléfono, no el alumno.
 *  - Los ACENTOS NO se perdonan: en francés `ou` (o) y `où` (dónde) son dos
 *    palabras, en italiano `perché` lleva el acento o es otra cosa. Pero el
 *    veredicto distingue «te falta un acento» de «esa palabra no es» — son dos
 *    errores distintos y el alumno tiene que saber cuál cometió.
 *  - La ß ALEMANA y su `ss` son la misma palabra en la ortografía alemana (la
 *    Suiza alemana escribe `ss` siempre): se pliegan SOLO en `de`. Decisión de
 *    la lengua, no del motor — por eso la normalización recibe la lengua.
 *
 * Sin `Normalizer` de intl a propósito: el CI no carga esa extensión y un
 * transliterador dependiente de plataforma daría veredictos distintos según la
 * máquina. El mapa de acentos es CERRADO y cubre las lenguas del curso
 * (fr/it/de/zh-pinyin) más el español de la interfaz.
 */
final class Normalizador
{
    /** Vocales acentuadas → base, para DETECTAR el error de acento. */
    private const SIN_ACENTO = [
        'à' => 'a', 'â' => 'a', 'á' => 'a', 'ä' => 'a', 'ã' => 'a', 'ā' => 'a', 'ǎ' => 'a',
        'è' => 'e', 'ê' => 'e', 'é' => 'e', 'ë' => 'e', 'ē' => 'e', 'ě' => 'e',
        'ì' => 'i', 'î' => 'i', 'í' => 'i', 'ï' => 'i', 'ī' => 'i', 'ǐ' => 'i',
        'ò' => 'o', 'ô' => 'o', 'ó' => 'o', 'ö' => 'o', 'õ' => 'o', 'ō' => 'o', 'ǒ' => 'o',
        'ù' => 'u', 'û' => 'u', 'ú' => 'u', 'ü' => 'u', 'ū' => 'u', 'ǔ' => 'u', 'ǖ' => 'u',
        'ǘ' => 'u', 'ǚ' => 'u', 'ǜ' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
    ];

    /**
     * La forma canónica con la que se COMPARA: minúsculas, sin espacios
     * sobrantes, apóstrofos rectos — y lo que la lengua pliegue por su cuenta.
     * Los acentos SE CONSERVAN: distinguirlos es parte del ejercicio.
     */
    public static function normalizar(string $texto, string $lengua): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = (string) preg_replace('/\s+/u', ' ', $t);
        $t = strtr($t, ['’' => "'", 'ʼ' => "'", '‘' => "'"]);

        if ($lengua === 'de') {
            $t = str_replace('ß', 'ss', $t);
        }

        return $t;
    }

    /** La misma forma, ADEMÁS sin acentos: solo para clasificar el error. */
    public static function sinAcentos(string $texto, string $lengua): string
    {
        return strtr(self::normalizar($texto, $lengua), self::SIN_ACENTO);
    }
}
