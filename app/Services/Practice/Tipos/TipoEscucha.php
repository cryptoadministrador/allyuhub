<?php

namespace App\Services\Practice\Tipos;

use App\Models\PracticeItem;
use App\Services\Practice\PracticeEngine;
use App\Services\Audio\AlmacenDeAudio;
use InvalidArgumentException;

/**
 * La mecánica de choice con un clip delante (#26). El clip SÍ viaja en next;
 * la transcripción NO — es columna oculta como la clave, y se revela en el
 * veredicto: leer lo que oíste es la mitad del ejercicio.
 */
class TipoEscucha extends TipoPorClave
{
    public function payload(PracticeItem $item, PracticeEngine $engine, string $seed): array
    {
        return [...parent::payload($item, $engine, $seed), 'audio_src' => $item->audio_src];
    }

    public function corregir(PracticeItem $item, array $data, PracticeEngine $engine, string $seed): array
    {
        return [...parent::corregir($item, $data, $engine, $seed),
            'transcripcion' => $item->transcripcion];
    }

    public function desdeBanco(array $entrada): array
    {
        return [...parent::desdeBanco($entrada),
            // `audio_src` ya viene resuelto: el banco nombra el clip por su
            // clave (it/u1/saludo) y el sembrador lo publicó en el almacén.
            'audio_src' => $entrada['audio_src'],
            'transcripcion' => $entrada['transcripcion'],
        ];
    }

    public function alGuardar(PracticeItem $item): void
    {
        parent::alGuardar($item);

        if ($item->audio_src === null) {
            throw new InvalidArgumentException('Un ítem escucha sin clip no existe: falta audio_src.');
        }
        if (trim((string) $item->transcripcion) === '') {
            throw new InvalidArgumentException(
                'Un ítem escucha sin transcripción no existe para quien no puede oírlo.',
            );
        }
    }
}
