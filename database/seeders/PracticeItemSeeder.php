<?php

namespace Database\Seeders;

use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Banco de ítems de práctica (fase C del motor v2): 17 ítems de física
 * parametrizados sobre las 8 destrezas MINEDEC con is_verified=true
 * (mecánica y óptica — las mismas que usan los dos simuladores).
 *
 * Reglas del banco:
 *  - SOLO destrezas verificadas: alinear ítems contra marcadores sin verificar
 *    sería inventarse el currículo (misma lógica que el CrosswalkSeeder).
 *  - IDEMPOTENTE: updateOrCreate por (objetivo, seq) — resembrar actualiza el
 *    contenido sin duplicar ítems ni cambiarles el id.
 *  - Los enunciados llevan variables {x}; los rangos van en `params` (con `g`
 *    explícita como constante del ítem) y la solución es una expresión evaluable
 *    por MathExpression. Tolerancias del 2 % (relativa) o ±0.5° (absoluta).
 *
 * Física de cada expresión (revisada dos veces):
 *  plano inclinado F∥ = m·g·sinθ, N = m·g·cosθ; ángulo crítico θc = atan(μs)
 *  y μs = tan(θc); rozamiento cinético f = μk·N; fuerza neta colineal F1−F2;
 *  peso P = m·g; Ep = m·g·h; caída sin rozamiento v = √(2gh) (conservación);
 *  segunda ley a = F/m y F = m·a; sistema de 2 bloques a = F/(m1+m2) y
 *  T = m2·F/(m1+m2) (F aplicada sobre m1, cuerda hacia m2, sin rozamiento);
 *  lentes 1/f = 1/d_o + 1/d_i ⇒ d_i = d_o·f/(d_o−f) y f = d_o·d_i/(d_o+d_i)
 *  (convención gaussiana, objeto real e imagen real en convergente, d_o > f);
 *  aumento m = −d_i/d_o y altura h_i = h_o·d_i/d_o (en magnitud).
 */
class PracticeItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = $this->items();
        $codes = array_values(array_unique(array_column($items, 0)));
        $objectives = $this->verifiedObjectives($codes);

        $seqByCode = [];
        foreach ($items as [$code, $statement, $params, $expr, $tol, $kind, $unit]) {
            $seq = $seqByCode[$code] = ($seqByCode[$code] ?? -1) + 1;

            PracticeItem::updateOrCreate(
                ['objective_id' => $objectives[$code]->id, 'seq' => $seq],
                [
                    'statement' => ['es' => $statement],
                    'params' => $params,
                    'solution_expr' => $expr,
                    'tolerance' => $tol,
                    'tolerance_kind' => $kind,
                    'answer_unit' => $unit,
                    // Firmados: son 17 ítems escritos a mano contra las 8
                    // destrezas verificadas, con la física revisada dos veces
                    // (ver la cabecera). No pasan por `practica:firmar`.
                    'reviewed_at' => now(),
                ],
            );
        }
    }

    /**
     * Las destrezas verificadas de EC-MINEDEC indexadas por código.
     * Falla ruidoso si falta alguna, si no está verificada o si el código es
     * ambiguo (replicado por el importador en varios grados).
     *
     * @param  list<string>  $codes
     * @return array<string, LearningObjective>
     */
    private function verifiedObjectives(array $codes): array
    {
        $versionIds = FrameworkVersion::query()
            ->whereIn('framework_id', Framework::where('code', 'EC-MINEDEC')->pluck('id'))
            ->pluck('id');

        $found = LearningObjective::query()
            ->whereIn('version_id', $versionIds)
            ->whereIn('native_code', $codes)
            ->get()
            ->groupBy('native_code');

        $objectives = [];
        foreach ($codes as $code) {
            $candidates = $found->get($code, collect());

            if ($candidates->isEmpty()) {
                throw new RuntimeException(
                    "PracticeItemSeeder: no existe la destreza {$code} en EC-MINEDEC. ".
                    'Siembra primero el grafo (CurriculumSeeder o importador MINEDEC).',
                );
            }
            if ($candidates->count() > 1) {
                throw new RuntimeException(
                    "PracticeItemSeeder: el código {$code} es ambiguo (aparece en ".
                    $candidates->count().' objetivos). Desambigua por nodo antes de sembrar ítems.',
                );
            }
            $objective = $candidates->first();
            if (! $objective->is_verified) {
                throw new RuntimeException(
                    "PracticeItemSeeder: la destreza {$code} no está verificada (is_verified=false). ".
                    'Los ítems solo se siembran sobre destrezas cotejadas con el currículo oficial.',
                );
            }
            $objectives[$code] = $objective;
        }

        return $objectives;
    }

    /**
     * [destreza, enunciado es, params, solution_expr, tolerancia, tipo, unidad]
     * El orden dentro de cada destreza define el `seq` (clave de idempotencia):
     * AÑADIR ítems al final de su destreza, no intercalarlos.
     */
    private function items(): array
    {
        return [
            // ---------- CN.F.5.1.9 — plano inclinado: descomposición del peso ----------
            [
                'CN.F.5.1.9',
                'Un bloque de {m} kg reposa sobre un plano inclinado que forma {theta}° con la horizontal. '.
                'Tomando g = {g} m/s², calcula la componente del peso paralela al plano, en newtons.',
                ['m' => ['min' => 2, 'max' => 15, 'step' => 0.5],
                    'theta' => ['min' => 10, 'max' => 45, 'step' => 1],
                    'g' => ['const' => 9.8]],
                'm * g * sin(deg2rad(theta))',
                0.02, 'rel', 'N',
            ],
            [
                'CN.F.5.1.9',
                'Un bloque de {m} kg reposa sobre un plano inclinado que forma {theta}° con la horizontal. '.
                'Tomando g = {g} m/s², calcula la componente del peso perpendicular al plano '.
                '(igual al módulo de la fuerza normal), en newtons.',
                ['m' => ['min' => 2, 'max' => 15, 'step' => 0.5],
                    'theta' => ['min' => 10, 'max' => 45, 'step' => 1],
                    'g' => ['const' => 9.8]],
                'm * g * cos(deg2rad(theta))',
                0.02, 'rel', 'N',
            ],

            // ---------- CN.F.5.1.12 — rozamiento y ángulo crítico ----------
            [
                'CN.F.5.1.12',
                'Un bloque descansa sobre un plano de inclinación regulable. Si el coeficiente de '.
                'rozamiento estático entre las superficies es {mu}, calcula el ángulo crítico al que '.
                'el bloque está a punto de deslizar, en grados.',
                ['mu' => ['min' => 0.2, 'max' => 0.9, 'step' => 0.05]],
                'rad2deg(atan(mu))',
                0.5, 'abs', '°',
            ],
            [
                'CN.F.5.1.12',
                'Al levantar lentamente un plano inclinado, un bloque comienza a deslizar justo cuando '.
                'la inclinación llega a {theta_c}°. Calcula el coeficiente de rozamiento estático μs '.
                '(adimensional).',
                ['theta_c' => ['min' => 15, 'max' => 40, 'step' => 1]],
                'tan(deg2rad(theta_c))',
                0.02, 'rel', null,
            ],
            [
                'CN.F.5.1.12',
                'Un bloque de {m} kg desliza por un plano inclinado de {theta}° con coeficiente de '.
                'rozamiento cinético {mu_k}. Tomando g = {g} m/s², calcula el módulo de la fuerza de '.
                'rozamiento sobre el bloque, en newtons.',
                ['m' => ['min' => 2, 'max' => 15, 'step' => 0.5],
                    'theta' => ['min' => 10, 'max' => 40, 'step' => 1],
                    'mu_k' => ['min' => 0.1, 'max' => 0.6, 'step' => 0.05],
                    'g' => ['const' => 9.8]],
                'mu_k * m * g * cos(deg2rad(theta))',
                0.02, 'rel', 'N',
            ],

            // ---------- CN.4.3.5 — fuerzas y sus efectos (8.º EGB) ----------
            [
                'CN.4.3.5',
                'Dos fuerzas horizontales opuestas actúan sobre una caja: una de {F1} N hacia la derecha '.
                'y otra de {F2} N hacia la izquierda. Calcula el módulo de la fuerza neta, en newtons.',
                ['F1' => ['min' => 60, 'max' => 150, 'step' => 5],
                    'F2' => ['min' => 10, 'max' => 55, 'step' => 5]],
                'F1 - F2',
                0.02, 'rel', 'N',
            ],
            [
                'CN.4.3.5',
                'En un diagrama de cuerpo libre, el peso es la fuerza con que la Tierra atrae al objeto. '.
                'Calcula el peso de una mochila de {m} kg, tomando g = {g} m/s², en newtons.',
                ['m' => ['min' => 0.5, 'max' => 20, 'step' => 0.5],
                    'g' => ['const' => 9.8]],
                'm * g',
                0.02, 'rel', 'N',
            ],

            // ---------- CN.4.3.7 — energía y su conservación (8.º EGB) ----------
            [
                'CN.4.3.7',
                'Una maceta de {m} kg está en un balcón a {h} m del suelo. Tomando g = {g} m/s², '.
                'calcula su energía potencial gravitatoria respecto del suelo, en julios.',
                ['m' => ['min' => 0.5, 'max' => 10, 'step' => 0.5],
                    'h' => ['min' => 1, 'max' => 20, 'step' => 0.5],
                    'g' => ['const' => 9.8]],
                'm * g * h',
                0.02, 'rel', 'J',
            ],
            [
                'CN.4.3.7',
                'Se deja caer una pelota desde {h} m de altura. Sin rozamiento del aire, toda la energía '.
                'potencial se convierte en cinética. Tomando g = {g} m/s², ¿con qué rapidez llega al '.
                'suelo, en m/s?',
                ['h' => ['min' => 1, 'max' => 20, 'step' => 0.5],
                    'g' => ['const' => 9.8]],
                'sqrt(2 * g * h)',
                0.02, 'rel', 'm/s',
            ],

            // ---------- CN.4.3.10 — fuerza, masa y aceleración (10.º EGB) ----------
            [
                'CN.4.3.10',
                'Sobre un carrito de {m} kg actúa una fuerza neta de {F} N. '.
                'Calcula su aceleración, en m/s².',
                ['m' => ['min' => 0.5, 'max' => 12, 'step' => 0.5],
                    'F' => ['min' => 5, 'max' => 60, 'step' => 1]],
                'F / m',
                0.02, 'rel', 'm/s²',
            ],
            [
                'CN.4.3.10',
                'En el experimento del riel, un deslizador de {m} kg acelera a {a} m/s². '.
                '¿Qué fuerza neta actúa sobre él, en newtons?',
                ['m' => ['min' => 0.5, 'max' => 20, 'step' => 0.5],
                    'a' => ['min' => 0.5, 'max' => 8, 'step' => 0.25]],
                'm * a',
                0.02, 'rel', 'N',
            ],

            // ---------- CN.F.5.1.4 — leyes de Newton en sistemas (1.º BGU) ----------
            [
                'CN.F.5.1.4',
                'Dos bloques de {m1} kg y {m2} kg, unidos por una cuerda, descansan sobre una superficie '.
                'horizontal sin rozamiento. Se aplica una fuerza horizontal de {F} N sobre el bloque de '.
                '{m1} kg. Calcula la aceleración del sistema, en m/s².',
                ['m1' => ['min' => 2, 'max' => 10, 'step' => 0.5],
                    'm2' => ['min' => 2, 'max' => 10, 'step' => 0.5],
                    'F' => ['min' => 10, 'max' => 100, 'step' => 5]],
                'F / (m1 + m2)',
                0.02, 'rel', 'm/s²',
            ],
            [
                'CN.F.5.1.4',
                'En el mismo sistema (bloques de {m1} kg y {m2} kg sobre superficie sin rozamiento, '.
                'fuerza de {F} N aplicada sobre el de {m1} kg), calcula la tensión de la cuerda que '.
                'arrastra al bloque de {m2} kg, en newtons.',
                ['m1' => ['min' => 2, 'max' => 10, 'step' => 0.5],
                    'm2' => ['min' => 2, 'max' => 10, 'step' => 0.5],
                    'F' => ['min' => 10, 'max' => 100, 'step' => 5]],
                'm2 * F / (m1 + m2)',
                0.02, 'rel', 'N',
            ],

            // ---------- CN.F.5.3.7 — lentes delgadas (1.º BGU) ----------
            [
                'CN.F.5.3.7',
                'Una lente convergente tiene distancia focal de {f} cm. Un objeto real se coloca a '.
                '{d_o} cm de la lente. Con la ecuación 1/f = 1/d_o + 1/d_i, calcula a qué distancia '.
                'se forma la imagen real, en cm.',
                // d_o mínimo (25) > f máximo (20): la imagen es siempre real y d_o − f nunca es 0.
                ['f' => ['min' => 5, 'max' => 20, 'step' => 1],
                    'd_o' => ['min' => 25, 'max' => 60, 'step' => 1]],
                'd_o * f / (d_o - f)',
                0.02, 'rel', 'cm',
            ],
            [
                'CN.F.5.3.7',
                'En el banco óptico, un objeto a {d_o} cm de una lente convergente forma una imagen '.
                'real nítida a {d_i} cm. Calcula la distancia focal de la lente, en cm.',
                ['d_o' => ['min' => 20, 'max' => 60, 'step' => 1],
                    'd_i' => ['min' => 20, 'max' => 60, 'step' => 1]],
                'd_o * d_i / (d_o + d_i)',
                0.02, 'rel', 'cm',
            ],

            // ---------- CN.F.5.3.8 — aumento lateral (1.º BGU) ----------
            [
                'CN.F.5.3.8',
                'Un objeto está a {d_o} cm de una lente, que forma su imagen real a {d_i} cm de la '.
                'lente. Con la convención m = −d_i/d_o, calcula el aumento lateral (negativo: la '.
                'imagen real está invertida).',
                ['d_i' => ['min' => 20, 'max' => 80, 'step' => 1],
                    'd_o' => ['min' => 20, 'max' => 80, 'step' => 1]],
                '-(d_i / d_o)',
                0.02, 'rel', null,
            ],
            [
                'CN.F.5.3.8',
                'Un objeto de {h_o} cm de alto está a {d_o} cm de una lente convergente, que forma su '.
                'imagen real a {d_i} cm. Calcula la altura de la imagen, en cm (magnitud, |m| = d_i/d_o).',
                ['h_o' => ['min' => 1, 'max' => 10, 'step' => 0.5],
                    'd_o' => ['min' => 20, 'max' => 80, 'step' => 1],
                    'd_i' => ['min' => 20, 'max' => 80, 'step' => 1]],
                'h_o * d_i / d_o',
                0.02, 'rel', 'cm',
            ],
        ];
    }
}
