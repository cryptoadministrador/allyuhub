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
 * `reviewed_at`). UN GUION POR UNIDAD Y LENGUA: 36 diálogos (9 × it/fr/de/zh).
 * El vocabulario de cada guion sale del banco de ESA unidad y ESA lengua, y
 * cada uno lleva el punto gramatical de la unidad dentro; los callejones
 * (`va: null` + `pista`) son SU error típico, nunca un error inventado. Los
 * de chino van en caracteres Y pinyin con tonos, porque sin audio el alumno
 * de A1 lee el pinyin. En la U1:
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


    // ============ IT U2 · la familia: avere, el plural, la edad con avere ============
    [
        'lengua' => 'it',
        'unidad' => 2,
        'objective' => 'A1.IO.3',
        'slug' => 'la-mia-famiglia',
        'titulo' => 'La mia famiglia',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marco: Ciao, Ana! Hai fratelli?',
                'clip' => 'it/u2/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Sì, ho una sorella.', 'va' => 'eta'],
                    ['texto' => 'No, non ho fratelli.', 'va' => 'genitori'],
                    ['texto' => 'Sì, sono una sorella.', 'va' => null,
                        'pista' => 'Tener hermanos es «avere»: «Ho una sorella». «Sono» es ser.'],
                ],
            ],
            [
                'id' => 'eta',
                'dice' => 'Quanti anni ha tua sorella?',
                'clip' => 'it/u2/dialogo/eta',
                'respuestas' => [
                    ['texto' => 'Ha diciotto anni.', 'va' => 'genitori'],
                    ['texto' => 'È diciotto anni.', 'va' => null,
                        'pista' => 'La edad va con «avere»: «Ha diciotto anni», no «è».'],
                ],
            ],
            [
                'id' => 'genitori',
                'dice' => 'E i tuoi genitori? Come si chiamano?',
                'clip' => 'it/u2/dialogo/genitori',
                'respuestas' => [
                    ['texto' => 'Mio padre si chiama Luis e mia madre si chiama Rosa.', 'va' => 'animali'],
                    ['texto' => 'Il mio padre si chiama Luis.', 'va' => null,
                        'pista' => 'Con la familia en singular NO va el artículo: «mio padre», «mia madre».'],
                ],
            ],
            [
                'id' => 'animali',
                'dice' => 'Bello! Avete animali a casa?',
                'clip' => 'it/u2/dialogo/animali',
                'respuestas' => [
                    ['texto' => 'Sì, abbiamo due gatti.', 'va' => 'tu'],
                    ['texto' => 'Sì, abbiamo due gatto.', 'va' => null,
                        'pista' => 'Dos gatos: el plural de «gatto» es «gatti». Las -o pasan a -i.'],
                ],
            ],
            [
                'id' => 'tu',
                'dice' => 'Io ho un fratello, Luca. Ha dodici anni.',
                'clip' => 'it/u2/dialogo/tu',
                'respuestas' => [
                    ['texto' => 'E tu, quanti anni hai?', 'va' => 'fin_risposta'],
                    ['texto' => 'E tu, quanti anni sei?', 'va' => null,
                        'pista' => '«Sei» es ser. La edad se PREGUNTA también con avere: «Quanti anni hai?».'],
                ],
            ],
            [
                'id' => 'fin_risposta',
                'dice' => 'Ho quindici anni, come te! A domani, Ana.',
                'clip' => 'it/u2/dialogo/fin_risposta',
                'respuestas' => [
                    ['texto' => 'A domani, Marco!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Ciao!',
                'clip' => 'it/u2/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ IT U3 · la hora y la rutina: che ore sono, verbos en -are, a che ora ============
    [
        'lengua' => 'it',
        'unidad' => 3,
        'objective' => 'A1.IO.2',
        'slug' => 'che-ore-sono',
        'titulo' => 'Che ore sono?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marco: Ana, scusa, che ore sono?',
                'clip' => 'it/u3/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Sono le due e mezza.', 'va' => 'lezione'],
                    ['texto' => 'È le due e mezza.', 'va' => null,
                        'pista' => 'De las dos en adelante la hora es plural: «Sono le due». Solo «È l\'una» va en singular.'],
                ],
            ],
            [
                'id' => 'lezione',
                'dice' => 'Le due e mezza! A che ora inizia la lezione?',
                'clip' => 'it/u3/dialogo/lezione',
                'respuestas' => [
                    ['texto' => 'Inizia alle tre.', 'va' => 'sveglia'],
                    ['texto' => 'Inizia a le tre.', 'va' => null,
                        'pista' => '«A» + «le» se juntan: «alle tre».'],
                ],
            ],
            [
                'id' => 'sveglia',
                'dice' => 'Tu a che ora ti alzi la mattina?',
                'clip' => 'it/u3/dialogo/sveglia',
                'respuestas' => [
                    ['texto' => 'Mi alzo alle sei.', 'va' => 'colazione'],
                    ['texto' => 'Alzo alle sei.', 'va' => null,
                        'pista' => 'Es un verbo reflexivo: «MI alzo». Sin el «mi» no es levantarse.'],
                ],
            ],
            [
                'id' => 'colazione',
                'dice' => 'Presto! E cosa fai dopo?',
                'clip' => 'it/u3/dialogo/colazione',
                'respuestas' => [
                    ['texto' => 'Faccio colazione e vado a scuola.', 'va' => 'sera'],
                    ['texto' => 'Faccio colazione e vado a la scuola.', 'va' => null,
                        'pista' => 'Ir a la escuela es «vado a scuola», sin artículo. Como «a casa».'],
                ],
            ],
            [
                'id' => 'sera',
                'dice' => 'E la sera? Studi o guardi la TV?',
                'clip' => 'it/u3/dialogo/sera',
                'respuestas' => [
                    ['texto' => 'Studio un po\' e poi guardo la TV.', 'va' => 'fin_risposta'],
                    ['texto' => 'Studia un po\' e poi guarda la TV.', 'va' => null,
                        'pista' => 'Hablas de ti: la forma de «io» termina en -o: «studio», «guardo».'],
                ],
            ],
            [
                'id' => 'fin_risposta',
                'dice' => 'Anch\'io! Dai, sono le tre: andiamo a lezione.',
                'clip' => 'it/u3/dialogo/fin_risposta',
                'respuestas' => [
                    ['texto' => 'Andiamo!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Via!',
                'clip' => 'it/u3/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ IT U4 · gustos: mi piace / mi piacciono, anche a me, non ============
    [
        'lengua' => 'it',
        'unidad' => 4,
        'objective' => 'A1.IO.2',
        'slug' => 'ti-piace',
        'titulo' => 'Ti piace?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marco: Ana, ti piace la musica?',
                'clip' => 'it/u4/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Sì, mi piace molto.', 'va' => 'plurale'],
                    ['texto' => 'Sì, io piace molto.', 'va' => null,
                        'pista' => 'Lo que gusta es el sujeto: «MI piace» (a mí me gusta), no «io piace».'],
                ],
            ],
            [
                'id' => 'plurale',
                'dice' => 'Anche a me! E i film italiani, ti piacciono?',
                'clip' => 'it/u4/dialogo/plurale',
                'respuestas' => [
                    ['texto' => 'Sì, mi piacciono i film italiani.', 'va' => 'negativo'],
                    ['texto' => 'Sì, mi piace i film italiani.', 'va' => null,
                        'pista' => 'Los films son varios: «mi piacciono». «Mi piace» solo con singular.'],
                ],
            ],
            [
                'id' => 'negativo',
                'dice' => 'E il caffè? A me piace tantissimo.',
                'clip' => 'it/u4/dialogo/negativo',
                'respuestas' => [
                    ['texto' => 'A me non piace il caffè. Preferisco il tè.', 'va' => 'sport'],
                    ['texto' => 'A me piace non il caffè.', 'va' => null,
                        'pista' => 'El «non» va delante del verbo: «non piace».'],
                ],
            ],
            [
                'id' => 'sport',
                'dice' => 'Ti piace fare sport?',
                'clip' => 'it/u4/dialogo/sport',
                'respuestas' => [
                    ['texto' => 'Sì, mi piace giocare a calcio.', 'va' => 'fin_risposta'],
                    ['texto' => 'Sì, mi piacciono giocare a calcio.', 'va' => null,
                        'pista' => 'Con un verbo detrás (giocare) va «mi piace», en singular.'],
                ],
            ],
            [
                'id' => 'fin_risposta',
                'dice' => 'Anche a me piace il calcio! Domenica giochiamo insieme?',
                'clip' => 'it/u4/dialogo/fin_risposta',
                'respuestas' => [
                    ['texto' => 'Volentieri! A domenica.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'A domenica!',
                'clip' => 'it/u4/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ IT U5 · en la ciudad: dov'è, c'è, a destra / a sinistra, vicino / lontano ============
    [
        'lengua' => 'it',
        'unidad' => 5,
        'objective' => 'A1.IO.2',
        'slug' => 'dov-e-la-stazione',
        'titulo' => 'Scusi, dov\'è la stazione?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '(Un señor en la calle) Buongiorno.',
                'clip' => 'it/u5/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Buongiorno, scusi, dov\'è la stazione?', 'va' => 'dritto'],
                    ['texto' => 'Ciao, scusa, dov\'è la stazione?', 'va' => null,
                        'pista' => 'Es un adulto desconocido: «Buongiorno» y «scusi» (de usted), no «ciao» y «scusa».'],
                ],
            ],
            [
                'id' => 'dritto',
                'dice' => 'La stazione? Vada dritto e poi giri a destra.',
                'clip' => 'it/u5/dialogo/dritto',
                'respuestas' => [
                    ['texto' => 'È lontana?', 'va' => 'vicina'],
                    ['texto' => 'È lontano?', 'va' => null,
                        'pista' => 'La stazione es femenina: «È lontana?».'],
                ],
            ],
            [
                'id' => 'vicina',
                'dice' => 'No, è vicina, cinque minuti a piedi. C\'è una farmacia all\'angolo: la stazione è lì davanti.',
                'clip' => 'it/u5/dialogo/vicina',
                'respuestas' => [
                    ['texto' => 'Davanti alla farmacia. Perfetto.', 'va' => 'banca'],
                    ['texto' => 'Dietro la farmacia. Perfetto.', 'va' => null,
                        'pista' => 'Ha dicho «davanti» (delante), no «dietro» (detrás).'],
                ],
            ],
            [
                'id' => 'banca',
                'dice' => 'E vicino alla stazione ci sono anche una banca e un bar.',
                'clip' => 'it/u5/dialogo/banca',
                'respuestas' => [
                    ['texto' => 'Grazie mille, arrivederci!', 'va' => 'fin'],
                    ['texto' => 'Grazie mille, ciao!', 'va' => null,
                        'pista' => 'Con un adulto desconocido la despedida es «arrivederci».'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Prego, arrivederci!',
                'clip' => 'it/u5/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ IT U6 · al bar: vorrei, il partitivo, il conto ============
    [
        'lengua' => 'it',
        'unidad' => 6,
        'objective' => 'A1.IO.2',
        'slug' => 'al-bar',
        'titulo' => 'Al bar',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Cameriere: Buongiorno! Cosa prende?',
                'clip' => 'it/u6/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Buongiorno. Vorrei un cappuccino e un cornetto, per favore.', 'va' => 'acqua'],
                    ['texto' => 'Buongiorno. Voglio un cappuccino.', 'va' => null,
                        'pista' => '«Voglio» suena a orden. Para pedir con educación: «Vorrei…».'],
                ],
            ],
            [
                'id' => 'acqua',
                'dice' => 'Subito. Anche dell\'acqua?',
                'clip' => 'it/u6/dialogo/acqua',
                'respuestas' => [
                    ['texto' => 'Sì, un bicchiere d\'acqua naturale, grazie.', 'va' => 'panino'],
                    ['texto' => 'Sì, un bicchiere di acqua naturale, grazie.', 'va' => null,
                        'pista' => 'Delante de vocal «di» pierde la i: «d\'acqua».'],
                ],
            ],
            [
                'id' => 'panino',
                'dice' => 'Vuole anche qualcosa da mangiare? Abbiamo dei panini.',
                'clip' => 'it/u6/dialogo/panino',
                'respuestas' => [
                    ['texto' => 'No, grazie, il cornetto basta.', 'va' => 'conto'],
                    ['texto' => 'Sì, vorrei un panino con il prosciutto.', 'va' => 'conto'],
                    ['texto' => 'Sì, vorrei uno panino.', 'va' => null,
                        'pista' => '«Uno» solo va delante de s+consonante o z (uno studente). Aquí: «un panino».'],
                ],
            ],
            [
                'id' => 'conto',
                'dice' => '(Al terminar) Ecco. Tutto bene?',
                'clip' => 'it/u6/dialogo/conto',
                'respuestas' => [
                    ['texto' => 'Sì, buonissimo. Il conto, per favore. Quant\'è?', 'va' => 'prezzo'],
                    ['texto' => 'Sì, buonissimo. Quanto costa il conto?', 'va' => null,
                        'pista' => 'Para la cuenta se pregunta «Quant\'è?» (¿cuánto es?).'],
                ],
            ],
            [
                'id' => 'prezzo',
                'dice' => 'Sono quattro euro e cinquanta.',
                'clip' => 'it/u6/dialogo/prezzo',
                'respuestas' => [
                    ['texto' => 'Ecco a Lei. Grazie, arrivederci!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Grazie a Lei, arrivederci!',
                'clip' => 'it/u6/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ IT U7 · en la tienda: quanto costa, questo / quello, la concordancia, il tempo ============
    [
        'lengua' => 'it',
        'unidad' => 7,
        'objective' => 'A1.IO.2',
        'slug' => 'quanto-costa',
        'titulo' => 'Quanto costa?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Commessa: Buonasera! Posso aiutarla?',
                'clip' => 'it/u7/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Buonasera. Quanto costa questa maglietta?', 'va' => 'prezzo'],
                    ['texto' => 'Buonasera. Quanto costa questo maglietta?', 'va' => null,
                        'pista' => 'La maglietta es femenina: «questa maglietta».'],
                ],
            ],
            [
                'id' => 'prezzo',
                'dice' => 'Questa costa venticinque euro.',
                'clip' => 'it/u7/dialogo/prezzo',
                'respuestas' => [
                    ['texto' => 'È un po\' cara. E quella rossa?', 'va' => 'rossa'],
                    ['texto' => 'È un po\' caro. E quella rosso?', 'va' => null,
                        'pista' => 'Concordancia: la maglietta es «cara», y la de allí es «quella rossa».'],
                ],
            ],
            [
                'id' => 'rossa',
                'dice' => 'Quella rossa costa quindici euro. È in offerta.',
                'clip' => 'it/u7/dialogo/rossa',
                'respuestas' => [
                    ['texto' => 'Perfetto, prendo quella rossa. Avete anche dei pantaloni neri?', 'va' => 'pantaloni'],
                    ['texto' => 'Perfetto, prendo quella rossa. Avete anche dei pantaloni nero?', 'va' => null,
                        'pista' => '«Pantaloni» es plural masculino: «pantaloni neri».'],
                ],
            ],
            [
                'id' => 'pantaloni',
                'dice' => 'Sì, lì a destra. Fa freddo oggi, eh?',
                'clip' => 'it/u7/dialogo/pantaloni',
                'respuestas' => [
                    ['texto' => 'Sì, fa molto freddo e piove.', 'va' => 'fin_risposta'],
                    ['texto' => 'Sì, è molto freddo e piove.', 'va' => null,
                        'pista' => 'El tiempo se dice con «fare»: «fa freddo», «fa caldo».'],
                ],
            ],
            [
                'id' => 'fin_risposta',
                'dice' => 'Allora una maglietta e i pantaloni: trentacinque euro in tutto.',
                'clip' => 'it/u7/dialogo/fin_risposta',
                'respuestas' => [
                    ['texto' => 'Va bene. Grazie, arrivederci!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Arrivederci e buona serata!',
                'clip' => 'it/u7/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ IT U8 · ayer: passato prossimo con avere, il participio, non ho ============
    [
        'lengua' => 'it',
        'unidad' => 8,
        'objective' => 'A1.IO.2',
        'slug' => 'cosa-hai-fatto-ieri',
        'titulo' => 'Cosa hai fatto ieri?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marco: Ana, cosa hai fatto ieri?',
                'clip' => 'it/u8/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Ho studiato e ho guardato un film.', 'va' => 'film'],
                    ['texto' => 'Ho studiare e ho guardare un film.', 'va' => null,
                        'pista' => 'Con «ho» va el participio: studiATO, guardATO.'],
                    ['texto' => 'Studiato e guardato un film.', 'va' => null,
                        'pista' => 'Falta el auxiliar: «HO studiato», «HO guardato». Sin él no es pasado.'],
                ],
            ],
            [
                'id' => 'film',
                'dice' => 'Che film hai visto?',
                'clip' => 'it/u8/dialogo/film',
                'respuestas' => [
                    ['texto' => 'Ho visto un film italiano, molto bello.', 'va' => 'compiti'],
                    ['texto' => 'Ho veduto un film italiano.', 'va' => null,
                        'pista' => 'El participio de «vedere» es irregular: «visto».'],
                ],
            ],
            [
                'id' => 'compiti',
                'dice' => 'E i compiti? Li hai fatti?',
                'clip' => 'it/u8/dialogo/compiti',
                'respuestas' => [
                    ['texto' => 'No, non ho fatto i compiti. Ero stanca.', 'va' => 'io'],
                    ['texto' => 'No, ho non fatto i compiti.', 'va' => null,
                        'pista' => 'El «non» va delante del auxiliar: «non ho fatto».'],
                ],
            ],
            [
                'id' => 'io',
                'dice' => 'Neanche io! Ieri ho giocato a calcio tutto il pomeriggio.',
                'clip' => 'it/u8/dialogo/io',
                'respuestas' => [
                    ['texto' => 'Hai vinto?', 'va' => 'fin_risposta'],
                    ['texto' => 'Hai vincere?', 'va' => null,
                        'pista' => 'Participio: «vinto» (irregular). «Hai vinto?».'],
                ],
            ],
            [
                'id' => 'fin_risposta',
                'dice' => 'Sì, tre a uno! Facciamo i compiti insieme oggi?',
                'clip' => 'it/u8/dialogo/fin_risposta',
                'respuestas' => [
                    ['texto' => 'Sì, dai. Alle quattro a casa mia.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Perfetto, a dopo!',
                'clip' => 'it/u8/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ IT U9 · reparar la conversación: non ho capito, può ripetere, come si dice ============
    [
        'lengua' => 'it',
        'unidad' => 9,
        'objective' => 'A1.IO.1',
        'slug' => 'non-ho-capito',
        'titulo' => 'Non ho capito',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Prof.ssa Rossi: Ana, cosa hai fatto durante il fine settimana?',
                'clip' => 'it/u9/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Scusi, non ho capito. Può ripetere, per favore?', 'va' => 'lento'],
                    ['texto' => 'Sì, sì.', 'va' => null,
                        'pista' => 'No has entendido «fine settimana». Decir que sí no es hablar: pide que repita, «Può ripetere?».'],
                    ['texto' => 'Scusa, non ho capito. Puoi ripetere?', 'va' => null,
                        'pista' => 'Es la profesora: de usted. «Scusi» y «può ripetere».'],
                ],
            ],
            [
                'id' => 'lento',
                'dice' => 'Certo. Cosa — hai — fatto — nel — fine — settimana?',
                'clip' => 'it/u9/dialogo/lento',
                'respuestas' => [
                    ['texto' => 'Cosa significa «fine settimana»?', 'va' => 'significato'],
                    ['texto' => 'Più lentamente, per favore.', 'va' => null,
                        'pista' => 'Ya lo ha dicho despacio. Lo que no sabes es una palabra: «Cosa significa…?».'],
                ],
            ],
            [
                'id' => 'significato',
                'dice' => 'Il fine settimana è sabato e domenica.',
                'clip' => 'it/u9/dialogo/significato',
                'respuestas' => [
                    ['texto' => 'Ah! Sabato ho giocato a calcio e domenica ho visto un film.', 'va' => 'come_si_dice'],
                    ['texto' => 'Ah! Sabato gioco a calcio e domenica vedo un film.', 'va' => null,
                        'pista' => 'Fue el fin de semana pasado: «ho giocato», «ho visto».'],
                ],
            ],
            [
                'id' => 'come_si_dice',
                'dice' => 'Bene! E com\'era il film?',
                'clip' => 'it/u9/dialogo/come_si_dice',
                'respuestas' => [
                    ['texto' => 'Professoressa, come si dice «divertida» in italiano?', 'va' => 'divertente'],
                    ['texto' => 'Il film era molto divertida.', 'va' => null,
                        'pista' => 'Te falta la palabra: pregúntala en italiano en vez de meter el español. «Come si dice … in italiano?».'],
                ],
            ],
            [
                'id' => 'divertente',
                'dice' => 'Si dice «divertente».',
                'clip' => 'it/u9/dialogo/divertente',
                'respuestas' => [
                    ['texto' => 'Divertente! Il film era molto divertente. Grazie!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Bravissima, Ana. Arrivederci!',
                'clip' => 'it/u9/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],


    // ============ FR U2 · la familia: avoir, mon / ma, el plural ============
    [
        'lengua' => 'fr',
        'unidad' => 2,
        'objective' => 'A1.IO.3',
        'slug' => 'ma-famille',
        'titulo' => 'Ma famille',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marc : Salut, Ana ! Tu as des frères et sœurs ?',
                'clip' => 'fr/u2/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Oui, j\'ai une sœur.', 'va' => 'age'],
                    ['texto' => 'Non, je suis fille unique.', 'va' => 'parents'],
                    ['texto' => 'Oui, je suis une sœur.', 'va' => null,
                        'pista' => 'Tener hermanos es «avoir»: «J\'ai une sœur». «Je suis» es ser.'],
                ],
            ],
            [
                'id' => 'age',
                'dice' => 'Elle a quel âge, ta sœur ?',
                'clip' => 'fr/u2/dialogo/age',
                'respuestas' => [
                    ['texto' => 'Elle a dix-huit ans.', 'va' => 'parents'],
                    ['texto' => 'Elle est dix-huit ans.', 'va' => null,
                        'pista' => 'La edad va con «avoir»: «Elle a dix-huit ans».'],
                ],
            ],
            [
                'id' => 'parents',
                'dice' => 'Et tes parents, ils s\'appellent comment ?',
                'clip' => 'fr/u2/dialogo/parents',
                'respuestas' => [
                    ['texto' => 'Mon père s\'appelle Luis et ma mère s\'appelle Rosa.', 'va' => 'animaux'],
                    ['texto' => 'Ma père s\'appelle Luis et mon mère s\'appelle Rosa.', 'va' => null,
                        'pista' => 'El posesivo concuerda con la persona: «mon père» (masculino), «ma mère» (femenino).'],
                ],
            ],
            [
                'id' => 'animaux',
                'dice' => 'Vous avez des animaux ?',
                'clip' => 'fr/u2/dialogo/animaux',
                'respuestas' => [
                    ['texto' => 'Oui, nous avons deux chats.', 'va' => 'toi'],
                    ['texto' => 'Oui, nous avons deux chat.', 'va' => null,
                        'pista' => 'Plural: «deux chats». La -s no se oye, pero se escribe.'],
                ],
            ],
            [
                'id' => 'toi',
                'dice' => 'Moi, j\'ai un frère, Lucas. Il a douze ans.',
                'clip' => 'fr/u2/dialogo/toi',
                'respuestas' => [
                    ['texto' => 'Et toi, tu as quel âge ?', 'va' => 'fin_reponse'],
                    ['texto' => 'Et toi, tu es quel âge ?', 'va' => null,
                        'pista' => 'La edad se pregunta con avoir: «Tu as quel âge ?».'],
                ],
            ],
            [
                'id' => 'fin_reponse',
                'dice' => 'J\'ai quinze ans, comme toi ! À demain, Ana.',
                'clip' => 'fr/u2/dialogo/fin_reponse',
                'respuestas' => [
                    ['texto' => 'À demain, Marc !', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Salut !',
                'clip' => 'fr/u2/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ FR U3 · la hora y la rutina: quelle heure est-il, verbos en -er, à quelle heure ============
    [
        'lengua' => 'fr',
        'unidad' => 3,
        'objective' => 'A1.IO.2',
        'slug' => 'quelle-heure-est-il',
        'titulo' => 'Quelle heure est-il ?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marc : Ana, quelle heure est-il ?',
                'clip' => 'fr/u3/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Il est deux heures et demie.', 'va' => 'cours'],
                    ['texto' => 'Sont deux heures et demie.', 'va' => null,
                        'pista' => 'La hora siempre con «il est»: «Il est deux heures». El pronombre no se calla.'],
                ],
            ],
            [
                'id' => 'cours',
                'dice' => 'Déjà ! Le cours commence à quelle heure ?',
                'clip' => 'fr/u3/dialogo/cours',
                'respuestas' => [
                    ['texto' => 'Il commence à trois heures.', 'va' => 'lever'],
                    ['texto' => 'Commence à trois heures.', 'va' => null,
                        'pista' => 'Falta el sujeto: «IL commence». En francés nunca se calla.'],
                ],
            ],
            [
                'id' => 'lever',
                'dice' => 'Tu te lèves à quelle heure, le matin ?',
                'clip' => 'fr/u3/dialogo/lever',
                'respuestas' => [
                    ['texto' => 'Je me lève à six heures.', 'va' => 'petit_dej'],
                    ['texto' => 'Je lève à six heures.', 'va' => null,
                        'pista' => 'Levantarse es reflexivo: «je ME lève».'],
                ],
            ],
            [
                'id' => 'petit_dej',
                'dice' => 'Tôt ! Et après ?',
                'clip' => 'fr/u3/dialogo/petit_dej',
                'respuestas' => [
                    ['texto' => 'Je prends le petit-déjeuner et je vais à l\'école.', 'va' => 'soir'],
                    ['texto' => 'Je prends le petit-déjeuner et je vais à école.', 'va' => null,
                        'pista' => '«À» + «l\'école»: «je vais à l\'école», con el artículo.'],
                ],
            ],
            [
                'id' => 'soir',
                'dice' => 'Et le soir, tu étudies ou tu regardes la télé ?',
                'clip' => 'fr/u3/dialogo/soir',
                'respuestas' => [
                    ['texto' => 'J\'étudie un peu et je regarde la télé.', 'va' => 'fin_reponse'],
                    ['texto' => 'J\'étudier un peu et je regarder la télé.', 'va' => null,
                        'pista' => 'Con «je» el verbo se conjuga: «j\'étudie», «je regarde». La forma en -er es el infinitivo.'],
                ],
            ],
            [
                'id' => 'fin_reponse',
                'dice' => 'Moi aussi ! Allez, il est trois heures : on va en cours.',
                'clip' => 'fr/u3/dialogo/fin_reponse',
                'respuestas' => [
                    ['texto' => 'On y va !', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Allez !',
                'clip' => 'fr/u3/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ FR U4 · gustos: aimer + article, moi aussi, ne… pas ============
    [
        'lengua' => 'fr',
        'unidad' => 4,
        'objective' => 'A1.IO.2',
        'slug' => 'tu-aimes',
        'titulo' => 'Tu aimes ?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marc : Ana, tu aimes la musique ?',
                'clip' => 'fr/u4/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Oui, j\'aime beaucoup la musique.', 'va' => 'films'],
                    ['texto' => 'Oui, j\'aime beaucoup musique.', 'va' => null,
                        'pista' => 'Con «aimer» la cosa lleva SIEMPRE artículo: «j\'aime LA musique».'],
                ],
            ],
            [
                'id' => 'films',
                'dice' => 'Moi aussi ! Et les films français ?',
                'clip' => 'fr/u4/dialogo/films',
                'respuestas' => [
                    ['texto' => 'J\'adore les films français.', 'va' => 'cafe'],
                    ['texto' => 'J\'adore des films français.', 'va' => null,
                        'pista' => 'Con aimer / adorer va el artículo definido: «LES films», no «des».'],
                ],
            ],
            [
                'id' => 'cafe',
                'dice' => 'Et le café ? Moi, j\'adore le café.',
                'clip' => 'fr/u4/dialogo/cafe',
                'respuestas' => [
                    ['texto' => 'Je n\'aime pas le café. Je préfère le thé.', 'va' => 'sport'],
                    ['texto' => 'Je pas aime le café.', 'va' => null,
                        'pista' => 'La negación tiene dos piezas alrededor del verbo: «je N\'aime PAS».'],
                ],
            ],
            [
                'id' => 'sport',
                'dice' => 'Tu aimes faire du sport ?',
                'clip' => 'fr/u4/dialogo/sport',
                'respuestas' => [
                    ['texto' => 'Oui, j\'aime jouer au foot.', 'va' => 'fin_reponse'],
                    ['texto' => 'Oui, j\'aime jouer le foot.', 'va' => null,
                        'pista' => 'Jugar a un deporte: «jouer AU foot» (à + le).'],
                ],
            ],
            [
                'id' => 'fin_reponse',
                'dice' => 'Moi aussi, j\'aime le foot ! On joue dimanche ?',
                'clip' => 'fr/u4/dialogo/fin_reponse',
                'respuestas' => [
                    ['texto' => 'Avec plaisir ! À dimanche.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'À dimanche !',
                'clip' => 'fr/u4/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ FR U5 · en la ciudad: où est, il y a, à droite / à gauche, vous ============
    [
        'lengua' => 'fr',
        'unidad' => 5,
        'objective' => 'A1.IO.2',
        'slug' => 'ou-est-la-gare',
        'titulo' => 'Pardon, où est la gare ?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '(Un señor en la calle) Bonjour.',
                'clip' => 'fr/u5/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Bonjour, pardon, où est la gare, s\'il vous plaît ?', 'va' => 'tout_droit'],
                    ['texto' => 'Salut, où est la gare, s\'il te plaît ?', 'va' => null,
                        'pista' => 'Es un adulto desconocido: «Bonjour», «pardon» y «s\'il VOUS plaît».'],
                ],
            ],
            [
                'id' => 'tout_droit',
                'dice' => 'La gare ? Vous allez tout droit, puis vous tournez à droite.',
                'clip' => 'fr/u5/dialogo/tout_droit',
                'respuestas' => [
                    ['texto' => 'C\'est loin ?', 'va' => 'pres'],
                    ['texto' => 'Est loin ?', 'va' => null,
                        'pista' => 'Falta el sujeto: «C\'est loin ?».'],
                ],
            ],
            [
                'id' => 'pres',
                'dice' => 'Non, c\'est à cinq minutes à pied. Il y a une pharmacie au coin : la gare est juste en face.',
                'clip' => 'fr/u5/dialogo/pres',
                'respuestas' => [
                    ['texto' => 'En face de la pharmacie. D\'accord.', 'va' => 'banque'],
                    ['texto' => 'Derrière la pharmacie. D\'accord.', 'va' => null,
                        'pista' => 'Ha dicho «en face» (enfrente), no «derrière» (detrás).'],
                ],
            ],
            [
                'id' => 'banque',
                'dice' => 'Et à côté de la gare, il y a aussi une banque et un café.',
                'clip' => 'fr/u5/dialogo/banque',
                'respuestas' => [
                    ['texto' => 'Merci beaucoup, monsieur. Au revoir !', 'va' => 'fin'],
                    ['texto' => 'Merci, salut !', 'va' => null,
                        'pista' => 'Con un adulto desconocido: «Au revoir», nunca «salut».'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'De rien. Au revoir !',
                'clip' => 'fr/u5/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ FR U6 · au café: je voudrais, du / de la, l'addition ============
    [
        'lengua' => 'fr',
        'unidad' => 6,
        'objective' => 'A1.IO.2',
        'slug' => 'au-cafe',
        'titulo' => 'Au café',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Serveur : Bonjour ! Vous désirez ?',
                'clip' => 'fr/u6/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Bonjour. Je voudrais un chocolat chaud et un croissant, s\'il vous plaît.', 'va' => 'eau'],
                    ['texto' => 'Bonjour. Je veux un chocolat chaud.', 'va' => null,
                        'pista' => '«Je veux» suena a orden. Para pedir con educación: «Je voudrais…».'],
                ],
            ],
            [
                'id' => 'eau',
                'dice' => 'Très bien. Et de l\'eau ?',
                'clip' => 'fr/u6/dialogo/eau',
                'respuestas' => [
                    ['texto' => 'Oui, un verre d\'eau, merci.', 'va' => 'sandwich'],
                    ['texto' => 'Oui, un verre de eau, merci.', 'va' => null,
                        'pista' => 'Delante de vocal, «de» pierde la e: «un verre d\'eau».'],
                ],
            ],
            [
                'id' => 'sandwich',
                'dice' => 'Vous voulez manger quelque chose ? Nous avons des sandwichs.',
                'clip' => 'fr/u6/dialogo/sandwich',
                'respuestas' => [
                    ['texto' => 'Non merci, le croissant suffit.', 'va' => 'addition'],
                    ['texto' => 'Oui, je voudrais un sandwich au fromage.', 'va' => 'addition'],
                    ['texto' => 'Oui, je voudrais du sandwich.', 'va' => null,
                        'pista' => 'El partitivo «du» es para lo que no se cuenta (du pain, du fromage). Un sándwich se cuenta: «UN sandwich».'],
                ],
            ],
            [
                'id' => 'addition',
                'dice' => '(Al terminar) Voilà. Ça a été ?',
                'clip' => 'fr/u6/dialogo/addition',
                'respuestas' => [
                    ['texto' => 'Oui, très bon. L\'addition, s\'il vous plaît. Ça fait combien ?', 'va' => 'prix'],
                    ['texto' => 'Oui, très bon. Combien coûte l\'addition ?', 'va' => null,
                        'pista' => 'Para la cuenta se pregunta «Ça fait combien ?».'],
                ],
            ],
            [
                'id' => 'prix',
                'dice' => 'Ça fait cinq euros cinquante.',
                'clip' => 'fr/u6/dialogo/prix',
                'respuestas' => [
                    ['texto' => 'Voilà. Merci, au revoir !', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Merci à vous, bonne journée !',
                'clip' => 'fr/u6/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ FR U7 · en la tienda: ça coûte combien, ce / cette, la concordancia, il fait ============
    [
        'lengua' => 'fr',
        'unidad' => 7,
        'objective' => 'A1.IO.2',
        'slug' => 'ca-coute-combien',
        'titulo' => 'Ça coûte combien ?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Vendeuse : Bonjour ! Je peux vous aider ?',
                'clip' => 'fr/u7/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Bonjour. Ce tee-shirt coûte combien ?', 'va' => 'prix'],
                    ['texto' => 'Bonjour. Cette tee-shirt coûte combien ?', 'va' => null,
                        'pista' => '«Tee-shirt» es masculino: «CE tee-shirt». «Cette» es para femenino (cette veste).'],
                ],
            ],
            [
                'id' => 'prix',
                'dice' => 'Celui-ci coûte vingt-cinq euros.',
                'clip' => 'fr/u7/dialogo/prix',
                'respuestas' => [
                    ['texto' => 'C\'est un peu cher. Et la veste rouge ?', 'va' => 'veste'],
                    ['texto' => 'C\'est un peu cher. Et la veste rouge, il coûte combien ?', 'va' => null,
                        'pista' => 'La veste es femenina: «ELLE coûte combien ?».'],
                ],
            ],
            [
                'id' => 'veste',
                'dice' => 'La veste rouge coûte quarante euros. Elle est en promotion.',
                'clip' => 'fr/u7/dialogo/veste',
                'respuestas' => [
                    ['texto' => 'Parfait, je prends la veste. Vous avez aussi des pantalons noirs ?', 'va' => 'temps'],
                    ['texto' => 'Parfait, je prends la veste. Vous avez aussi des pantalons noir ?', 'va' => null,
                        'pista' => 'Plural: «des pantalons noirS». El adjetivo concuerda aunque no se oiga.'],
                ],
            ],
            [
                'id' => 'temps',
                'dice' => 'Oui, là, à droite. Il fait froid aujourd\'hui, non ?',
                'clip' => 'fr/u7/dialogo/temps',
                'respuestas' => [
                    ['texto' => 'Oui, il fait très froid et il pleut.', 'va' => 'fin_reponse'],
                    ['texto' => 'Oui, c\'est très froid et il pleut.', 'va' => null,
                        'pista' => 'El tiempo se dice con «il fait»: «il fait froid», «il fait chaud».'],
                ],
            ],
            [
                'id' => 'fin_reponse',
                'dice' => 'Alors la veste et le pantalon : soixante euros en tout.',
                'clip' => 'fr/u7/dialogo/fin_reponse',
                'respuestas' => [
                    ['texto' => 'D\'accord. Merci, au revoir !', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Au revoir, bonne journée !',
                'clip' => 'fr/u7/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ FR U8 · hier: passé composé avec avoir, le participe, ne… pas ============
    [
        'lengua' => 'fr',
        'unidad' => 8,
        'objective' => 'A1.IO.2',
        'slug' => 'qu-est-ce-que-tu-as-fait-hier',
        'titulo' => 'Qu\'est-ce que tu as fait hier ?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marc : Ana, qu\'est-ce que tu as fait hier ?',
                'clip' => 'fr/u8/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'J\'ai étudié et j\'ai regardé un film.', 'va' => 'film'],
                    ['texto' => 'J\'ai étudier et j\'ai regarder un film.', 'va' => null,
                        'pista' => 'Con «j\'ai» va el participio: étudiÉ, regardÉ. Se oye igual, se escribe -é.'],
                    ['texto' => 'Étudié et regardé un film.', 'va' => null,
                        'pista' => 'Falta el auxiliar: «J\'AI étudié». Sin él no es pasado.'],
                ],
            ],
            [
                'id' => 'film',
                'dice' => 'Quel film tu as vu ?',
                'clip' => 'fr/u8/dialogo/film',
                'respuestas' => [
                    ['texto' => 'J\'ai vu un film français, très bien.', 'va' => 'devoirs'],
                    ['texto' => 'J\'ai voir un film français.', 'va' => null,
                        'pista' => 'El participio de «voir» es «vu»: «j\'ai vu».'],
                ],
            ],
            [
                'id' => 'devoirs',
                'dice' => 'Et les devoirs ? Tu les as faits ?',
                'clip' => 'fr/u8/dialogo/devoirs',
                'respuestas' => [
                    ['texto' => 'Non, je n\'ai pas fait les devoirs. J\'étais fatiguée.', 'va' => 'moi'],
                    ['texto' => 'Non, je n\'ai fait pas les devoirs.', 'va' => null,
                        'pista' => 'El «pas» va justo detrás del auxiliar: «je n\'ai PAS fait».'],
                ],
            ],
            [
                'id' => 'moi',
                'dice' => 'Moi non plus ! Hier, j\'ai joué au foot tout l\'après-midi.',
                'clip' => 'fr/u8/dialogo/moi',
                'respuestas' => [
                    ['texto' => 'Tu as gagné ?', 'va' => 'fin_reponse'],
                    ['texto' => 'Tu as gagner ?', 'va' => null,
                        'pista' => 'Participio: «gagné». «Tu as gagné ?».'],
                ],
            ],
            [
                'id' => 'fin_reponse',
                'dice' => 'Oui, trois à un ! On fait les devoirs ensemble aujourd\'hui ?',
                'clip' => 'fr/u8/dialogo/fin_reponse',
                'respuestas' => [
                    ['texto' => 'Oui, d\'accord. À quatre heures chez moi.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Parfait, à tout à l\'heure !',
                'clip' => 'fr/u8/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ FR U9 · reparar la conversación: je n'ai pas compris, vous pouvez répéter, comment on dit ============
    [
        'lengua' => 'fr',
        'unidad' => 9,
        'objective' => 'A1.IO.1',
        'slug' => 'je-n-ai-pas-compris',
        'titulo' => 'Je n\'ai pas compris',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Mme Durand : Ana, qu\'est-ce que tu as fait ce week-end ?',
                'clip' => 'fr/u9/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Pardon, madame, je n\'ai pas compris. Vous pouvez répéter, s\'il vous plaît ?', 'va' => 'lent'],
                    ['texto' => 'Oui, oui.', 'va' => null,
                        'pista' => 'No has entendido «week-end». Decir que sí no es hablar: pide que repita, «Vous pouvez répéter ?».'],
                    ['texto' => 'Quoi ? Tu peux répéter ?', 'va' => null,
                        'pista' => 'Es la profesora: de usted. «Vous pouvez répéter, s\'il vous plaît ?».'],
                ],
            ],
            [
                'id' => 'lent',
                'dice' => 'Bien sûr. Qu\'est-ce que — tu as fait — ce — week-end ?',
                'clip' => 'fr/u9/dialogo/lent',
                'respuestas' => [
                    ['texto' => 'Qu\'est-ce que ça veut dire, « week-end » ?', 'va' => 'sens'],
                    ['texto' => 'Plus lentement, s\'il vous plaît.', 'va' => null,
                        'pista' => 'Ya lo ha dicho despacio. Lo que no sabes es una palabra: «Qu\'est-ce que ça veut dire… ?».'],
                ],
            ],
            [
                'id' => 'sens',
                'dice' => 'Le week-end, c\'est samedi et dimanche.',
                'clip' => 'fr/u9/dialogo/sens',
                'respuestas' => [
                    ['texto' => 'Ah ! Samedi j\'ai joué au foot et dimanche j\'ai vu un film.', 'va' => 'comment_on_dit'],
                    ['texto' => 'Ah ! Samedi je joue au foot et dimanche je vois un film.', 'va' => null,
                        'pista' => 'Fue el fin de semana pasado: «j\'ai joué», «j\'ai vu».'],
                ],
            ],
            [
                'id' => 'comment_on_dit',
                'dice' => 'Très bien ! Et le film, il était comment ?',
                'clip' => 'fr/u9/dialogo/comment_on_dit',
                'respuestas' => [
                    ['texto' => 'Madame, comment on dit « divertida » en français ?', 'va' => 'drole'],
                    ['texto' => 'Le film était très divertida.', 'va' => null,
                        'pista' => 'Te falta la palabra: pregúntala en francés en vez de meter el español. «Comment on dit … en français ?».'],
                ],
            ],
            [
                'id' => 'drole',
                'dice' => 'On dit « drôle ».',
                'clip' => 'fr/u9/dialogo/drole',
                'respuestas' => [
                    ['texto' => 'Drôle ! Le film était très drôle. Merci, madame !', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Bravo, Ana. Au revoir !',
                'clip' => 'fr/u9/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],


    // ============ DE U2 · la familia: haben, los tres géneros, la edad con sein ============
    [
        'lengua' => 'de',
        'unidad' => 2,
        'objective' => 'A1.IO.3',
        'slug' => 'meine-familie',
        'titulo' => 'Meine Familie',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marco: Hallo, Ana! Hast du Geschwister?',
                'clip' => 'de/u2/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Ja, ich habe eine Schwester.', 'va' => 'alter'],
                    ['texto' => 'Nein, ich habe keine Geschwister.', 'va' => 'eltern'],
                    ['texto' => 'Ja, ich bin eine Schwester.', 'va' => null,
                        'pista' => 'Tener hermanos es «haben»: «Ich habe eine Schwester». «Ich bin» es ser.'],
                ],
            ],
            [
                'id' => 'alter',
                'dice' => 'Wie alt ist deine Schwester?',
                'clip' => 'de/u2/dialogo/alter',
                'respuestas' => [
                    ['texto' => 'Sie ist achtzehn.', 'va' => 'eltern'],
                    ['texto' => 'Sie hat achtzehn.', 'va' => null,
                        'pista' => 'En alemán la edad va con SEIN: «Sie ist achtzehn». Al revés que en español.'],
                ],
            ],
            [
                'id' => 'eltern',
                'dice' => 'Und deine Eltern? Wie heißen sie?',
                'clip' => 'de/u2/dialogo/eltern',
                'respuestas' => [
                    ['texto' => 'Mein Vater heißt Luis und meine Mutter heißt Rosa.', 'va' => 'tiere'],
                    ['texto' => 'Meine Vater heißt Luis und mein Mutter heißt Rosa.', 'va' => null,
                        'pista' => 'El posesivo sigue el género: «mein Vater» (der), «meine Mutter» (die).'],
                ],
            ],
            [
                'id' => 'tiere',
                'dice' => 'Habt ihr Haustiere?',
                'clip' => 'de/u2/dialogo/tiere',
                'respuestas' => [
                    ['texto' => 'Ja, wir haben zwei Katzen.', 'va' => 'du'],
                    ['texto' => 'Ja, wir haben zwei Katze.', 'va' => null,
                        'pista' => 'Dos gatos: el plural es «Katzen». Aprende cada palabra con su plural.'],
                ],
            ],
            [
                'id' => 'du',
                'dice' => 'Ich habe einen Bruder, Lukas. Er ist zwölf.',
                'clip' => 'de/u2/dialogo/du',
                'respuestas' => [
                    ['texto' => 'Und du, wie alt bist du?', 'va' => 'fin_antwort'],
                    ['texto' => 'Und du, wie alt hast du?', 'va' => null,
                        'pista' => 'La edad se pregunta con sein: «Wie alt BIST du?».'],
                ],
            ],
            [
                'id' => 'fin_antwort',
                'dice' => 'Ich bin fünfzehn, wie du! Bis morgen, Ana.',
                'clip' => 'de/u2/dialogo/fin_antwort',
                'respuestas' => [
                    ['texto' => 'Bis morgen, Marco!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Tschüss!',
                'clip' => 'de/u2/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ DE U3 · la hora y la rutina: wie spät, halb, el verbo segundo con «um» ============
    [
        'lengua' => 'de',
        'unidad' => 3,
        'objective' => 'A1.IO.2',
        'slug' => 'wie-spaet-ist-es',
        'titulo' => 'Wie spät ist es?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marco: Ana, wie spät ist es?',
                'clip' => 'de/u3/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Es ist halb drei.', 'va' => 'unterricht'],
                    ['texto' => 'Es ist halb zwei.', 'va' => null,
                        'pista' => 'Son las dos y media. «Halb» mira a la hora SIGUIENTE: las dos y media es «halb drei» (media hacia las tres).'],
                ],
            ],
            [
                'id' => 'unterricht',
                'dice' => 'Schon halb drei! Wann beginnt der Unterricht?',
                'clip' => 'de/u3/dialogo/unterricht',
                'respuestas' => [
                    ['texto' => 'Der Unterricht beginnt um drei.', 'va' => 'aufstehen'],
                    ['texto' => 'Der Unterricht um drei beginnt.', 'va' => null,
                        'pista' => 'El verbo va en SEGUNDA posición: «Der Unterricht beginnt um drei».'],
                ],
            ],
            [
                'id' => 'aufstehen',
                'dice' => 'Wann stehst du morgens auf?',
                'clip' => 'de/u3/dialogo/aufstehen',
                'respuestas' => [
                    ['texto' => 'Ich stehe um sechs auf.', 'va' => 'fruehstueck'],
                    ['texto' => 'Ich aufstehe um sechs.', 'va' => null,
                        'pista' => '«Aufstehen» se parte: el verbo en segunda posición y «auf» al FINAL: «Ich stehe um sechs auf».'],
                ],
            ],
            [
                'id' => 'fruehstueck',
                'dice' => 'Früh! Und dann?',
                'clip' => 'de/u3/dialogo/fruehstueck',
                'respuestas' => [
                    ['texto' => 'Dann frühstücke ich und gehe zur Schule.', 'va' => 'abend'],
                    ['texto' => 'Dann ich frühstücke und gehe zur Schule.', 'va' => null,
                        'pista' => 'Si la frase empieza por «dann», el verbo sigue siendo lo segundo: «Dann FRÜHSTÜCKE ich».'],
                ],
            ],
            [
                'id' => 'abend',
                'dice' => 'Und am Abend? Lernst du oder siehst du fern?',
                'clip' => 'de/u3/dialogo/abend',
                'respuestas' => [
                    ['texto' => 'Ich lerne ein bisschen und dann sehe ich fern.', 'va' => 'fin_antwort'],
                    ['texto' => 'Ich lerne ein bisschen und dann ich sehe fern.', 'va' => null,
                        'pista' => 'Después de «dann», el verbo primero y el sujeto detrás: «dann sehe ich fern».'],
                ],
            ],
            [
                'id' => 'fin_antwort',
                'dice' => 'Ich auch! Komm, es ist drei Uhr: wir gehen in den Unterricht.',
                'clip' => 'de/u3/dialogo/fin_antwort',
                'respuestas' => [
                    ['texto' => 'Los geht\'s!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Los!',
                'clip' => 'de/u3/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ DE U4 · gustos: mögen, gern, ich auch, nicht ============
    [
        'lengua' => 'de',
        'unidad' => 4,
        'objective' => 'A1.IO.2',
        'slug' => 'magst-du',
        'titulo' => 'Magst du…?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marco: Ana, magst du Musik?',
                'clip' => 'de/u4/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Ja, ich mag Musik sehr.', 'va' => 'filme'],
                    ['texto' => 'Ja, ich möge Musik sehr.', 'va' => null,
                        'pista' => '«Mögen» es irregular: «ich mag», «du magst».'],
                ],
            ],
            [
                'id' => 'filme',
                'dice' => 'Ich auch! Siehst du gern Filme?',
                'clip' => 'de/u4/dialogo/filme',
                'respuestas' => [
                    ['texto' => 'Ja, ich sehe gern deutsche Filme.', 'va' => 'kaffee'],
                    ['texto' => 'Ja, ich gern sehe deutsche Filme.', 'va' => null,
                        'pista' => '«Gern» va DETRÁS del verbo, nunca delante: «ich sehe gern».'],
                ],
            ],
            [
                'id' => 'kaffee',
                'dice' => 'Und Kaffee? Ich trinke sehr gern Kaffee.',
                'clip' => 'de/u4/dialogo/kaffee',
                'respuestas' => [
                    ['texto' => 'Ich trinke nicht gern Kaffee. Ich trinke lieber Tee.', 'va' => 'sport'],
                    ['texto' => 'Ich nicht trinke gern Kaffee.', 'va' => null,
                        'pista' => '«Nicht» va detrás del verbo: «ich trinke nicht gern». El verbo sigue segundo.'],
                ],
            ],
            [
                'id' => 'sport',
                'dice' => 'Machst du gern Sport?',
                'clip' => 'de/u4/dialogo/sport',
                'respuestas' => [
                    ['texto' => 'Ja, ich spiele gern Fußball.', 'va' => 'fin_antwort'],
                    ['texto' => 'Ja, ich mag spielen Fußball.', 'va' => null,
                        'pista' => 'Para «me gusta hacer algo» se usa el verbo + «gern»: «ich spiele gern Fußball».'],
                ],
            ],
            [
                'id' => 'fin_antwort',
                'dice' => 'Ich auch! Spielen wir am Sonntag zusammen?',
                'clip' => 'de/u4/dialogo/fin_antwort',
                'respuestas' => [
                    ['texto' => 'Gern! Bis Sonntag.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Bis Sonntag!',
                'clip' => 'de/u4/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ DE U5 · en la ciudad: wo ist, es gibt + acusativo, rechts / links, Sie ============
    [
        'lengua' => 'de',
        'unidad' => 5,
        'objective' => 'A1.IO.2',
        'slug' => 'wo-ist-der-bahnhof',
        'titulo' => 'Entschuldigung, wo ist der Bahnhof?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '(Un señor en la calle) Guten Tag.',
                'clip' => 'de/u5/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Guten Tag. Entschuldigung, wo ist der Bahnhof?', 'va' => 'geradeaus'],
                    ['texto' => 'Hallo! Wo ist der Bahnhof?', 'va' => null,
                        'pista' => 'Es un adulto desconocido: «Guten Tag» y «Entschuldigung» antes de preguntar.'],
                ],
            ],
            [
                'id' => 'geradeaus',
                'dice' => 'Der Bahnhof? Gehen Sie geradeaus und dann rechts.',
                'clip' => 'de/u5/dialogo/geradeaus',
                'respuestas' => [
                    ['texto' => 'Ist das weit?', 'va' => 'nah'],
                    ['texto' => 'Das weit ist?', 'va' => null,
                        'pista' => 'Pregunta de sí / no: el verbo va PRIMERO. «Ist das weit?».'],
                ],
            ],
            [
                'id' => 'nah',
                'dice' => 'Nein, fünf Minuten zu Fuß. Es gibt eine Apotheke an der Ecke: der Bahnhof ist gegenüber.',
                'clip' => 'de/u5/dialogo/nah',
                'respuestas' => [
                    ['texto' => 'Gegenüber der Apotheke. Gut.', 'va' => 'bank'],
                    ['texto' => 'Hinter der Apotheke. Gut.', 'va' => null,
                        'pista' => 'Ha dicho «gegenüber» (enfrente), no «hinter» (detrás).'],
                ],
            ],
            [
                'id' => 'bank',
                'dice' => 'Und neben dem Bahnhof gibt es auch eine Bank und ein Café.',
                'clip' => 'de/u5/dialogo/bank',
                'respuestas' => [
                    ['texto' => 'Gibt es dort auch einen Supermarkt?', 'va' => 'supermarkt'],
                    ['texto' => 'Gibt es dort auch ein Supermarkt?', 'va' => null,
                        'pista' => '«Es gibt» lleva ACUSATIVO, y el masculino cambia: «einen Supermarkt».'],
                ],
            ],
            [
                'id' => 'supermarkt',
                'dice' => 'Ja, links vom Bahnhof.',
                'clip' => 'de/u5/dialogo/supermarkt',
                'respuestas' => [
                    ['texto' => 'Vielen Dank! Auf Wiedersehen!', 'va' => 'fin'],
                    ['texto' => 'Danke, tschüss!', 'va' => null,
                        'pista' => 'Con un adulto desconocido: «Auf Wiedersehen».'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Bitte, auf Wiedersehen!',
                'clip' => 'de/u5/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ DE U6 · im Café: ich möchte + acusativo, die Rechnung ============
    [
        'lengua' => 'de',
        'unidad' => 6,
        'objective' => 'A1.IO.2',
        'slug' => 'im-cafe',
        'titulo' => 'Im Café',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Kellner: Guten Tag! Was möchten Sie?',
                'clip' => 'de/u6/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Guten Tag. Ich möchte einen Kakao und ein Croissant, bitte.', 'va' => 'wasser'],
                    ['texto' => 'Guten Tag. Ich will einen Kakao.', 'va' => null,
                        'pista' => '«Ich will» suena a orden de niño. Para pedir: «Ich möchte…».'],
                    ['texto' => 'Guten Tag. Ich möchte ein Kakao und ein Croissant, bitte.', 'va' => null,
                        'pista' => 'Der Kakao es masculino, y lo que pides va en acusativo: «EINEN Kakao». «Ein Croissant» (das) sí queda igual.'],
                ],
            ],
            [
                'id' => 'wasser',
                'dice' => 'Gern. Auch ein Wasser?',
                'clip' => 'de/u6/dialogo/wasser',
                'respuestas' => [
                    ['texto' => 'Ja, ein Glas Wasser ohne Kohlensäure, bitte.', 'va' => 'brot'],
                    ['texto' => 'Ja, ein Glas von Wasser, bitte.', 'va' => null,
                        'pista' => 'En alemán la cantidad va pegada, sin «von»: «ein Glas Wasser».'],
                ],
            ],
            [
                'id' => 'brot',
                'dice' => 'Möchten Sie auch etwas essen? Wir haben belegte Brote.',
                'clip' => 'de/u6/dialogo/brot',
                'respuestas' => [
                    ['texto' => 'Nein, danke, das Croissant reicht.', 'va' => 'rechnung'],
                    ['texto' => 'Ja, ich möchte ein Brot mit Käse.', 'va' => 'rechnung'],
                    ['texto' => 'Ja, ich möchte einen Brot mit Käse.', 'va' => null,
                        'pista' => 'Das Brot es neutro: en acusativo sigue «ein Brot». Solo el masculino cambia a «einen».'],
                ],
            ],
            [
                'id' => 'rechnung',
                'dice' => '(Al terminar) Bitte sehr. Hat es geschmeckt?',
                'clip' => 'de/u6/dialogo/rechnung',
                'respuestas' => [
                    ['texto' => 'Ja, sehr gut. Die Rechnung, bitte. Was macht das?', 'va' => 'preis'],
                    ['texto' => 'Ja, sehr gut. Wie viel kostet die Rechnung?', 'va' => null,
                        'pista' => 'Para la cuenta se pregunta «Was macht das?» (¿cuánto es?).'],
                ],
            ],
            [
                'id' => 'preis',
                'dice' => 'Das macht fünf Euro fünfzig.',
                'clip' => 'de/u6/dialogo/preis',
                'respuestas' => [
                    ['texto' => 'Bitte. Danke, auf Wiedersehen!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Danke, auf Wiedersehen!',
                'clip' => 'de/u6/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ DE U7 · en la tienda: was kostet, dieser, el adjetivo predicativo, das Wetter ============
    [
        'lengua' => 'de',
        'unidad' => 7,
        'objective' => 'A1.IO.2',
        'slug' => 'was-kostet-das',
        'titulo' => 'Was kostet das?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Verkäuferin: Guten Tag! Kann ich Ihnen helfen?',
                'clip' => 'de/u7/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Guten Tag. Was kostet dieses T-Shirt?', 'va' => 'preis'],
                    ['texto' => 'Guten Tag. Was kostet diese T-Shirt?', 'va' => null,
                        'pista' => 'Das T-Shirt es neutro: «dieses T-Shirt». «Diese» es para femenino (diese Jacke).'],
                ],
            ],
            [
                'id' => 'preis',
                'dice' => 'Das kostet fünfundzwanzig Euro.',
                'clip' => 'de/u7/dialogo/preis',
                'respuestas' => [
                    ['texto' => 'Das ist ein bisschen teuer. Und die rote Jacke?', 'va' => 'jacke'],
                    ['texto' => 'Das ist ein bisschen teures. Und die rote Jacke?', 'va' => null,
                        'pista' => 'Detrás de «sein» el adjetivo NO cambia: «Das ist teuer». Solo delante del nombre lleva terminación (die rote Jacke).'],
                ],
            ],
            [
                'id' => 'jacke',
                'dice' => 'Die rote Jacke kostet vierzig Euro. Sie ist im Angebot.',
                'clip' => 'de/u7/dialogo/jacke',
                'respuestas' => [
                    ['texto' => 'Gut, ich nehme die Jacke. Haben Sie auch schwarze Hosen?', 'va' => 'wetter'],
                    ['texto' => 'Gut, ich nehme die Jacke. Haben Sie auch schwarz Hosen?', 'va' => null,
                        'pista' => 'Delante del nombre el adjetivo lleva terminación: «schwarze Hosen».'],
                ],
            ],
            [
                'id' => 'wetter',
                'dice' => 'Ja, dort rechts. Es ist kalt heute, oder?',
                'clip' => 'de/u7/dialogo/wetter',
                'respuestas' => [
                    ['texto' => 'Ja, es ist sehr kalt und es regnet.', 'va' => 'fin_antwort'],
                    ['texto' => 'Ja, es macht sehr kalt und es regnet.', 'va' => null,
                        'pista' => 'El tiempo en alemán va con «sein»: «Es ist kalt», «es ist warm». Sin «machen».'],
                ],
            ],
            [
                'id' => 'fin_antwort',
                'dice' => 'Also die Jacke und die Hose: sechzig Euro zusammen.',
                'clip' => 'de/u7/dialogo/fin_antwort',
                'respuestas' => [
                    ['texto' => 'In Ordnung. Danke, auf Wiedersehen!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Auf Wiedersehen, schönen Tag noch!',
                'clip' => 'de/u7/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ DE U8 · gestern: Perfekt con haben, el participio AL FINAL, nicht ============
    [
        'lengua' => 'de',
        'unidad' => 8,
        'objective' => 'A1.IO.2',
        'slug' => 'was-hast-du-gestern-gemacht',
        'titulo' => 'Was hast du gestern gemacht?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Marco: Ana, was hast du gestern gemacht?',
                'clip' => 'de/u8/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Ich habe gelernt und einen Film gesehen.', 'va' => 'film'],
                    ['texto' => 'Ich habe gelernt und gesehen einen Film.', 'va' => null,
                        'pista' => 'El participio va AL FINAL de la frase: «einen Film gesehen».'],
                    ['texto' => 'Ich habe lernen und einen Film sehen.', 'va' => null,
                        'pista' => 'Con «habe» va el participio: geLERNt, geSEHen.'],
                ],
            ],
            [
                'id' => 'film',
                'dice' => 'Welchen Film hast du gesehen?',
                'clip' => 'de/u8/dialogo/film',
                'respuestas' => [
                    ['texto' => 'Ich habe einen deutschen Film gesehen, sehr gut.', 'va' => 'hausaufgaben'],
                    ['texto' => 'Ich habe gesehen einen deutschen Film.', 'va' => null,
                        'pista' => '«Gesehen» cierra la frase: «Ich habe einen deutschen Film gesehen».'],
                ],
            ],
            [
                'id' => 'hausaufgaben',
                'dice' => 'Und die Hausaufgaben? Hast du sie gemacht?',
                'clip' => 'de/u8/dialogo/hausaufgaben',
                'respuestas' => [
                    ['texto' => 'Nein, ich habe die Hausaufgaben nicht gemacht. Ich war müde.', 'va' => 'ich'],
                    ['texto' => 'Nein, ich habe nicht die Hausaufgaben gemacht.', 'va' => null,
                        'pista' => '«Nicht» va justo delante del participio: «…die Hausaufgaben NICHT gemacht».'],
                ],
            ],
            [
                'id' => 'ich',
                'dice' => 'Ich auch nicht! Gestern habe ich den ganzen Nachmittag Fußball gespielt.',
                'clip' => 'de/u8/dialogo/ich',
                'respuestas' => [
                    ['texto' => 'Hast du gewonnen?', 'va' => 'fin_antwort'],
                    ['texto' => 'Hast du gewinnen?', 'va' => null,
                        'pista' => 'Participio irregular: «gewonnen». «Hast du gewonnen?».'],
                ],
            ],
            [
                'id' => 'fin_antwort',
                'dice' => 'Ja, drei zu eins! Machen wir heute die Hausaufgaben zusammen?',
                'clip' => 'de/u8/dialogo/fin_antwort',
                'respuestas' => [
                    ['texto' => 'Ja, gern. Um vier bei mir.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Super, bis später!',
                'clip' => 'de/u8/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ DE U9 · reparar la conversación: ich verstehe nicht, wie bitte, wie sagt man ============
    [
        'lengua' => 'de',
        'unidad' => 9,
        'objective' => 'A1.IO.1',
        'slug' => 'ich-verstehe-nicht',
        'titulo' => 'Ich verstehe nicht',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => 'Frau Müller: Ana, was hast du am Wochenende gemacht?',
                'clip' => 'de/u9/dialogo/inicio',
                'respuestas' => [
                    ['texto' => 'Entschuldigung, ich verstehe nicht. Können Sie das bitte wiederholen?', 'va' => 'langsam'],
                    ['texto' => 'Ja, ja, okay.', 'va' => null,
                        'pista' => 'No has entendido «Wochenende». Decir que sí no es hablar: pide que repita, «Können Sie das wiederholen?».'],
                    ['texto' => 'Wie bitte? Kannst du das wiederholen?', 'va' => null,
                        'pista' => 'Es la profesora: de Sie. «Können SIE das bitte wiederholen?».'],
                ],
            ],
            [
                'id' => 'langsam',
                'dice' => 'Natürlich. Was — hast du — am — Wochenende — gemacht?',
                'clip' => 'de/u9/dialogo/langsam',
                'respuestas' => [
                    ['texto' => 'Was bedeutet «Wochenende»?', 'va' => 'bedeutung'],
                    ['texto' => 'Langsamer, bitte.', 'va' => null,
                        'pista' => 'Ya lo ha dicho despacio. Lo que no sabes es una palabra: «Was bedeutet…?».'],
                ],
            ],
            [
                'id' => 'bedeutung',
                'dice' => 'Das Wochenende ist Samstag und Sonntag.',
                'clip' => 'de/u9/dialogo/bedeutung',
                'respuestas' => [
                    ['texto' => 'Ah! Am Samstag habe ich Fußball gespielt und am Sonntag habe ich einen Film gesehen.', 'va' => 'wie_sagt_man'],
                    ['texto' => 'Ah! Am Samstag spiele ich Fußball und am Sonntag sehe ich einen Film.', 'va' => null,
                        'pista' => 'Fue el fin de semana pasado: Perfekt. «habe … gespielt», «habe … gesehen».'],
                ],
            ],
            [
                'id' => 'wie_sagt_man',
                'dice' => 'Sehr gut! Und wie war der Film?',
                'clip' => 'de/u9/dialogo/wie_sagt_man',
                'respuestas' => [
                    ['texto' => 'Frau Müller, wie sagt man «divertida» auf Deutsch?', 'va' => 'lustig'],
                    ['texto' => 'Der Film war sehr divertida.', 'va' => null,
                        'pista' => 'Te falta la palabra: pregúntala en alemán en vez de meter el español. «Wie sagt man … auf Deutsch?».'],
                ],
            ],
            [
                'id' => 'lustig',
                'dice' => 'Man sagt «lustig».',
                'clip' => 'de/u9/dialogo/lustig',
                'respuestas' => [
                    ['texto' => 'Lustig! Der Film war sehr lustig. Danke, Frau Müller!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => 'Sehr gut, Ana. Auf Wiedersehen!',
                'clip' => 'de/u9/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],


    // ============ ZH U1 · el primer día: 是, 叫, país + 人, 您 con la profesora ============
    [
        'lengua' => 'zh',
        'unidad' => 1,
        'objective' => 'A1.IO.1',
        'slug' => 'di-yi-tian',
        'titulo' => '第一天 · Dì yī tiān · El primer día',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '你好！我是王老师。你叫什么名字？  Nǐ hǎo! Wǒ shì Wáng lǎoshī. Nǐ jiào shénme míngzi?',
                'clip' => 'zh/u1/dialogo/inicio',
                'respuestas' => [
                    ['texto' => '老师好！我叫安娜。  Lǎoshī hǎo! Wǒ jiào Ānnà.', 'va' => 'guojia'],
                    ['texto' => '你好！我是安娜。  Nǐ hǎo! Wǒ shì Ānnà.', 'va' => 'guojia'],
                    ['texto' => '再见！  Zàijiàn!', 'va' => null,
                        'pista' => 'Te preguntan tu NOMBRE: contesta con 我叫… (wǒ jiào…). Y a una profesora se la saluda con 老师好.'],
                ],
            ],
            [
                'id' => 'guojia',
                'dice' => '安娜，你是哪国人？  Ānnà, nǐ shì nǎ guó rén?',
                'clip' => 'zh/u1/dialogo/guojia',
                'respuestas' => [
                    ['texto' => '我是厄瓜多尔人。  Wǒ shì Èguāduō’ěr rén.', 'va' => 'xuesheng'],
                    ['texto' => '我叫安娜。  Wǒ jiào Ānnà.', 'va' => null,
                        'pista' => 'Tu nombre ya lo dijiste. Te preguntan de qué país eres: 我是 + país + 人.'],
                    ['texto' => '我是厄瓜多尔。  Wǒ shì Èguāduō’ěr.', 'va' => null,
                        'pista' => 'Casi: falta 人 rén. «Ecuatoriana» es 厄瓜多尔人, país + persona.'],
                ],
            ],
            [
                'id' => 'xuesheng',
                'dice' => '厄瓜多尔！你是学生吗？  Èguāduō’ěr! Nǐ shì xuésheng ma?',
                'clip' => 'zh/u1/dialogo/xuesheng',
                'respuestas' => [
                    ['texto' => '是，我是学生。  Shì, wǒ shì xuésheng.', 'va' => 'disan'],
                    ['texto' => '不是，我是老师。  Bú shì, wǒ shì lǎoshī.', 'va' => null,
                        'pista' => 'La profesora es ella. Tú eres la alumna: 是，我是学生.'],
                ],
            ],
            [
                'id' => 'disan',
                'dice' => '好。他是李明，他是北京人。  Hǎo. Tā shì Lǐ Míng, tā shì Běijīng rén.',
                'clip' => 'zh/u1/dialogo/disan',
                'respuestas' => [
                    ['texto' => '你好，李明！  Nǐ hǎo, Lǐ Míng!', 'va' => 'ni_ne'],
                    ['texto' => '你叫什么名字？  Nǐ jiào shénme míngzi?', 'va' => null,
                        'pista' => 'Li Ming ya tiene nombre. Salúdalo: 你好，李明！ — con un compañero sí va el 你好 a secas.'],
                ],
            ],
            [
                'id' => 'ni_ne',
                'dice' => '李明：你好，安娜！我是中国人。你呢？  Lǐ Míng: Nǐ hǎo, Ānnà! Wǒ shì Zhōngguó rén. Nǐ ne?',
                'clip' => 'zh/u1/dialogo/ni_ne',
                'respuestas' => [
                    ['texto' => '我是厄瓜多尔人，基多人。  Wǒ shì Èguāduō’ěr rén, Jīduō rén.', 'va' => 'despedida'],
                    ['texto' => '我是中国人。  Wǒ shì Zhōngguó rén.', 'va' => null,
                        'pista' => 'Chino es él. Tú eres de Ecuador: 我是厄瓜多尔人.'],
                ],
            ],
            [
                'id' => 'despedida',
                'dice' => '王老师：很好，安娜。再见！  Wáng lǎoshī: Hěn hǎo, Ānnà. Zàijiàn!',
                'clip' => 'zh/u1/dialogo/despedida',
                'respuestas' => [
                    ['texto' => '老师再见！  Lǎoshī zàijiàn!', 'va' => 'fin'],
                    ['texto' => '谢谢，再见！  Xièxie, zàijiàn!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => '明天见！  Míngtiān jiàn!',
                'clip' => 'zh/u1/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ ZH U2 · la familia: 有 / 没有, 口 / 个, la edad sin verbo ============
    [
        'lengua' => 'zh',
        'unidad' => 2,
        'objective' => 'A1.IO.3',
        'slug' => 'ni-jia-you-ji-kou-ren',
        'titulo' => '你家有几口人？ · ¿Cuántos sois en casa?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '李明：安娜，你家有几口人？  Lǐ Míng: Ānnà, nǐ jiā yǒu jǐ kǒu rén?',
                'clip' => 'zh/u2/dialogo/inicio',
                'respuestas' => [
                    ['texto' => '我家有四口人。  Wǒ jiā yǒu sì kǒu rén.', 'va' => 'shei'],
                    ['texto' => '我家有四个人。  Wǒ jiā yǒu sì gè rén.', 'va' => null,
                        'pista' => 'Se entiende, pero los miembros de la familia se cuentan con 口 kǒu, no con 个: 四口人.'],
                    ['texto' => '我家是四。  Wǒ jiā shì sì.', 'va' => null,
                        'pista' => '«Tener» es 有 yǒu, no 是. Y el número necesita su medidor: 我家有四口人.'],
                ],
            ],
            [
                'id' => 'shei',
                'dice' => '四口人？都有谁？  Sì kǒu rén? Dōu yǒu shéi?',
                'clip' => 'zh/u2/dialogo/shei',
                'respuestas' => [
                    ['texto' => '爸爸、妈妈、姐姐和我。  Bàba, māma, jiějie hé wǒ.', 'va' => 'gege'],
                    ['texto' => '我有一个狗。  Wǒ yǒu yí gè gǒu.', 'va' => null,
                        'pista' => 'Te preguntan QUIÉNES son los cuatro. Nómbralos: 爸爸、妈妈、… 和我.'],
                ],
            ],
            [
                'id' => 'gege',
                'dice' => '你有哥哥吗？  Nǐ yǒu gēge ma?',
                'clip' => 'zh/u2/dialogo/gege',
                'respuestas' => [
                    ['texto' => '没有。我有一个姐姐。  Méiyǒu. Wǒ yǒu yí gè jiějie.', 'va' => 'sui'],
                    ['texto' => '不有。  Bù yǒu.', 'va' => null,
                        'pista' => '有 nunca se niega con 不: es 没有 méiyǒu.'],
                    ['texto' => '没有。我有一姐姐。  Méiyǒu. Wǒ yǒu yī jiějie.', 'va' => null,
                        'pista' => 'Falta el medidor entre el número y la persona: 一个姐姐 yí gè jiějie.'],
                ],
            ],
            [
                'id' => 'sui',
                'dice' => '你姐姐多大？  Nǐ jiějie duō dà?',
                'clip' => 'zh/u2/dialogo/sui',
                'respuestas' => [
                    ['texto' => '她十八岁。  Tā shíbā suì.', 'va' => 'ni_ne'],
                    ['texto' => '她是十八岁。  Tā shì shíbā suì.', 'va' => null,
                        'pista' => 'La edad va SIN verbo: 她十八岁. Ni 是 ni 有.'],
                ],
            ],
            [
                'id' => 'ni_ne',
                'dice' => '我家有五口人。我有一个哥哥和一个妹妹。  Wǒ jiā yǒu wǔ kǒu rén. Wǒ yǒu yí gè gēge hé yí gè mèimei.',
                'clip' => 'zh/u2/dialogo/ni_ne',
                'respuestas' => [
                    ['texto' => '你妹妹几岁？  Nǐ mèimei jǐ suì?', 'va' => 'fin_pregunta'],
                    ['texto' => '你有妹妹吗？  Nǐ yǒu mèimei ma?', 'va' => null,
                        'pista' => 'Acaba de decir que tiene una hermana menor. Pregúntale la edad: 你妹妹几岁？ (几 para un niño pequeño).'],
                ],
            ],
            [
                'id' => 'fin_pregunta',
                'dice' => '她八岁。她叫小美。  Tā bā suì. Tā jiào Xiǎo Měi.',
                'clip' => 'zh/u2/dialogo/fin_pregunta',
                'respuestas' => [
                    ['texto' => '好，谢谢！再见！  Hǎo, xièxie! Zàijiàn!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => '再见，安娜！  Zàijiàn, Ānnà!',
                'clip' => 'zh/u2/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ ZH U3 · la hora y la rutina: 几点, 两点, la hora antes del verbo ============
    [
        'lengua' => 'zh',
        'unidad' => 3,
        'objective' => 'A1.IO.2',
        'slug' => 'xianzai-ji-dian',
        'titulo' => '现在几点？ · ¿Qué hora es?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '李明：安娜，现在几点？  Lǐ Míng: Ānnà, xiànzài jǐ diǎn?',
                'clip' => 'zh/u3/dialogo/inicio',
                'respuestas' => [
                    ['texto' => '现在两点半。  Xiànzài liǎng diǎn bàn.', 'va' => 'shangke'],
                    ['texto' => '现在二点半。  Xiànzài èr diǎn bàn.', 'va' => null,
                        'pista' => 'Con 点 el «dos» es 两 liǎng, no 二: 两点半.'],
                ],
            ],
            [
                'id' => 'shangke',
                'dice' => '两点半！我们三点上课。你几点起床？  Liǎng diǎn bàn! Wǒmen sān diǎn shàngkè. Nǐ jǐ diǎn qǐchuáng?',
                'clip' => 'zh/u3/dialogo/shangke',
                'respuestas' => [
                    ['texto' => '我六点起床。  Wǒ liù diǎn qǐchuáng.', 'va' => 'zaofan'],
                    ['texto' => '我起床六点。  Wǒ qǐchuáng liù diǎn.', 'va' => null,
                        'pista' => 'La hora va ANTES del verbo: 我六点起床.'],
                ],
            ],
            [
                'id' => 'zaofan',
                'dice' => '六点！你在哪儿吃早饭？  Liù diǎn! Nǐ zài nǎr chī zǎofàn?',
                'clip' => 'zh/u3/dialogo/zaofan',
                'respuestas' => [
                    ['texto' => '我在家吃早饭。  Wǒ zài jiā chī zǎofàn.', 'va' => 'wanshang'],
                    ['texto' => '我吃早饭在家。  Wǒ chī zǎofàn zài jiā.', 'va' => null,
                        'pista' => '在 + lugar va ANTES del verbo, como la hora: 我在家吃早饭.'],
                    ['texto' => '我是家。  Wǒ shì jiā.', 'va' => null,
                        'pista' => 'Un lugar va con 在 zài, no con 是: 我在家吃早饭.'],
                ],
            ],
            [
                'id' => 'wanshang',
                'dice' => '晚上你做什么？  Wǎnshang nǐ zuò shénme?',
                'clip' => 'zh/u3/dialogo/wanshang',
                'respuestas' => [
                    ['texto' => '晚上我看书，十点睡觉。  Wǎnshang wǒ kàn shū, shí diǎn shuìjiào.', 'va' => 'ni_ne'],
                    ['texto' => '我上午上课。  Wǒ shàngwǔ shàngkè.', 'va' => null,
                        'pista' => 'Te preguntan por la NOCHE (晚上). Cuenta qué haces por la noche y a qué hora duermes.'],
                ],
            ],
            [
                'id' => 'ni_ne',
                'dice' => '我也十点睡觉！好，三点了，上课吧。  Wǒ yě shí diǎn shuìjiào! Hǎo, sān diǎn le, shàngkè ba.',
                'clip' => 'zh/u3/dialogo/ni_ne',
                'respuestas' => [
                    ['texto' => '好的，走吧！  Hǎo de, zǒu ba!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => '走！  Zǒu!',
                'clip' => 'zh/u3/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ ZH U4 · gustos: 喜欢, 也, el adjetivo con 很 ============
    [
        'lengua' => 'zh',
        'unidad' => 4,
        'objective' => 'A1.IO.2',
        'slug' => 'ni-xihuan-shenme',
        'titulo' => '你喜欢什么？ · ¿Qué te gusta?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '李明：安娜，你喜欢什么？  Lǐ Míng: Ānnà, nǐ xǐhuan shénme?',
                'clip' => 'zh/u4/dialogo/inicio',
                'respuestas' => [
                    ['texto' => '我喜欢音乐和足球。  Wǒ xǐhuan yīnyuè hé zúqiú.', 'va' => 'kafei'],
                    ['texto' => '我是音乐。  Wǒ shì yīnyuè.', 'va' => null,
                        'pista' => '«Me gusta» es 我喜欢 wǒ xǐhuan + la cosa. Sin 是.'],
                ],
            ],
            [
                'id' => 'kafei',
                'dice' => '我也喜欢足球！你喜欢咖啡吗？  Wǒ yě xǐhuan zúqiú! Nǐ xǐhuan kāfēi ma?',
                'clip' => 'zh/u4/dialogo/kafei',
                'respuestas' => [
                    ['texto' => '不喜欢，我喜欢茶。  Bù xǐhuan, wǒ xǐhuan chá.', 'va' => 'cha'],
                    ['texto' => '喜欢，我很喜欢咖啡。  Xǐhuan, wǒ hěn xǐhuan kāfēi.', 'va' => 'cha'],
                    ['texto' => '没喜欢。  Méi xǐhuan.', 'va' => null,
                        'pista' => '喜欢 se niega con 不: 不喜欢. 没 solo va con 有.'],
                ],
            ],
            [
                'id' => 'cha',
                'dice' => '中国茶很好喝。你觉得呢？  Zhōngguó chá hěn hǎohē. Nǐ juéde ne?',
                'clip' => 'zh/u4/dialogo/cha',
                'respuestas' => [
                    ['texto' => '对，中国茶很好喝。  Duì, Zhōngguó chá hěn hǎohē.', 'va' => 'dianying'],
                    ['texto' => '对，中国茶是好喝。  Duì, Zhōngguó chá shì hǎohē.', 'va' => null,
                        'pista' => 'Delante de un adjetivo no va 是: sujeto + 很 + adjetivo. 中国茶很好喝.'],
                ],
            ],
            [
                'id' => 'dianying',
                'dice' => '你喜欢看电影吗？  Nǐ xǐhuan kàn diànyǐng ma?',
                'clip' => 'zh/u4/dialogo/dianying',
                'respuestas' => [
                    ['texto' => '喜欢！我也喜欢看书。  Xǐhuan! Wǒ yě xǐhuan kàn shū.', 'va' => 'fin_pregunta'],
                    ['texto' => '喜欢！我喜欢看书也。  Xǐhuan! Wǒ xǐhuan kàn shū yě.', 'va' => null,
                        'pista' => '也 va entre el sujeto y el verbo, nunca al final: 我也喜欢看书.'],
                ],
            ],
            [
                'id' => 'fin_pregunta',
                'dice' => '太好了！明天我们看电影吧。  Tài hǎo le! Míngtiān wǒmen kàn diànyǐng ba.',
                'clip' => 'zh/u4/dialogo/fin_pregunta',
                'respuestas' => [
                    ['texto' => '好的！明天见。  Hǎo de! Míngtiān jiàn.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => '明天见！  Míngtiān jiàn!',
                'clip' => 'zh/u4/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ ZH U5 · en la ciudad: 请问, 在哪儿, referencia + posición, 远 / 近 ============
    [
        'lengua' => 'zh',
        'unidad' => 5,
        'objective' => 'A1.IO.2',
        'slug' => 'qingwen-yiyuan-zai-nar',
        'titulo' => '请问，医院在哪儿？ · Disculpe, ¿dónde está el hospital?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '（Una señora en la calle）你好。  Nǐ hǎo.',
                'clip' => 'zh/u5/dialogo/inicio',
                'respuestas' => [
                    ['texto' => '请问，医院在哪儿？  Qǐngwèn, yīyuàn zài nǎr?', 'va' => 'pangbian'],
                    ['texto' => '医院哪儿？  Yīyuàn nǎr?', 'va' => null,
                        'pista' => 'Falta el verbo de lugar: 医院【在】哪儿？ Y a un desconocido se le aborda con 请问.'],
                ],
            ],
            [
                'id' => 'pangbian',
                'dice' => '医院在学校旁边。  Yīyuàn zài xuéxiào pángbiān.',
                'clip' => 'zh/u5/dialogo/pangbian',
                'respuestas' => [
                    ['texto' => '远吗？  Yuǎn ma?', 'va' => 'jin'],
                    ['texto' => '医院在哪儿？  Yīyuàn zài nǎr?', 'va' => null,
                        'pista' => 'Ya te lo ha dicho: al lado de la escuela. Pregunta si está lejos: 远吗？'],
                ],
            ],
            [
                'id' => 'jin',
                'dice' => '不远，很近。你看，学校在那儿。  Bù yuǎn, hěn jìn. Nǐ kàn, xuéxiào zài nàr.',
                'clip' => 'zh/u5/dialogo/jin',
                'respuestas' => [
                    ['texto' => '啊，医院在学校旁边，我知道了。  À, yīyuàn zài xuéxiào pángbiān, wǒ zhīdào le.', 'va' => 'shangdian'],
                    ['texto' => '啊，医院在旁边学校。  À, yīyuàn zài pángbiān xuéxiào.', 'va' => null,
                        'pista' => 'Primero la referencia, después la posición: 学校旁边 («de la escuela, al lado»).'],
                ],
            ],
            [
                'id' => 'shangdian',
                'dice' => '对。医院前面有一个商店。  Duì. Yīyuàn qiánmiàn yǒu yí gè shāngdiàn.',
                'clip' => 'zh/u5/dialogo/shangdian',
                'respuestas' => [
                    ['texto' => '好，谢谢您！  Hǎo, xièxie nín!', 'va' => 'fin'],
                    ['texto' => '好，谢谢你！  Hǎo, xièxie nǐ!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => '不客气。再见！  Bú kèqi. Zàijiàn!',
                'clip' => 'zh/u5/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ ZH U6 · en el restaurante: 要, medidores, 多少钱, 买单 ============
    [
        'lengua' => 'zh',
        'unidad' => 6,
        'objective' => 'A1.IO.2',
        'slug' => 'zai-fanguan-dianfan',
        'titulo' => '在饭馆点饭 · Pedir en el restaurante',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '服务员：你好！你要什么？  Fúwùyuán: Nǐ hǎo! Nǐ yào shénme?',
                'clip' => 'zh/u6/dialogo/inicio',
                'respuestas' => [
                    ['texto' => '我要一碗面条。  Wǒ yào yì wǎn miàntiáo.', 'va' => 'he'],
                    ['texto' => '我要一个面条。  Wǒ yào yí gè miàntiáo.', 'va' => null,
                        'pista' => 'La comida lleva su medidor: los fideos van en cuenco, 一碗面条 yì wǎn miàntiáo.'],
                    ['texto' => '我喜欢面条。  Wǒ xǐhuan miàntiáo.', 'va' => null,
                        'pista' => 'Que te gusten está bien, pero para PEDIR se usa 要 yào: 我要一碗面条.'],
                ],
            ],
            [
                'id' => 'he',
                'dice' => '好。喝什么？  Hǎo. Hē shénme?',
                'clip' => 'zh/u6/dialogo/he',
                'respuestas' => [
                    ['texto' => '一杯茶。  Yì bēi chá.', 'va' => 'jige'],
                    ['texto' => '一碗茶。  Yì wǎn chá.', 'va' => null,
                        'pista' => 'El té va en vaso o taza: 一杯茶 yì bēi chá. 碗 es para el arroz y los fideos.'],
                ],
            ],
            [
                'id' => 'jige',
                'dice' => '一杯茶。你们几个人？  Yì bēi chá. Nǐmen jǐ gè rén?',
                'clip' => 'zh/u6/dialogo/jige',
                'respuestas' => [
                    ['texto' => '两个人。我朋友也要一碗面条。  Liǎng gè rén. Wǒ péngyou yě yào yì wǎn miàntiáo.', 'va' => 'maidan'],
                    ['texto' => '二个人。  Èr gè rén.', 'va' => null,
                        'pista' => 'Delante de un medidor, «dos» es 两 liǎng: 两个人.'],
                ],
            ],
            [
                'id' => 'maidan',
                'dice' => '（Después de comer）  好吃吗？  Hǎochī ma?',
                'clip' => 'zh/u6/dialogo/maidan',
                'respuestas' => [
                    ['texto' => '很好吃！服务员，买单。多少钱？  Hěn hǎochī! Fúwùyuán, mǎidān. Duōshao qián?', 'va' => 'qian'],
                    ['texto' => '是好吃。几钱？  Shì hǎochī. Jǐ qián?', 'va' => null,
                        'pista' => 'Dos cosas: el adjetivo va con 很, no con 是 (很好吃); y «cuánto cuesta» es 多少钱, porque 几 solo llega hasta diez.'],
                ],
            ],
            [
                'id' => 'qian',
                'dice' => '两碗面条，一杯茶，三十五块。  Liǎng wǎn miàntiáo, yì bēi chá, sānshíwǔ kuài.',
                'clip' => 'zh/u6/dialogo/qian',
                'respuestas' => [
                    ['texto' => '好，给您。谢谢！  Hǎo, gěi nín. Xièxie!', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => '谢谢，再见！  Xièxie, zàijiàn!',
                'clip' => 'zh/u6/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ ZH U7 · en la tienda: 这个 / 那个, 件, 太贵了, el tiempo ============
    [
        'lengua' => 'zh',
        'unidad' => 7,
        'objective' => 'A1.IO.2',
        'slug' => 'zhe-jian-yifu-duoshao-qian',
        'titulo' => '这件衣服多少钱？ · ¿Cuánto cuesta esta prenda?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '售货员：你好！你要买什么？  Shòuhuòyuán: Nǐ hǎo! Nǐ yào mǎi shénme?',
                'clip' => 'zh/u7/dialogo/inicio',
                'respuestas' => [
                    ['texto' => '这件衣服多少钱？  Zhè jiàn yīfu duōshao qián?', 'va' => 'gui'],
                    ['texto' => '这衣服多少钱？  Zhè yīfu duōshao qián?', 'va' => null,
                        'pista' => 'Entre 这 y el nombre va el medidor: para la ropa, 件. 这件衣服.'],
                    ['texto' => '这个件衣服多少钱？  Zhège jiàn yīfu duōshao qián?', 'va' => null,
                        'pista' => 'Un medidor, no dos: si el nombre tiene el suyo (件), el 个 se retira. 这件衣服.'],
                ],
            ],
            [
                'id' => 'gui',
                'dice' => '这件一百二十块。  Zhè jiàn yìbǎi èrshí kuài.',
                'clip' => 'zh/u7/dialogo/gui',
                'respuestas' => [
                    ['texto' => '太贵了！那件呢？  Tài guì le! Nà jiàn ne?', 'va' => 'nage'],
                    ['texto' => '太贵！  Tài guì!', 'va' => null,
                        'pista' => '太 va con su 了 al final: 太贵了 tài guì le. Y pregunta por la otra: 那件呢？'],
                ],
            ],
            [
                'id' => 'nage',
                'dice' => '那件八十块，很便宜。  Nà jiàn bāshí kuài, hěn piányi.',
                'clip' => 'zh/u7/dialogo/nage',
                'respuestas' => [
                    ['texto' => '好，我买那件。  Hǎo, wǒ mǎi nà jiàn.', 'va' => 'tianqi'],
                    ['texto' => '好，我要卖那件。  Hǎo, wǒ yào mài nà jiàn.', 'va' => null,
                        'pista' => 'Ojo con el tono: 买 mǎi (tercer tono) es comprar; 卖 mài (cuarto) es vender. Tú compras: 我买那件.'],
                ],
            ],
            [
                'id' => 'tianqi',
                'dice' => '好的。今天天气怎么样？外面下雨吗？  Hǎo de. Jīntiān tiānqì zěnmeyàng? Wàimiàn xià yǔ ma?',
                'clip' => 'zh/u7/dialogo/tianqi',
                'respuestas' => [
                    ['texto' => '不下雨，但是很冷。  Bú xià yǔ, dànshì hěn lěng.', 'va' => 'fin_pregunta'],
                    ['texto' => '不下雨，但是是冷。  Bú xià yǔ, dànshì shì lěng.', 'va' => null,
                        'pista' => '«Hace frío» es 很冷 hěn lěng, sin 是: el adjetivo ya es el verbo.'],
                ],
            ],
            [
                'id' => 'fin_pregunta',
                'dice' => '很冷！这件衣服很好。给你。  Hěn lěng! Zhè jiàn yīfu hěn hǎo. Gěi nǐ.',
                'clip' => 'zh/u7/dialogo/fin_pregunta',
                'respuestas' => [
                    ['texto' => '谢谢！再见。  Xièxie! Zàijiàn.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => '再见！  Zàijiàn!',
                'clip' => 'zh/u7/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ ZH U8 · ayer: 了 y 没, la respuesta corta repite el verbo ============
    [
        'lengua' => 'zh',
        'unidad' => 8,
        'objective' => 'A1.IO.2',
        'slug' => 'ni-zuotian-zuo-le-shenme',
        'titulo' => '你昨天做了什么？ · ¿Qué hiciste ayer?',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '李明：安娜，你昨天做了什么？  Lǐ Míng: Ānnà, nǐ zuótiān zuò le shénme?',
                'clip' => 'zh/u8/dialogo/inicio',
                'respuestas' => [
                    ['texto' => '我去了商店，买了一件衣服。  Wǒ qù le shāngdiàn, mǎi le yí jiàn yīfu.', 'va' => 'dianying'],
                    ['texto' => '我了去商店。  Wǒ le qù shāngdiàn.', 'va' => null,
                        'pista' => '了 va DETRÁS del verbo, pegado a él: 我去了商店.'],
                ],
            ],
            [
                'id' => 'dianying',
                'dice' => '你看了电影吗？  Nǐ kàn le diànyǐng ma?',
                'clip' => 'zh/u8/dialogo/dianying',
                'respuestas' => [
                    ['texto' => '看了。我和朋友看了一个电影。  Kàn le. Wǒ hé péngyou kàn le yí gè diànyǐng.', 'va' => 'zuoye'],
                    ['texto' => '没看。我太累了。  Méi kàn. Wǒ tài lèi le.', 'va' => 'zuoye'],
                    ['texto' => '不看了。  Bú kàn le.', 'va' => null,
                        'pista' => 'Para decir que algo NO pasó: 没 + verbo, y sin 了. «No la vi» es 没看.'],
                ],
            ],
            [
                'id' => 'zuoye',
                'dice' => '你做了作业吗？  Nǐ zuò le zuòyè ma?',
                'clip' => 'zh/u8/dialogo/zuoye',
                'respuestas' => [
                    ['texto' => '没做。晚上我太累了。  Méi zuò. Wǎnshang wǒ tài lèi le.', 'va' => 'jintian'],
                    ['texto' => '没做了。  Méi zuò le.', 'va' => null,
                        'pista' => '没 y 了 no van juntos nunca: 没做, sin 了.'],
                ],
            ],
            [
                'id' => 'jintian',
                'dice' => '我也没做！今天我们一起做吧。  Wǒ yě méi zuò! Jīntiān wǒmen yìqǐ zuò ba.',
                'clip' => 'zh/u8/dialogo/jintian',
                'respuestas' => [
                    ['texto' => '好的！下午三点，在我家。  Hǎo de! Xiàwǔ sān diǎn, zài wǒ jiā.', 'va' => 'fin'],
                    ['texto' => '好的！在我家，下午三点。  Hǎo de! Zài wǒ jiā, xiàwǔ sān diǎn.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => '好，下午见！  Hǎo, xiàwǔ jiàn!',
                'clip' => 'zh/u8/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],

    // ============ ZH U9 · reparar la conversación: 听不懂, 请再说一遍, 怎么说 ============
    [
        'lengua' => 'zh',
        'unidad' => 9,
        'objective' => 'A1.IO.1',
        'slug' => 'wo-ting-bu-dong',
        'titulo' => '我听不懂 · No entiendo',
        'nodos' => [
            [
                'id' => 'inicio',
                'dice' => '王老师：安娜，你周末做了什么？  Wáng lǎoshī: Ānnà, nǐ zhōumò zuò le shénme?',
                'clip' => 'zh/u9/dialogo/inicio',
                'respuestas' => [
                    ['texto' => '对不起，老师，我听不懂。请再说一遍。  Duìbuqǐ, lǎoshī, wǒ tīng bu dǒng. Qǐng zài shuō yí biàn.', 'va' => 'manyidianr'],
                    ['texto' => '是，是。  Shì, shì.', 'va' => null,
                        'pista' => 'No has entendido «周末». Decir que sí a todo no es hablar chino: pide que repita, 请再说一遍.'],
                    ['texto' => '¿Cómo? No entiendo.', 'va' => null,
                        'pista' => 'En chino: 我听不懂 wǒ tīng bu dǒng. Es exactamente para esto.'],
                ],
            ],
            [
                'id' => 'manyidianr',
                'dice' => '周末——你——做了——什么？  Zhōumò — nǐ — zuò le — shénme?',
                'clip' => 'zh/u9/dialogo/manyidianr',
                'respuestas' => [
                    ['texto' => '«周末»是什么意思？  «Zhōumò» shì shénme yìsi?', 'va' => 'yisi'],
                    ['texto' => '请说慢一点儿。  Qǐng shuō màn yìdiǎnr.', 'va' => null,
                        'pista' => 'Ya lo ha dicho despacio. Lo que no sabes es una PALABRA: pregunta qué significa, …是什么意思？'],
                ],
            ],
            [
                'id' => 'yisi',
                'dice' => '周末是星期六和星期天。  Zhōumò shì xīngqīliù hé xīngqītiān.',
                'clip' => 'zh/u9/dialogo/yisi',
                'respuestas' => [
                    ['texto' => '啊，周末！我去了公园，也看了一个电影。  À, zhōumò! Wǒ qù le gōngyuán, yě kàn le yí gè diànyǐng.', 'va' => 'zenme_shuo'],
                    ['texto' => '啊，周末！我去公园，看电影。  À, zhōumò! Wǒ qù gōngyuán, kàn diànyǐng.', 'va' => null,
                        'pista' => 'Fue el fin de semana pasado: acciones terminadas, con 了 detrás del verbo. 去了公园，看了一个电影.'],
                ],
            ],
            [
                'id' => 'zenme_shuo',
                'dice' => '很好！电影怎么样？  Hěn hǎo! Diànyǐng zěnmeyàng?',
                'clip' => 'zh/u9/dialogo/zenme_shuo',
                'respuestas' => [
                    ['texto' => '老师，«divertida» 用中文怎么说？  Lǎoshī, «divertida» yòng Zhōngwén zěnme shuō?', 'va' => 'youyisi'],
                    ['texto' => '电影很 divertida。  Diànyǐng hěn divertida.', 'va' => null,
                        'pista' => 'Te falta una palabra: pregúntala en chino en vez de meterla en español. «…» 用中文怎么说？'],
                ],
            ],
            [
                'id' => 'youyisi',
                'dice' => '«有意思»。  «Yǒu yìsi».',
                'clip' => 'zh/u9/dialogo/youyisi',
                'respuestas' => [
                    ['texto' => '有意思？电影很有意思！谢谢老师。  Yǒu yìsi? Diànyǐng hěn yǒu yìsi! Xièxie lǎoshī.', 'va' => 'fin'],
                ],
            ],
            [
                'id' => 'fin',
                'dice' => '对！太好了，安娜。再见！  Duì! Tài hǎo le, Ānnà. Zàijiàn!',
                'clip' => 'zh/u9/dialogo/fin',
                'fin' => true,
            ],
        ],
    ],
];
