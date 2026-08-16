<?php

namespace Tests\Feature;

use App\Models\Alignment;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Track;
use App\Models\TrackPhase;
use App\Services\Blueprint\CourseBlueprint;
use App\Services\Blueprint\YamlWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * El puente AllyuHub → pipeline de cursos Moodle (e-learnium). Lo que se prueba
 * aquí es el CONTRATO que consume el compilador: si esto cambia sin querer, los
 * cursos generados se desalinean del currículo en silencio.
 */
class CourseBlueprintTest extends TestCase
{
    use RefreshDatabase;

    private FrameworkVersion $version;

    private CurNode $subject;

    private CurNode $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $this->version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);

        $bgu = CurNode::create([
            'version_id' => $this->version->id, 'node_type' => 'nivel',
            'native_code' => 'BGU', 'title' => ['es' => 'Bachillerato'], 'path' => 'bgu', 'seq' => 1,
        ]);
        $this->grade = CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $bgu->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º de Bachillerato'], 'path' => 'bgu.g11', 'seq' => 11,
        ]);
        $this->subject = CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $this->grade->id, 'node_type' => 'asignatura',
            'native_code' => 'M', 'title' => ['es' => 'Matemática'], 'path' => 'bgu.g11.m', 'seq' => 1,
        ]);
    }

    private function block(string $code, string $title, int $seq): CurNode
    {
        return CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $this->subject->id, 'node_type' => 'bloque',
            'native_code' => $code, 'title' => ['es' => $title],
            'path' => 'bgu.g11.m.'.strtolower($code), 'seq' => $seq,
        ]);
    }

    private function objective(CurNode $node, string $code, string $statement): LearningObjective
    {
        return LearningObjective::create([
            'node_id' => $node->id, 'version_id' => $this->version->id,
            'native_code' => $code, 'statement' => ['es' => $statement], 'is_verified' => true,
        ]);
    }

    private function build(?Track $track = null, ?string $idnumber = null): array
    {
        return app(CourseBlueprint::class)->forNode($this->subject->fresh(), $track, $idnumber);
    }

    public function test_arma_unidades_desde_los_bloques_en_orden(): void
    {
        $algebra = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $geometria = $this->block('M.5.2', 'Geometría y medida', 2);
        $this->objective($algebra, 'M.5.1.1', 'Reconocer conjuntos numéricos');
        $this->objective($geometria, 'M.5.2.1', 'Aplicar el teorema de Pitágoras');

        $blueprint = $this->build();

        $this->assertSame(CourseBlueprint::CONTRACT, $blueprint['contrato']);
        $this->assertSame('AH-M-G11', $blueprint['curso']['idnumber']);
        $this->assertSame('Matemática', $blueprint['curso']['titulo']);
        $this->assertSame('g11', $blueprint['curso']['grado']['codigo']);
        $this->assertSame(['unidad-01', 'unidad-02'], array_column($blueprint['unidades'], 'slug'));
        $this->assertSame('Álgebra y funciones', $blueprint['unidades'][0]['titulo']);
        $this->assertSame('M.5.2.1', $blueprint['unidades'][1]['destrezas'][0]['codigo']);
        $this->assertSame(2, $blueprint['resumen']['destrezas']);
    }

    /**
     * El orden es CURRICULAR (longitud + alfabético), no alfabético puro: la
     * destreza 10 va después de la 2, como en el catálogo.
     */
    public function test_ordena_las_destrezas_en_orden_curricular(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $this->objective($block, 'M.5.1.10', 'Décima');
        $this->objective($block, 'M.5.1.2', 'Segunda');

        $codes = array_column($this->build()['unidades'][0]['destrezas'], 'codigo');

        $this->assertSame(['M.5.1.2', 'M.5.1.10'], $codes);
    }

    /** Una DCD colgada de la asignatura (sin bloque) no puede desaparecer del curso. */
    public function test_las_destrezas_sin_bloque_van_a_la_unidad_cero(): void
    {
        $this->objective($this->subject, 'M.5.0.1', 'Destreza suelta');
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $this->objective($block, 'M.5.1.1', 'Con bloque');

        $blueprint = $this->build();

        $this->assertSame(['unidad-00', 'unidad-01'], array_column($blueprint['unidades'], 'slug'));
        $this->assertSame('M.5.0.1', $blueprint['unidades'][0]['destrezas'][0]['codigo']);
        $this->assertSame(2, $blueprint['resumen']['destrezas']);
    }

    /**
     * Los árboles no tienen todos la misma profundidad (MINEDEC llega a bloque;
     * Cambridge encadena subject → stage → strand). Si la unidad solo mirara a
     * sus hijos directos, exportar un grado o una asignatura Cambridge daría un
     * curso con unidades y CERO destrezas — y con éxito.
     */
    public function test_la_unidad_se_lleva_todo_su_subarbol(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $sub = CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $block->id, 'node_type' => 'subbloque',
            'native_code' => 'M.5.1.A', 'title' => ['es' => 'Números reales'],
            'path' => $block->path.'.a', 'seq' => 1,
        ]);
        $this->objective($block, 'M.5.1.1', 'Directa del bloque');
        $this->objective($sub, 'M.5.1.2', 'Nieta del nodo del curso');

        $blueprint = $this->build();

        $this->assertCount(1, $blueprint['unidades']);
        $this->assertSame(['M.5.1.1', 'M.5.1.2'],
            array_column($blueprint['unidades'][0]['destrezas'], 'codigo'));
        $this->assertSame(2, $blueprint['resumen']['destrezas']);
    }

    public function test_marca_lo_practicable_y_enlaza_a_allyuhub(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $conItems = $this->objective($block, 'M.5.1.1', 'Con ítems');
        $this->objective($block, 'M.5.1.2', 'Sin ítems');
        PracticeItem::factory()->count(2)->create(['objective_id' => $conItems->id]);

        $destrezas = $this->build()['unidades'][0]['destrezas'];

        $this->assertTrue($destrezas[0]['practicable']);
        $this->assertSame(2, $destrezas[0]['items']);
        $this->assertSame(url('/practicar/'.$conItems->id), $destrezas[0]['practica_url']);
        $this->assertFalse($destrezas[1]['practicable']);
        $this->assertNull($destrezas[1]['practica_url']);
        $this->assertSame(1, $this->build()['resumen']['destrezas_practicables']);
    }

    /**
     * Mismo criterio que el motor de práctica: la arista sin revisar y sin
     * method=manual no existe para nadie (ni para el selector ni para el curso).
     */
    public function test_trae_prerrequisitos_admitidos_y_descarta_los_no_revisados(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $avanzada = $this->objective($block, 'M.5.1.2', 'Avanzada');
        $base = $this->objective($block, 'M.5.1.1', 'Base');
        $ruido = $this->objective($block, 'M.5.1.3', 'Propuesta por la IA');

        Alignment::create([
            'source_id' => $avanzada->id, 'target_id' => $base->id,
            'relation' => 'prerequisite', 'method' => 'manual', 'confidence' => 1,
        ]);
        Alignment::create([
            'source_id' => $avanzada->id, 'target_id' => $ruido->id,
            'relation' => 'prerequisite', 'method' => 'llm-assisted', 'confidence' => 0.9,
        ]);

        $destrezas = collect($this->build()['unidades'][0]['destrezas'])->keyBy('codigo');

        $this->assertSame([[
            'id' => $base->id, 'codigo' => 'M.5.1.1', 'enunciado' => 'Base',
            'marco' => 'EC-MINEDEC', 'practicable' => false,
        ]], $destrezas['M.5.1.2']['prerrequisitos']);
        $this->assertSame([], $destrezas['M.5.1.1']['prerrequisitos']);
    }

    public function test_el_track_aporta_peso_y_fase(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $objective = $this->objective($block, 'M.5.1.1', 'Con peso');

        $track = Track::create(['code' => 'ORD', 'label' => ['es' => 'Ordinaria'], 'modality' => 'presencial']);
        $phase = TrackPhase::create([
            'track_id' => $track->id, 'seq' => 1, 'label' => ['es' => '1.º BGU'],
            'grade_node_id' => $this->grade->id,
        ]);
        $phase->objectives()->attach($objective->id, ['weight' => 1.5, 'source' => 'mapeo-interno']);

        $destreza = $this->build($track)['unidades'][0]['destrezas'][0];

        $this->assertSame('ORD', $this->build($track)['curso']['track']);
        // assertSame y no assertEquals: PostgreSQL devuelve el decimal como
        // string ("1.50") y assertEquals(1.5, '1.50') pasa — el contrato
        // cambiaba de tipo entre el CI (SQLite) y producción sin que nadie
        // se enterara.
        $this->assertSame(1.5, $destreza['peso']);
        $this->assertSame('1.º BGU', $destreza['fase']);
    }

    /** Sin track no hay peso: el mismo curso sirve a ORD y a PCEI. */
    public function test_sin_track_no_hay_peso(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $this->objective($block, 'M.5.1.1', 'Suelta');

        $this->assertNull($this->build()['unidades'][0]['destrezas'][0]['peso']);
        $this->assertNull($this->build()['curso']['track']);
    }

    /**
     * El fingerprint es el disparador de recompilación: cambia con el
     * currículo y NO con el nombre del curso ni con el idnumber.
     */
    public function test_el_fingerprint_sigue_al_curriculo_no_a_la_carcasa(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $objective = $this->objective($block, 'M.5.1.1', 'Original');

        $original = $this->build()['fingerprint'];

        $this->assertSame($original, $this->build(null, 'OTRO-IDNUMBER')['fingerprint']);

        $this->subject->update(['title' => ['es' => 'Matemáticas']]);
        $this->assertSame($original, $this->build()['fingerprint']);

        $objective->update(['statement' => ['es' => 'Corregido contra el PDF oficial']]);
        $this->assertNotSame($original, $this->build()['fingerprint']);
    }

    /**
     * Una reimportación cambia TODOS los uuid sin tocar una coma del currículo.
     * Si el fingerprint los llevara dentro, cada `migrate --seed` mandaría a
     * recompilar todos los cursos.
     */
    public function test_el_fingerprint_sobrevive_a_una_reimportacion(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $objective = $this->objective($block, 'M.5.1.1', 'Reconocer conjuntos numéricos');

        $original = $this->build()['fingerprint'];

        // Mismo currículo, filas nuevas (uuid nuevos).
        $objective->delete();
        $block->delete();
        $nuevo = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $this->objective($nuevo, 'M.5.1.1', 'Reconocer conjuntos numéricos');

        $this->assertSame($original, $this->build()['fingerprint']);
    }

    /**
     * Los uuid son v7: sus primeros hex son la marca de tiempo, así que un
     * prefijo NO identifica. Dos nodos sin código nativo creados en el mismo
     * milisegundo se llevaban el mismo idnumber — y el idnumber es la identidad
     * del curso en Moodle.
     */
    public function test_dos_nodos_sin_codigo_nativo_no_comparten_idnumber(): void
    {
        $ids = collect(['a', 'b'])->map(fn (string $slug) => CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $this->grade->id,
            'node_type' => 'asignatura', 'native_code' => null,
            'title' => ['es' => 'Sin código '.$slug], 'path' => 'bgu.g11.'.$slug, 'seq' => 1,
        ]))->map(fn (CurNode $n) => app(CourseBlueprint::class)->forNode($n)['curso']['idnumber']);

        $this->assertNotSame($ids[0], $ids[1]);
        $this->assertStringStartsWith('AH-', $ids[0]);
    }

    public function test_el_endpoint_devuelve_el_blueprint(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $this->objective($block, 'M.5.1.1', 'Reconocer conjuntos numéricos');

        $this->getJson("/api/v1/nodes/{$this->subject->id}/blueprint")
            ->assertOk()
            ->assertJsonPath('contrato', CourseBlueprint::CONTRACT)
            ->assertJsonPath('curso.idnumber', 'AH-M-G11')
            ->assertJsonPath('unidades.0.destrezas.0.codigo', 'M.5.1.1');

        $this->getJson("/api/v1/nodes/{$this->subject->id}/blueprint?idnumber=IA-MAT-1BGU")
            ->assertOk()
            ->assertJsonPath('curso.idnumber', 'IA-MAT-1BGU');

        $this->getJson("/api/v1/nodes/{$this->subject->id}/blueprint?track=NO-EXISTE")->assertNotFound();
        $this->getJson("/api/v1/nodes/{$this->subject->id}/blueprint?idnumber=mal%20idnumber")
            ->assertStatus(422);
    }

    /** Un nodo que no es un curso (un nivel entero) no se sirve: no se pagina un blueprint. */
    public function test_el_endpoint_rechaza_un_nodo_demasiado_grande(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $now = now();
        $rows = collect(range(1, 1501))->map(fn (int $i) => [
            'id' => (string) Str::uuid7(),
            'node_id' => $block->id, 'version_id' => $this->version->id,
            'native_code' => 'M.5.1.'.$i, 'statement' => json_encode(['es' => 'Masiva '.$i]),
            'created_at' => $now, 'updated_at' => $now,
        ])->all();
        LearningObjective::insert($rows);

        $this->getJson("/api/v1/nodes/{$this->subject->id}/blueprint")->assertStatus(422);
    }

    /** La API v1 es de SOLO LECTURA: el blueprint no abre una puerta de escritura. */
    public function test_el_endpoint_no_acepta_escrituras(): void
    {
        $this->postJson("/api/v1/nodes/{$this->subject->id}/blueprint")->assertStatus(405);
    }

    /**
     * El YAML que consume el compilador tiene que volver a leerse igual: el
     * oráculo es un parser de verdad (symfony/yaml), no una regex.
     */
    public function test_el_yaml_se_puede_volver_a_leer_sin_perder_nada(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones: "casos" especiales', 1);
        $this->objective($block, 'M.5.1.1', 'Resolver ecuaciones con {llaves}, #almohadillas: y "comillas"');
        $this->objective($block, 'M.5.1.2', 'Aplicar razones trigonométricas — con guion largo y tildes áéíóú');

        $blueprint = $this->build();
        $yaml = app(YamlWriter::class)->dump($blueprint);

        $this->assertSame($blueprint, Yaml::parse($yaml));
        // Determinista: dos generaciones del mismo grafo dan los mismos bytes.
        $this->assertSame($yaml, app(YamlWriter::class)->dump($this->build()));
    }

    /** Los números tienen que llegar al compilador como números y sin perder cifras. */
    public function test_el_yaml_no_degrada_los_numeros(): void
    {
        $caso = ['a' => 0.5, 'b' => 100.0, 'c' => 1.0e-5, 'd' => 1.23456789, 'e' => 3, 'f' => null];

        $this->assertSame($caso, Yaml::parse(app(YamlWriter::class)->dump($caso)));
    }

    /**
     * Un enunciado con UTF-8 roto (los hay saliendo de pdftotext) hacía que
     * json_encode devolviera false y la destreza se emitía VACÍA en un YAML
     * perfectamente válido. Un currículo corrupto tiene que hacer ruido.
     */
    public function test_el_yaml_estalla_ante_texto_corrupto_en_vez_de_vaciarlo(): void
    {
        $this->expectException(\JsonException::class);

        app(YamlWriter::class)->dump(['enunciado' => "caf\xE9 en latin1"]);
    }

    public function test_el_comando_escribe_curso_yaml_y_cobertura(): void
    {
        $block = $this->block('M.5.1', 'Álgebra y funciones', 1);
        $objective = $this->objective($block, 'M.5.1.1', 'Reconocer conjuntos numéricos');
        PracticeItem::factory()->create(['objective_id' => $objective->id]);

        $out = storage_path('framework/testing/blueprint-'.uniqid());

        // Sintaxis de ARRAY a propósito: con el comando en string, StringInput
        // se come los backslashes del path de Windows como si fueran escapes
        // de shell (--out se pierde y el comando "triunfa" imprimiendo a
        // consola). En Linux/CI nunca se vio porque storage_path no lleva \.
        $this->artisan('curso:blueprint', ['nodo' => 'M', '--grado' => 'g11', '--out' => $out])
            ->assertSuccessful();

        $yaml = Yaml::parse(file_get_contents($out.'/curso.yaml'));
        $this->assertSame('AH-M-G11', $yaml['curso']['idnumber']);
        $this->assertSame('M.5.1.1', $yaml['unidades'][0]['destrezas'][0]['codigo']);

        $cobertura = file_get_contents($out.'/COBERTURA.md');
        $this->assertStringContainsString('| `M.5.1.1` |', $cobertura);
        $this->assertStringContainsString('1 ítem(s)', $cobertura);
        $this->assertStringContainsString($yaml['fingerprint'], $cobertura);

        // Idempotente: repetir no cambia un byte.
        $before = file_get_contents($out.'/curso.yaml');
        $this->artisan('curso:blueprint', ['nodo' => 'M', '--grado' => 'g11', '--out' => $out])
            ->assertSuccessful();
        $this->assertSame($before, file_get_contents($out.'/curso.yaml'));

        array_map('unlink', glob($out.'/*'));
        rmdir($out);
    }

    /** Un código repetido entre grados NO se adivina (regla 2: la clave es marco+versión+código). */
    public function test_el_comando_no_adivina_ante_un_codigo_ambiguo(): void
    {
        $otroGrado = CurNode::create([
            'version_id' => $this->version->id, 'node_type' => 'grado',
            'native_code' => 'g12', 'title' => ['es' => '2.º de Bachillerato'], 'path' => 'bgu.g12', 'seq' => 12,
        ]);
        CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $otroGrado->id, 'node_type' => 'asignatura',
            'native_code' => 'M', 'title' => ['es' => 'Matemática'], 'path' => 'bgu.g12.m', 'seq' => 1,
        ]);

        $this->artisan('curso:blueprint M')->assertFailed();
        $this->artisan('curso:blueprint M --grado=g11')->assertSuccessful();
        $this->artisan('curso:blueprint NO-EXISTE')->assertFailed();
    }
}
