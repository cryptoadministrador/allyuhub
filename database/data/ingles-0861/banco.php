<?php

/**
 * INGLÉS 0861 · el contenido de los tres Stages, reunido (PR 18).
 *
 * Un fichero por Stage (`stage7.php`, `stage8.php`, `stage9.php`), cada uno con
 * sus lecciones, ítems, vocabulario y guion, anclados a los descriptores
 * PROPIOS `EN<stage>.<strand>.<n>` (marco AH-EN0861, PR 16). Este fichero solo
 * los junta; los tres bancos de siempre (`banco-lenguas.php`,
 * `vocabulario-lenguas.php`, `dialogos-lenguas.php`) lo incorporan al final,
 * así que `lenguas:sembrar`, `vocabulario:sembrar` y `dialogos:sembrar` no
 * cambian y el inglés entra por el mismo circuito que el resto.
 *
 * Consignas, explicaciones y pistas en ESPAÑOL (la interfaz lo está y el
 * alumno es hispanohablante); todo el material, las opciones y las respuestas
 * en INGLÉS. Sin audio: nada de `escucha`/`dictado` hasta que haya clips.
 *
 * ESCRITO POR LA IA, PENDIENTE DE FIRMA: nace sin `reviewed_at` y no llega a un
 * alumno hasta que un docente de inglés lo firma en /docente/revisar?lengua=en.
 */

$stages = array_map(fn (int $n) => require __DIR__."/stage{$n}.php", [7, 8, 9]);

return [
    'lecciones' => array_merge(...array_column($stages, 'lecciones')),
    'items' => array_merge(...array_column($stages, 'items')),
    'vocabulario' => array_merge(...array_column($stages, 'vocabulario')),
    'dialogos' => array_column($stages, 'dialogo'),
];
