<?php

namespace App\Services\Practice\Tipos;

use App\Models\PracticeItem;
use InvalidArgumentException;

/**
 * El vocabulario CERRADO de tipos de ítem, y la única forma de obtener uno.
 *
 * Un kind que no está aquí no existe: ni se guarda (el guardián `saving` pasa
 * por este registro y revienta) ni se sirve. Cerrado a propósito — la portada
 * contó `'simulator'` en verde durante meses porque el vocabulario de kinds de
 * recursos vivía en un comentario (#25); este vive en código que falla.
 */
final class Registro
{
    private const TIPOS = [
        PracticeItem::NUMERIC => TipoNumerico::class,
        PracticeItem::CHOICE => TipoPorClave::class,
        PracticeItem::ESCUCHA => TipoEscucha::class,
        PracticeItem::HUECO => TipoHueco::class,
        PracticeItem::DICTADO => TipoDictado::class,
        PracticeItem::ORDEN => TipoOrden::class,
        PracticeItem::PARES => TipoPares::class,
    ];

    /**
     * Los kinds registrados, para quien tenga que RECORRERLOS — el oráculo de
     * no-filtración itera esta lista, no una copia escrita a mano: un tipo que
     * se registre mañana nace DENTRO del oráculo, no fuera.
     */
    public static function kinds(): array
    {
        return array_keys(self::TIPOS);
    }

    public static function de(?string $kind): Tipo
    {
        $clase = self::TIPOS[$kind] ?? null;

        if ($clase === null) {
            throw new InvalidArgumentException(
                'Kind «'.var_export($kind, true).'» desconocido. '.
                'Admitidos: '.implode(', ', array_keys(self::TIPOS)).'.',
            );
        }

        return new $clase;
    }
}
