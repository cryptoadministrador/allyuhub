<?php

namespace App\Services\Practice;

use InvalidArgumentException;

/**
 * Núcleo del motor de práctica parametrizada (plan v2 §Frente 1).
 *
 * Determinismo: la semilla es sha256(item:user:intento) y cada parámetro se
 * deriva de sha256(semilla:nombre) → misma semilla, mismos números, SIEMPRE.
 * Aquí está prohibido rand()/mt_rand()/random_int(): nada de azar sin semilla.
 *
 * La verificación es programática y ocurre solo en el servidor: nunca se
 * persiste un "correcto" calculado por el cliente.
 */
class PracticeEngine
{
    /** Semilla determinista del intento: hash(item_id : user_id : n_intento). */
    public function seedFor(string $itemId, int|string $userId, int $attemptNo): string
    {
        return hash('sha256', "{$itemId}:{$userId}:{$attemptNo}");
    }

    /**
     * Instancia los parámetros del ítem a partir de la especificación:
     *   {"m": {"min": 1, "max": 20, "step": 0.5}, "g": {"const": 9.8}}
     * Los valores caen siempre sobre la rejilla min + k·step, dentro de [min, max].
     *
     * @return array<string, float>
     */
    public function sampleParams(array $spec, string $seed): array
    {
        $params = [];

        foreach ($spec as $name => $def) {
            if (array_key_exists('const', $def)) {
                $params[$name] = (float) $def['const'];

                continue;
            }
            if (! isset($def['min'], $def['max'])) {
                throw new InvalidArgumentException("El parámetro '{$name}' necesita min/max o const");
            }

            $min = (float) $def['min'];
            $max = (float) $def['max'];
            $step = (float) ($def['step'] ?? 1);
            if ($step <= 0 || $max < $min) {
                throw new InvalidArgumentException("Rango inválido para el parámetro '{$name}'");
            }

            $steps = (int) floor(($max - $min) / $step + 1e-9) + 1;
            // 12 hex = 48 bits de la sub-semilla: sesgo despreciable para rejillas reales.
            $n = hexdec(substr(hash('sha256', "{$seed}:{$name}"), 0, 12));
            $params[$name] = round($min + ($n % $steps) * $step, 6);
        }

        return $params;
    }

    /**
     * Interpola {nombre} en el enunciado multilingüe {"es": "...", "en": "..."}.
     *
     * @return array<string, string>
     */
    public function renderStatement(array $statement, array $params): array
    {
        $repl = [];
        foreach ($params as $name => $value) {
            $repl['{'.$name.'}'] = self::formatNumber((float) $value);
        }

        return array_map(fn (string $text) => strtr($text, $repl), $statement);
    }

    /**
     * Evalúa la expresión de solución con los parámetros instanciados y compara
     * la respuesta del alumno con tolerancia absoluta ('abs') o relativa ('rel').
     *
     * @return array{expected: float, is_correct: bool}
     */
    public function verify(string $expr, array $params, float $answer, float $tolerance, string $kind = 'rel'): array
    {
        $expected = MathExpression::evaluate($expr, $params);
        $margin = $kind === 'abs' ? $tolerance : $tolerance * abs($expected);

        return [
            'expected' => $expected,
            'is_correct' => is_finite($expected) && abs($answer - $expected) <= $margin + 1e-12,
        ];
    }

    // ---------- Opción múltiple ----------

    /**
     * Las opciones en el orden en que se PINTAN, barajado por la semilla.
     *
     * Ojo con el alcance: esto es cosmética y solo cosmética. La respuesta
     * viaja por CLAVE inmutable, así que si `next` y `submitAttempt` barajaran
     * distinto no pasaría absolutamente nada — no hay forma de que un orden
     * discrepante califique mal, porque el orden no entra en la comparación.
     * Se baraja para que la correcta no caiga siempre en el mismo sitio.
     *
     * Determinismo igual que los números: sha256(semilla:opcion:clave) da el
     * peso de cada opción. Aquí `shuffle()` estaría tan prohibido como `rand()`:
     * repetir la petición del mismo intento tiene que dar el mismo orden.
     *
     * @param  list<array{key: string, text: array<string, string>}>  $opciones
     * @return list<array{key: string, text: array<string, string>}>
     */
    public function shuffleOptions(array $opciones, string $seed): array
    {
        $conPeso = array_map(fn (array $o) => [
            'peso' => hash('sha256', "{$seed}:opcion:{$o['key']}"),
            'opcion' => $o,
        ], array_values($opciones));

        // Desempate por clave: orden total, sin depender del orden de llegada.
        usort($conPeso, fn (array $a, array $b) => [$a['peso'], $a['opcion']['key']]
            <=> [$b['peso'], $b['opcion']['key']]);

        return array_column($conPeso, 'opcion');
    }

    /**
     * Corrige un ítem de opción múltiple: una comparación de claves y punto.
     *
     * Sin semilla, sin orden, sin estado. Esa es la gracia — el camino `choice`
     * no depende del número de intento ni de nada que `next` y `submitAttempt`
     * tengan que recalcular igual.
     *
     * @return array{expected_key: string, is_correct: bool}
     */
    public function verifyChoice(string $correcta, string $eleccion): array
    {
        return [
            'expected_key' => $correcta,
            'is_correct' => $eleccion === $correcta,
        ];
    }

    /** 8.0 → "8", 27.5 → "27.5" (sin ceros de relleno en los enunciados). */
    private static function formatNumber(float $v): string
    {
        return rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.');
    }
}
