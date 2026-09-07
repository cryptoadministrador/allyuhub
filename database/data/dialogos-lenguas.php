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
 *   - `clip` = clave de audio, como el resto del audio del curso.
 *
 * EL CLIP SE DECLARA AUNQUE EL FICHERO NO EXISTA. Es la única excepción a la
 * regla del banco de lenguas, donde un clip ausente aborta la siembra: allí,
 * sin audio no hay ejercicio de escucha; aquí el diálogo se juega entero
 * leyendo y el audio es un añadido. `dialogos:sembrar` conserva la clave,
 * rellena la ruta solo si el fichero está, y AVISA de los que faltan. El día
 * que lleguen las grabaciones, re-sembrar las engancha sin tocar este fichero.
 *
 * ESCRITO POR LA IA, PENDIENTE DE QUE UN PROFESOR LO FIRME (nace sin
 * `reviewed_at`). El vocabulario de cada guion sale del banco U1 de Carlos de
 * ESA lengua, y cada uno lleva el punto de la unidad dentro:
 *
 *   it — `essere` singular, chiamarsi, provenienza, presentar a un tercero.
 *   fr — la decisión tu / vous: el interlocutor es una PROFESORA, así que
 *        `salut` es el callejón y `bonjour` / `au revoir` lo correcto. Y la
 *        edad va con `avoir` (`j'ai quinze ans`), no con `être`.
 *   de — el mismo par du / Sie con una profesora (`Guten Tag` frente a
 *        `Hallo`), el origen con `kommen aus` y no con `sein`, y EL VERBO EN
 *        SEGUNDA POSICIÓN, que es la regla de gramática de la unidad.
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
                'clip' => 'it/u1/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Mi chiamo Ana.', 'va' => 'provenienza'],
                    ['texto' => 'Bene, grazie.', 'va' => null,
                        'pista' => 'Te preguntan tu NOMBRE, no cómo estás. Empieza por «Mi chiamo…».'],
                ],
            ],
            [
                'id' => 'provenienza',
                'dice' => 'Piacere, Ana! Di dove sei?',
                'clip' => 'it/u1/dialogo/provenienza',
                'respuestas' => [
                    ['texto' => 'Sono di Quito.', 'va' => 'come_stai'],
                    ['texto' => 'Mi chiamo Ana.', 'va' => null,
                        'pista' => 'Tu nombre ya lo dijiste. Ahora: de dónde eres, «Sono di…».'],
                ],
            ],
            [
                'id' => 'come_stai',
                'dice' => 'Ah, l\'Ecuador! Come stai?',
                'clip' => 'it/u1/dialogo/come_stai',
                'respuestas' => [
                    ['texto' => 'Bene, grazie. E tu?', 'va' => 'terzo'],
                    ['texto' => 'Sono di Quito.', 'va' => null,
                        'pista' => 'Eso ya lo dijiste. Te preguntan cómo estás: «Bene…».'],
                ],
            ],
            [
                'id' => 'terzo',
                'dice' => 'Bene! Lei è Sofia. È di Roma.',
                'clip' => 'it/u1/dialogo/terzo',
                'respuestas' => [
                    ['texto' => 'Ciao, Sofia! Piacere.', 'va' => 'despedida'],
                    ['texto' => 'Come ti chiami?', 'va' => null,
                        'pista' => 'Sofia ya tiene nombre. Salúdala: «Ciao, Sofia!».'],
                ],
            ],
            [
                'id' => 'despedida',
                'dice' => 'Molto bene, Ana. Arrivederci!',
                'clip' => 'it/u1/dialogo/despedida',
                'respuestas' => [
                    ['texto' => 'Arrivederci!', 'va' => 'fin'],
                    ['texto' => 'Ciao!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'A presto!',
                'clip' => 'it/u1/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    [
        'lengua' => 'fr',
        'unidad' => 1,
        'objective' => 'A1.IO.1',
        'slug' => 'le-premier-jour',
        'titulo' => 'Le premier jour',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Bonjour ! Je m\'appelle madame Durand. Et toi, comment tu t\'appelles ?',
                'clip' => 'fr/u1/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Bonjour ! Je m\'appelle Ana.', 'va' => 'origine'],
                    ['texto' => 'Salut ! Ça va ?', 'va' => null,
                        'pista' => 'Es una profesora, no una amiga: con un adulto se dice «Bonjour», nunca «Salut». Y te preguntan tu nombre.'],
                ],
            ],
            [
                'id' => 'origine',
                'dice' => 'Enchantée, Ana. Tu es française ?',
                'clip' => 'fr/u1/dialogo/origine',
                'respuestas' => [
                    ['texto' => 'Non, je suis équatorienne. Je suis de Quito.', 'va' => 'age'],
                    ['texto' => 'Oui, je suis de Paris.', 'va' => null,
                        'pista' => 'Eres de Ecuador: «Non, je suis équatorienne».'],
                ],
            ],
            [
                'id' => 'age',
                'dice' => 'L\'Équateur ! Et tu as quel âge ?',
                'clip' => 'fr/u1/dialogo/age',
                'respuestas' => [
                    ['texto' => 'J\'ai quinze ans.', 'va' => 'troisieme'],
                    ['texto' => 'Je suis quinze ans.', 'va' => null,
                        'pista' => 'La edad va con «avoir», no con «être»: «J\'ai quinze ans».'],
                ],
            ],
            [
                'id' => 'troisieme',
                'dice' => 'Très bien. C\'est Marc. Il est français, il est de Lyon.',
                'clip' => 'fr/u1/dialogo/troisieme',
                'respuestas' => [
                    ['texto' => 'Bonjour, Marc ! Enchantée.', 'va' => 'despedida'],
                    ['texto' => 'Comment tu t\'appelles ?', 'va' => null,
                        'pista' => 'Marc ya tiene nombre. Salúdalo: «Bonjour, Marc !».'],
                ],
            ],
            [
                'id' => 'despedida',
                'dice' => 'Parfait. À demain, Ana. Au revoir !',
                'clip' => 'fr/u1/dialogo/despedida',
                'respuestas' => [
                    ['texto' => 'Au revoir, madame !', 'va' => 'fin'],
                    ['texto' => 'Salut !', 'va' => null,
                        'pista' => '«Salut» sirve para despedirse de un amigo. Con la profesora: «Au revoir».'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Au revoir !',
                'clip' => 'fr/u1/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    [
        'lengua' => 'de',
        'unidad' => 1,
        'objective' => 'A1.IO.1',
        'slug' => 'der-erste-tag',
        'titulo' => 'Der erste Tag',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Guten Tag! Ich heiße Frau Müller. Wie heißt du?',
                'clip' => 'de/u1/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Guten Tag! Ich heiße Ana.', 'va' => 'herkunft'],
                    ['texto' => 'Hallo! Tschüss!', 'va' => null,
                        'pista' => 'Es una profesora: con un adulto van «Guten Tag» y «Auf Wiedersehen», no «Hallo» ni «Tschüss». Y te preguntan tu nombre.'],
                ],
            ],
            [
                'id' => 'herkunft',
                'dice' => 'Guten Tag, Ana. Woher kommst du?',
                'clip' => 'de/u1/dialogo/herkunft',
                'respuestas' => [
                    ['texto' => 'Ich komme aus Ecuador, aus Quito.', 'va' => 'schule'],
                    ['texto' => 'Ich bin aus Ecuador.', 'va' => null,
                        'pista' => 'En alemán el origen se dice con «kommen aus», no con «sein»: «Ich komme aus…».'],
                ],
            ],
            [
                'id' => 'schule',
                'dice' => 'Ecuador! Bist du Schülerin hier?',
                'clip' => 'de/u1/dialogo/schule',
                'respuestas' => [
                    ['texto' => 'Ja, ich bin Schülerin.', 'va' => 'dritte'],
                    ['texto' => 'Nein, ich bin Frau Müller.', 'va' => null,
                        'pista' => 'Frau Müller es la profesora. Tú eres la alumna: «Ja, ich bin Schülerin».'],
                ],
            ],
            [
                'id' => 'dritte',
                'dice' => 'Gut. Das ist Marco. Er kommt aus Berlin.',
                'clip' => 'de/u1/dialogo/dritte',
                'respuestas' => [
                    ['texto' => 'Hallo, Marco!', 'va' => 'zweite_position'],
                    ['texto' => 'Wie heißt du?', 'va' => null,
                        'pista' => 'Marco ya tiene nombre. Salúdalo: «Hallo, Marco!» — con él sí va el «Hallo», que es de tu edad.'],
                ],
            ],
            [
                'id' => 'zweite_position',
                'dice' => 'Heute bin ich müde. Und du?',
                'clip' => 'de/u1/dialogo/zweite_position',
                'respuestas' => [
                    ['texto' => 'Ich bin auch müde.', 'va' => 'abschied'],
                    ['texto' => 'Ich müde bin.', 'va' => null,
                        'pista' => 'El verbo va en SEGUNDA posición, siempre: «Ich bin auch müde».'],
                ],
            ],
            [
                'id' => 'abschied',
                'dice' => 'Gut. Auf Wiedersehen, Ana!',
                'clip' => 'de/u1/dialogo/abschied',
                'respuestas' => [
                    ['texto' => 'Auf Wiedersehen, Frau Müller!', 'va' => 'fin'],
                    ['texto' => 'Tschüss!', 'va' => null,
                        'pista' => '«Tschüss» es informal. Con la profesora: «Auf Wiedersehen».'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Bis morgen!',
                'clip' => 'de/u1/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],
];
