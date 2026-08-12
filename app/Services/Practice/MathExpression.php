<?php

namespace App\Services\Practice;

use InvalidArgumentException;

/**
 * Evaluador seguro de expresiones de solución del motor de práctica.
 *
 * Parser propio (shunting-yard) — NUNCA eval(): solo números, variables
 * declaradas, operadores aritméticos y funciones de lista blanca. La respuesta
 * del alumno se verifica SIEMPRE en el servidor contra estas expresiones.
 */
class MathExpression
{
    /** Funciones de un argumento permitidas en expresiones de solución. */
    private const FUNCTIONS = [
        'sin', 'cos', 'tan', 'asin', 'acos', 'atan',
        'sqrt', 'abs', 'deg2rad', 'rad2deg',
    ];

    private const CONSTANTS = ['pi' => M_PI];

    /** operador => [precedencia, asociativa a la derecha]. 'neg' es el menos unario. */
    private const OPERATORS = [
        '+' => [1, false], '-' => [1, false],
        '*' => [2, false], '/' => [2, false],
        'neg' => [3, true],
        '^' => [4, true],
    ];

    /** Evalúa la expresión con las variables dadas (int|float). */
    public static function evaluate(string $expr, array $vars = []): float
    {
        return self::evalRpn(self::toRpn(self::tokenize($expr)), $vars);
    }

    /** @return list<array{type: string, value: string}> */
    private static function tokenize(string $expr): array
    {
        $tokens = [];
        $len = strlen($expr);
        $i = 0;

        while ($i < $len) {
            $c = $expr[$i];

            if (ctype_space($c)) {
                $i++;
            } elseif (preg_match('/\G\d+(\.\d+)?/', $expr, $m, 0, $i)) {
                $tokens[] = ['type' => 'num', 'value' => $m[0]];
                $i += strlen($m[0]);
            } elseif (preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $expr, $m, 0, $i)) {
                $tokens[] = ['type' => 'ident', 'value' => $m[0]];
                $i += strlen($m[0]);
            } elseif (str_contains('+-*/^()', $c)) {
                $tokens[] = ['type' => $c === '(' || $c === ')' ? $c : 'op', 'value' => $c];
                $i++;
            } else {
                throw new InvalidArgumentException("Carácter no permitido en la expresión: '{$c}'");
            }
        }

        if ($tokens === []) {
            throw new InvalidArgumentException('Expresión vacía');
        }

        return $tokens;
    }

    /**
     * Shunting-yard con validación de sintaxis (operando donde toca, paréntesis
     * balanceados, menos unario detectado por contexto).
     *
     * @return list<array{type: string, value: string}>
     */
    private static function toRpn(array $tokens): array
    {
        $output = [];
        $stack = [];
        $expectOperand = true;   // al inicio, tras '(' o tras un operador

        foreach ($tokens as $k => $t) {
            switch ($t['type']) {
                case 'num':
                    if (! $expectOperand) {
                        throw new InvalidArgumentException('Sintaxis inválida: operando inesperado');
                    }
                    $output[] = $t;
                    $expectOperand = false;
                    break;

                case 'ident':
                    if (! $expectOperand) {
                        throw new InvalidArgumentException('Sintaxis inválida: operando inesperado');
                    }
                    $isCall = ($tokens[$k + 1]['type'] ?? null) === '(';
                    if ($isCall) {
                        if (! in_array($t['value'], self::FUNCTIONS, true)) {
                            throw new InvalidArgumentException("Función no permitida: {$t['value']}");
                        }
                        $stack[] = ['type' => 'func', 'value' => $t['value']];
                        // sigue esperando operando (el argumento tras el paréntesis)
                    } else {
                        $output[] = ['type' => 'var', 'value' => $t['value']];
                        $expectOperand = false;
                    }
                    break;

                case 'op':
                    $op = $t['value'];
                    if ($expectOperand) {
                        if ($op !== '-') {
                            throw new InvalidArgumentException("Sintaxis inválida: operador '{$op}' sin operando");
                        }
                        $op = 'neg';   // menos unario
                    }
                    [$prec, $rightAssoc] = self::OPERATORS[$op];
                    while ($stack !== []) {
                        $top = end($stack);
                        if ($top['type'] !== 'op') {
                            break;
                        }
                        [$topPrec] = self::OPERATORS[$top['value']];
                        if ($topPrec > $prec || ($topPrec === $prec && ! $rightAssoc)) {
                            $output[] = array_pop($stack);
                        } else {
                            break;
                        }
                    }
                    $stack[] = ['type' => 'op', 'value' => $op];
                    $expectOperand = true;
                    break;

                case '(':
                    if (! $expectOperand) {
                        throw new InvalidArgumentException('Sintaxis inválida: "(" inesperado');
                    }
                    $stack[] = $t;
                    break;

                case ')':
                    if ($expectOperand) {
                        throw new InvalidArgumentException('Sintaxis inválida: ")" tras operador');
                    }
                    while ($stack !== [] && end($stack)['type'] !== '(') {
                        $output[] = array_pop($stack);
                    }
                    if ($stack === []) {
                        throw new InvalidArgumentException('Paréntesis desbalanceados');
                    }
                    array_pop($stack);   // descarta '('
                    if ($stack !== [] && end($stack)['type'] === 'func') {
                        $output[] = array_pop($stack);
                    }
                    break;
            }
        }

        if ($expectOperand) {
            throw new InvalidArgumentException('Sintaxis inválida: la expresión termina en operador');
        }
        while ($stack !== []) {
            $top = array_pop($stack);
            if ($top['type'] === '(') {
                throw new InvalidArgumentException('Paréntesis desbalanceados');
            }
            $output[] = $top;
        }

        return $output;
    }

    private static function evalRpn(array $rpn, array $vars): float
    {
        $stack = [];

        foreach ($rpn as $t) {
            switch ($t['type']) {
                case 'num':
                    $stack[] = (float) $t['value'];
                    break;

                case 'var':
                    $name = $t['value'];
                    if (array_key_exists($name, $vars)) {
                        if (! is_numeric($vars[$name])) {
                            throw new InvalidArgumentException("La variable '{$name}' no es numérica");
                        }
                        $stack[] = (float) $vars[$name];
                    } elseif (array_key_exists($name, self::CONSTANTS)) {
                        $stack[] = self::CONSTANTS[$name];
                    } else {
                        throw new InvalidArgumentException("Variable no definida: {$name}");
                    }
                    break;

                case 'func':
                    if ($stack === []) {
                        throw new InvalidArgumentException("Falta el argumento de {$t['value']}()");
                    }
                    $stack[] = ($t['value'])(array_pop($stack));
                    break;

                case 'op':
                    if ($t['value'] === 'neg') {
                        if ($stack === []) {
                            throw new InvalidArgumentException('Falta el operando del menos unario');
                        }
                        $stack[] = -array_pop($stack);
                        break;
                    }
                    if (count($stack) < 2) {
                        throw new InvalidArgumentException("Faltan operandos para '{$t['value']}'");
                    }
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = match ($t['value']) {
                        '+' => $a + $b,
                        '-' => $a - $b,
                        '*' => $a * $b,
                        '/' => fdiv($a, $b),   // INF/NAN en vez de excepción; verify() lo marcará incorrecto
                        '^' => $a ** $b,
                    };
                    break;
            }
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException('Expresión malformada');
        }

        return $stack[0];
    }
}
