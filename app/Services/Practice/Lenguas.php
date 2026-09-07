<?php

namespace App\Services\Practice;

/**
 * Las lenguas del curso, en una LISTA CERRADA.
 *
 * Cerrada por la misma razón que el vocabulario de kinds: una lengua que no
 * está aquí no se siembra (el sembrador la rechaza) ni se sirve (`?lengua=`
 * fuera de la lista es 422, no una lengua nueva creada por un typo). Añadir una
 * lengua al curso es añadirla aquí — un sitio, no tres.
 *
 * `en` es la quinta y NO es como las otras cuatro: su curso no cuelga del MCER
 * sino de Cambridge (CAIE-LSEC, Stages 7-9). Lo que decide el marco y las
 * unidades de cada curso es `database/data/cursos-lenguas.php`, no esta lista:
 * aquí solo se dice QUÉ lenguas existen.
 */
final class Lenguas
{
    public const LISTA = ['fr', 'it', 'de', 'zh', 'en'];
}
