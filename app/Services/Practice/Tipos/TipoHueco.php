<?php

namespace App\Services\Practice\Tipos;

use App\Models\PracticeItem;
use App\Services\Practice\PracticeEngine;
use InvalidArgumentException;

/**
 * Rellenar el hueco, SIN banco de opciones: escribir la forma es más difícil
 * que reconocerla, y esa dificultad ES el ejercicio.
 *
 * La solución es `{lengua, textos: [...]}` — varias formas aceptadas, porque
 * «s'appelle» y «se llama Marie» pueden valer las dos. La corrección usa el
 * `Normalizador` POR LENGUA: mayúsculas y espacios se perdonan, los acentos
 * no — pero el veredicto distingue «te falta un acento» (`detalle: 'acento'`)
 * de «esa palabra no es» (`detalle: 'palabra'`): son dos errores distintos y
 * el alumno tiene que saber cuál cometió.
 */
class TipoHueco extends Tipo
{
    public function camposDeRespuesta(): array
    {
        return ['respuesta'];
    }

    public function reglas(PracticeItem $item): array
    {
        return [
            'respuesta' => 'required|array',
            'respuesta.texto' => 'required|string|max:200',
        ];
    }

    public function payload(PracticeItem $item, PracticeEngine $engine, string $seed): array
    {
        return ['statement' => $item->statement];
    }

    public function corregir(PracticeItem $item, array $data, PracticeEngine $engine, string $seed): array
    {
        $lengua = (string) $item->solucion['lengua'];
        $dado = Normalizador::normalizar((string) $data['respuesta']['texto'], $lengua);

        $detalle = 'palabra';
        $correcto = false;
        foreach ($item->solucion['textos'] as $aceptado) {
            if ($dado === Normalizador::normalizar((string) $aceptado, $lengua)) {
                $correcto = true;
                $detalle = null;
                break;
            }
            // La misma palabra sin sus acentos: error DE ACENTO, no de
            // palabra. `ou` no es `où`, pero decirle «esa palabra no es» a
            // quien solo olvidó la tilde es enseñarle mal dónde se equivocó.
            if (Normalizador::sinAcentos($dado, $lengua)
                === Normalizador::sinAcentos((string) $aceptado, $lengua)) {
                $detalle = 'acento';
            }
        }

        return [
            'is_correct' => $correcto,
            'detalle' => $detalle,
            // Lo esperado se revela DESPUÉS de responder, como expected en
            // numérico: es pedagogía, no un secreto.
            'esperado' => (string) $item->solucion['textos'][0],
            'texto' => (string) $data['respuesta']['texto'],
        ];
    }

    public function columnas(PracticeItem $item, array $veredicto, array $data, PracticeEngine $engine, string $seed): array
    {
        return [
            'params' => [],
            'answer' => null,
            'expected' => null,
            'answer_key' => null,
            'respuesta' => ['texto' => (string) $data['respuesta']['texto']],
        ];
    }

    public function desdeBanco(array $entrada): array
    {
        return [
            'statement' => $entrada['consigna'],
            // La lengua de la solución ES la lengua de la entrada: no se
            // declara dos veces para que no puedan divergir.
            'solucion' => ['lengua' => $entrada['lengua'], 'textos' => $entrada['aceptadas']],
        ];
    }

    public function alGuardar(PracticeItem $item): void
    {
        $s = $item->solucion;

        if (! is_array($s) || trim((string) ($s['lengua'] ?? '')) === '') {
            throw new InvalidArgumentException(
                'Un hueco sin lengua no se puede corregir: la normalización es POR LENGUA, no global.',
            );
        }

        $textos = $s['textos'] ?? [];
        $validos = is_array($textos)
            ? array_filter($textos, fn ($t) => is_string($t) && trim($t) !== '')
            : [];
        if ($validos === [] || count($validos) !== count($textos)) {
            throw new InvalidArgumentException(
                'Un hueco necesita al menos una forma aceptada en solucion.textos, y ninguna vacía.',
            );
        }
    }
}
