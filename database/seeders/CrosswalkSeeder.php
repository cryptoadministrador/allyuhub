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
 * AS & A Level) e IB (MYP, DP). Añade además la progresión entre marcos como
 * aristas `prerequisite`, que es lo que permitirá al motor de práctica retroceder
 * cuando un estudiante falla.
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
 * van con method=manual porque son estructurales de los propios marcos, pero tampoco
 * se dan por revisadas.
 *
 * Excepción consagrada (motor de práctica v2): el AdaptiveSelector NAVEGA por las
 * aristas prerequisite con method=manual aunque no estén revisadas — son autoría
 * humana, no propuesta de IA, y no se muestran como equivalencias. Ver el docblock
 * de App\Services\Practice\AdaptiveSelector para el razonamiento y su condición
 * de revisión.
 *
 * Dos cosas que el grafo real impuso y conviene no olvidar:
 *  - Cambridge Lower Secondary NO tiene lentes ni F = ma: su física llega a fuerzas
 *    equilibradas/no equilibradas (8Pf.03), momentos (8Pf.04) y conservación
 *    cualitativa de la energía (9Pf.03). Por eso óptica y segunda ley solo enganchan
 *    de IGCSE hacia arriba.
 *  - DP y AS & A Level son titulaciones PARALELAS de 16-19: ningún prerrequisito
 *    cruza de una a otra, ambas ramas cuelgan de IGCSE.
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
        'trabajo-y-potencia' => ['Trabajo mecánico y potencia', 'fisica', 'bachillerato'],
        'trigonometria-triangulo-rectangulo' => ['Trigonometría del triángulo rectángulo', 'matematica', 'superior'],
        'ecuaciones-y-sistemas-lineales' => ['Ecuaciones y sistemas lineales', 'matematica', 'superior'],
        'funcion-lineal-y-su-grafica' => ['La función lineal y su gráfica', 'matematica', 'superior'],
        'derivada-como-tasa-de-cambio' => ['La derivada como tasa de cambio', 'matematica', 'bachillerato'],
    ];

    /**
     * Crosswalk MINEDEC → marcos internacionales.
     * [concepto, source, [ [target, relation, confidence], ... ] ]
     * source/target = [código de framework, native_code].
     */
    private const CROSSWALK = [
        // CN.4.3.5 — «Explicar las fuerzas que actúan sobre los objetos e interpretar
        // sus efectos mediante diagramas y experimentación» (8.º EGB).
        ['fuerzas-y-diagramas-de-cuerpo-libre', ['EC-MINEDEC', 'CN.4.3.5'], [
            [['CAIE-LSEC', '8Pf.03'], 'exact', 0.80],
            [['CAIE-IGCSE', '0625.1.5.1'], 'broader', 0.80],
            [['CAIE-ASA', '9709.4.1'], 'broader', 0.60],
            [['CAIE-ASA', '9702.4.2'], 'broader', 0.60],
            [['IB-DP', 'DP.PHY.A.2'], 'broader', 0.65],
            [['IB-MYP', 'MYP.SCI.3.A.ii'], 'related', 0.55],
        ]],
        // CN.4.3.7 — «Analizar las formas de energía y sus transformaciones, e inferir
        // la conservación de la energía en sistemas mecánicos sencillos» (8.º EGB).
        ['conservacion-energia-mecanica', ['EC-MINEDEC', 'CN.4.3.7'], [
            [['CAIE-LSEC', '7Pf.01'], 'narrower', 0.70],
            [['CAIE-LSEC', '9Pf.03'], 'exact', 0.85],
            [['CAIE-IGCSE', '0625.1.7.1'], 'exact', 0.85],
            [['CAIE-ASA', '9702.5.1'], 'broader', 0.80],
            [['CAIE-ASA', '9709.4.5'], 'broader', 0.60],
            [['IB-DP', 'DP.PHY.A.3'], 'broader', 0.80],
        ]],
        // CN.4.3.10 — «Indagar experimentalmente la relación entre fuerza, masa y
        // aceleración, y comunicar los resultados con gráficas y tablas» (10.º EGB).
        // Lower Secondary no llega a F = ma: lo más cercano es 8Pf.03, y la parte
        // experimental engancha en Thinking and Working Scientifically.
        ['segunda-ley-de-newton', ['EC-MINEDEC', 'CN.4.3.10'], [
            [['CAIE-LSEC', '8Pf.03'], 'narrower', 0.60],
            [['CAIE-IGCSE', '0625.1.5.1'], 'exact', 0.80],
            [['CAIE-ASA', '9702.3.1'], 'broader', 0.80],
            [['CAIE-ASA', '9709.4.4'], 'broader', 0.75],
            [['IB-DP', 'DP.PHY.A.2'], 'broader', 0.75],
            [['CAIE-LSEC', '8TWSp.04'], 'related', 0.55],
            [['IB-MYP', 'MYP.SCI.3.C.ii'], 'related', 0.55],
        ]],
        // CN.F.5.1.9 — plano inclinado y descomposición del peso (1.º BGU).
        ['plano-inclinado', ['EC-MINEDEC', 'CN.F.5.1.9'], [
            [['CAIE-ASA', '9709.4.1'], 'exact', 0.85],
            [['CAIE-ASA', '9702.4.2'], 'exact', 0.85],
            [['IB-DP', 'DP.PHY.A.2'], 'exact', 0.80],
            [['CAIE-IGCSE', '0625.1.5.1'], 'narrower', 0.70],
        ]],
        // CN.F.5.1.12 — coeficiente de rozamiento a partir del ángulo crítico (1.º BGU).
        // Ningún marco internacional pide la DETERMINACIÓN EXPERIMENTAL de μ, así que
        // ninguna arista es 'exact': todas cubren el modelo F = μR, no el experimento.
        ['coeficiente-de-rozamiento', ['EC-MINEDEC', 'CN.F.5.1.12'], [
            [['CAIE-ASA', '9709.4.1'], 'broader', 0.75],
            [['IB-DP', 'DP.PHY.A.2'], 'broader', 0.75],
            [['CAIE-ASA', '9702.3.1'], 'broader', 0.70],
            [['CAIE-IGCSE', '0625.1.5.1'], 'narrower', 0.60],
            [['CAIE-LSEC', '8Pf.03'], 'narrower', 0.50],
        ]],
        // CN.F.5.1.4 — leyes de Newton en sistemas de cuerpos (1.º BGU).
        ['leyes-de-newton-sistemas', ['EC-MINEDEC', 'CN.F.5.1.4'], [
            [['CAIE-ASA', '9709.4.4'], 'exact', 0.90],
            [['IB-DP', 'DP.PHY.A.2'], 'exact', 0.85],
            [['CAIE-ASA', '9702.3.1'], 'exact', 0.85],
            [['CAIE-IGCSE', '0625.1.5.1'], 'narrower', 0.75],
        ]],
        // CN.F.5.3.7 — formación de imágenes en lentes delgadas (1.º BGU).
        ['optica-lentes-delgadas', ['EC-MINEDEC', 'CN.F.5.3.7'], [
            [['CAIE-IGCSE', '0625.3.2.3'], 'exact', 0.90],
            [['CAIE-LSEC', '8Ps.02'], 'narrower', 0.60],
            [['IB-DP', 'DP.PHY.C.2'], 'related', 0.55],
        ]],
        // CN.F.5.3.8 — aumento lateral. En 0625 vive DENTRO de 3.2.3 Thin lenses,
        // no en 3.2.4 (que es dispersión), así que apunta al mismo objetivo.
        ['aumento-lateral', ['EC-MINEDEC', 'CN.F.5.3.8'], [
            [['CAIE-IGCSE', '0625.3.2.3'], 'broader', 0.85],
        ]],
    ];

    /**
     * Progresión: [source, target_prerrequisito, concepto|null].
     * Se lee «para intentar SOURCE hay que dominar antes TARGET».
     */
    private const PREREQUISITES = [
        // Física: fuerzas y segunda ley.
        [['CAIE-IGCSE', '0625.1.5.1'], ['CAIE-LSEC', '8Pf.03'], 'fuerzas-y-diagramas-de-cuerpo-libre'],
        [['CAIE-ASA', '9702.3.1'], ['CAIE-IGCSE', '0625.1.5.1'], 'segunda-ley-de-newton'],
        [['CAIE-ASA', '9709.4.4'], ['CAIE-ASA', '9709.4.1'], 'leyes-de-newton-sistemas'],
        [['IB-DP', 'DP.PHY.A.2'], ['CAIE-IGCSE', '0625.1.5.1'], 'segunda-ley-de-newton'],
        [['CAIE-IGCSE', '0625.1.5.2'], ['CAIE-LSEC', '8Pf.04'], 'fuerzas-y-diagramas-de-cuerpo-libre'],
        // Física: energía, trabajo y potencia.
        [['CAIE-LSEC', '9Pf.03'], ['CAIE-LSEC', '7Pf.01'], 'conservacion-energia-mecanica'],
        [['CAIE-IGCSE', '0625.1.7.1'], ['CAIE-LSEC', '9Pf.03'], 'conservacion-energia-mecanica'],
        [['CAIE-IGCSE', '0625.1.7.2'], ['CAIE-IGCSE', '0625.1.7.1'], 'trabajo-y-potencia'],
        [['CAIE-IGCSE', '0625.1.7.4'], ['CAIE-IGCSE', '0625.1.7.2'], 'trabajo-y-potencia'],
        [['CAIE-ASA', '9702.5.1'], ['CAIE-IGCSE', '0625.1.7.1'], 'conservacion-energia-mecanica'],
        [['IB-DP', 'DP.PHY.A.3'], ['CAIE-IGCSE', '0625.1.7.1'], 'conservacion-energia-mecanica'],
        // Física: óptica.
        [['CAIE-IGCSE', '0625.3.2.2'], ['CAIE-LSEC', '8Ps.02'], 'optica-lentes-delgadas'],
        [['CAIE-IGCSE', '0625.3.2.3'], ['CAIE-IGCSE', '0625.3.2.2'], 'optica-lentes-delgadas'],
        [['IB-DP', 'DP.PHY.C.2'], ['CAIE-IGCSE', '0625.3.2.2'], 'optica-lentes-delgadas'],
        // Matemática: ecuaciones.
        [['CAIE-LSEC', '9Ae.05'], ['CAIE-LSEC', '8Ae.06'], 'ecuaciones-y-sistemas-lineales'],
        [['CAIE-LSEC', '9Ae.06'], ['CAIE-LSEC', '9Ae.05'], 'ecuaciones-y-sistemas-lineales'],
        [['CAIE-IGCSE', '0580.C2.5'], ['CAIE-LSEC', '9Ae.06'], 'ecuaciones-y-sistemas-lineales'],
        [['CAIE-ASA', '9709.1.1'], ['CAIE-IGCSE', '0580.C2.5'], 'ecuaciones-y-sistemas-lineales'],
        // Matemática: función lineal y gráficas.
        [['CAIE-LSEC', '8As.06'], ['CAIE-LSEC', '7As.04'], 'funcion-lineal-y-su-grafica'],
        [['CAIE-LSEC', '9As.06'], ['CAIE-LSEC', '8As.06'], 'funcion-lineal-y-su-grafica'],
        [['CAIE-IGCSE', '0580.C2.9'], ['CAIE-LSEC', '9As.06'], 'funcion-lineal-y-su-grafica'],
        // Matemática: trigonometría.
        [['CAIE-IGCSE', '0580.C6.1'], ['CAIE-LSEC', '9Gg.10'], 'trigonometria-triangulo-rectangulo'],
        [['CAIE-IGCSE', '0580.C6.2'], ['CAIE-IGCSE', '0580.C6.1'], 'trigonometria-triangulo-rectangulo'],
        [['CAIE-IGCSE', '0580.E6.5'], ['CAIE-IGCSE', '0580.C6.2'], 'trigonometria-triangulo-rectangulo'],
        [['CAIE-ASA', '9709.1.5'], ['CAIE-IGCSE', '0580.E6.4'], 'trigonometria-triangulo-rectangulo'],
        [['IB-DP', 'DP.MAA.3'], ['CAIE-IGCSE', '0580.E6.5'], 'trigonometria-triangulo-rectangulo'],
        // Matemática: cálculo. DP no cuelga de A Level (son paralelos): ambos de IGCSE.
        [['CAIE-ASA', '9709.1.7'], ['CAIE-IGCSE', '0580.E2.12'], 'derivada-como-tasa-de-cambio'],
        [['CAIE-ASA', '9709.1.8'], ['CAIE-ASA', '9709.1.7'], 'derivada-como-tasa-de-cambio'],
        [['IB-DP', 'DP.MAA.5'], ['CAIE-IGCSE', '0580.E2.12'], 'derivada-como-tasa-de-cambio'],
    ];

    /** ['FW|codigo' => [ ['id' => uuid, 'ver' => label, 'path' => path], ... ] */
    private array $objectives = [];

    public function run(): void
    {
        $this->loadObjectiveIndex();

        DB::transaction(function () {
            $concepts = [];
            foreach (self::CONCEPTS as $slug => [$label, $domain, $band]) {
                $concepts[$slug] = Concept::firstOrCreate(
                    ['slug' => $slug],
                    ['label' => ['es' => $label], 'domain' => $domain, 'band' => $band],
                )->id;
            }

            foreach (self::CROSSWALK as [$slug, $source, $targets]) {
                foreach ($targets as [$target, $relation, $confidence]) {
                    Alignment::updateOrCreate(
                        [
                            'source_id' => $this->objective($source),
                            'target_id' => $this->objective($target),
                            'relation' => $relation,
                        ],
                        [
                            'concept_id' => $concepts[$slug],
                            'confidence' => $confidence,
                            'method' => 'llm-assisted',
                            // reviewed_at queda NULL a propósito: fuera de producción hasta
                            // que un docente firme la revisión (regla 5 de CLAUDE.md).
                        ],
                    );
                }
            }

            foreach (self::PREREQUISITES as [$source, $target, $slug]) {
                Alignment::updateOrCreate(
                    [
                        'source_id' => $this->objective($source),
                        'target_id' => $this->objective($target),
                        'relation' => 'prerequisite',
                    ],
                    [
                        'concept_id' => $slug ? $concepts[$slug] : null,
                        'confidence' => 0.90,
                        'method' => 'manual',
                    ],
                );
            }
        });
    }

    /**
     * Índice (framework, código) => lista de candidatos.
     *
     * Se guarda una LISTA y no un id porque el código solo no identifica un objetivo
     * (regla 2 de CLAUDE.md): el importador MINEDEC replica la misma destreza en cada
     * grado de un subnivel, y un marco puede tener dos versiones vigentes con los
     * mismos códigos. Si hay ambigüedad, `objective()` falla en vez de elegir al azar.
     */
    private function loadObjectiveIndex(): void
    {
        $rows = DB::table('learning_objectives as lo')
            ->join('framework_versions as fv', 'fv.id', '=', 'lo.version_id')
            ->join('frameworks as f', 'f.id', '=', 'fv.framework_id')
            ->join('cur_nodes as n', 'n.id', '=', 'lo.node_id')
            ->select('lo.id', 'lo.native_code', 'f.code as fw', 'fv.label as ver', 'n.path')
            ->whereNotNull('lo.native_code')
            ->orderBy('fv.label')
            ->orderBy('n.path')
            ->get();

        foreach ($rows as $r) {
            $this->objectives[$r->fw.'|'.$r->native_code][] = $r;
        }
    }

    /**
     * @param  array{0: string, 1: string, 2?: string}  $ref  [framework, código, prefijo de path opcional]
     */
    private function objective(array $ref): string
    {
        [$fw, $code] = $ref;
        $candidates = $this->objectives[$fw.'|'.$code] ?? [];

        if ($candidates === []) {
            throw new RuntimeException(
                "Crosswalk: no existe el objetivo {$fw}|{$code}. ".
                '¿Sembraste antes CurriculumSeeder e InternationalFrameworksSeeder?'
            );
        }

        if (isset($ref[2])) {
            $candidates = array_values(array_filter(
                $candidates,
                fn ($c) => str_starts_with($c->path, $ref[2]),
            ));
        }

        if (count($candidates) > 1) {
            $donde = implode(', ', array_map(fn ($c) => $c->ver.':'.$c->path, $candidates));

            throw new RuntimeException(
                "Crosswalk: {$fw}|{$code} es ambiguo, aparece en {$donde}. ".
                'Añade un tercer elemento al par con el prefijo de path que lo desambigüe.'
            );
        }

        return $candidates[0]->id;
    }
}
