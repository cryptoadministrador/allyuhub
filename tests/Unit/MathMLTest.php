<?php

namespace Tests\Unit;

use App\Services\Lesson\MathML;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * LaTeX → MathML, en el SERVIDOR y al sembrar.
 *
 * Por qué no KaTeX: son ~280 KB y el presupuesto del bundle son 450 (vamos por
 * 366). MathML lo pintan los navegadores de forma nativa, cuesta 0 KB de
 * JavaScript y —esto es lo que de verdad importa— un lector de pantalla lo LEE:
 * «un medio», no «imagen sin descripción».
 *
 * Por qué un ÁRBOL y no una cadena de MathML: renderizar una cadena exigiría
 * `dangerouslySetInnerHTML`, que en contenido salido de un pipeline de IA es un
 * agujero de inyección. El árbol se convierte en elementos de React uno a uno,
 * así que no hay forma de que un `<script>` acabe siendo un `<script>`.
 *
 * Y por qué un SUBCONJUNTO cerrado: es la misma decisión que MathExpression
 * —lista blanca, nunca eval()—. Lo que no está previsto revienta al sembrar,
 * donde lo ve quien escribe, y no se pinta vacío delante de un alumno.
 */
class MathMLTest extends TestCase
{
    private function arbol(string $latex): array
    {
        return (new MathML)->render($latex);
    }

    /** Aplana el árbol a texto: lo que un lector de pantalla acabaría diciendo. */
    private function texto(array $nodo): string
    {
        if (isset($nodo['t'])) {
            return $nodo['t'];
        }

        return implode('', array_map(fn ($h) => $this->texto($h), $nodo['h'] ?? []));
    }

    public function test_un_numero_suelto_es_un_mn(): void
    {
        $this->assertSame(
            ['e' => 'math', 'h' => [['e' => 'mn', 't' => '42']]],
            $this->arbol('42'),
        );
    }

    public function test_una_variable_es_un_mi(): void
    {
        $this->assertSame(
            ['e' => 'math', 'h' => [['e' => 'mi', 't' => 'x']]],
            $this->arbol('x'),
        );
    }

    /** Los operadores van en `mo`: es lo que hace que se lean como operadores. */
    public function test_una_suma_separa_numeros_y_operadores(): void
    {
        $this->assertSame([
            'e' => 'math',
            'h' => [
                ['e' => 'mn', 't' => '2'],
                ['e' => 'mo', 't' => '+'],
                ['e' => 'mn', 't' => '3'],
            ],
        ], $this->arbol('2 + 3'));
    }

    public function test_la_fraccion_es_un_mfrac_con_sus_dos_partes(): void
    {
        $arbol = $this->arbol('\frac{1}{2}');

        $this->assertSame('mfrac', $arbol['h'][0]['e']);
        $this->assertSame(['e' => 'mn', 't' => '1'], $arbol['h'][0]['h'][0]);
        $this->assertSame(['e' => 'mn', 't' => '2'], $arbol['h'][0]['h'][1]);
    }

    public function test_la_potencia_es_un_msup(): void
    {
        $arbol = $this->arbol('x^{2}');

        $this->assertSame('msup', $arbol['h'][0]['e']);
        $this->assertSame('x', $this->texto($arbol['h'][0]['h'][0]));
        $this->assertSame('2', $this->texto($arbol['h'][0]['h'][1]));
    }

    public function test_el_subindice_es_un_msub(): void
    {
        $this->assertSame('msub', $this->arbol('a_{1}')['h'][0]['e']);
    }

    public function test_la_raiz_es_un_msqrt(): void
    {
        $arbol = $this->arbol('\sqrt{16}');

        $this->assertSame('msqrt', $arbol['h'][0]['e']);
        $this->assertSame('16', $this->texto($arbol['h'][0]));
    }

    /** Sin llaves, el exponente es UN solo carácter: es la regla de LaTeX. */
    public function test_la_potencia_sin_llaves_toma_un_solo_caracter(): void
    {
        $arbol = $this->arbol('x^2y');

        $this->assertSame('msup', $arbol['h'][0]['e']);
        $this->assertSame('2', $this->texto($arbol['h'][0]['h'][1]));
        $this->assertSame(['e' => 'mi', 't' => 'y'], $arbol['h'][1]);
    }

    public function test_los_simbolos_con_nombre_salen_con_su_caracter(): void
    {
        foreach ([
            '\pi' => 'π', '\times' => '×', '\cdot' => '·', '\div' => '÷',
            '\le' => '≤', '\ge' => '≥', '\neq' => '≠', '\pm' => '±',
        ] as $latex => $esperado) {
            $this->assertSame($esperado, $this->texto($this->arbol($latex)),
                "El símbolo {$latex} no se convirtió");
        }
    }

    public function test_los_parentesis_se_conservan_como_operadores(): void
    {
        $arbol = $this->arbol('(x+1)');

        $this->assertSame('(', $arbol['h'][0]['t']);
        $this->assertSame('mo', $arbol['h'][0]['e']);
        $this->assertSame(')', $arbol['h'][4]['t']);
    }

    /** Una fórmula real de 8.º: fracción, potencia y raíz juntas. */
    public function test_una_formula_compuesta_de_verdad(): void
    {
        $arbol = $this->arbol('x = \frac{-b \pm \sqrt{b^{2} - 4ac}}{2a}');

        $this->assertSame('math', $arbol['e']);
        // El texto aplanado conserva TODO lo que hay que leer.
        $plano = $this->texto($arbol);
        foreach (['x', '=', '−', 'b', '±', '2', '4', 'a', 'c'] as $trozo) {
            $this->assertStringContainsString($trozo, $plano);
        }
    }

    // ================= LO QUE NO ESTÁ PREVISTO, REVIENTA =================

    public function test_un_comando_desconocido_revienta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arbol('\integral{x}');
    }

    public function test_un_comando_peligroso_revienta_en_vez_de_pasar(): void
    {
        foreach (['\input{/etc/passwd}', '\href{javascript:alert(1)}{x}', '\write18{rm}'] as $malo) {
            try {
                $this->arbol($malo);
                $this->fail("«{$malo}» no reventó: un comando no previsto llegó al alumno.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_una_llave_sin_cerrar_revienta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arbol('\frac{1}{2');
    }

    public function test_un_caracter_fuera_del_subconjunto_revienta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arbol('x & y');
    }

    /**
     * Lo más importante del conversor, y no es que reviente: es que una etiqueta
     * se convierte en NODOS DE TEXTO. `<` y `>` son operadores matemáticos
     * legítimos —«2 < 3»— así que rechazarlos sería romper el subconjunto por
     * un miedo mal puesto. Lo que hay que garantizar es que el `<` acabe en un
     * `mo` y la palabra `script` en varios `mi`: datos, nunca marcado.
     */
    public function test_una_etiqueta_acaba_siendo_texto_no_un_elemento(): void
    {
        $arbol = $this->arbol('<script>alert(1)</script>');

        $recorrer = function (array $n) use (&$recorrer) {
            $this->assertStringStartsWith('m', $n['e']);
            $this->assertNotSame('script', $n['e']);
            foreach ($n['h'] ?? [] as $h) {
                $recorrer($h);
            }
        };
        $recorrer($arbol);

        // Y el texto sigue ahí, como texto: no se ha perdido ni ejecutado.
        $this->assertStringContainsString('script', $this->texto($arbol));
    }

    public function test_todo_nodo_generado_lleva_un_nombre_de_la_lista_blanca(): void
    {
        $permitidos = ['math', 'mn', 'mi', 'mo', 'mrow', 'mfrac', 'msup', 'msub', 'msqrt'];

        $recorrer = function (array $n) use (&$recorrer, $permitidos) {
            $this->assertContains($n['e'], $permitidos, "Elemento inesperado: {$n['e']}");
            foreach ($n['h'] ?? [] as $h) {
                $recorrer($h);
            }
        };

        $recorrer($this->arbol('\frac{\sqrt{x^{2}}}{2\pi} \le a_{1}'));
    }
}
