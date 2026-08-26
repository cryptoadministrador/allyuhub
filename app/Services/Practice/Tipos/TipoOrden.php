<?php

namespace App\Services\Practice\Tipos;

use App\Models\PracticeItem;
use App\Services\Practice\PracticeEngine;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Ordenar palabras. Cada palabra lleva su id inmutable; el alumno devuelve
 * una SECUENCIA DE IDS — la posición pintada no participa en la corrección
 * por construcción, que es la regla que hizo imposible la clase de bug de #22.
 *
 * La solución es un CONJUNTO de secuencias válidas, no una: «Morgen gehe ich
 * zur Schule» y «Ich gehe morgen zur Schule» están las dos bien, y un alemán
 * que antepone el complemento puede estar en lo cierto. Aceptar el conjunto
 * no es aceptar de más: lo que no está en el conjunto es error.
 */
class TipoOrden extends Tipo
{
    public function camposDeRespuesta(): array
    {
        return ['respuesta'];
    }

    public function reglas(PracticeItem $item): array
    {
        $claves = $item->clavesValidas();

        // Un id inventado, repetido o de menos es un 422 —cliente roto—, no
        // un falso «incorrecto»: el alumno no ha fallado nada.
        return [
            'respuesta' => 'required|array',
            'respuesta.ids' => ['required', 'array', 'size:'.count($claves)],
            'respuesta.ids.*' => ['string', 'distinct:strict', Rule::in($claves)],
        ];
    }

    public function payload(PracticeItem $item, PracticeEngine $engine, string $seed): array
    {
        return [
            'statement' => $item->statement,
            // SIEMPRE barajadas: servirlas en orden regalaría la respuesta.
            // El barajado es de pintado; la corrección va por ids.
            'options' => array_map(fn (array $o) => [
                'key' => (string) $o['key'],
                'text' => $o['text'],
            ], $engine->shuffleOptions($item->opciones(), $seed)),
        ];
    }

    public function corregir(PracticeItem $item, array $data, PracticeEngine $engine, string $seed): array
    {
        $dada = array_map(strval(...), array_values($data['respuesta']['ids']));

        $valida = false;
        foreach ($item->solucion['secuencias'] as $secuencia) {
            if ($dada === array_map(strval(...), array_values($secuencia))) {
                $valida = true;
                break;
            }
        }

        return [
            'is_correct' => $valida,
            'ids' => $dada,
            // La primera secuencia válida, para enseñarla tras responder. El
            // cliente pinta las palabras a partir de sus ids.
            'secuencia_correcta' => array_map(strval(...), $item->solucion['secuencias'][0]),
        ];
    }

    public function columnas(PracticeItem $item, array $veredicto, array $data, PracticeEngine $engine, string $seed): array
    {
        return [
            'params' => [],
            'answer' => null,
            'expected' => null,
            'answer_key' => null,
            'respuesta' => ['ids' => $veredicto['ids']],
        ];
    }

    public function desdeBanco(array $entrada): array
    {
        return [
            'statement' => $entrada['consigna'],
            'options' => array_map(fn (array $p) => [
                'key' => (string) $p['clave'],
                'text' => $p['texto'],
            ], $entrada['palabras']),
            'solucion' => ['secuencias' => $entrada['secuencias']],
        ];
    }

    public function alGuardar(PracticeItem $item): void
    {
        $claves = $item->clavesValidas();

        if (count($claves) < 2 || count($claves) !== count(array_unique($claves))) {
            throw new InvalidArgumentException(
                'Un orden necesita al menos dos palabras con ids únicos en options.',
            );
        }

        $secuencias = $item->solucion['secuencias'] ?? null;
        if (! is_array($secuencias) || $secuencias === []) {
            throw new InvalidArgumentException(
                'Un orden sin solucion.secuencias no se puede corregir.',
            );
        }

        $canon = $claves;
        sort($canon);
        foreach ($secuencias as $secuencia) {
            $s = is_array($secuencia) ? array_map(strval(...), $secuencia) : [];
            $ordenada = $s;
            sort($ordenada);
            // Cada secuencia usa TODAS las palabras exactamente una vez: un
            // trozo o una palabra fantasma es un ítem roto, no una variante.
            if ($ordenada !== $canon) {
                throw new InvalidArgumentException(
                    'Cada secuencia de solucion.secuencias tiene que ser una permutación '.
                    'exacta de las claves de options.',
                );
            }
        }
    }
}
