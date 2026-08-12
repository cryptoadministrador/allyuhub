<?php

namespace Tests\Unit;

use App\Services\Practice\PracticeEngine;
use PHPUnit\Framework\TestCase;

/**
 * Generación determinista de parámetros del motor de práctica:
 * semilla = sha256(item:user:intento). Misma semilla → mismos números, siempre.
 * PROHIBIDO rand()/mt_rand()/random_int() sin semilla en este motor.
 */
class PracticeEngineTest extends TestCase
{
    private const SPEC = [
        'm' => ['min' => 1, 'max' => 20, 'step' => 0.5],
        'theta' => ['min' => 10, 'max' => 45, 'step' => 1],
        'g' => ['const' => 9.8],
    ];

    public function test_semilla_estable_y_sensible_a_cada_componente(): void
    {
        $engine = new PracticeEngine;

        $seed = $engine->seedFor('item-a', 1, 1);
        $this->assertSame($seed, $engine->seedFor('item-a', 1, 1));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $seed);

        $this->assertNotSame($seed, $engine->seedFor('item-b', 1, 1));
        $this->assertNotSame($seed, $engine->seedFor('item-a', 2, 1));
        $this->assertNotSame($seed, $engine->seedFor('item-a', 1, 2));
    }

    public function test_misma_semilla_mismos_parametros(): void
    {
        $engine = new PracticeEngine;
        $seed = $engine->seedFor('item-a', 1, 1);

        $this->assertSame(
            $engine->sampleParams(self::SPEC, $seed),
            $engine->sampleParams(self::SPEC, $seed),
        );
    }

    public function test_alumnos_distintos_numeros_distintos(): void
    {
        $engine = new PracticeEngine;

        // Entradas fijas → salida determinista: esta desigualdad no puede "flakear".
        $a = $engine->sampleParams(self::SPEC, $engine->seedFor('item-a', 1, 1));
        $b = $engine->sampleParams(self::SPEC, $engine->seedFor('item-a', 2, 1));
        $this->assertNotSame($a, $b);

        // Y el intento siguiente del mismo alumno también cambia los números.
        $a2 = $engine->sampleParams(self::SPEC, $engine->seedFor('item-a', 1, 2));
        $this->assertNotSame($a, $a2);
    }

    public function test_parametros_respetan_rango_y_paso(): void
    {
        $engine = new PracticeEngine;

        foreach ([1, 2, 3, 4, 5] as $user) {
            $params = $engine->sampleParams(self::SPEC, $engine->seedFor('item-a', $user, 1));

            foreach (['m' => 0.5, 'theta' => 1.0] as $name => $step) {
                $v = $params[$name];
                $spec = self::SPEC[$name];
                $this->assertGreaterThanOrEqual($spec['min'], $v);
                $this->assertLessThanOrEqual($spec['max'], $v);
                // El valor cae exactamente sobre la rejilla min + k·step.
                $k = ($v - $spec['min']) / $step;
                $this->assertEqualsWithDelta(round($k), $k, 1e-9);
            }
        }
    }

    public function test_constantes_pasan_tal_cual(): void
    {
        $engine = new PracticeEngine;
        $params = $engine->sampleParams(self::SPEC, $engine->seedFor('item-a', 1, 1));

        $this->assertSame(9.8, $params['g']);
    }

    public function test_render_del_enunciado_multilingue(): void
    {
        $engine = new PracticeEngine;

        $out = $engine->renderStatement(
            ['es' => 'Un bloque de {m} kg sobre un plano a {theta}°.', 'en' => 'A {m} kg block on a {theta}° incline.'],
            ['m' => 8.0, 'theta' => 27.5],
        );

        // 8.0 se muestra como "8"; 27.5 conserva el decimal.
        $this->assertSame('Un bloque de 8 kg sobre un plano a 27.5°.', $out['es']);
        $this->assertSame('A 8 kg block on a 27.5° incline.', $out['en']);
    }

    public function test_verificacion_con_tolerancia_absoluta_y_relativa(): void
    {
        $engine = new PracticeEngine;
        $params = ['m' => 2.0, 'g' => 9.8, 'theta' => 30.0];
        $expr = 'm * g * sin(deg2rad(theta))';   // = 9.8

        // Exacta → correcta.
        $r = $engine->verify($expr, $params, 9.8, 0.01, 'abs');
        $this->assertTrue($r['is_correct']);
        $this->assertEqualsWithDelta(9.8, $r['expected'], 1e-9);

        // Dentro de tolerancia absoluta.
        $this->assertTrue($engine->verify($expr, $params, 9.805, 0.01, 'abs')['is_correct']);
        // Fuera de tolerancia absoluta.
        $this->assertFalse($engine->verify($expr, $params, 9.85, 0.01, 'abs')['is_correct']);

        // Relativa: 2 % de 9.8 admite ±0.196.
        $this->assertTrue($engine->verify($expr, $params, 9.99, 0.02, 'rel')['is_correct']);
        $this->assertFalse($engine->verify($expr, $params, 10.1, 0.02, 'rel')['is_correct']);

        // Muy equivocada → incorrecta.
        $this->assertFalse($engine->verify($expr, $params, 3.0, 0.02, 'rel')['is_correct']);
    }
}
