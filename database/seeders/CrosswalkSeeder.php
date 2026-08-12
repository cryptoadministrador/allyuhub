<?php

namespace Database\Seeders;

use App\Models\Alignment;
use App\Models\Concept;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CAPA 2 — Primeras aristas del crosswalk STEM (roadmap CLAUDE.md §3).
 *
 * Qué hace: ancla las destrezas MINEDEC **verificadas** de 8.º EGB–3.º BGU en
 * conceptos, y desde ahí las conecta con Cambridge (Lower Secondary, IGCSE,
 * AS & A Level) e IB (MYP, DP). Añade además la progresión interna de Cambridge
 * como aristas `prerequisite`, que es lo que permitirá al motor de práctica
 * retroceder cuando un estudiante falla.
 *
 * Por qué son tan pocas: hoy solo 8 destrezas MINEDEC de este tramo tienen
 * is_verified=true (mecánica y óptica, las que usan los dos simuladores). El resto
 * de la semilla son marcadores que el importador oficial sustituirá — alinear contra
 * un marcador sería inventarse el crosswalk. Cada vez que `mineduc:import` verifique
 * un área nueva, este seeder crece.
 *
 * REGLA 5 DE CLAUDE.md — nada de esto entra a producción todavía:
 * todas las aristas salen con reviewed_at = NULL, así que `Alignment::production()`
 * las excluye. La IA propone (method=llm-assisted), el docente dispone: revisar y
 * firmar es un acto humano y no se simula desde un seeder. Las aristas de progresión
 * interna van con method=manual porque son estructurales del propio marco, pero
 * tampoco se dan por revisadas.
 *
 * Semántica de `relation` (source → target):
 *   exact        cubren lo mismo con demanda cognitiva comparable
 *   narrower     el target es una parte / caso particular del source
 *   broader      el target exige más o abarca más que el source
 *   related      hay solape temático pero no equivalencia (típico con criterios MYP,
 *                que evalúan procesos, no contenidos)
 *   prerequisite hay que dominar el target antes de intentar el source
 */
class CrosswalkSeeder extends Seeder
{
    /** slug => [label_es, dominio, banda] */
    private const CONCEPTS = [
        'fuerzas-y-diagramas-de-cuerpo-libre' => ['Fuerzas y diagramas de cuerpo libre', 'fisica', 'superior'],
        'conservacion-energia-mecanica' => ['Conservación de la energía mecánica', 'fisica', 'superior'],
        'segunda-ley-de-newton' => ['Segunda ley de Newton (F = ma)', 'fisica', 'superior'],
        'plano-inclinado' => ['Equilibrio y movimiento en el plano inclinado', 'fisica', 'bachillerato'],
        'coeficiente-de-rozamiento' => ['Rozamiento y coeficiente de fricción', 'fisica', 'bachillerato'],
        'leyes-de-newton-sistemas' => ['Leyes de Newton en sistemas de cuerpos', 'fisica', 'bachillerato'],
        'optica-lentes-delgadas' => ['Formación de imágenes en lentes delgadas', 'fisica', 'bachillerato'],
        'aumento-lateral' => ['Aumento lateral de una imagen', 'fisica', 'bachillerato'],
        'trigonometria-triangulo-rectangulo' => ['Trigonometría del triángulo rectángulo', 'matematica', 'superior'],
        'ecuaciones-y-sistemas-lineales' => ['Ecuaciones y sistemas lineales', 'matematica', 'superior'],
        'derivada-como-tasa-de-cambio' => ['La derivada como tasa de cambio', 'matematica', 'bachillerato'],
    ];

    /**
     * Crosswalk MINEDEC → marcos internacionales.
     * [concepto, source, [ [target, relation, confidence], ... ] ]
     * source/target = [código de framework, native_code].
     */
    private const CROSSWALK = [
        ['fuerzas-y-diagramas-de-cuerpo-libre', ['EC-MINEDEC', 'CN.4.3.5'], [
            [['CAIE-LSEC', '7Pf.01'], 'exact', 0.85],
            [['CAIE-LSEC', '7Pf.02'], 'narrower', 0.75],
            [['CAIE-IGCSE', '0625.1.5.1'], 'broader', 0.80],
            [['CAIE-ASA', '9709.M1.2.1'], 'broader', 0.60],
            [['IB-DP', 'DP.PHY.A.2.1'], 'broader', 0.65],
            [['IB-MYP', 'MYP.SCI.3.A.ii'], 'related', 0.55],
        ]],
        ['conservacion-energia-mecanica', ['EC-MINEDEC', 'CN.4.3.7'], [
            [['CAIE-LSEC', '7Pf.04'], 'narrower', 0.70],
            [['CAIE-LSEC', '8Pf.01'], 'exact', 0.85],
            [['CAIE-IGCSE', '0625.1.7.2'], 'exact', 0.85],
            [['CAIE-IGCSE', '0625.1.7.3'], 'broader', 0.70],
            [['CAIE-ASA', '9702.5.2.1'], 'broader', 0.75],
            [['CAIE-ASA', '9709.M1.5.1'], 'broader', 0.60],
            [['IB-DP', 'DP.PHY.A.3.1'], 'broader', 0.80],
        ]],
        ['segunda-ley-de-newton', ['EC-MINEDEC', 'CN.4.3.10'], [
            [['CAIE-LSEC', '9Pf.01'], 'exact', 0.90],
            [['CAIE-IGCSE', '0625.1.5.2'], 'exact', 0.85],
            [['CAIE-ASA', '9702.3.1.1'], 'broader', 0.80],
            [['IB-DP', 'DP.PHY.A.2.2'], 'broader', 0.75],
            [['CAIE-LSEC', '7TWSa.03'], 'related', 0.60],
            [['IB-MYP', 'MYP.SCI.3.C.ii'], 'related', 0.55],
        ]],
        ['plano-inclinado', ['EC-MINEDEC', 'CN.F.5.1.9'], [
            [['CAIE-ASA', '9709.M1.2.2'], 'exact', 0.90],
            [['CAIE-ASA', '9702.4.2.1'], 'exact', 0.85],
            [['IB-DP', 'DP.PHY.A.2.1'], 'exact', 0.85],
            [['CAIE-IGCSE', '0625.1.5.5'], 'narrower', 0.75],
        ]],
        ['coeficiente-de-rozamiento', ['EC-MINEDEC', 'CN.F.5.1.12'], [
            [['CAIE-ASA', '9709.M1.2.3'], 'exact', 0.85],
            [['IB-DP', 'DP.PHY.A.2.3'], 'exact', 0.85],
            [['CAIE-ASA', '9702.3.1.3'], 'broader', 0.80],
            [['CAIE-IGCSE', '0625.1.5.3'], 'narrower', 0.60],
            [['CAIE-LSEC', '8Pf.04'], 'narrower', 0.50],
        ]],
        ['leyes-de-newton-sistemas', ['EC-MINEDEC', 'CN.F.5.1.4'], [
            [['CAIE-ASA', '9709.M1.3.1'], 'exact', 0.90],
            [['IB-DP', 'DP.PHY.A.2.2'], 'exact', 0.90],
            [['CAIE-ASA', '9702.3.1.1'], 'exact', 0.85],
            [['CAIE-IGCSE', '0625.1.5.2'], 'narrower', 0.75],
        ]],
        ['optica-lentes-delgadas', ['EC-MINEDEC', 'CN.F.5.3.7'], [
            [['CAIE-IGCSE', '0625.3.2.3'], 'exact', 0.90],
            [['CAIE-LSEC', '9Pl.01'], 'narrower', 0.70],
            [['IB-DP', 'DP.PHY.C.2.4'], 'related', 0.60],
        ]],
        ['aumento-lateral', ['EC-MINEDEC', 'CN.F.5.3.8'], [
            [['CAIE-IGCSE', '0625.3.2.4'], 'exact', 0.90],
            [['CAIE-IGCSE', '0625.3.2.3'], 'broader', 0.70],
        ]],
    ];

    /**
     * Progresión: [source, target_prerrequisito, concepto|null].
     * Se lee «para intentar SOURCE hay que dominar antes TARGET».
     */
    private const PREREQUISITES = [
        [['CAIE-LSEC', '9Pf.01'], ['CAIE-LSEC', '7Pf.02'], 'segunda-ley-de-newton'],
        [['CAIE-IGCSE', '0625.1.5.2'], ['CAIE-LSEC', '9Pf.01'], 'segunda-ley-de-newton'],
        [['CAIE-ASA', '9702.3.1.1'], ['CAIE-IGCSE', '0625.1.5.2'], 'segunda-ley-de-newton'],
        [['IB-DP', 'DP.PHY.A.2.2'], ['CAIE-IGCSE', '0625.1.5.2'], 'segunda-ley-de-newton'],
        [['CAIE-IGCSE', '0625.1.7.2'], ['CAIE-LSEC', '8Pf.01'], 'conservacion-energia-mecanica'],
        [['IB-DP', 'DP.PHY.A.3.1'], ['CAIE-IGCSE', '0625.1.7.2'], 'conservacion-energia-mecanica'],
        [['CAIE-ASA', '9709.M1.2.2'], ['CAIE-IGCSE', '0625.1.5.5'], 'plano-inclinado'],
        [['CAIE-ASA', '9709.M1.2.3'], ['CAIE-IGCSE', '0625.1.5.3'], 'coeficiente-de-rozamiento'],
        [['CAIE-IGCSE', '0625.3.2.3'], ['CAIE-LSEC', '8Pl.02'], 'optica-lentes-delgadas'],
        [['CAIE-IGCSE', '0625.3.2.4'], ['CAIE-IGCSE', '0625.3.2.3'], 'aumento-lateral'],
        [['CAIE-IGCSE', '0580.C6.2'], ['CAIE-LSEC', '9Gg.07'], 'trigonometria-triangulo-rectangulo'],
        [['CAIE-IGCSE', '0580.E6.4'], ['CAIE-IGCSE', '0580.C6.2'], 'trigonometria-triangulo-rectangulo'],
        [['IB-DP', 'DP.MAA.3.2'], ['CAIE-IGCSE', '0580.E6.4'], 'trigonometria-triangulo-rectangulo'],
        [['CAIE-IGCSE', '0580.C2.5'], ['CAIE-LSEC', '9Ae.04'], 'ecuaciones-y-sistemas-lineales'],
        [['CAIE-LSEC', '9Ae.04'], ['CAIE-LSEC', '8Ae.05'], 'ecuaciones-y-sistemas-lineales'],
        [['CAIE-ASA', '9709.P1.1.1'], ['CAIE-IGCSE', '0580.E2.13'], 'ecuaciones-y-sistemas-lineales'],
        [['CAIE-ASA', '9709.P1.7.1'], ['CAIE-IGCSE', '0580.E2.11'], 'derivada-como-tasa-de-cambio'],
        [['IB-DP', 'DP.MAA.5.2'], ['CAIE-ASA', '9709.P1.7.1'], 'derivada-como-tasa-de-cambio'],
        [['IB-DP', 'DP.MAA.5.6'], ['IB-DP', 'DP.MAA.5.2'], 'derivada-como-tasa-de-cambio'],
    ];

    /** ['FW|codigo' => uuid del learning_objective] */
    private array $objectives = [];

    public function run(): void
    {
        $this->loadObjectiveIndex();

        DB::transaction(function () {
            $concepts = [];
            foreach (self::CONCEPTS as $slug => [$label, $domain, $band]) {
                $concepts[$slug] = Concept::create([
                    'slug' => $slug,
                    'label' => ['es' => $label],
                    'domain' => $domain,
                    'band' => $band,
                ])->id;
            }

            foreach (self::CROSSWALK as [$slug, $source, $targets]) {
                foreach ($targets as [$target, $relation, $confidence]) {
                    Alignment::create([
                        'source_id' => $this->objective($source),
                        'target_id' => $this->objective($target),
                        'concept_id' => $concepts[$slug],
                        'relation' => $relation,
                        'confidence' => $confidence,
                        'method' => 'llm-assisted',
                        // reviewed_at queda NULL a propósito: fuera de producción hasta
                        // que un docente firme la revisión (regla 5 de CLAUDE.md).
                    ]);
                }
            }

            foreach (self::PREREQUISITES as [$source, $target, $slug]) {
                Alignment::create([
                    'source_id' => $this->objective($source),
                    'target_id' => $this->objective($target),
                    'concept_id' => $slug ? $concepts[$slug] : null,
                    'relation' => 'prerequisite',
                    'confidence' => 0.90,
                    'method' => 'manual',
                ]);
            }
        });
    }

    /** Índice (framework, native_code) => id. La clave NUNCA es el código solo (regla 2). */
    private function loadObjectiveIndex(): void
    {
        $rows = DB::table('learning_objectives as lo')
            ->join('framework_versions as fv', 'fv.id', '=', 'lo.version_id')
            ->join('frameworks as f', 'f.id', '=', 'fv.framework_id')
            ->select('lo.id', 'lo.native_code', 'f.code as fw')
            ->whereNotNull('lo.native_code')
            ->get();

        foreach ($rows as $r) {
            $this->objectives[$r->fw.'|'.$r->native_code] = $r->id;
        }
    }

    /** @param  array{0: string, 1: string}  $ref */
    private function objective(array $ref): string
    {
        $key = $ref[0].'|'.$ref[1];

        return $this->objectives[$key]
            ?? throw new RuntimeException("Crosswalk: no existe el objetivo {$key}. ¿Sembraste antes CurriculumSeeder e InternationalFrameworksSeeder?");
    }
}
