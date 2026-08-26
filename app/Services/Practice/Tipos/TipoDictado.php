<?php

namespace App\Services\Practice\Tipos;

use App\Models\PracticeItem;
use App\Services\Practice\PracticeEngine;
use InvalidArgumentException;

/**
 * Oír y escribir: la normalización de hueco sobre el audio de #26. El clip
 * viaja en next; la transcripción se revela en el veredicto — junto al
 * `detalle` de acento/palabra, es lo que convierte el fallo en aprendizaje.
 */
class TipoDictado extends TipoHueco
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

    public function alGuardar(PracticeItem $item): void
    {
        parent::alGuardar($item);

        if ($item->audio_src === null) {
            throw new InvalidArgumentException('Un dictado sin clip no existe: falta audio_src.');
        }
        if (trim((string) $item->transcripcion) === '') {
            throw new InvalidArgumentException('Un dictado sin transcripción no se puede revelar.');
        }
    }
}
