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
];
