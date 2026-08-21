<?php

namespace App\Services\Lesson;

use InvalidArgumentException;

/**
 * Convierte un SUBCONJUNTO cerrado de LaTeX en un árbol MathML.
 *
 * Tres decisiones, y las tres son de presupuesto o de seguridad:
 *
 * 1. EN EL SERVIDOR, al sembrar. KaTeX son ~280 KB y el tope del bundle son 450
 *    (vamos por 366): lo reventaría él solo. MathML lo pintan los navegadores
 *    de forma nativa, cuesta 0 KB de JavaScript y un lector de pantalla lo LEE
 *    —«un medio»— en vez de callarse ante una imagen.
 *
 * 2. UN ÁRBOL, no una cadena. Pintar una cadena de MathML exigiría
 *    `dangerouslySetInnerHTML`, y esto es contenido que va a salir de un
 *    pipeline de IA. El árbol se convierte en elementos de React uno a uno, así
 *    que no existe el camino por el que un `<script>` acabe siendo un `<script>`.
 *
 * 3. UN SUBCONJUNTO, con lista blanca. Misma decisión que MathExpression: lo
 *    que no está previsto revienta AQUÍ, donde lo ve quien escribe la lección,
 *    y no se pinta vacío delante de un alumno.
 *
 * Lo que entiende: números, variables de una letra, + − * / = < > , . ; ( ) [ ],
 * \frac{}{}, \sqrt{}, ^ y _ (con llaves o un solo carácter), y los símbolos con
 * nombre de SIMBOLOS. Nada más. Para ampliarlo se añade aquí y se prueba aquí.
 */
class MathML
{
    /** Comandos de un argumento o de ninguno, con su elemento MathML. */
    private const SIMBOLOS = [
        'pi' => 'π', 'times' => '×', 'cdot' => '·', 'div' => '÷',
        'le' => '≤', 'ge' => '≥', 'neq' => '≠', 'pm' => '±',
        'alpha' => 'α', 'beta' => 'β', 'theta' => 'θ', 'mu' => 'μ',
        'approx' => '≈', 'infty' => '∞', 'rightarrow' => '→',
    ];

    /** Operadores de un carácter. El menos se pinta con el signo tipográfico. */
    private const OPERADORES = [
        '+' => '+', '-' => '−', '*' => '·', '/' => '/', '=' => '=',
        '<' => '<', '>' => '>', '(' => '(', ')' => ')', '[' => '[', ']' => ']',
        ',' => ',', '.' => '.', ';' => ';', '%' => '%', '!' => '!',
    ];

    private string $src = '';

    private int $i = 0;

    /**
     * @return array{e: string, h: list<array>}  Árbol MathML listo para pintar.
     */
    public function render(string $latex): array
    {
        $this->src = $latex;
        $this->i = 0;

        $hijos = $this->nodos(fin: null);

        return ['e' => 'math', 'h' => $hijos];
    }

    /**
     * Lee nodos hasta el final de la cadena o hasta `}` si estamos dentro de un
     * grupo. `$fin` distingue los dos casos: sin él, una llave sin cerrar
     * pasaría inadvertida.
     *
     * @return list<array>
     */
    private function nodos(?string $fin): array
    {
        $salida = [];

        while ($this->i < mb_strlen($this->src)) {
            $c = mb_substr($this->src, $this->i, 1);

            if ($c === '}') {
                if ($fin !== '}') {
                    throw new InvalidArgumentException("Llave de cierre sin abrir en «{$this->src}»");
                }
                $this->i++;

                return $salida;
            }

            $nodo = $this->siguienteNodo();
            if ($nodo === null) {
                continue;   // espacio en blanco
            }

            // ^ y _ se aplican al nodo ANTERIOR: por eso se resuelven aquí y no
            // dentro de siguienteNodo(), que no sabe qué vino antes.
            if ($nodo === '^' || $nodo === '_') {
                if ($salida === []) {
                    throw new InvalidArgumentException("«{$nodo}» sin nada a lo que aplicarse en «{$this->src}»");
                }
                $base = array_pop($salida);
                $salida[] = [
                    'e' => $nodo === '^' ? 'msup' : 'msub',
                    'h' => [$base, $this->argumento()],
                ];

                continue;
            }

            $salida[] = $nodo;
        }

        if ($fin === '}') {
            throw new InvalidArgumentException("Falta cerrar una llave en «{$this->src}»");
        }

        return $salida;
    }

    /** Un nodo, la marca '^'/'_' , o null si era espacio. */
    private function siguienteNodo(): array|string|null
    {
        $c = mb_substr($this->src, $this->i, 1);

        if (trim($c) === '') {
            $this->i++;

            return null;
        }

        if ($c === '\\') {
            return $this->comando();
        }

        if ($c === '^' || $c === '_') {
            $this->i++;

            return $c;
        }

        if ($c === '{') {
            $this->i++;

            return ['e' => 'mrow', 'h' => $this->nodos(fin: '}')];
        }

        if (preg_match('/\d/', $c) === 1) {
            preg_match('/\d+(?:[.,]\d+)?/u', mb_substr($this->src, $this->i), $m);
            $this->i += mb_strlen($m[0]);

            return ['e' => 'mn', 't' => $m[0]];
        }

        if (preg_match('/\p{L}/u', $c) === 1) {
            $this->i++;

            return ['e' => 'mi', 't' => $c];
        }

        if (isset(self::OPERADORES[$c])) {
            $this->i++;

            return ['e' => 'mo', 't' => self::OPERADORES[$c]];
        }

        throw new InvalidArgumentException(
            "Carácter «{$c}» fuera del subconjunto admitido, en «{$this->src}»",
        );
    }

    /** Un `\comando`, siempre de la lista blanca. */
    private function comando(): array
    {
        $this->i++;   // la barra
        preg_match('/^[a-zA-Z]+/', mb_substr($this->src, $this->i), $m);

        if ($m === []) {
            throw new InvalidArgumentException("Barra invertida suelta en «{$this->src}»");
        }

        $nombre = $m[0];
        $this->i += mb_strlen($nombre);

        if ($nombre === 'frac') {
            return ['e' => 'mfrac', 'h' => [$this->argumento(), $this->argumento()]];
        }

        if ($nombre === 'sqrt') {
            return ['e' => 'msqrt', 'h' => [$this->argumento()]];
        }

        if (isset(self::SIMBOLOS[$nombre])) {
            // Las letras griegas son identificadores; el resto, operadores.
            $esLetra = in_array($nombre, ['pi', 'alpha', 'beta', 'theta', 'mu'], true);

            return ['e' => $esLetra ? 'mi' : 'mo', 't' => self::SIMBOLOS[$nombre]];
        }

        throw new InvalidArgumentException(
            "Comando «\\{$nombre}» no está en el subconjunto admitido. ".
            'Si hace falta, se añade a MathML::SIMBOLOS y se prueba.',
        );
    }

    /**
     * El argumento de \frac, \sqrt, ^ o _: un grupo entre llaves, o un solo
     * nodo si no las lleva (`x^2`). Es la regla de LaTeX, no una licencia.
     */
    private function argumento(): array
    {
        while ($this->i < mb_strlen($this->src) && trim(mb_substr($this->src, $this->i, 1)) === '') {
            $this->i++;
        }

        if ($this->i >= mb_strlen($this->src)) {
            throw new InvalidArgumentException("Falta un argumento al final de «{$this->src}»");
        }

        if (mb_substr($this->src, $this->i, 1) === '{') {
            $this->i++;
            $hijos = $this->nodos(fin: '}');

            return count($hijos) === 1 ? $hijos[0] : ['e' => 'mrow', 'h' => $hijos];
        }

        $nodo = $this->siguienteNodo();
        if (! is_array($nodo)) {
            throw new InvalidArgumentException("Argumento inválido en «{$this->src}»");
        }

        return $nodo;
    }
}
