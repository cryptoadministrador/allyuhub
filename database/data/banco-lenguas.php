<?php

/**
 * BANCO DE LENGUAS — contenido de los cursos de idiomas, anclado al MCER.
 *
 * SEPARADO de banco-practica.php a propósito: otro marco (CEFR, no
 * EC-MINEDEC), otro anclaje (descriptor exacto, no prefijo de bloque), otros
 * tipos de ítem, y enunciados que no siempre van en español. Mezclarlos
 * garantizaba que un día alguien sembrara italiano contra una destreza de
 * física.
 *
 * FORMATO: entradas POR CLAVE, nunca posicionales — cada tipo declara cómo se
 * lee la suya en `App\Services\Practice\Tipos\*::desdeBanco()`. Claves
 * comunes: tipo, descriptor (código MCER exacto), lengua (lista cerrada en
 * `Practice\Lenguas`), seq, consigna. El material del ejercicio va en la
 * lengua que se aprende, con su clave de idioma real (`['it' => …]`): la
 * consigna sí va en español.
 *
 * AUDIO POR CLAVE: `'clip' => 'it/u1/saludo'` nombra un fichero de
 * database/data/audio-lenguas/it/u1/saludo.(mp3|ogg|m4a). El sembrador lo
 * publica en el almacén (hash del contenido) y sustituye la ruta; un clip que
 * falta revienta la siembra nombrándolo. Quien escribe este fichero NUNCA
 * escribe un hash.
 *
 * ALCANCE ACTUAL: Unidad 1 de ITALIANO (presentarse y saludar), sin los
 * ejercicios de audio — los clips los graba el equipo de contenido y entran
 * por `--audio` cuando existan. Las otras tres lenguas y las unidades 2-9
 * entran aquí con el mismo formato.
 *
 * Todo nace SIN FIRMAR. La revisión es POR LENGUA:
 *   php artisan practica:firmar --bloque=A1.IO.it
 *   php artisan lecciones:firmar --bloque=A1.CO.it
 */

return [
    'lecciones' => [
        [
            'lengua' => 'it', 'descriptor' => 'A1.IO.3', 'slug' => 'presentarse',
            'titulo' => ['es' => 'Presentarse en italiano'],
            'resumen' => ['es' => 'Decir cómo te llamas, de dónde eres y preguntar a otra persona.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Presentarse en italiano necesita tres piezas: decir tu nombre, decir de dónde eres y devolver la pregunta. Con eso ya puedes tener tu primera conversación real.']],
                ['tipo' => 'lista', 'items' => [
                    ['es' => 'Io sono Marco. — Yo soy Marco.'],
                    ['es' => 'Mi chiamo Anna. — Me llamo Anna.'],
                    ['es' => 'Sono dell\'Ecuador. — Soy de Ecuador.'],
                    ['es' => 'E tu, come ti chiami? — Y tú, ¿cómo te llamas?'],
                ]],
                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Un primer diálogo completo'], 'pasos' => [
                    ['texto' => ['es' => '— Ciao! Mi chiamo Marco. E tu?']],
                    ['texto' => ['es' => '— Ciao, Marco! Io sono Anna.']],
                    ['texto' => ['es' => '— Piacere! (¡Encantado!)']],
                    ['texto' => ['es' => 'Fíjate: «mi chiamo» (me llamo) y «io sono» (yo soy) valen los dos para presentarte. Los italianos los alternan igual que nosotros.']],
                ]],
                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «io sono chiamo Marco», mezclando las dos fórmulas. O «mi chiamo Marco» o «io sono Marco» — nunca las dos a la vez.']],
                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'La doble consonante suena de verdad: «chiamo» lleva una m, «mamma» lleva dos y se oyen. En italiano cambiar una doble puede cambiar la palabra.']],
            ],
        ],
    ],

    'items' => [
        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Completa la presentación: « Io ___ Marco. » (ser)'],
            'aceptadas' => ['sono'],
        ],
        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 2,
            'consigna' => ['es' => 'Completa: « Mi ___ Anna. » (llamarse)'],
            'aceptadas' => ['chiamo'],
        ],
        [
            'tipo' => 'orden', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 3,
            'consigna' => ['es' => 'Ordena la pregunta: ¿y tú cómo te llamas?'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['it' => 'e tu,']],
                ['clave' => 'w2', 'texto' => ['it' => 'come']],
                ['clave' => 'w3', 'texto' => ['it' => 'ti']],
                ['clave' => 'w4', 'texto' => ['it' => 'chiami?']],
            ],
            'secuencias' => [['w1', 'w2', 'w3', 'w4']],
        ],
        [
            'tipo' => 'pares', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 4,
            'consigna' => ['es' => 'Empareja cada frase con su significado.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['it' => 'mi chiamo']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['it' => 'io sono']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['it' => 'piacere']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'me llamo']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'yo soy']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'encantado']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3']],
        ],
        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Lees «USCITA» sobre una puerta del aeropuerto. ¿Qué señala?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'La salida']],
                ['clave' => 'b', 'texto' => ['es' => 'La entrada']],
                ['clave' => 'c', 'texto' => ['es' => 'La aduana']],
            ],
            'correcta' => 'a',
        ],
    ],
];
