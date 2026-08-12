<?php

namespace Tests\Unit;

use App\Services\Practice\MathExpression;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Evaluador seguro de expresiones de solución (motor de práctica).
 * Nunca eval(): parser propio con variables y funciones matemáticas de lista blanca.
 */
class MathExpressionTest extends TestCase
{
    public function test_precedencia_aritmetica(): void
    {
        $this->assertEqualsWithDelta(14.0, MathExpression::evaluate('2 + 3 * 4'), 1e-12);
        $this->assertEqualsWithDelta(20.0, MathExpression::evaluate('(2 + 3) * 4'), 1e-12);
        $this->assertEqualsWithDelta(3.5, MathExpression::evaluate('7 / 2'), 1e-12);
    }

    public function test_potencia_asociativa_a_la_derecha(): void
    {
        $this->assertEqualsWithDelta(512.0, MathExpression::evaluate('2 ^ 3 ^ 2'), 1e-12);
    }

    public function test_menos_unario(): void
    {
        // Convención matemática: -2^2 = -(2^2)
        $this->assertEqualsWithDelta(-4.0, MathExpression::evaluate('-2 ^ 2'), 1e-12);
        $this->assertEqualsWithDelta(-6.0, MathExpression::evaluate('3 * -2'), 1e-12);
    }

    public function test_variables(): void
    {
        $this->assertEqualsWithDelta(
            9.8,
            MathExpression::evaluate('m * g * sin(deg2rad(theta))', ['m' => 2, 'g' => 9.8, 'theta' => 30]),
            1e-9,
        );
    }

    public function test_funciones_trigonometricas_del_plano_inclinado(): void
    {
        // μ = tan(θc) y θc = atan(μ): el par de fórmulas de CN.F.5.1.12.
        $this->assertEqualsWithDelta(26.565051177078, MathExpression::evaluate('rad2deg(atan(mu))', ['mu' => 0.5]), 1e-9);
        $this->assertEqualsWithDelta(1.0, MathExpression::evaluate('tan(deg2rad(45))'), 1e-9);
        $this->assertEqualsWithDelta(4.0, MathExpression::evaluate('sqrt(16)'), 1e-12);
        $this->assertEqualsWithDelta(3.0, MathExpression::evaluate('abs(-3)'), 1e-12);
        $this->assertEqualsWithDelta(M_PI, MathExpression::evaluate('pi'), 1e-12);
    }

    public function test_variable_desconocida_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MathExpression::evaluate('m * x', ['m' => 2]);
    }

    public function test_funcion_desconocida_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MathExpression::evaluate('system(1)', ['system' => 1]);
    }

    public function test_expresion_malformada_lanza_excepcion(): void
    {
        foreach (['2 +', '(2', '2 2', '', '* 3'] as $bad) {
            try {
                MathExpression::evaluate($bad);
                $this->fail("La expresión '{$bad}' debió lanzar InvalidArgumentException");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
