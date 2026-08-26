<?php

namespace App\Services\Practice\Tipos;

use App\Models\PracticeItem;
use App\Services\Practice\PracticeEngine;
use InvalidArgumentException;

/**
 * El tipo de siempre: rangos en `params`, expresión en `solution_expr`,
 * instanciación determinista por semilla y verificación con tolerancia.
 * Movido aquí TAL CUAL desde el controlador — el comportamiento lo fijan los
 * tests de siempre (PracticeApiTest y compañía), que no cambiaron.
 */
class TipoNumerico extends Tipo
{
    public function camposDeRespuesta(): array
    {
        return ['answer'];
    }

    public function reglas(PracticeItem $item): array
    {
        return ['answer' => 'required|numeric'];
    }

    public function payload(PracticeItem $item, PracticeEngine $engine, string $seed): array
    {
        $params = $engine->sampleParams($item->params, $seed);

        return [
            'statement' => $engine->renderStatement($item->statement, $params),
            'params' => $params,
            'answer_unit' => $item->answer_unit,
            'tolerance' => $item->tolerance,
            'tolerance_kind' => $item->tolerance_kind,
        ];
    }

    public function corregir(PracticeItem $item, array $data, PracticeEngine $engine, string $seed): array
    {
        $params = $engine->sampleParams($item->params, $seed);
        $result = $engine->verify(
            $item->solution_expr, $params, (float) $data['answer'],
            $item->tolerance, $item->tolerance_kind,
        );

        return [
            'is_correct' => $result['is_correct'],
            'expected' => $result['expected'],
            'answer' => (float) $data['answer'],
        ];
    }

    public function columnas(PracticeItem $item, array $veredicto, array $data, PracticeEngine $engine, string $seed): array
    {
        return [
            'params' => $engine->sampleParams($item->params, $seed),
            'answer' => $veredicto['answer'],
            'expected' => $veredicto['expected'],
            'answer_key' => null,
            'respuesta' => null,
        ];
    }

    public function alGuardar(PracticeItem $item): void
    {
        if (trim((string) $item->solution_expr) === '') {
            throw new InvalidArgumentException(
                'Un ítem numérico sin solution_expr no se puede corregir.',
            );
        }
    }
}
