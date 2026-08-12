<?php

namespace Database\Seeders;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\Track;
use App\Models\TrackPhase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siembra el marco EC-MINEDEC desde database/data/curriculo-semilla.json
 * (13 grados, ~100 asignaturas, ~1010 destrezas — estructura oficial; las destrezas
 * con is_verified=true están cotejadas con el Currículo Nacional, el resto son
 * marcadores que el importador real sustituirá), crea los trayectos ORD + PCEI
 * y registra los dos simuladores existentes alineados a sus destrezas.
 */
class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/curriculo-semilla.json')), true);

        DB::transaction(function () use ($data) {
            // ---------- Marco y versión ----------
            $fw = Framework::create([
                'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
                'country' => 'EC',
                'label' => ['es' => 'Currículo Nacional del Ecuador'],
            ]);
            $ver = FrameworkVersion::create([
                'framework_id' => $fw->id, 'label' => '2016+2023',
                'source_url' => 'https://educacion.gob.ec/curriculo-2/',
            ]);

            // ---------- Árbol: nivel → subnivel → grado → asignatura → bloque ----------
            $nivelNodes = [];
            $subnivelNodes = [];
            $gradeNodes = [];   // gid => CurNode
            $seq = ['nivel' => 0, 'sub' => 0];

            foreach ($data['grados'] as $g) {
                $nivelKey = Str::slug($g['nivel'], '_');
                if (! isset($nivelNodes[$nivelKey])) {
                    $nivelNodes[$nivelKey] = CurNode::create([
                        'version_id' => $ver->id, 'node_type' => 'nivel',
                        'title' => ['es' => $g['nivel']], 'seq' => $seq['nivel']++,
                        'path' => $nivelKey,
                    ]);
                }
                $subKey = $nivelKey.'.'.Str::slug($g['subnivel'], '_');
                if (! isset($subnivelNodes[$subKey])) {
                    $subnivelNodes[$subKey] = CurNode::create([
                        'version_id' => $ver->id, 'parent_id' => $nivelNodes[$nivelKey]->id,
                        'node_type' => 'subnivel', 'title' => ['es' => $g['subnivel']],
                        'seq' => $seq['sub']++, 'path' => $subKey,
                    ]);
                }
                $gPath = $subKey.'.'.$g['id'];
                $grade = CurNode::create([
                    'version_id' => $ver->id, 'parent_id' => $subnivelNodes[$subKey]->id,
                    'node_type' => 'grado', 'native_code' => $g['id'],
                    'title' => ['es' => $g['label']], 'seq' => (int) substr($g['id'], 1),
                    'path' => $gPath, 'age_min' => $g['edad'], 'age_max' => $g['edad'] + 1,
                    'attrs' => [
                        'corto' => $g['corto'], 'horas_min' => $g['horasMin'],
                        'cambridge' => $g['cambridge'], 'ib' => $g['ib'],
                    ],
                ]);
                $gradeNodes[$g['id']] = $grade;

                foreach ($g['asignaturas'] as $si => $s) {
                    $sPath = $gPath.'.'.Str::slug($s['codigo'], '_');
                    $subj = CurNode::create([
                        'version_id' => $ver->id, 'parent_id' => $grade->id,
                        'node_type' => 'asignatura', 'native_code' => $s['codigo'],
                        'title' => ['es' => $s['nombre']], 'seq' => $si, 'path' => $sPath,
                        'attrs' => ['area' => $s['area'], 'horas' => $s['horas']],
                    ]);
                    foreach ($s['unidades'] as $u) {
                        $uPath = $sPath.'.b'.$u['n'];
                        $bloque = CurNode::create([
                            'version_id' => $ver->id, 'parent_id' => $subj->id,
                            'node_type' => 'bloque', 'title' => ['es' => $u['titulo']],
                            'seq' => $u['n'], 'path' => $uPath,
                        ]);
                        foreach ($u['destrezas'] as $d) {
                            LearningObjective::create([
                                'node_id' => $bloque->id, 'version_id' => $ver->id,
                                'native_code' => $d['codigo'],
                                'statement' => ['es' => $d['texto']],
                                'is_essential' => $d['esencial'],
                                'is_verified' => $d['verificada'],
                            ]);
                        }
                    }
                }
            }

            // ---------- Trayectos ----------
            $ord = Track::create([
                'code' => 'ORD', 'label' => ['es' => 'Educación ordinaria'],
                'modality' => 'presencial',
            ]);
            foreach ($gradeNodes as $gid => $node) {
                TrackPhase::create([
                    'track_id' => $ord->id, 'seq' => (int) substr($gid, 1),
                    'label' => $node->title, 'duration_months' => 10,
                    'grade_node_id' => $node->id,
                ]);
            }

            // PCEI/EPJA — duraciones del portal Juntos; VALIDAR contra los acuerdos (§riesgos).
            $pcei = [
                ['PCEI-ALFA', 'Alfabetización', 10, 15, ['g2', 'g3']],
                ['PCEI-POST', 'Post-alfabetización', 20, 15, ['g4', 'g5', 'g6', 'g7']],
                ['PCEI-BSI', 'Básica Superior Intensiva', 11, 15, ['g8', 'g9', 'g10']],
                ['PCEI-BI', 'Bachillerato Intensivo', 15, 18, ['g11', 'g12', 'g13']],
            ];
            foreach ($pcei as [$code, $label, $months, $minAge, $grades]) {
                $track = Track::create([
                    'code' => $code, 'label' => ['es' => $label],
                    'modality' => 'semipresencial', 'min_age' => $minAge,
                    'min_gap_years' => 3, 'module_days' => 100,
                    'attrs' => ['normativa' => ['MINEDUC-2024-00046-A', 'MINEDUC-2025-00010-A',
                                                'MINEDUC-2017-00040-A']],
                ]);
                // Fase 0: propedéutica obligatoria (reforma 2025-00010-A).
                TrackPhase::create([
                    'track_id' => $track->id, 'seq' => 0,
                    'label' => ['es' => 'Fase propedéutica — diagnóstico y nivelación'],
                    'is_propedeutic' => true,
                ]);
                $per = $months / count($grades);
                foreach ($grades as $i => $gid) {
                    $phase = TrackPhase::create([
                        'track_id' => $track->id, 'seq' => $i + 1,
                        'label' => ['es' => 'Módulo '.($i + 1).' · equivale a '.$gradeNodes[$gid]->title['es']],
                        'duration_months' => round($per, 1),
                        'grade_node_id' => $gradeNodes[$gid]->id,
                    ]);
                    // Dosificación semilla: todos los objetivos del grado equivalente.
                    // El importador del Acuerdo 2017-00040-A la sustituirá con la dosificación real.
                    $ids = LearningObjective::query()
                        ->whereIn('node_id', CurNode::query()->descendantsOf($gradeNodes[$gid])->pluck('id'))
                        ->pluck('id');
                    $phase->objectives()->attach(
                        $ids->mapWithKeys(fn ($id) => [$id => ['source' => 'mapeo-interno']])->all()
                    );
                }
            }

            // ---------- Los dos simuladores reales del prototipo ----------
            $sims = [
                ['plano-inclinado', 'Laboratorio: plano inclinado con rozamiento',
                 ['CN.F.5.1.9', 'CN.F.5.1.12', 'CN.4.3.5', 'CN.4.3.10'], 25],
                ['lente-delgada', 'Laboratorio: banco óptico de lente delgada',
                 ['CN.F.5.3.7', 'CN.F.5.3.8'], 20],
            ];
            foreach ($sims as [$slug, $title, $codes, $dur]) {
                $res = Resource::create([
                    'slug' => $slug, 'kind' => 'lab',
                    'title' => ['es' => $title], 'duration_min' => $dur,
                    'status' => 'published', 'license' => 'CC BY-SA 4.0',
                    'a11y' => ['wcag' => '2.2AA', 'keyboard' => true, 'screenreader' => true],
                ]);
                $v = ResourceVersion::create([
                    'resource_id' => $res->id, 'semver' => '1.0.0',
                    'published_at' => now(),
                ]);
                $res->update(['current_version_id' => $v->id]);
                $objs = LearningObjective::whereIn('native_code', $codes)->pluck('id');
                $res->objectives()->attach(
                    $objs->mapWithKeys(fn ($id) => [$id => ['role' => 'primary']])->all()
                );
            }
        });
    }
}
