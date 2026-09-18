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
        // La LENGUA viaja con el ítem (no la solución): la interfaz la necesita
        // para decir «te falta el tono» en chino donde en francés dice «revisa
        // el acento», y para pedir el pinyin con tonos o con números. Es la
        // lengua del ítem, que ya es pública (se pidió con ?lengua=).
        return ['statement' => $item->statement, 'lengua' => (string) ($item->solucion['lengua'] ?? $item->lengua)];
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

    public function revelan(): array
    {
        return ['esperado'];
    }

    /**
     * Andamiaje: el texto libre se convierte en TRES OPCIONES —la correcta y
     * dos distractores— sacadas de la unidad: las soluciones de otros huecos y
     * dictados FIRMADOS de la misma lengua (los del mismo descriptor primero),
     * que son palabras del nivel y no inventos. Si no hay bastantes, la propia
     * palabra sin sus acentos y con dos letras cambiadas. Orden determinista
     * por la semilla: la buena no va siempre en el mismo sitio.
     *
     * @return array{opciones: list<string>}
     */
    public function andamiaje(PracticeItem $item, array $veredicto, PracticeEngine $engine, string $seed): ?array
    {
        $lengua = (string) $item->solucion['lengua'];
        $aceptadas = collect($item->solucion['textos'])->map(fn ($t) => Normalizador::normalizar((string) $t, $lengua));
        $esperado = (string) $item->solucion['textos'][0];

        $vecinas = PracticeItem::query()
            ->where('lengua', $item->lengua)
            ->whereIn('kind', [PracticeItem::HUECO, PracticeItem::DICTADO])
            ->whereNotNull('reviewed_at')
            ->where('id', '!=', $item->id)
            ->get(['id', 'objective_id', 'solucion'])
            ->map(fn (PracticeItem $v) => (string) ($v->solucion['textos'][0] ?? ''))
            ->filter(fn ($t) => $t !== '' && ! $aceptadas->contains(Normalizador::normalizar($t, $lengua)))
            ->unique(fn ($t) => Normalizador::normalizar($t, $lengua))
            ->sortBy(fn ($t) => hash('sha256', "{$seed}:distractor:{$t}"))
            ->values();

        $distractores = $vecinas->take(2)->all();
        foreach ($this->variantes($esperado, $lengua) as $variante) {
            if (count($distractores) >= 2) {
                break;
            }
            if (! $aceptadas->contains(Normalizador::normalizar($variante, $lengua))
                && ! in_array($variante, $distractores, true)) {
                $distractores[] = $variante;
            }
        }

        $opciones = collect([$esperado, ...$distractores])
            ->sortBy(fn ($t) => hash('sha256', "{$seed}:opcion:{$t}"))
            ->values()->all();

        return ['opciones' => $opciones];
    }

    /** Distractores de última hora: la misma palabra, mal escrita a propósito. */
    private function variantes(string $esperado, string $lengua): array
    {
        $sinAcentos = Normalizador::sinAcentos($esperado, $lengua);
        $letras = mb_str_split($esperado);
        $cambiada = count($letras) >= 3
            ? implode('', [...array_slice($letras, 0, 1), $letras[2], $letras[1], ...array_slice($letras, 3)])
            : $esperado.$esperado;

        return [$sinAcentos, $cambiada, mb_strtoupper(mb_substr($esperado, 0, 1)).mb_substr($esperado, 1).'e'];
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
