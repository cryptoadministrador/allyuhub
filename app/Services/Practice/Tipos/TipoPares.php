<?php

namespace App\Services\Practice\Tipos;

use App\Models\PracticeItem;
use App\Services\Practice\PracticeEngine;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Emparejar, n a n, con DOS O MÁS columnas: en chino son tres — carácter,
 * pinyin y significado. Cada elemento lleva su id y su columna; el alumno
 * devuelve TUPLAS DE IDS (una clave por columna, en el orden de las
 * columnas), así que el barajado de pintado no participa en la corrección.
 *
 * LA REGLA DE CRÉDITO, decidida y no emergida: `is_correct` es TODO O NADA
 * —alimenta el dominio y la nota AGS del aula, y un «casi» que cuenta como
 * acierto inflaría el dominio sin que nadie lo decidiera—; el VEREDICTO sí
 * enseña el parcial (cuántas parejas de cuántas), porque en A1 eso es lo que
 * enseña. Fijada en TiposDeLenguaTest.
 */
class TipoPares extends Tipo
{
    public function camposDeRespuesta(): array
    {
        return ['respuesta'];
    }

    /** Los nombres de columna, ordenados: la posición de la tupla es la columna. */
    private function columnasDe(PracticeItem $item): array
    {
        $cols = array_values(array_unique(array_map(
            fn (array $o) => (string) ($o['col'] ?? ''), $item->opciones(),
        )));
        sort($cols);

        return $cols;
    }

    /** @return array<string, list<string>> claves por columna, ordenadas por columna */
    private function clavesPorColumna(PracticeItem $item): array
    {
        $porCol = [];
        foreach ($item->opciones() as $o) {
            $porCol[(string) ($o['col'] ?? '')][] = (string) $o['key'];
        }
        ksort($porCol);

        return $porCol;
    }

    public function reglas(PracticeItem $item): array
    {
        $porCol = $this->clavesPorColumna($item);
        $numParejas = count(reset($porCol) ?: []);
        $numColumnas = count($porCol);

        return [
            'respuesta' => 'required|array',
            'respuesta.parejas' => ['required', 'array', 'size:'.$numParejas],
            'respuesta.parejas.*' => ['array', 'size:'.$numColumnas],
            'respuesta.parejas.*.*' => ['string', 'distinct:strict', Rule::in($item->clavesValidas())],
        ];
    }

    public function payload(PracticeItem $item, PracticeEngine $engine, string $seed): array
    {
        // Barajado DENTRO de cada columna (una semilla derivada por columna):
        // las columnas mantienen su orden, los elementos no.
        $barajadas = [];
        foreach (array_keys($this->clavesPorColumna($item)) as $col) {
            $delCol = array_values(array_filter(
                $item->opciones(), fn (array $o) => (string) ($o['col'] ?? '') === $col,
            ));
            foreach ($engine->shuffleOptions($delCol, "{$seed}:col:{$col}") as $o) {
                $barajadas[] = [
                    'key' => (string) $o['key'],
                    'col' => (string) $o['col'],
                    'text' => $o['text'],
                ];
            }
        }

        return ['statement' => $item->statement, 'options' => $barajadas];
    }

    public function corregir(PracticeItem $item, array $data, PracticeEngine $engine, string $seed): array
    {
        $columnas = $this->columnasDe($item);
        $colDe = [];
        foreach ($item->opciones() as $o) {
            $colDe[(string) $o['key']] = (string) ($o['col'] ?? '');
        }

        // Estructura: cada tupla lleva UNA clave de cada columna, en orden.
        // Lo que no cumpla eso es un cliente roto: 422, no un «incorrecto».
        $dadas = [];
        foreach ($data['respuesta']['parejas'] as $tupla) {
            $tupla = array_map(strval(...), array_values($tupla));
            foreach ($tupla as $i => $clave) {
                if ($colDe[$clave] !== $columnas[$i]) {
                    throw ValidationException::withMessages([
                        'respuesta' => "La posición {$i} de cada pareja corresponde a la columna «{$columnas[$i]}».",
                    ]);
                }
            }
            $dadas[] = $tupla;
        }

        $esperadas = array_map(
            fn (array $t) => array_map(strval(...), array_values($t)),
            $item->solucion['parejas'],
        );

        // Conjunto contra conjunto: el orden de la LISTA de parejas no
        // significa nada (solo el interno de la tupla, que es la columna).
        $aciertos = count(array_filter($dadas, fn (array $t) => in_array($t, $esperadas, true)));

        return [
            'is_correct' => $aciertos === count($esperadas),
            'parejas_correctas' => $aciertos,
            'total' => count($esperadas),
            'parejas' => $dadas,
            'parejas_esperadas' => $esperadas,
        ];
    }

    public function columnas(PracticeItem $item, array $veredicto, array $data, PracticeEngine $engine, string $seed): array
    {
        return [
            'params' => [],
            'answer' => null,
            'expected' => null,
            'answer_key' => null,
            'respuesta' => ['parejas' => $veredicto['parejas']],
        ];
    }

    public function alGuardar(PracticeItem $item): void
    {
        $porCol = $this->clavesPorColumna($item);

        if (count($porCol) < 2) {
            throw new InvalidArgumentException('Un pares necesita al menos dos columnas en options.');
        }
        $tamanos = array_map(count(...), $porCol);
        if (count(array_unique($tamanos)) !== 1 || reset($tamanos) < 2) {
            throw new InvalidArgumentException(
                'Las columnas de un pares llevan el MISMO número de elementos (n a n), y al menos dos.',
            );
        }

        $claves = $item->clavesValidas();
        if (count($claves) !== count(array_unique($claves))) {
            throw new InvalidArgumentException('Las claves de options se repiten.');
        }

        $columnas = array_keys($porCol);
        $parejas = $item->solucion['parejas'] ?? null;
        if (! is_array($parejas) || count($parejas) !== reset($tamanos)) {
            throw new InvalidArgumentException(
                'solucion.parejas tiene que emparejar TODOS los elementos: una tupla por fila.',
            );
        }

        $usadas = [];
        foreach ($parejas as $tupla) {
            $t = is_array($tupla) ? array_map(strval(...), array_values($tupla)) : [];
            if (count($t) !== count($columnas)) {
                throw new InvalidArgumentException(
                    'Cada pareja de solucion.parejas lleva una clave por columna.',
                );
            }
            foreach ($t as $i => $clave) {
                if (! in_array($clave, $porCol[$columnas[$i]], true)) {
                    throw new InvalidArgumentException(
                        "La clave «{$clave}» no pertenece a la columna «{$columnas[$i]}».",
                    );
                }
                if (isset($usadas[$clave])) {
                    throw new InvalidArgumentException(
                        "La clave «{$clave}» se usa dos veces en solucion.parejas.",
                    );
                }
                $usadas[$clave] = true;
            }
        }
    }
}
