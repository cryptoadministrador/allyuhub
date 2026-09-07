<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\User;
use App\Services\Practice\Lenguas;
use Database\Seeders\CambridgeEnglishSeeder;
use Database\Seeders\CefrSeeder;
use Database\Seeders\InternationalFrameworksSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PR 6 · CAMBRIDGE INGLÉS ENTRA AL GRAFO, y el cascarón deja de dar por hecho
 * que un curso son nueve unidades del MCER.
 *
 * El inglés rompe las tres reglas que el cascarón tenía ESCRITAS DENTRO: no son
 * nueve unidades (son tres Stages), su marco no es el MCER (es CAIE) y sus
 * códigos no empiezan por `A1.`. Los oráculos de aquí fijan las dos mitades:
 *
 *  1. **Los tres cursos existentes NO CAMBIAN.** Es el riesgo real del PR: la
 *     generalización se hace sobre un curso vivo en producción.
 *  2. El inglés existe, con SU estructura, y sin contenido inventado.
 */
class CursoInglesTest extends TestCase
{
    use RefreshDatabase;

    /** El molde del MCER, ESCRITO A MANO aquí a propósito. */
    private const UNIDADES_MCER = [
        1 => ['Primer contacto', ['A1.CO.2', 'A1.IO.3', 'A1.CE.1']],
        2 => ['Yo y los míos', ['A1.PO.1', 'A1.CE.2', 'A1.EE.2']],
        3 => ['Mi día a día', ['A1.CO.3', 'A1.PO.2', 'A1.IO.2']],
        4 => ['Lo que me gusta', ['A1.PO.2', 'A1.EE.1', 'A1.IO.2']],
        5 => ['En la ciudad', ['A1.CE.3', 'A1.IO.2', 'A1.CO.1']],
        6 => ['Comer y beber', ['A1.IO.2', 'A1.PO.2', 'A1.CO.1']],
        7 => ['Comprar y el tiempo', ['A1.IO.2', 'A1.CE.3', 'A1.PO.2']],
        8 => ['Contar lo que hice', ['A1.PO.2', 'A1.EE.1', 'A1.IO.2']],
        9 => ['Repaso y proyecto', ['A1.IO.1', 'A1.PO.2', 'A1.CE.3']],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
    }

    // ================= 1. los tres cursos vivos NO cambian =================

    /**
     * EL ORÁCULO DE NO-REGRESIÓN del PR: recorre `/corso/it`, `/corso/fr` y
     * `/corso/de` y sus NUEVE unidades, y compara contra el molde escrito a mano
     * arriba —no contra `cursos-lenguas.php`, que sería preguntarle al acusado—.
     * Si la generalización mueve una unidad, un título o un descriptor de los
     * cursos que ya están en producción, esto cae.
     */
    public function test_los_tres_cursos_existentes_siguen_identicos(): void
    {
        foreach (['it', 'fr', 'de'] as $lengua) {
            $this->get("/corso/{$lengua}")->assertOk()
                ->assertInertia(function (Assert $p) use ($lengua) {
                    $p->component('corso')->where('lengua', $lengua)->has('unidades', 9);
                    foreach (self::UNIDADES_MCER as $n => [$titulo, $_]) {
                        $p->where('unidades.'.($n - 1).'.n', $n)
                            ->where('unidades.'.($n - 1).'.titulo', $titulo);
                    }
                });

            foreach (self::UNIDADES_MCER as $n => [$titulo, $codigos]) {
                $this->get("/corso/{$lengua}/u{$n}")->assertOk()
                    ->assertInertia(fn (Assert $p) => $p
                        ->component('corso-unidad')
                        ->where('unidad.n', $n)
                        ->where('unidad.titulo', $titulo)
                        // Los «Puedo…» son los descriptores del molde, en orden
                        // y sin repetir (el servicio los pasa por `unique()`).
                        ->where('puedo', fn ($puedo) => collect($puedo)->pluck('code')->all()
                            === array_values(array_unique($codigos))));
            }
        }
    }

    /** Y la tarea de producción del MCER sigue en pie (U2 tiene EE y PO). */
    public function test_la_tarea_de_produccion_del_mcer_sigue_existiendo(): void
    {
        $this->get('/corso/it/u2/producir')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('producir')
                ->where('productivos', fn ($ps) => collect($ps)->pluck('tipo')->sort()->values()->all()
                    === ['escritura', 'voz']));
    }

    // ================= 2. el inglés, con SU estructura =================

    public function test_el_curso_de_ingles_existe_con_sus_tres_stages(): void
    {
        $this->get('/corso/en')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('corso')
                ->where('lengua', 'en')
                ->where('nombre', 'Inglés')
                // TRES unidades, no nueve: el cascarón ya no lo da por hecho.
                ->has('unidades', 3)
                ->where('unidades.0.n', 7)
                ->where('unidades.0.titulo', 'Stage 7')
                ->where('unidades.2.n', 9)
                // Sin contenido sembrado, las tres son «próximamente».
                ->where('unidades.0.estado', 'proximamente')
                ->where('unidades.1.estado', 'proximamente')
                ->where('unidades.2.estado', 'proximamente')
                // Y no hay «lo único que hacer ahora»: no hay nada que hacer.
                ->where('siguiente', null));
    }

    /**
     * LAS UNIDADES SON POR CURSO, no globales. El inglés no tiene u1 y el
     * italiano sí; el inglés tiene u7 y el italiano también. Con el molde
     * global de antes, `/corso/en/u1` habría respondido 200 con una unidad
     * del MCER dentro de un curso de Cambridge.
     */
    public function test_las_unidades_son_de_cada_curso(): void
    {
        $this->get('/corso/en/u7')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('unidad.titulo', 'Stage 7'));

        $this->get('/corso/en/u1')->assertNotFound();
        $this->get('/corso/en/u10')->assertNotFound();

        // El mismo número, en el otro curso, sigue existiendo.
        $this->get('/corso/it/u1')->assertOk();
        $this->get('/corso/it/u7')->assertOk();
    }

    /**
     * El inglés no declara destrezas productivas, así que no tiene página de
     * tarea. Antes la regla era `str_contains($code, '.EE.')` escrita en el
     * controlador: con códigos de Cambridge no casa nunca, pero la regla vivía
     * en el sitio equivocado. Ahora la declara el curso.
     */
    public function test_el_ingles_no_tiene_tarea_de_produccion(): void
    {
        $this->get('/corso/en/u7/producir')->assertNotFound();
    }

    /** Sin diálogo firmado, «hablar» lo dice; no es un enlace muerto. */
    public function test_hablar_en_ingles_dice_proximamente(): void
    {
        $this->get('/corso/en/u7/hablar')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('dialogo')->where('dialogo', null));
    }

    // ================= 3. la lengua sigue cerrada, con CINCO =================

    public function test_la_lengua_es_cerrada_con_las_cinco(): void
    {
        $this->assertSame(['fr', 'it', 'de', 'zh', 'en'], Lenguas::LISTA);

        foreach (Lenguas::LISTA as $lengua) {
            $this->get("/corso/{$lengua}")->assertOk();
        }

        $this->get('/corso/klingon')->assertNotFound();
        $this->get('/corso/eng')->assertNotFound();
    }

    // ================= 4. regla de oro =================

    public function test_el_invitado_ve_el_curso_de_ingles_y_no_escribe_nada(): void
    {
        Queue::fake();
        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        $this->get('/corso/en')->assertOk();
        $this->get('/corso/en/u7')->assertOk();
        $this->get('/corso/en/u7/hablar')->assertOk();

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()]);
        Queue::assertNothingPushed();
    }

    // ================= 5. el grafo: sin códigos inventados =================

    public function test_el_ingles_de_cambridge_entra_al_grafo_con_codigos_reales(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CambridgeEnglishSeeder::class);

        // Cambridge Primary no existía como marco; los otros tres ya estaban.
        $this->assertNotNull(Framework::where('code', 'CAIE-PRI')->first());

        // Los injertos cuelgan del programa que YA estaba, sin duplicarlo.
        foreach (['lsec' => 'lsec.en0861', 'igcse' => 'igcse.en0500', 'asa' => 'asa.en9093'] as $padre => $hijo) {
            $nodo = CurNode::where('path', $hijo)->firstOrFail();
            $this->assertSame(
                CurNode::where('path', $padre)->firstOrFail()->id,
                $nodo->parent_id,
                "«{$hijo}» no colgó de «{$padre}».",
            );
            $this->assertSame(1, CurNode::where('path', $padre)->count(), "Se duplicó el programa «{$padre}».");
        }

        // Los AO de los syllabus PÚBLICOS entran con su código real, prefijados
        // por syllabus (la clave es (marco, versión, código) y Cambridge recicla).
        foreach (['0500.R1', '0500.SL5', '0510.W4', '0511.S4', '0472.L1', '9093.AO5'] as $code) {
            $obj = LearningObjective::where('native_code', $code)->first();
            $this->assertNotNull($obj, "Falta el objetivo «{$code}».");
            $this->assertFalse((bool) $obj->is_verified,
                "«{$code}» entró verificado: los enunciados son paráfrasis sin cotejar.");
        }

        // NINGÚN código inventado: los marcos de Primary y Lower Secondary son
        // de descarga protegida, así que entran sus strands y sub-strands como
        // NODOS y CERO objetivos. Si algún día aparecen objetivos ahí sin que
        // nadie aporte el framework oficial, es que alguien se los inventó.
        foreach (['primary.en0058', 'lsec.en0861'] as $path) {
            $nodo = CurNode::where('path', $path)->firstOrFail();
            $this->assertSame(0, LearningObjective::where('node_id', $nodo->id)->count(),
                "«{$path}» trae objetivos con código, y su framework no es público.");
            $this->assertGreaterThan(0, CurNode::where('parent_id', $nodo->id)->count());
            $this->assertNotNull($nodo->attrs['source_url'] ?? null, "«{$path}» sin source_url.");
        }

        // Cada sub-strand cita su fuente: son paráfrasis de un PDF público.
        $sinFuente = CurNode::where('node_type', 'sub_strand')->get()
            ->filter(fn (CurNode $n) => ($n->attrs['source_url'] ?? null) === null);
        $this->assertTrue($sinFuente->isEmpty(), 'Hay sub-strands sin source_url.');
    }

    /**
     * EL MAPEO CON EL MCER: declarado donde es defendible, y NO fabricado donde
     * no lo es.
     *
     * El encargo pedía el crosswalk Cambridge ↔ MCER. Al ir a hacerlo aparecen
     * dos cosas que lo impiden en forma de `alignments`:
     *
     *  1. El CEFR sembrado tiene SOLO A1 (13 descriptores). Los objetivos
     *     ingleses que sí traen código son IGCSE y AS & A Level, que están en
     *     B1-C1. Enlazarlos a A1 sería escribir una equivalencia falsa, y el
     *     crosswalk de este repo solo ancla en destrezas verificadas.
     *  2. 0058/0861/0500 son inglés como PRIMERA lengua, y el MCER mide
     *     segundas lenguas: ahí la banda no es «desconocida», es que no aplica.
     *
     * Así que el mapeo entra como ATRIBUTO declarado del nodo —aproximado y sin
     * cotejar, que es el equivalente a `reviewed_at` nulo— y la tabla
     * `alignments` se queda vacía para el inglés hasta que existan A2/B1/B2/C1.
     */
    public function test_el_mapeo_con_el_mcer_se_declara_y_no_se_fabrica(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CambridgeEnglishSeeder::class);

        // La línea de SEGUNDA lengua (y 9093) declara su banda, marcada como
        // aproximada y sin cotejar.
        foreach (['igcse.esl0510' => 'B1-B2', 'igcse.esl0511' => 'B1-B2', 'asa.en9093' => 'C1'] as $path => $banda) {
            $attrs = CurNode::where('path', $path)->firstOrFail()->attrs;
            $this->assertSame($banda, $attrs['mcer_aprox'] ?? null, "«{$path}» sin banda MCER declarada.");
            $this->assertStringContainsString('SIN cotejar', $attrs['mcer_fuente'] ?? '',
                "«{$path}» declara la banda como si estuviera cotejada.");
        }

        // La línea de PRIMERA lengua dice por qué NO se le pone banda.
        foreach (['primary.en0058', 'lsec.en0861'] as $path) {
            $attrs = CurNode::where('path', $path)->firstOrFail()->attrs;
            $this->assertStringContainsString('SIN MAPEAR', $attrs['mcer'] ?? '',
                "«{$path}» debería decir que no se le mapea banda, y por qué.");
            $this->assertArrayNotHasKey('mcer_aprox', $attrs);
        }

        // Y NADIE fabricó una equivalencia entre el inglés de Cambridge y A1.
        $cefr = LearningObjective::whereIn('version_id', \App\Models\FrameworkVersion::whereIn(
            'framework_id', Framework::where('code', 'CEFR')->select('id'))->select('id'))->pluck('id');
        $ingles = LearningObjective::where('native_code', 'like', '0500.%')
            ->orWhere('native_code', 'like', '0510.%')
            ->orWhere('native_code', 'like', '0511.%')
            ->orWhere('native_code', 'like', '0472.%')
            ->orWhere('native_code', 'like', '9093.%')
            ->pluck('id');

        $inventadas = \App\Models\Alignment::query()
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereIn('source_id', $cefr)->whereIn('target_id', $ingles))
                ->orWhere(fn ($w) => $w->whereIn('source_id', $ingles)->whereIn('target_id', $cefr)))
            ->count();

        $this->assertSame(0, $inventadas,
            'Se fabricó una equivalencia entre Cambridge (B1-C1) y el MCER A1, que es lo unico sembrado.');
    }

    /**
     * UN INJERTO SIN SU RAMA REVIENTA. El inglés de Lower Secondary cuelga del
     * programa `lsec` que siembra `InternationalFrameworksSeeder`; si ese no ha
     * corrido, media lengua se quedaría fuera del grafo. Es la misma disciplina
     * de la errata `CS.FL`/`CS.F`: un destino que no está no se ignora en
     * silencio, se grita.
     */
    public function test_el_injerto_revienta_si_falta_su_programa(): void
    {
        $revento = false;
        try {
            $this->seed(CambridgeEnglishSeeder::class);   // SIN los marcos internacionales
        } catch (\RuntimeException $e) {
            $revento = true;
            $this->assertStringContainsString('CAIE-LSEC', $e->getMessage());
        }

        $this->assertTrue($revento, 'El injerto se perdió en silencio al faltar su programa.');
        $this->assertSame(0, CurNode::where('path', 'lsec.en0861')->count());
    }

    /**
     * Y revienta TAMBIÉN cuando el marco sí está pero la rama concreta no.
     *
     * Sin este segundo caso, la guarda del marco tapa a la del nodo padre: la
     * mutación que hacía `return` en vez de lanzar sobrevivía porque el primer
     * chequeo se disparaba antes. Aquí el marco existe y lo que falta es `lsec`.
     */
    public function test_el_injerto_revienta_si_falta_la_rama_concreta(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);

        // Se borra el programa del que cuelga el inglés de Lower Secondary.
        CurNode::where('path', 'lsec')->delete();

        $revento = false;
        try {
            $this->seed(CambridgeEnglishSeeder::class);
        } catch (\RuntimeException $e) {
            $revento = true;
            $this->assertStringContainsString('lsec', $e->getMessage());
        }

        $this->assertTrue($revento, 'El injerto se colgó de la nada al faltar su rama.');
        $this->assertSame(0, CurNode::where('path', 'lsec.en0861')->count());
    }

    /**
     * El curso de inglés declara CAIE, no el MCER. Sin esto, `contexto()`
     * seguiría buscando sus descriptores en la versión del CEFR —que es lo que
     * hacía— y el día que el inglés tenga objetivos los buscaría en el marco
     * equivocado.
     */
    public function test_cada_curso_declara_su_marco(): void
    {
        $curso = app(\App\Services\Curso\CursoDeLenguas::class);

        $this->assertSame('CEFR', $curso->marco('it'));
        $this->assertSame('CEFR', $curso->marco('fr'));
        $this->assertSame('CAIE-LSEC', $curso->marco('en'));
    }
}
