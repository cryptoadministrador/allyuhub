<?php

/**
 * LOS GUIONES DEL INTERLOCUTOR. Un diálogo por unidad, escrito a mano: sin
 * modelo de lenguaje, así que el interlocutor no puede decir una palabra fuera
 * de nivel — está todo aquí.
 *
 * Forma de un nodo:
 *   ['id', 'dice', 'clip'?, 'respuestas' => [['texto', 'va', 'pista'?], …], 'fin'?]
 *   - `va` = id del nodo al que lleva la respuesta; `va: null` + `pista` es un
 *     callejón que VUELVE al mismo nodo con una ayuda (nunca un error).
 *   - un nodo con `fin: true` (y sin respuestas) cierra la conversación.
 *   - `clip` (opcional) = clave de audio, como el resto del audio del curso; se
 *     publica en el almacén PÚBLICO (es la voz del guion, no la de un menor).
 *
 * ESCRITO POR LA IA, PENDIENTE DE QUE UN PROFESOR LO FIRME (nace sin
 * `reviewed_at`). Vocabulario tomado del banco U1 de Carlos: saludos, `essere`
 * singular (sono/sei/è), chiamarsi, provenienza, y presentar a un tercero. Sin
 * clips todavía (texto): se añaden por clave cuando existan los ficheros.
 */

return [
    [
        'lengua' => 'it',
        'unidad' => 1,
        'objective' => 'A1.IO.1',
        'slug' => 'il-primo-giorno',
        'titulo' => 'Il primo giorno',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Ciao! Buongiorno. Come ti chiami?',
                'respuestas' => [
                    ['texto' => 'Mi chiamo Ana.', 'va' => 'provenienza'],
                    ['texto' => 'Bene, grazie.', 'va' => null,
                        'pista' => 'Te preguntan tu NOMBRE, no cómo estás. Empieza por «Mi chiamo…».'],
                ],
            ],
            [
                'id' => 'provenienza',
                'dice' => 'Piacere, Ana! Di dove sei?',
                'respuestas' => [
                    ['texto' => 'Sono di Quito.', 'va' => 'come_stai'],
                    ['texto' => 'Mi chiamo Ana.', 'va' => null,
                        'pista' => 'Tu nombre ya lo dijiste. Ahora: de dónde eres, «Sono di…».'],
                ],
            ],
            [
                'id' => 'come_stai',
                'dice' => 'Ah, l\'Ecuador! Come stai?',
                'respuestas' => [
                    ['texto' => 'Bene, grazie. E tu?', 'va' => 'terzo'],
                    ['texto' => 'Sono di Quito.', 'va' => null,
                        'pista' => 'Eso ya lo dijiste. Te preguntan cómo estás: «Bene…».'],
                ],
            ],
            [
                'id' => 'terzo',
                'dice' => 'Bene! Lei è Sofia. È di Roma.',
                'respuestas' => [
                    ['texto' => 'Ciao, Sofia! Piacere.', 'va' => 'despedida'],
                    ['texto' => 'Come ti chiami?', 'va' => null,
                        'pista' => 'Sofia ya tiene nombre. Salúdala: «Ciao, Sofia!».'],
                ],
            ],
            [
                'id' => 'despedida',
                'dice' => 'Molto bene, Ana. Arrivederci!',
                'respuestas' => [
                    ['texto' => 'Arrivederci!', 'va' => 'fin'],
                    ['texto' => 'Ciao!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'A presto!',
                'fin' => true,
            ],
        ],
    ],
];
