<?php

namespace Database\Seeders;

use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * 5 ítems reales de práctica (plano inclinado) para las destrezas de Física BGU:
 *  - CN.F.5.1.9  → descomposición del peso en el plano inclinado
 *  - CN.F.5.1.12 → rozamiento: ángulo crítico y μ = tan θc
 *
 * Los enunciados llevan variables {x}; los rangos van en `params` y la solución
 * es una expresión evaluable por MathExpression (g explícita como constante del
 * ítem para que el enunciado y la verificación usen el mismo valor).
 */
class PracticeItemSeeder extends Seeder
{
    public function run(): void
    {
        $versionIds = FrameworkVersion::query()
            ->whereIn('framework_id', Framework::where('code', 'EC-MINEDEC')->pluck('id'))
            ->pluck('id');

        $objectives = LearningObjective::query()
            ->whereIn('version_id', $versionIds)
            ->whereIn('native_code', ['CN.F.5.1.9', 'CN.F.5.1.12'])
            ->get()
            ->keyBy('native_code');

        foreach (['CN.F.5.1.9', 'CN.F.5.1.12'] as $code) {
            if (! $objectives->has($code)) {
                throw new RuntimeException(
                    "PracticeItemSeeder: no existe la destreza {$code} en EC-MINEDEC. ".
                    'Siembra primero el grafo (CurriculumSeeder o importador MINEDEC).',
                );
            }
        }

        // [destreza, enunciado es, params, solution_expr, tolerancia, tipo, unidad]
        $items = [
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
        ];

        $seqByCode = [];
        foreach ($items as [$code, $statement, $params, $expr, $tol, $kind, $unit]) {
            PracticeItem::create([
                'objective_id' => $objectives[$code]->id,
                'statement' => ['es' => $statement],
                'params' => $params,
                'solution_expr' => $expr,
                'tolerance' => $tol,
                'tolerance_kind' => $kind,
                'answer_unit' => $unit,
                'seq' => $seqByCode[$code] = ($seqByCode[$code] ?? -1) + 1,
            ]);
        }
    }
}
