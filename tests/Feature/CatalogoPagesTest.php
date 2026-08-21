<?php

namespace Tests\Feature;

use App\Models\Alignment;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Catálogo del currículo (misión ANTY, frente 1): /catalogo, /catalogo/{node},
 * /destreza/{objective} y /buscar. Aquí viven los oráculos 1, 5, 6, 7 y 8
 * del lado PHP; el 2 y el 4 (DOM/axe) viven en Vitest; el 3 en su propio test.
 */
class CatalogoPagesTest extends TestCase
{
    use RefreshDatabase;

    private FrameworkVersion $version;

    private CurNode $nivel;

    private CurNode $subnivel;

    private CurNode $grado;

    private CurNode $asignatura;

    private CurNode $bloque;

    private LearningObjective $verificada;   // con ítems

    private LearningObjective $marcador;     // sin ítems, sin verificar

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional del Ecuador'],
        ]);
        $this->version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);

        $this->nivel = $this->nodo(null, 'nivel', null, 'Bachillerato', 'bgu');
        $this->subnivel = $this->nodo($this->nivel, 'subnivel', null, 'BGU', 'bgu.bgu');
        $this->grado = $this->nodo($this->subnivel, 'grado', 'g11', '1.º BGU', 'bgu.bgu.g11');
        $this->asignatura = $this->nodo($this->grado, 'asignatura', 'CN.F', 'Física', 'bgu.bgu.g11.cn_f');
        $this->bloque = $this->nodo($this->asignatura, 'bloque', null, 'Movimiento y fuerza', 'bgu.bgu.g11.cn_f.b2');

        $this->verificada = LearningObjective::create([
            'node_id' => $this->bloque->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.1.12',
            'statement' => ['es' => 'Determinar el coeficiente de rozamiento entre dos superficies.'],
            'is_verified' => true,
        ]);
        PracticeItem::create([
            'objective_id' => $this->verificada->id,
            'statement' => ['es' => 'μs = {mu}'], 'params' => ['mu' => ['min' => 0.2, 'max' => 0.9, 'step' => 0.05]],
            'solution_expr' => 'rad2deg(atan(mu))', 'tolerance' => 0.5, 'tolerance_kind' => 'abs',
            // Firmado: este fixture prueba el MOTOR, y un ítem sin
            // revisar no llega al motor (ver DominioYFirmaTest).
            'reviewed_at' => now(),
        ]);

        $this->marcador = LearningObjective::create([
            'node_id' => $this->bloque->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.9.99',
            'statement' => ['es' => 'Reconocer los contenidos del bloque en el nivel correspondiente.'],
            'is_verified' => false,
        ]);

        $this->ana = User::factory()->create();
    }

    private function nodo(?CurNode $parent, string $type, ?string $code, string $title, string $path,
        array $attrs = [], ?float $edad = null): CurNode
    {
        // attrs y age_min son NOT NULL con default: se omiten si no se piden.
        return CurNode::create(array_filter([
            'version_id' => $this->version->id, 'parent_id' => $parent?->id,
            'node_type' => $type, 'native_code' => $code, 'age_min' => $edad,
            'title' => ['es' => $title], 'path' => $path, 'attrs' => $attrs ?: null,
        ], fn ($v) => $v !== null));
    }

    // ---------- Frente 2: el catálogo con cara ----------

    /**
     * La tarjeta de un grado necesita la etiqueta CORTA («1.º BGU», no «Primer
     * Año de Bachillerato General Unificado»), la edad y cuántas destrezas hay
     * debajo — que no cuelgan del grado, sino de sus bloques.
     */
    public function test_las_tarjetas_de_grado_traen_etiqueta_corta_edad_y_conteos(): void
    {
        $this->grado->update(['attrs' => ['corto' => '1.º BGU'], 'age_min' => 15]);

        $this->actingAs($this->ana)->get('/catalogo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.0.children.0.children.0.corto', '1.º BGU')
                // Comparación numérica, no de tipo: pgsql devuelve la edad como
                // float y sqlite como int, y el JSON conserva esa diferencia.
                ->where('tree.0.children.0.children.0.edad', fn ($edad) => (float) $edad === 15.0)
                ->where('tree.0.children.0.children.0.destrezas', 2)
                ->where('tree.0.children.0.children.0.verificadas', 1)
                ->where('tree.0.children.0.children.0.practicables', 1)
            );
    }

    /** Sin estilos sembrados, la tarjeta no lleva color: la UI degrada sola. */
    public function test_un_grado_sin_atributos_no_inventa_etiqueta_ni_edad(): void
    {
        $this->actingAs($this->ana)->get('/catalogo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('tree.0.children.0.children.0.corto')
                ->missing('tree.0.children.0.children.0.edad')
                ->where('tree.0.children.0.children.0.title', '1.º BGU')
            );
    }

    /** La tarjeta de asignatura lleva su icono, su color y su cuenta propia. */
    public function test_las_tarjetas_de_asignatura_traen_icono_color_y_conteos(): void
    {
        $this->asignatura->update(['attrs' => ['icon' => '⚛️', 'color' => '#3aa675']]);

        $this->actingAs($this->ana)->get("/catalogo/{$this->grado->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('children.0.title', 'Física')
                ->where('children.0.icon', '⚛️')
                ->where('children.0.color', '#3aa675')
                ->where('children.0.destrezas', 2)
                ->where('children.0.practicables', 1)
            );
    }

    /**
     * El acento se HEREDA: estando en un bloque de Física, la página sigue
     * sabiendo que es Física — el nodo actual no tiene color propio.
     */
    public function test_el_bloque_hereda_el_acento_de_su_asignatura(): void
    {
        $this->asignatura->update(['attrs' => ['icon' => '⚛️', 'color' => '#3aa675']]);

        $this->actingAs($this->ana)->get("/catalogo/{$this->bloque->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('asignatura.title', 'Física')
                ->where('asignatura.color', '#3aa675')
                ->missing('node.color')
            );

        // Y la ficha de la destreza, también.
        $this->actingAs($this->ana)->get("/destreza/{$this->verificada->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('asignatura.title', 'Física')
                ->where('asignatura.icon', '⚛️')
            );
    }

    /** Por encima de la asignatura no hay acento que heredar: null, no basura. */
    public function test_sin_asignatura_en_la_cadena_no_hay_acento(): void
    {
        $this->actingAs($this->ana)->get("/catalogo/{$this->grado->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('asignatura', null));
    }

    /** Las tarjetas del catálogo no pueden costar una consulta cada una. */
    public function test_el_catalogo_no_hace_una_consulta_por_tarjeta(): void
    {
        $this->calentarCacheDeLaCsp();
        $this->actingAs($this->ana);

        $contar = function (): int {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->get('/catalogo')->assertOk();

            return $n;
        };
        $pocos = $contar();

        // Nueve grados más, cada uno con su asignatura, su bloque y destrezas.
        foreach (range(1, 9) as $i) {
            $g = $this->nodo($this->subnivel, 'grado', "g{$i}", "Grado {$i}", "bgu.bgu.gx{$i}");
            $a = $this->nodo($g, 'asignatura', 'M', 'Matemática', "bgu.bgu.gx{$i}.m");
            $b = $this->nodo($a, 'bloque', null, 'Álgebra', "bgu.bgu.gx{$i}.m.b1");
            LearningObjective::create([
                'node_id' => $b->id, 'version_id' => $this->version->id,
                'native_code' => "M.5.1.{$i}", 'statement' => ['es' => 'x'], 'is_verified' => true,
            ]);
        }

        $this->assertSame($pocos, $contar(), 'Las consultas crecieron con el número de tarjetas');
    }

    /**
     * LA FRONTERA del contenido abierto, en un solo sitio y en las dos
     * direcciones. Este test nació porque solo `/catalogo` tenía oráculo de
     * sesión y estaba escondido dentro de otro; se podían sacar del grupo
     * `auth` las demás páginas sin que nada se pusiera rojo. Sigue haciendo ese
     * trabajo, pero al revés de como empezó: ahora lo que hay que vigilar es
     * que el contenido NO se vuelva a cerrar y que lo del alumno NO se abra.
     *
     * Se navega y se practica sin sesión (modelo Khan); su casa y su progreso
     * guardado siguen siendo suyos.
     */
    public function test_la_frontera_entre_lo_abierto_y_lo_del_alumno(): void
    {
        $recurso = Resource::create([
            'slug' => 'sim', 'kind' => 'lab', 'title' => ['es' => 'Sim'], 'status' => 'published',
        ]);

        foreach ([
            '/catalogo',
            "/catalogo/{$this->grado->id}",
            "/destreza/{$this->verificada->id}",
            '/buscar',
            '/buscar?q=rozamiento',
            "/recurso/{$recurso->id}",
            "/practicar/{$this->verificada->id}",
        ] as $url) {
            $this->get($url)->assertOk("{$url} se cerró: el contenido es abierto");
        }

        foreach (['/inicio', '/progreso'] as $url) {
            $this->get($url)->assertRedirect('/entrar', "{$url} quedó abierta al mundo");
        }
    }

    /**
     * REGRESIÓN (auditoría): el filtro «manual o revisada» de los prerrequisitos
     * —la misma política que navega el AdaptiveSelector— se podía borrar entero
     * sin un solo test rojo, porque todos los fixtures creaban aristas
     * `manual`. La primera tanda de prerrequisitos propuestos por IA habría
     * aparecido en la ficha del alumno como hecho consumado, saltándose la
     * regla 5.
     */
    public function test_un_prerrequisito_propuesto_por_ia_sin_revisar_no_se_muestra(): void
    {
        $previa = LearningObjective::create([
            'node_id' => $this->bloque->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.1.9', 'statement' => ['es' => 'Plano inclinado'],
            'is_verified' => true,
        ]);
        $propuesta = Alignment::create([
            'source_id' => $this->verificada->id, 'target_id' => $previa->id,
            'relation' => 'prerequisite', 'method' => 'llm-assisted', 'confidence' => 0.95,
        ]);

        $this->actingAs($this->ana)->get("/destreza/{$this->verificada->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('prerequisites', 0));

        // Firmada por un docente, sí entra.
        $propuesta->update(['reviewed_at' => now(), 'reviewed_by' => $this->ana->id]);

        $this->actingAs($this->ana)->get("/destreza/{$this->verificada->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('prerequisites', 1)
                ->where('prerequisites.0.native_code', 'CN.F.5.1.9')
            );
    }

    /**
     * REGRESIÓN (despliegue): la raíz servía el welcome de fábrica, que pedía
     * un resources/js/app.js inexistente y daba 500 EN PRODUCCIÓN (con
     * manifest de Vite). Sigue sin haber vistas de fábrica; lo que cambió es
     * el destino: el visitante ve la PORTADA (antes se le rebotaba al catálogo,
     * que a su vez lo mandaba a /entrar) y el alumno con sesión, su inicio.
     */
    public function test_la_raiz_no_sirve_vistas_de_fabrica(): void
    {
        $this->get('/')->assertOk();
        $this->actingAs($this->ana)->get('/')->assertRedirect('/inicio');
        $this->assertFileDoesNotExist(resource_path('views/welcome.blade.php'));
    }

    // ---------- /catalogo ----------

    public function test_catalogo_monta_el_arbol_hasta_grado(): void
    {
        // Abierto: el mismo árbol para el invitado y para el alumno.
        $this->get('/catalogo')->assertOk();

        $this->actingAs($this->ana)->get('/catalogo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalogo')
                ->has('frameworks', 1)
                ->where('frameworks.0.code', 'EC-MINEDEC')
                ->has('tree', 1)
                ->where('tree.0.title', 'Bachillerato')
                ->where('tree.0.children.0.children.0.title', '1.º BGU')
                ->where('tree.0.children.0.children.0.node_type', 'grado')
                // Hasta grado: las asignaturas NO van en el primer pintado.
                ->missing('tree.0.children.0.children.0.children')
            );
    }

    // ---------- /catalogo/{node} ----------

    public function test_catalogo_nodo_muestra_migas_hijos_y_destrezas(): void
    {
        $this->actingAs($this->ana)->get("/catalogo/{$this->bloque->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalogo-nodo')
                ->where('node.title', 'Movimiento y fuerza')
                // Migas: Nivel › Subnivel › Grado › Asignatura (el propio nodo no es miga).
                ->has('breadcrumbs', 4)
                ->where('breadcrumbs.0.title', 'Bachillerato')
                ->where('breadcrumbs.3.title', 'Física')
                ->has('objectives.data', 2)
                // Honestidad del catálogo: cada destreza declara su estado.
                ->where('objectives.data.0.is_verified', true)
                ->where('objectives.data.0.has_items', true)
                ->where('objectives.data.1.is_verified', false)
                ->where('objectives.data.1.has_items', false)
                // ORÁCULO 1: la ficha de lista jamás lleva la expresión de solución.
                ->missing('objectives.data.0.solution_expr')
            );
    }

    public function test_catalogo_nodo_pagina_de_50_en_50(): void
    {
        foreach (range(1, 60) as $i) {
            LearningObjective::create([
                'node_id' => $this->bloque->id, 'version_id' => $this->version->id,
                'native_code' => sprintf('CN.F.5.2.%d', $i),
                'statement' => ['es' => "Marcador {$i}"],
            ]);
        }

        $this->actingAs($this->ana)->get("/catalogo/{$this->bloque->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalogo-nodo')
                ->has('objectives.data', 50)
                ->where('objectives.total', 62)
            );
    }

    /** ORÁCULO 7: el coste en consultas no crece con el número de destrezas. */
    public function test_catalogo_nodo_sin_n_mas_1(): void
    {
        $this->actingAs($this->ana);

        $contar = function (string $url): int {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->get($url)->assertOk();

            return $n;
        };

        $this->calentarCacheDeLaCsp();
        $pocas = $contar("/catalogo/{$this->bloque->id}");

        foreach (range(1, 200) as $i) {
            LearningObjective::create([
                'node_id' => $this->bloque->id, 'version_id' => $this->version->id,
                'native_code' => sprintf('CN.F.5.3.%d', $i),
                'statement' => ['es' => "Marcador masivo {$i}"],
            ]);
        }

        $muchas = $contar("/catalogo/{$this->bloque->id}");

        $this->assertSame($pocas, $muchas, 'Las consultas crecieron con el número de destrezas (N+1)');
    }

    /** ORÁCULO 7 (segunda mitad): el HTML inicial no pesa un mundo. */
    public function test_catalogo_nodo_html_inicial_acotado(): void
    {
        foreach (range(1, 200) as $i) {
            LearningObjective::create([
                'node_id' => $this->bloque->id, 'version_id' => $this->version->id,
                'native_code' => sprintf('CN.F.5.4.%d', $i),
                'statement' => ['es' => str_repeat("Enunciado de relleno {$i}. ", 5)],
            ]);
        }

        $html = $this->actingAs($this->ana)->get("/catalogo/{$this->bloque->id}")->getContent();

        $this->assertLessThan(150 * 1024, strlen($html), 'El primer pintado pesa más de 150 KB');
    }

    // ---------- /destreza/{objective} ----------

    public function test_ficha_de_destreza_completa(): void
    {
        // Recurso publicado alineado + prerrequisito manual.
        $sim = Resource::create([
            'slug' => 'plano-inclinado', 'kind' => 'lab',
            'title' => ['es' => 'Laboratorio: plano inclinado'], 'status' => 'published',
        ]);
        $sim->objectives()->attach($this->verificada->id, ['role' => 'primary']);

        Alignment::create([
            'source_id' => $this->verificada->id, 'target_id' => $this->marcador->id,
            'relation' => 'prerequisite', 'method' => 'manual', 'confidence' => 0.9,
        ]);

        $this->actingAs($this->ana)->get("/destreza/{$this->verificada->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('destreza')
                ->where('objective.native_code', 'CN.F.5.1.12')
                ->where('objective.is_verified', true)
                ->where('objective.has_items', true)
                ->has('breadcrumbs', 5)   // hasta el bloque incluido
                ->where('breadcrumbs.4.title', 'Movimiento y fuerza')
                ->has('resources', 1)
                ->where('resources.0.slug', 'plano-inclinado')
                ->has('prerequisites', 1)
                ->where('prerequisites.0.native_code', 'CN.F.5.9.99')
                ->has('alignments', 0)   // nada revisado aún: sección vacía y honesta
                // ORÁCULO 1: nada sensible en las props.
                ->missing('objective.solution_expr')
            );
    }

    /** ORÁCULO 5: un borrador no aparece NI su bundle_url viaja en el HTML. */
    public function test_borradores_invisibles_en_la_ficha(): void
    {
        $draft = Resource::create([
            'slug' => 'sim-borrador', 'kind' => 'lab',
            'title' => ['es' => 'Simulador en obras'], 'status' => 'draft',
        ]);
        $v = ResourceVersion::create([
            'resource_id' => $draft->id, 'semver' => '0.1.0',
            'bundle_url' => 'https://cdn.allyuhub.test/SECRETO-BORRADOR/0.1.0/',
        ]);
        $draft->update(['current_version_id' => $v->id]);
        $draft->objectives()->attach($this->verificada->id, ['role' => 'primary']);

        $respuesta = $this->actingAs($this->ana)->get("/destreza/{$this->verificada->id}");

        $respuesta->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('resources', 0));
        $this->assertStringNotContainsString('SECRETO-BORRADOR', $respuesta->getContent());
        $this->assertStringNotContainsString('sim-borrador', $respuesta->getContent());

        // Tampoco alcanzable por URL directa (ya lo garantiza /recurso, se re-verifica).
        $this->actingAs($this->ana)->get("/recurso/{$draft->id}")->assertNotFound();
    }

    /** ORÁCULO 6a: una alineación REVISADA con confianza alta SÍ aparece. */
    public function test_equivalencia_revisada_aparece(): void
    {
        $cambridge = $this->objetivoCambridge();

        Alignment::create([
            'source_id' => $this->verificada->id, 'target_id' => $cambridge->id,
            'relation' => 'exact', 'method' => 'llm-assisted', 'confidence' => 0.9,
            'reviewed_by' => $this->ana->id, 'reviewed_at' => now(),
        ]);

        $this->actingAs($this->ana)->get("/destreza/{$this->verificada->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('alignments', 1)
                ->where('alignments.0.native_code', '0625.1.5.1')
                ->where('alignments.0.framework', 'CAIE-IGCSE')
                ->where('alignments.0.relation', 'exact')
            );
    }

    /** ORÁCULO 6b: la misma alineación SIN revisar NO aparece. */
    public function test_equivalencia_sin_revisar_no_aparece(): void
    {
        $cambridge = $this->objetivoCambridge();

        Alignment::create([
            'source_id' => $this->verificada->id, 'target_id' => $cambridge->id,
            'relation' => 'exact', 'method' => 'llm-assisted', 'confidence' => 0.9,
            // reviewed_at NULL a propósito: la IA propone, el docente dispone.
        ]);

        $respuesta = $this->actingAs($this->ana)->get("/destreza/{$this->verificada->id}");

        $respuesta->assertInertia(fn (Assert $page) => $page->has('alignments', 0));
        $this->assertStringNotContainsString('0625.1.5.1', $respuesta->getContent());
    }

    /** La ficha funciona también para una destreza de OTRO marco (Cambridge). */
    public function test_ficha_de_destreza_cambridge(): void
    {
        $cambridge = $this->objetivoCambridge();

        $this->actingAs($this->ana)->get("/destreza/{$cambridge->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('destreza')
                ->where('objective.native_code', '0625.1.5.1')
                ->where('objective.has_items', false)
            );
    }

    public function test_node_y_destreza_inexistentes_dan_404_limpio(): void
    {
        $this->actingAs($this->ana);
        $this->get('/catalogo/00000000-0000-0000-0000-000000000000')->assertNotFound();
        $this->get('/destreza/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    // ---------- /buscar ----------

    /** Auditoría: una arista prerequisite REVISADA no es una «equivalencia». */
    public function test_prerrequisito_revisado_no_se_disfraza_de_equivalencia(): void
    {
        Alignment::create([
            'source_id' => $this->verificada->id, 'target_id' => $this->marcador->id,
            'relation' => 'prerequisite', 'method' => 'manual', 'confidence' => 0.9,
            'reviewed_by' => $this->ana->id, 'reviewed_at' => now(),   // firmada por docente
        ]);

        $this->actingAs($this->ana)->get("/destreza/{$this->verificada->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('alignments', 0)      // NO es una equivalencia entre marcos
                ->has('prerequisites', 1)   // sigue siendo lo que es: un prerrequisito
            );
    }

    /** Auditoría: q manipulada (array) o desmedida no revienta ni expulsa de la app. */
    public function test_buscar_aguanta_queries_hostiles(): void
    {
        $this->actingAs($this->ana);

        // q[] como array: 200 con el buscador vacío, jamás un 500.
        $this->get('/buscar?q[]=rozamiento')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('buscar')->where('results', null));

        // q de 500 caracteres: 200 con resultados nulos, jamás un redirect fuera.
        $this->get('/buscar?q='.str_repeat('a', 500))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('buscar')->where('results', null));
    }

    /** Auditoría (regresión de intención del PR #10): orden NATURAL, no lexicográfico. */
    public function test_las_destrezas_se_ordenan_naturalmente_no_lexicograficamente(): void
    {
        LearningObjective::create([
            'node_id' => $this->bloque->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.1.2', 'statement' => ['es' => 'La dos'],
        ]);
        LearningObjective::create([
            'node_id' => $this->bloque->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.1.10', 'statement' => ['es' => 'La diez'],
        ]);

        $data = $this->actingAs($this->ana)
            ->get("/catalogo/{$this->bloque->id}")
            ->inertiaPage()['props']['objectives']['data'];

        $codigos = array_column($data, 'native_code');
        $this->assertLessThan(
            array_search('CN.F.5.1.10', $codigos),
            array_search('CN.F.5.1.2', $codigos),
            'CN.F.5.1.2 debe listarse ANTES que CN.F.5.1.10 (orden curricular, no de cadena)',
        );
    }

    public function test_buscar_renderiza_resultados_del_servidor(): void
    {
        $this->actingAs($this->ana)->get('/buscar?q=rozamiento')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('buscar')
                ->where('q', 'rozamiento')
                ->has('results', 1)
                ->where('results.0.native_code', 'CN.F.5.1.12')
                ->where('results.0.is_verified', true)
            );

        // Menos de 3 caracteres: sin resultados, sin error.
        $this->actingAs($this->ana)->get('/buscar?q=ab')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('results', null));
    }

    /** ORÁCULO 8: ningún enlace del catálogo muere con los datos sembrados. */
    public function test_ningun_enlace_del_catalogo_muere(): void
    {
        $this->actingAs($this->ana);

        // 1) Del árbol del catálogo salen nodos: todos responden.
        $arbol = $this->get('/catalogo')->inertiaPage()['props']['tree'] ?? null;
        $porVisitar = collect($arbol);
        $visitados = 0;
        while ($porVisitar->isNotEmpty()) {
            $n = $porVisitar->shift();
            $this->get('/catalogo/'.$n['id'])->assertOk();
            $visitados++;
            $porVisitar = $porVisitar->concat($n['children'] ?? []);
        }
        $this->assertGreaterThanOrEqual(3, $visitados);

        // 2) Del nodo salen destrezas: cada ficha responde.
        $props = $this->get("/catalogo/{$this->bloque->id}")->inertiaPage()['props'];
        foreach ($props['objectives']['data'] as $destreza) {
            $this->get('/destreza/'.$destreza['id'])->assertOk();
            // 3) Y practicar una destreza SIN ítems no es un 404: es la página
            //    con su estado vacío (el botón va deshabilitado en la ficha).
            $this->get('/practicar/'.$destreza['id'])->assertOk();
        }
    }

    private function objetivoCambridge(): LearningObjective
    {
        $caie = Framework::create([
            'code' => 'CAIE-IGCSE', 'authority' => 'Cambridge International', 'kind' => 'international',
            'label' => ['es' => 'Cambridge IGCSE'],
        ]);
        $ver = FrameworkVersion::create(['framework_id' => $caie->id, 'label' => '2023-2025']);
        $topic = CurNode::create([
            'version_id' => $ver->id, 'node_type' => 'topic',
            'native_code' => '0625', 'title' => ['es' => 'Physics — Forces'], 'path' => 'igcse.0625.f',
        ]);

        return LearningObjective::create([
            'node_id' => $topic->id, 'version_id' => $ver->id,
            'native_code' => '0625.1.5.1',
            'statement' => ['es' => 'Efectos de las fuerzas (paráfrasis).'],
        ]);
    }
}
