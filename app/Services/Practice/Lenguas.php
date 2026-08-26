<?php

namespace App\Services\Practice;

/**
 * Las lenguas del curso, en una LISTA CERRADA.
 *
 * Cerrada por la misma razón que el vocabulario de kinds: una lengua que no
 * está aquí no se siembra (el sembrador la rechaza) ni se sirve (`?lengua=`
 * fuera de la lista es 422, no una lengua nueva creada por un typo). Añadir la
 * quinta lengua del curso es añadirla aquí — un sitio, no tres.
 */
final class Lenguas
{
    public const LISTA = ['fr', 'it', 'de', 'zh'];
}
