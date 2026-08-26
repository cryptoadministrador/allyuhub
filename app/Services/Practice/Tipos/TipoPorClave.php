<?php

namespace App\Services\Practice\Tipos;

use App\Models\PracticeItem;
use App\Services\Practice\PracticeEngine;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Opción múltiple: opciones con clave inmutable, corrección por clave, la
 * semilla baraja SOLO el pintado. La clave correcta vive en `answer_key`
 * (columna oculta) y jamás en el payload — reglas de #22, movidas aquí tal
 * cual desde el controlador.
 */
class TipoPorClave extends Tipo
{
    public function camposDeRespuesta(): array
    {
        return ['answer_key'];
    }

    public function reglas(PracticeItem $item): array
    {
        // Una clave que no está entre las del ítem es un 422, no un falso
        // «incorrecto»: el alumno no ha fallado nada.
        return ['answer_key' => ['required', 'string', Rule::in($item->clavesValidas())]];
    }

    public function payload(PracticeItem $item, PracticeEngine $engine, string $seed): array
    {
        $opciones = $item->shuffle
            ? $engine->shuffleOptions($item->opciones(), $seed)
            : $item->opciones();

        return [
            'statement' => $item->statement,
            // LISTA BLANCA campo a campo: solo clave y texto.
            'options' => array_map(fn (array $o) => [
                'key' => (string) $o['key'],
                'text' => $o['text'],
            ], $opciones),
        ];
    }

    public function corregir(PracticeItem $item, array $data, PracticeEngine $engine, string $seed): array
    {
        $result = $engine->verifyChoice((string) $item->answer_key, (string) $data['answer_key']);

        return [
            'is_correct' => $result['is_correct'],
            'expected_key' => $result['expected_key'],
            'answer_key' => (string) $data['answer_key'],
        ];
    }

    public function columnas(PracticeItem $item, array $veredicto, array $data, PracticeEngine $engine, string $seed): array
    {
        return [
            'params' => [],
            'answer' => null,
            'expected' => null,
            'answer_key' => $veredicto['answer_key'],
            'respuesta' => null,
        ];
    }

    public function desdeBanco(array $entrada): array
    {
        return [
            'statement' => $entrada['consigna'],
            // Las opciones del banco van por clave declarada, nunca por
            // posición: la clave es el id inmutable con el que se corrige.
            'options' => array_map(fn (array $o) => [
                'key' => (string) $o['clave'],
                'text' => $o['texto'],
            ], $entrada['opciones']),
            'answer_key' => (string) $entrada['correcta'],
            'shuffle' => (bool) ($entrada['barajar'] ?? true),
        ];
    }

    public function alGuardar(PracticeItem $item): void
    {
        $claves = $item->clavesValidas();

        if (count($claves) < 2) {
            throw new InvalidArgumentException(
                'Un ítem de opción múltiple necesita al menos dos opciones.',
            );
        }
        if (count($claves) !== count(array_unique($claves))) {
            throw new InvalidArgumentException('Las claves de las opciones se repiten.');
        }
        if (! in_array((string) $item->answer_key, $claves, true)) {
            throw new InvalidArgumentException(
                'answer_key no está entre las claves de las opciones: el ítem sería incontestable.',
            );
        }
    }
}
