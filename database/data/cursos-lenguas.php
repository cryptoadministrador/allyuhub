<?php

/**
 * LA ESTRUCTURA DE LOS CURSOS DE LENGUA — y CADA CURSO DECLARA LA SUYA.
 *
 * Hasta que entró el inglés, aquí había un solo molde —nueve unidades sobre los
 * trece descriptores de A1— y el cascarón lo daba por hecho. El inglés lo rompe
 * en los tres sitios a la vez: no son nueve unidades sino tres STAGES, su marco
 * no es el MCER sino CAIE, y sus códigos no empiezan por `A1.`. Por eso ahora un
 * curso DECLARA su marco y su lista de unidades, y `CursoDeLenguas` no sabe
 * cuántas hay ni cómo se llaman los descriptores.
 *
 * El molde de 9 unidades del MCER.
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

/**
 * EL CURSO DE INGLÉS: Cambridge Lower Secondary English 0861, Stages 7-9.
 *
 * Un alumno de inglés no avanza por el MCER: avanza por STAGES y por syllabus,
 * que es lo que el colegio certifica. Se toma Lower Secondary —y no Primary ni
 * IGCSE— porque es la banda que este colegio enseña, la misma que ya declara
 * `CAIE-LSEC` en el grafo (`equivalencia_ec`: 8.º-10.º EGB).
 *
 * Los DESCRIPTORES son PROPIOS de AllyuHub (marco `AH-EN0861`, PR 16), no de
 * Cambridge: el framework completo de 0861 —el que trae el código de cada
 * objetivo— es de descarga protegida y aquí no se inventa un código. Cada
 * descriptor `EN<stage>.<strand>.<n>` cuelga del strand o sub-strand PÚBLICO
 * de 0861 que desarrolla (`database/data/ingles-0861-interno.php`), así que el
 * contenido se puede escribir ya y reanclarse el día que llegue el marco
 * oficial. Mientras no haya ítems ni lecciones firmados, las tres unidades
 * siguen saliendo «próximamente», pero ya con sus «Puedo…».
 */
$stagesIngles = [
    7 => [
        'titulo' => 'Stage 7',
        'puede' => 'Leer, escribir y hablar en inglés con los objetivos del Stage 7 de Cambridge Lower Secondary.',
        'descriptores' => ['EN7.R.1', 'EN7.R.2', 'EN7.R.3', 'EN7.R.4', 'EN7.W.1', 'EN7.W.2', 'EN7.W.3', 'EN7.W.4', 'EN7.SL.1', 'EN7.SL.2', 'EN7.SL.3'],
    ],
    8 => [
        'titulo' => 'Stage 8',
        'puede' => 'Ampliar lectura, escritura y expresión oral con los objetivos del Stage 8.',
        'descriptores' => ['EN8.R.1', 'EN8.R.2', 'EN8.R.3', 'EN8.R.4', 'EN8.W.1', 'EN8.W.2', 'EN8.W.3', 'EN8.W.4', 'EN8.SL.1', 'EN8.SL.2', 'EN8.SL.3'],
    ],
    9 => [
        'titulo' => 'Stage 9',
        'puede' => 'Cerrar Lower Secondary y quedar listo para IGCSE con los objetivos del Stage 9.',
        'descriptores' => ['EN9.R.1', 'EN9.R.2', 'EN9.R.3', 'EN9.R.4', 'EN9.W.1', 'EN9.W.2', 'EN9.W.3', 'EN9.W.4', 'EN9.SL.1', 'EN9.SL.2', 'EN9.SL.3'],
    ],
];

/**
 * Las destrezas PRODUCTIVAS de un curso: qué descriptor admite una tarea de
 * escritura y cuál una de voz (`/corso/{lengua}/u{n}/producir`).
 *
 * Era una regla escrita DENTRO del controlador (`str_contains($code, '.EE.')`),
 * cierta solo porque todos los cursos eran del MCER. Ahora la declara el curso:
 * el inglés no declara ninguna, así que su página de tarea no existe (404) en
 * vez de ofrecer una tarea contra un descriptor que no está.
 */
$productivasMcer = ['escritura' => '.EE.', 'voz' => '.PO.'];

return [
    'nombres' => [
        'fr' => 'Francés',
        'it' => 'Italiano',
        'de' => 'Alemán',
        'zh' => 'Chino',
        'en' => 'Inglés',
    ],
    'cursos' => [
        'fr' => ['marco' => 'CEFR', 'unidades' => $unidades, 'productivas' => $productivasMcer],
        'it' => ['marco' => 'CEFR', 'unidades' => $unidades, 'productivas' => $productivasMcer],
        'de' => ['marco' => 'CEFR', 'unidades' => $unidades, 'productivas' => $productivasMcer],
        'zh' => ['marco' => 'CEFR', 'unidades' => $unidades, 'productivas' => $productivasMcer],
        'en' => ['marco' => 'AH-EN0861', 'unidades' => $stagesIngles, 'productivas' => []],
    ],
];
