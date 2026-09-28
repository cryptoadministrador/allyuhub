<?php

/**
 * LAS RÚBRICAS DE PRODUCCIÓN — el criterio con el que un docente corrige lo que
 * un alumno escribe o dice. VIVEN EN EL CONTENIDO, no hardcodeadas en la vista:
 * el panel del docente las lee de aquí, así que ajustar un criterio es tocar
 * este fichero, no el JSX.
 *
 * Forma fija: 4 criterios × 3 niveles (0 = flojo, 1 = en camino, 2 = del
 * nivel). El nivel se guarda como índice inmutable, no como texto — así
 * reescribir el enunciado de un nivel no recalifica lo ya corregido.
 *
 * Una rúbrica por DESTREZA productiva (escritura / voz). Son las de A1 y valen
 * para las nueve unidades; la firma de `Rubricas::para` lleva la unidad para
 * que una rúbrica por unidad, si algún día hace falta, no cambie quién la
 * llama. Decisión (misión, «decidí yo»): una sola rúbrica de A1 por destreza,
 * no nueve iguales copiadas.
 */

return [
    'escritura' => [
        'titulo' => 'Escritura · A1',
        'criterios' => [
            [
                'clave' => 'tarea',
                'titulo' => 'Cumple la tarea',
                'niveles' => [
                    'No responde a lo que se pedía',
                    'Responde solo en parte',
                    'Responde a todo lo pedido',
                ],
            ],
            [
                'clave' => 'vocabulario',
                'titulo' => 'Vocabulario',
                'niveles' => [
                    'Muy pobre o ajeno a la unidad',
                    'Suficiente, con repeticiones',
                    'Variado para el nivel',
                ],
            ],
            [
                'clave' => 'gramatica',
                'titulo' => 'Gramática y estructuras',
                'niveles' => [
                    'Errores que impiden entender',
                    'Errores que no impiden entender',
                    'Estructuras del nivel bien usadas',
                ],
            ],
            [
                'clave' => 'ortografia',
                'titulo' => 'Ortografía',
                'niveles' => [
                    'Dificulta la lectura',
                    'Errores puntuales',
                    'Correcta para el nivel',
                ],
            ],
        ],
    ],

    'voz' => [
        'titulo' => 'Producción oral · A1',
        'criterios' => [
            [
                'clave' => 'tarea',
                'titulo' => 'Cumple la tarea',
                'niveles' => [
                    'No responde a lo que se pedía',
                    'Responde solo en parte',
                    'Responde a todo lo pedido',
                ],
            ],
            [
                'clave' => 'vocabulario',
                'titulo' => 'Vocabulario',
                'niveles' => [
                    'Muy pobre o ajeno a la unidad',
                    'Suficiente, con repeticiones',
                    'Variado para el nivel',
                ],
            ],
            [
                'clave' => 'fluidez',
                'titulo' => 'Fluidez',
                'niveles' => [
                    'Se interrumpe constantemente',
                    'Pausas frecuentes, pero se sigue',
                    'Ritmo adecuado para el nivel',
                ],
            ],
            [
                'clave' => 'pronunciacion',
                'titulo' => 'Pronunciación',
                'niveles' => [
                    'Difícil de entender',
                    'Comprensible con esfuerzo',
                    'Clara para el nivel',
                ],
            ],
        ],
    ],

    /*
     * RÚBRICAS POR CURSO (PR 19). Las de arriba son las de A1 y valen para los
     * cuatro cursos del MCER. Un curso que corrige otra cosa declara las suyas
     * aquí, por lengua; `Rubricas::para` cae a las de arriba si no las hay.
     * Misma forma fija (4 criterios × 3 niveles): el panel del docente y la
     * validación no cambian.
     *
     * Inglés 0861 (primera lengua, Stages 7-9): se corrige propósito,
     * organización y control de la lengua, no «vocabulario de la unidad» como
     * en A1. Redactadas para el docente, en español.
     */
    'cursos' => [
        'en' => [
            'escritura' => [
                'titulo' => 'Escritura · Inglés 0861',
                'criterios' => [
                    ['clave' => 'contenido', 'titulo' => 'Contenido y propósito', 'niveles' => [
                        'No responde a la tarea o se desvía del propósito',
                        'Responde a la tarea con ideas poco desarrolladas',
                        'Ideas relevantes y desarrolladas, adecuadas al propósito y al lector',
                    ]],
                    ['clave' => 'organizacion', 'titulo' => 'Organización', 'niveles' => [
                        'Sin estructura: ideas sueltas',
                        'Párrafos reconocibles, conectores limitados o repetidos',
                        'Párrafos bien construidos y enlazados con conectores variados',
                    ]],
                    ['clave' => 'lengua', 'titulo' => 'Oraciones, vocabulario y registro', 'niveles' => [
                        'Oraciones simples y repetitivas; registro inadecuado',
                        'Alguna variedad de oraciones y vocabulario; registro irregular',
                        'Oraciones y vocabulario variados y precisos; registro adecuado',
                    ]],
                    ['clave' => 'correccion', 'titulo' => 'Puntuación y ortografía', 'niveles' => [
                        'Errores frecuentes que dificultan la lectura',
                        'Errores puntuales que no impiden entender',
                        'Puntuación y ortografía correctas casi siempre',
                    ]],
                ],
            ],
            'voz' => [
                'titulo' => 'Expresión oral · Inglés 0861',
                'criterios' => [
                    ['clave' => 'contenido', 'titulo' => 'Contenido y propósito', 'niveles' => [
                        'No responde a la tarea',
                        'Responde con ideas poco desarrolladas o sin ejemplos',
                        'Ideas claras, apoyadas en razones o ejemplos',
                    ]],
                    ['clave' => 'organizacion', 'titulo' => 'Organización del discurso', 'niveles' => [
                        'Sin inicio ni cierre; ideas sueltas',
                        'Estructura reconocible, pocos marcadores del discurso',
                        'Introducción, desarrollo y cierre con marcadores claros',
                    ]],
                    ['clave' => 'fluidez', 'titulo' => 'Fluidez y claridad', 'niveles' => [
                        'Se interrumpe constantemente o no se entiende',
                        'Pausas frecuentes, pero se sigue',
                        'Ritmo natural, pronunciación clara',
                    ]],
                    ['clave' => 'registro', 'titulo' => 'Registro y público', 'niveles' => [
                        'Registro inadecuado para la situación',
                        'Registro adecuado solo en parte',
                        'Registro, tono y volumen adecuados al público',
                    ]],
                ],
            ],
        ],
    ],
];
