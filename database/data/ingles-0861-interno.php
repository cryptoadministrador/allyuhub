<?php

/**
 * INGLÉS 0861 · DESCRIPTORES PROPIOS DE ALLYUHUB (Stages 7-9).
 *
 * Por qué existe. Cambridge Lower Secondary English 0861 entró al grafo con
 * sus strands y sub-strands PÚBLICOS como nodos y CERO objetivos: su marco
 * completo es de descarga protegida y aquí no se inventa un código. Pero un
 * ítem y una lección se anclan a un DESCRIPTOR, así que sin descriptores el
 * curso `/corso/en` no podía tener contenido nunca.
 *
 * La salida: descriptores NUESTROS, en un marco NUESTRO (`AH-EN0861`, kind
 * `internal`), que dicen honradamente lo que son:
 *
 *  - El código es `EN<stage>.<strand>.<n>` y NO se parece a uno de Cambridge
 *    (`InglesInternoSeeder` rechaza lo que no tenga exactamente esa forma).
 *  - Cada uno declara en `ref` el strand o sub-strand PÚBLICO de 0861 que
 *    desarrolla. Esa referencia es una ruta del grafo y el seeder REVIENTA si
 *    no existe — un descriptor colgado de la nada no entra.
 *  - Entran con `is_verified = false` y `oficial = false`.
 *
 * El día que el colegio aporte el curriculum framework oficial, se importan
 * los códigos reales como `framework_version` de CAIE-LSEC y el contenido se
 * reancla por `ref` (mismo sub-strand), sin reescribir el banco a mano.
 *
 * Inglés como PRIMERA lengua: el MCER no aplica (mide segundas lenguas), así
 * que aquí no hay banda ninguna.
 *
 * ENUNCIADOS: BORRADOR de trabajo (septiembre de 2026), pendiente de que Carlos los
 * revise. Se redactan en primera persona («Puedo…»), como los del MCER, para
 * que el alumno los lea como objetivos suyos en la página de la unidad.
 */

$outline = 'https://www.cambridgeinternational.org/programmes-and-qualifications/cambridge-lower-secondary/curriculum/english/';

return [
    'framework' => [
        'code' => 'AH-EN0861',
        'authority' => 'AllyuHub',
        'kind' => 'internal',
        'label' => [
            'es' => 'Inglés 0861 · descriptores propios de AllyuHub (no oficiales)',
            'en' => 'English 0861 · AllyuHub own descriptors (unofficial)',
        ],
    ],
    'version' => [
        // ETIQUETA ESTABLE, a propósito: `versionesDe` elige la versión más
        // nueva y el contenido cuelga de los descriptores de UNA versión. Una
        // etiqueta con fecha, al cambiarla, crearía una versión vacía y dejaría
        // todo el inglés sembrado huérfano («próximamente») sin avisar. Los
        // enunciados se corrigen EN SITIO (el seeder actualiza); una versión
        // nueva es solo para el día que llegue el marco oficial.
        'label' => 'propio',
        // Referencia, no fuente: lo que se cita es el outline PÚBLICO del que
        // cuelgan los sub-strands, no un documento que contenga estos textos.
        'source_url' => $outline,
    ],
    // El programa de Cambridge del que cuelgan las referencias.
    'cambridge' => ['marco' => 'CAIE-LSEC', 'raiz' => 'lsec.en0861'],
    'strands' => [
        'R' => ['es' => 'Lectura', 'en' => 'Reading'],
        'W' => ['es' => 'Escritura', 'en' => 'Writing'],
        'SL' => ['es' => 'Expresión oral y escucha', 'en' => 'Speaking and listening'],
    ],
    'stages' => [
        7 => [
            'R' => [
                ['code' => 'EN7.R.1', 'ref' => 'lsec.en0861.reading.ss1',
                    'es' => 'Puedo leer con fluidez textos de ficción y no ficción de mi edad y usar el contexto para deducir palabras que no conozco.',
                    'en' => 'I can read age-appropriate fiction and non-fiction fluently and use context to work out unfamiliar words.'],
                ['code' => 'EN7.R.2', 'ref' => 'lsec.en0861.reading.ss2',
                    'es' => 'Puedo localizar y resumir las ideas principales y los datos explícitos de un texto.',
                    'en' => 'I can find and summarise the main ideas and explicit details in a text.'],
                ['code' => 'EN7.R.3', 'ref' => 'lsec.en0861.reading.ss3',
                    'es' => 'Puedo inferir los sentimientos, motivos y actitudes de los personajes a partir de pistas del texto.',
                    'en' => "I can infer characters' feelings, motives and attitudes from clues in the text."],
                ['code' => 'EN7.R.4', 'ref' => 'lsec.en0861.reading.ss4',
                    'es' => 'Puedo reconocer cómo el autor usa la elección de palabras y el lenguaje figurado (símil, metáfora) para crear un efecto.',
                    'en' => "I can recognise how a writer's word choice and figurative language (simile, metaphor) create an effect."],
            ],
            'W' => [
                ['code' => 'EN7.W.1', 'ref' => 'lsec.en0861.writing.ss2',
                    'es' => 'Puedo planificar y escribir textos narrativos y descriptivos pensando en su propósito y en quién los va a leer.',
                    'en' => 'I can plan and write narrative and descriptive texts for a clear purpose and reader.'],
                ['code' => 'EN7.W.2', 'ref' => 'lsec.en0861.writing.ss3',
                    'es' => 'Puedo organizar un texto en párrafos, cada uno con su idea principal, unidos por conectores de tiempo y secuencia.',
                    'en' => 'I can organise a text into paragraphs, each with a main idea, linked by time and sequence connectives.'],
                ['code' => 'EN7.W.3', 'ref' => 'lsec.en0861.writing.ss4',
                    'es' => 'Puedo combinar oraciones simples y compuestas y puntuarlas bien: punto, coma, interrogación, exclamación y apóstrofo.',
                    'en' => 'I can combine simple and compound sentences and punctuate them accurately, including apostrophes.'],
                ['code' => 'EN7.W.4', 'ref' => 'lsec.en0861.writing.ss5',
                    'es' => 'Puedo escribir sin faltas las palabras frecuentes y aplicar las reglas de prefijos y sufijos.',
                    'en' => 'I can spell common words correctly and apply prefix and suffix rules.'],
            ],
            'SL' => [
                ['code' => 'EN7.SL.1', 'ref' => 'lsec.en0861.speaking_listening',
                    'es' => 'Puedo escuchar una exposición y resumir sus puntos principales.',
                    'en' => 'I can listen to a talk and summarise its main points.'],
                ['code' => 'EN7.SL.2', 'ref' => 'lsec.en0861.speaking_listening',
                    'es' => 'Puedo hacer una presentación breve y ordenada, adaptando el volumen y el ritmo al público.',
                    'en' => 'I can give a short, organised presentation, adapting volume and pace to the audience.'],
                ['code' => 'EN7.SL.3', 'ref' => 'lsec.en0861.speaking_listening',
                    'es' => 'Puedo participar en una discusión de grupo respetando los turnos y partiendo de lo que dicen los demás.',
                    'en' => "I can take part in a group discussion, taking turns and building on others' ideas."],
            ],
        ],
        8 => [
            'R' => [
                ['code' => 'EN8.R.1', 'ref' => 'lsec.en0861.reading.ss1',
                    'es' => 'Puedo elegir cómo leer —de un vistazo, buscando un dato o con atención— según lo que necesito del texto.',
                    'en' => 'I can choose how to read — skimming, scanning or close reading — depending on my purpose.'],
                ['code' => 'EN8.R.2', 'ref' => 'lsec.en0861.reading.ss3',
                    'es' => 'Puedo explicar lo que un texto sugiere sin decirlo y distinguir un hecho de una opinión.',
                    'en' => 'I can explain what a text implies and tell fact from opinion.'],
                ['code' => 'EN8.R.3', 'ref' => 'lsec.en0861.reading.ss4',
                    'es' => 'Puedo analizar cómo la estructura de un texto (orden, párrafos, oraciones cortas) crea tensión o énfasis.',
                    'en' => 'I can analyse how structure (order, paragraphing, short sentences) builds tension or emphasis.'],
                ['code' => 'EN8.R.4', 'ref' => 'lsec.en0861.reading.ss5',
                    'es' => 'Puedo reconocer el propósito y el punto de vista del autor en un texto persuasivo.',
                    'en' => "I can recognise a writer's purpose and viewpoint in a persuasive text."],
            ],
            'W' => [
                ['code' => 'EN8.W.1', 'ref' => 'lsec.en0861.writing.ss2',
                    'es' => 'Puedo escribir textos argumentativos y persuasivos eligiendo un registro formal o informal según el destinatario.',
                    'en' => 'I can write argumentative and persuasive texts, choosing a formal or informal register for the reader.'],
                ['code' => 'EN8.W.2', 'ref' => 'lsec.en0861.writing.ss3',
                    'es' => 'Puedo estructurar un texto con introducción, desarrollo y cierre, y enlazar los párrafos con conectores de causa y contraste.',
                    'en' => 'I can structure a text with an introduction, development and conclusion, linking paragraphs with cause and contrast connectives.'],
                ['code' => 'EN8.W.3', 'ref' => 'lsec.en0861.writing.ss4',
                    'es' => 'Puedo usar oraciones subordinadas y puntuar con precisión dos puntos, punto y coma y los diálogos.',
                    'en' => 'I can use subordinate clauses and punctuate colons, semicolons and dialogue accurately.'],
                ['code' => 'EN8.W.4', 'ref' => 'lsec.en0861.writing.ss1',
                    'es' => 'Puedo revisar y corregir un borrador propio para que sea más claro y preciso.',
                    'en' => 'I can revise and edit my own draft to make it clearer and more precise.'],
            ],
            'SL' => [
                ['code' => 'EN8.SL.1', 'ref' => 'lsec.en0861.speaking_listening',
                    'es' => 'Puedo escuchar un argumento e identificar la postura y las razones que la sostienen.',
                    'en' => 'I can listen to an argument and identify the position and the reasons behind it.'],
                ['code' => 'EN8.SL.2', 'ref' => 'lsec.en0861.speaking_listening',
                    'es' => 'Puedo defender oralmente una opinión con razones y ejemplos.',
                    'en' => 'I can defend an opinion aloud with reasons and examples.'],
                ['code' => 'EN8.SL.3', 'ref' => 'lsec.en0861.speaking_listening',
                    'es' => 'Puedo adaptar mi forma de hablar (registro, tono) a situaciones formales e informales.',
                    'en' => 'I can adapt how I speak (register, tone) to formal and informal situations.'],
            ],
        ],
        9 => [
            'R' => [
                ['code' => 'EN9.R.1', 'ref' => 'lsec.en0861.reading.ss2',
                    'es' => 'Puedo sintetizar la información de dos textos sobre el mismo tema.',
                    'en' => 'I can synthesise information from two texts on the same topic.'],
                ['code' => 'EN9.R.2', 'ref' => 'lsec.en0861.reading.ss3',
                    'es' => 'Puedo interpretar las ideas y los temas implícitos a lo largo de un texto completo.',
                    'en' => 'I can interpret implicit ideas and themes across a whole text.'],
                ['code' => 'EN9.R.3', 'ref' => 'lsec.en0861.reading.ss4',
                    'es' => 'Puedo comparar cómo dos autores usan el lenguaje y la estructura para conseguir sus efectos.',
                    'en' => 'I can compare how two writers use language and structure to achieve their effects.'],
                ['code' => 'EN9.R.4', 'ref' => 'lsec.en0861.reading.ss5',
                    'es' => 'Puedo valorar si un texto cumple su propósito y reconocer las convenciones de su género.',
                    'en' => 'I can evaluate how well a text achieves its purpose and recognise the conventions of its genre.'],
            ],
            'W' => [
                ['code' => 'EN9.W.1', 'ref' => 'lsec.en0861.writing.ss2',
                    'es' => 'Puedo escribir en distintos géneros (ensayo, artículo, reseña, relato) controlando el propósito, el tono y el registro.',
                    'en' => 'I can write in a range of genres (essay, article, review, story), controlling purpose, tone and register.'],
                ['code' => 'EN9.W.2', 'ref' => 'lsec.en0861.writing.ss3',
                    'es' => 'Puedo organizar un texto largo con cohesión, anticipando, recapitulando y usando conectores del discurso.',
                    'en' => 'I can organise an extended text cohesively, signposting, summarising and using discourse markers.'],
                ['code' => 'EN9.W.3', 'ref' => 'lsec.en0861.writing.ss4',
                    'es' => 'Puedo variar la longitud y el tipo de oración y la puntuación para crear efectos buscados.',
                    'en' => 'I can vary sentence length, type and punctuation for deliberate effect.'],
                ['code' => 'EN9.W.4', 'ref' => 'lsec.en0861.writing.ss5',
                    'es' => 'Puedo escribir sin faltas el vocabulario académico y las palabras de ortografía irregular.',
                    'en' => 'I can spell academic vocabulary and irregular words correctly.'],
            ],
            'SL' => [
                ['code' => 'EN9.SL.1', 'ref' => 'lsec.en0861.speaking_listening',
                    'es' => 'Puedo escuchar con sentido crítico y valorar la solidez de un argumento hablado.',
                    'en' => 'I can listen critically and evaluate how strong a spoken argument is.'],
                ['code' => 'EN9.SL.2', 'ref' => 'lsec.en0861.speaking_listening',
                    'es' => 'Puedo preparar y dar una presentación formal con apoyo visual y responder a las preguntas del público.',
                    'en' => 'I can plan and give a formal presentation with visual support and answer questions from the audience.'],
                ['code' => 'EN9.SL.3', 'ref' => 'lsec.en0861.speaking_listening',
                    'es' => 'Puedo moderar una discusión de grupo, resumir las posturas y ayudar a llegar a una conclusión.',
                    'en' => 'I can lead a group discussion, summarise positions and help reach a conclusion.'],
            ],
        ],
    ],
];
