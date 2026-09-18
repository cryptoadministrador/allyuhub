<?php

namespace Tests\Support;

use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Services\Practice\Tipos\Registro;

/**
 * UN ÍTEM DE CADA KIND del Registro, con su respuesta BUENA y una MALA. Lo
 * comparten la prueba de unidad (PR 8) y el bucle de «otra vez» (PR 13): los
 * dos oráculos recorren `Registro::kinds()`, y un kind nuevo sin fixture aquí
 * cae en los dos a la vez.
 */
trait UnoDeCadaKind
{
    private function objetivoDeKind(string $code): LearningObjective
    {
        return LearningObjective::where('native_code', $code)->firstOrFail();
    }

    /**
     * UN ÍTEM DE CADA KIND del Registro, en una lengua y sobre un descriptor de
     * la U1, con su respuesta BUENA y una MALA, y sus secretos (centinelas SIN
     * acentos, por la serialización unicode de Inertia/JSON).
     *
     * @return array<string, array{item: PracticeItem, buena: array, mala: array, secretos: list<string>}>
     */
    protected function unoDeCadaKind(string $lengua, string $code = 'A1.CO.2'): array
    {
        $obj = $this->objetivoDeKind($code)->id;
        $base = fn (string $kind, int $seq) => [
            'objective_id' => $obj, 'kind' => $kind, 'lengua' => $lengua,
            'params' => [], 'seq' => $seq, 'reviewed_at' => now(),
        ];
        $opciones = [['key' => 'a', 'text' => ['es' => 'una']], ['key' => 'b', 'text' => ['es' => 'otra']]];

        $todos = [
            PracticeItem::NUMERIC => [
                'item' => PracticeItem::create([...$base('numeric', 1),
                    'statement' => ['es' => 'Calcula el triple de {zz}'],
                    'params' => ['zz' => ['const' => 2]], 'solution_expr' => 'zz * 3',
                    'tolerance' => 0.01, 'tolerance_kind' => 'abs',
                ]),
                'buena' => ['answer' => 6], 'mala' => ['answer' => 999],
                'secretos' => ['solution_expr', 'zz * 3'],
            ],
            PracticeItem::CHOICE => [
                'item' => PracticeItem::create([...$base('choice', 2),
                    'statement' => ['es' => 'Elige la buena.'], 'options' => $opciones, 'answer_key' => 'a',
                    'attrs' => ['nota_interna' => 'CENTINELA-CHOICE-ATTRS'],
                ]),
                'buena' => ['answer_key' => 'a'], 'mala' => ['answer_key' => 'b'],
                'secretos' => ['answer_key', 'CENTINELA-CHOICE-ATTRS'],
            ],
            PracticeItem::ESCUCHA => [
                'item' => PracticeItem::create([...$base('escucha', 3),
                    'statement' => ['es' => 'Escucha y elige.'], 'options' => $opciones, 'answer_key' => 'a',
                    'audio_src' => '/audio/aabbccddeeff0011.mp3', 'transcripcion' => 'CENTINELA-ESCUCHA-TRANS',
                ]),
                'buena' => ['answer_key' => 'a'], 'mala' => ['answer_key' => 'b'],
                'secretos' => ['answer_key', 'CENTINELA-ESCUCHA-TRANS', 'transcripcion'],
            ],
            PracticeItem::HUECO => [
                'item' => PracticeItem::create([...$base('hueco', 4),
                    'statement' => ['es' => 'Completa el saludo.'],
                    'solucion' => ['lengua' => $lengua, 'textos' => ['CENTINELA-HUECO-SOL']],
                ]),
                'buena' => ['respuesta' => ['texto' => 'CENTINELA-HUECO-SOL']], 'mala' => ['respuesta' => ['texto' => 'zzz']],
                'secretos' => ['CENTINELA-HUECO-SOL', 'solucion'],
            ],
            PracticeItem::ORDEN => [
                'item' => PracticeItem::create([...$base('orden', 5),
                    'statement' => ['es' => 'Ordena.'],
                    'options' => [
                        ['key' => 'o1', 'text' => ['de' => 'eins']],
                        ['key' => 'o2', 'text' => ['de' => 'zwei']],
                        ['key' => 'o3', 'text' => ['de' => 'drei']],
                    ],
                    'solucion' => ['secuencias' => [['o2', 'o1', 'o3']]],
                ]),
                'buena' => ['respuesta' => ['ids' => ['o2', 'o1', 'o3']]], 'mala' => ['respuesta' => ['ids' => ['o1', 'o2', 'o3']]],
                'secretos' => ['"o2","o1","o3"', 'secuencias', 'solucion'],
            ],
            PracticeItem::PARES => [
                'item' => PracticeItem::create([...$base('pares', 6),
                    'statement' => ['es' => 'Empareja.'],
                    'options' => [
                        ['key' => 'x1', 'col' => 'a', 'text' => ['fr' => 'un']],
                        ['key' => 'x2', 'col' => 'a', 'text' => ['fr' => 'deux']],
                        ['key' => 'y1', 'col' => 'b', 'text' => ['es' => 'uno']],
                        ['key' => 'y2', 'col' => 'b', 'text' => ['es' => 'dos']],
                    ],
                    'solucion' => ['parejas' => [['x1', 'y1'], ['x2', 'y2']]],
                ]),
                'buena' => ['respuesta' => ['parejas' => [['x1', 'y1'], ['x2', 'y2']]]],
                'mala' => ['respuesta' => ['parejas' => [['x1', 'y2'], ['x2', 'y1']]]],
                'secretos' => ['"x1","y1"', 'parejas', 'solucion'],
            ],
            PracticeItem::DICTADO => [
                'item' => PracticeItem::create([...$base('dictado', 7),
                    'statement' => ['es' => 'Escribe lo que oyes.'],
                    'audio_src' => '/audio/aabbccddeeff0011.mp3', 'transcripcion' => 'CENTINELA-DICTADO-TRANS',
                    'solucion' => ['lengua' => $lengua, 'textos' => ['CENTINELA-DICTADO-SOL']],
                ]),
                'buena' => ['respuesta' => ['texto' => 'CENTINELA-DICTADO-SOL']], 'mala' => ['respuesta' => ['texto' => 'zzz']],
                'secretos' => ['CENTINELA-DICTADO-SOL', 'CENTINELA-DICTADO-TRANS', 'transcripcion', 'solucion'],
            ],
        ];

        // El oráculo recorre el REGISTRO: un kind nuevo sin fixture aquí no
        // nace sin prueba de unidad ni sin oráculo de no-filtración.
        foreach (Registro::kinds() as $kind) {
            $this->assertArrayHasKey($kind, $todos, "El kind «{$kind}» está en el Registro y no tiene fixture de prueba.");
        }

        return $todos;
    }

}
