<?php

/**
 * MCER · NIVEL A1 — descriptores «Puedo…» de las cinco destrezas comunicativas.
 *
 * Redactados en primera persona a partir de la PARRILLA DE AUTOEVALUACIÓN del
 * MCER (Consejo de Europa, 2001) y del Volumen Complementario (2020), que son
 * documentos PÚBLICOS Y CITABLES — al revés que los syllabus de Cambridge/IB,
 * cuyos enunciados entraron como paráfrasis sin verificar. Por eso estos
 * descriptores entran con `is_verified = true` y cada uno lleva su
 * `source_url`: la diferencia está en los datos, no en un comentario.
 *
 * LA LENGUA NO ES PARTE DEL CÓDIGO. `A1.IO.3` es el mismo descriptor en
 * italiano y en alemán: es lo que hace que los cuatro cursos sean el mismo
 * curso. La lengua es un atributo del contenido que aterriza (columna
 * `lengua` de ítems y recursos), no del marco.
 */

$parrilla = 'https://www.coe.int/en/web/portfolio/self-assessment-grid';
$companion = 'https://rm.coe.int/common-european-framework-of-reference-for-languages-learning-teaching/16809ea0d4';

return [
    'framework' => [
        'code' => 'CEFR',
        'authority' => 'Consejo de Europa',
        'kind' => 'international',
        'label' => ['es' => 'Marco Común Europeo de Referencia para las Lenguas'],
    ],
    'version' => [
        'label' => 'MCER 2001 · Volumen complementario 2020',
        'source_url' => $companion,
    ],
    'areas' => [
        ['code' => 'A1.CO', 'titulo' => ['es' => 'Comprensión oral'], 'descriptores' => [
            ['code' => 'A1.CO.1', 'source_url' => $parrilla, 'statement' => [
                'es' => 'Puedo reconocer palabras y expresiones muy básicas que se usan habitualmente, relativas a mí, a mi familia y a mi entorno inmediato, cuando se habla despacio y con claridad.',
            ]],
            ['code' => 'A1.CO.2', 'source_url' => $companion, 'statement' => [
                'es' => 'Puedo comprender saludos y fórmulas de cortesía cotidianas cuando se pronuncian despacio y con claridad.',
            ]],
            ['code' => 'A1.CO.3', 'source_url' => $companion, 'statement' => [
                'es' => 'Puedo comprender números, precios y horas cuando se dicen despacio y con claridad.',
            ]],
        ]],
        ['code' => 'A1.CE', 'titulo' => ['es' => 'Comprensión escrita'], 'descriptores' => [
            ['code' => 'A1.CE.1', 'source_url' => $parrilla, 'statement' => [
                'es' => 'Puedo comprender palabras y nombres conocidos y frases muy sencillas, por ejemplo las que hay en letreros, carteles y catálogos.',
            ]],
            ['code' => 'A1.CE.2', 'source_url' => $companion, 'statement' => [
                'es' => 'Puedo comprender mensajes breves y sencillos, por ejemplo una postal o un saludo escrito.',
            ]],
            ['code' => 'A1.CE.3', 'source_url' => $companion, 'statement' => [
                'es' => 'Puedo seguir indicaciones escritas breves y sencillas, por ejemplo cómo ir de un sitio a otro.',
            ]],
        ]],
        ['code' => 'A1.IO', 'titulo' => ['es' => 'Interacción oral'], 'descriptores' => [
            ['code' => 'A1.IO.1', 'source_url' => $parrilla, 'statement' => [
                'es' => 'Puedo participar en una conversación de forma sencilla siempre que la otra persona repita o reformule lo que dice más despacio y me ayude a expresar lo que intento decir.',
            ]],
            ['code' => 'A1.IO.2', 'source_url' => $parrilla, 'statement' => [
                'es' => 'Puedo plantear y contestar preguntas sencillas sobre temas de necesidad inmediata o asuntos muy habituales.',
            ]],
            ['code' => 'A1.IO.3', 'source_url' => $companion, 'statement' => [
                'es' => 'Puedo presentarme, presentar a otra persona, y pedir y dar datos personales básicos: dónde vivo, qué tengo, a quién conozco.',
            ]],
        ]],
        ['code' => 'A1.PO', 'titulo' => ['es' => 'Producción oral'], 'descriptores' => [
            ['code' => 'A1.PO.1', 'source_url' => $parrilla, 'statement' => [
                'es' => 'Puedo utilizar expresiones y frases sencillas para describir el lugar donde vivo y las personas que conozco.',
            ]],
            ['code' => 'A1.PO.2', 'source_url' => $companion, 'statement' => [
                'es' => 'Puedo describirme a mí mismo, decir qué hago y dónde vivo, con frases aisladas y sencillas.',
            ]],
        ]],
        ['code' => 'A1.EE', 'titulo' => ['es' => 'Expresión escrita'], 'descriptores' => [
            ['code' => 'A1.EE.1', 'source_url' => $parrilla, 'statement' => [
                'es' => 'Puedo escribir postales cortas y sencillas, por ejemplo para enviar felicitaciones.',
            ]],
            ['code' => 'A1.EE.2', 'source_url' => $parrilla, 'statement' => [
                'es' => 'Puedo rellenar formularios con datos personales: mi nombre, mi nacionalidad y mi dirección.',
            ]],
        ]],
    ],
];
