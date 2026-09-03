<?php

/**
 * LA ESTRUCTURA DE LOS CUATRO CURSOS — el molde de 9 unidades del MCER.
 *
 * Nueve unidades idénticas en las cuatro lenguas (lo que el alumno SABE HACER
 * es lo mismo; cambia cómo se dice), del `ESQUELETO-9-UNIDADES.md` de Carlos.
 * Cada unidad declara sus DESCRIPTORES MCER —los «Puedo…» que la portada pinta
 * como objetivos del alumno— y un título/resumen del curso.
 *
 * Los descriptores existen en `cefr-a1.php`; aquí solo se REPARTEN por unidad.
 * U1 y U2 usan los descriptores que ya usa el banco de Carlos; U3–U9 reparten
 * los trece de A1 según el esqueleto (DECISIÓN mía, provisional hasta que el
 * contenido de esas unidades aterrice — mientras no haya ítems firmados de una
 * lengua para un descriptor, la unidad se pinta «próximamente», así que el
 * reparto exacto de U3–U9 no llega todavía a ningún alumno).
 *
 * La LENGUA no vive aquí: el molde es el mismo para las cuatro. Qué lenguas
 * tienen curso lo dice `Practice\Lenguas::LISTA`; qué unidades tienen contenido
 * lo decide si hay ítems/lecciones firmados de esa lengua sobre sus
 * descriptores. Un curso sin nada sembrado son nueve «próximamente».
 */

$unidades = [
    1 => [
        'titulo' => 'Primer contacto',
        'puede' => 'Saludar, decir tu nombre y de dónde eres, y presentar a otra persona.',
        'descriptores' => ['A1.CO.2', 'A1.IO.3', 'A1.CE.1'],
    ],
    2 => [
        'titulo' => 'Yo y los míos',
        'puede' => 'Hablar de tu familia, decir edades y describir a alguien.',
        'descriptores' => ['A1.PO.1', 'A1.CE.2', 'A1.EE.2'],
    ],
    3 => [
        'titulo' => 'Mi día a día',
        'puede' => 'Decir la hora y los días, y contar tu rutina.',
        'descriptores' => ['A1.CO.3', 'A1.PO.2', 'A1.IO.2'],
    ],
    4 => [
        'titulo' => 'Lo que me gusta',
        'puede' => 'Decir qué te gusta y qué no, y por qué.',
        'descriptores' => ['A1.PO.2', 'A1.EE.1', 'A1.IO.2'],
    ],
    5 => [
        'titulo' => 'En la ciudad',
        'puede' => 'Preguntar dónde está algo y entender una dirección.',
        'descriptores' => ['A1.CE.3', 'A1.IO.2', 'A1.CO.1'],
    ],
    6 => [
        'titulo' => 'Comer y beber',
        'puede' => 'Pedir en un café, comprar comida y decir cantidades.',
        'descriptores' => ['A1.IO.2', 'A1.PO.2', 'A1.CO.1'],
    ],
    7 => [
        'titulo' => 'Comprar y el tiempo',
        'puede' => 'Preguntar precios y hablar de ropa y del clima.',
        'descriptores' => ['A1.IO.2', 'A1.CE.3', 'A1.PO.2'],
    ],
    8 => [
        'titulo' => 'Contar lo que hice',
        'puede' => 'Narrar algo que pasó, en frases cortas.',
        'descriptores' => ['A1.PO.2', 'A1.EE.1', 'A1.IO.2'],
    ],
    9 => [
        'titulo' => 'Repaso y proyecto',
        'puede' => 'Sostener una conversación de dos minutos sobre todo lo anterior.',
        'descriptores' => ['A1.IO.1', 'A1.PO.2', 'A1.CE.3'],
    ],
];

return [
    'nombres' => [
        'fr' => 'Francés',
        'it' => 'Italiano',
        'de' => 'Alemán',
        'zh' => 'Chino',
    ],
    'unidades' => $unidades,
];
