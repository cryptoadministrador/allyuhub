<?php

namespace Tests\Feature;

use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\User;
use App\Services\Practice\RachaDeAlumno;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PR 1 · EL CASCARÓN DEL CURSO.
 *
 * Un alumno de italiano entra por `/corso/it`, ve nueve unidades y UNA sola
 * cosa que hacer ahora. El estado —qué unidad está abierta, cuál terminada, el
 * dominio agregado, la racha— se calcula en el SERVIDOR y llega como props;
 * React pinta. Y el invitado lo ve todo sin sesión y sin escribir una fila.
 *
 * Aquí se cierra además el cabo suelto de #28: `/destreza` no filtraba
 * recursos por lengua. Seguro por circunstancia (solo había italiano); se
 * cierra igual que los ítems, en las dos direcciones, con DOS lenguas sembradas.
 */
class CascaronCursoTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
        $this->ana = User::factory()->create();
    }

    /** Firma y devuelve un ítem de práctica sembrado sobre un descriptor. */
    private function itemFirmado(string $code, string $lengua, int $seq = 0): PracticeItem
    {
        $descriptor = LearningObjective::where('native_code', $code)->firstOrFail();

        return PracticeItem::create([
            'objective_id' => $descriptor->id, 'kind' => 'hueco', 'lengua' => $lengua,
            'statement' => ['es' => "Completa ({$lengua})."], 'params' => [],
            'solucion' => ['lengua' => $lengua, 'textos' => ['x']],
            'seq' => $seq, 'reviewed_at' => now(),
        ]);
    }

    // ================= la portada del curso =================

    public function test_la_portada_pinta_nueve_unidades_con_su_estado(): void
    {
        // U1 con contenido firmado (italiano); el resto, sin contenido.
        $this->itemFirmado('A1.CO.2', 'it');

        $this->get('/corso/it')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('corso')
                ->where('lengua', 'it')
                ->where('nombre', 'Italiano')
                ->has('unidades', 9)
                // U1 tiene contenido → disponible o en-curso, jamás «próximamente».
                ->where('unidades.0.estado', 'disponible')
                ->where('unidades.0.n', 1)
                // Una unidad sin contenido sembrado se pinta «próximamente»,
                // nunca vacía.
                ->where('unidades.8.estado', 'proximamente')
            );
    }

    public function test_la_portada_ofrece_una_sola_cosa_que_hacer(): void
    {
        $this->itemFirmado('A1.CO.2', 'it');

        $this->get('/corso/it')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                // El siguiente paso es UNO y lleva a practicar el primer
                // descriptor no dominado de la primera unidad abierta.
                ->where('siguiente.unidad', 1)
                ->has('siguiente.url')
                ->where('siguiente.lengua', 'it')
            );
    }

    /** Una lengua fuera de la lista cerrada no tiene curso: 404, no una inventada. */
    public function test_una_lengua_fuera_de_la_lista_es_404(): void
    {
        $this->get('/corso/klingon')->assertNotFound();
        $this->get('/corso/es')->assertNotFound();
    }

    public function test_una_unidad_fuera_de_rango_es_404(): void
    {
        $this->get('/corso/it/u0')->assertNotFound();
        $this->get('/corso/it/u10')->assertNotFound();
    }

    // ================= la unidad =================

    public function test_la_unidad_pinta_sus_puedo_como_objetivos_del_alumno(): void
    {
        $this->itemFirmado('A1.CO.2', 'it');

        $this->get('/corso/it/u1')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('corso-unidad')
                ->where('unidad.n', 1)
                ->has('puedo')
                // El «Puedo…» se pinta como objetivo: su enunciado del MCER.
                ->where('puedo.0.code', 'A1.CO.2')
                ->has('puedo.0.statement')
                ->where('puedo.0.has_items', true)
            );
    }

    // ================= la regla de oro =================

    /**
     * > El invitado ve todo esto sin sesión y sin escribir una fila.
     */
    public function test_el_invitado_ve_el_curso_a_cero_sin_escribir_nada(): void
    {
        $this->itemFirmado('A1.CO.2', 'it');

        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        $this->get('/corso/it')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('se_guarda', false)
                ->where('racha.dias', 0)
                ->where('unidades.0.dominio', 0)
            );
        $this->get('/corso/it/u1')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('se_guarda', false));

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()],
            'Un invitado que mira el curso escribió una fila.');
    }

    /** Con sesión, el dominio y la racha son los del alumno. */
    public function test_con_sesion_el_dominio_agregado_es_el_del_alumno(): void
    {
        $item = $this->itemFirmado('A1.CO.2', 'it');
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $item->objective_id,
            'mastery' => 0.9, 'streak' => 3, 'attempts_count' => 4,
            'mastered_at' => now(), 'last_attempt_at' => now(),
        ]);

        $this->actingAs($this->ana)->get('/corso/it')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('se_guarda', true)
                ->where('unidades.0.dominio', 0.9)
                ->where('unidades.0.estado', 'completada')
            );
    }

    /**
     * COMPLETADA exige TODOS los descriptores con contenido dominados, no
     * «alguno». La unidad 2 tiene DOS descriptores con contenido (PO.1 y EE.2)
     * y solo uno dominado: en-curso, no completada. Con un solo descriptor por
     * unidad «alguno» y «todos» coinciden y la mutación sobrevive — el fixture
     * cómodo de siempre.
     */
    public function test_completada_exige_todos_los_descriptores_no_alguno(): void
    {
        $po = $this->itemFirmado('A1.PO.1', 'it', 10);
        $ee = $this->itemFirmado('A1.EE.2', 'it', 11);

        // Solo PO.1 dominado; EE.2 tocado pero no dominado.
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $po->objective_id,
            'mastery' => 0.95, 'streak' => 4, 'attempts_count' => 5,
            'mastered_at' => now(), 'last_attempt_at' => now(),
        ]);
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $ee->objective_id,
            'mastery' => 0.3, 'streak' => 1, 'attempts_count' => 1,
            'mastered_at' => null, 'last_attempt_at' => now(),
        ]);

        $this->actingAs($this->ana)->get('/corso/it')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('unidades.1.estado', 'en-curso'));
    }

    /**
     * EL SIGUIENTE PASO es la primera unidad NO COMPLETADA, no la primera a
     * secas. Con la U1 entera dominada, el siguiente paso salta a la U2. Con
     * la U1 siempre primera, `first()` y «primera no completada» coinciden y la
     * mutación sobrevive.
     */
    public function test_el_siguiente_paso_salta_la_unidad_ya_completada(): void
    {
        // U1: su único descriptor con contenido, dominado.
        $u1 = $this->itemFirmado('A1.CO.2', 'it', 20);
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $u1->objective_id,
            'mastery' => 1.0, 'streak' => 5, 'attempts_count' => 6,
            'mastered_at' => now(), 'last_attempt_at' => now(),
        ]);
        // U2: contenido sin tocar.
        $this->itemFirmado('A1.PO.1', 'it', 21);

        $this->actingAs($this->ana)->get('/corso/it')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('unidades.0.estado', 'completada')
                ->where('siguiente.unidad', 2));
    }

    /** El invitado ve CERO aunque OTRO alumno tenga dominio: nunca el de otro. */
    public function test_el_invitado_ve_cero_aunque_otro_tenga_dominio(): void
    {
        $item = $this->itemFirmado('A1.CO.2', 'it', 30);
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $item->objective_id,
            'mastery' => 0.9, 'streak' => 3, 'attempts_count' => 4,
            'mastered_at' => now(), 'last_attempt_at' => now(),
        ]);

        // Invitado (sin actingAs): el dominio de Ana no es suyo.
        $this->get('/corso/it')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('unidades.0.dominio', 0)
                ->where('unidades.0.estado', 'disponible'));
    }

    // ================= la racha (regla: 3 días naturales) =================

    public function test_la_racha_se_rompe_a_los_tres_dias_no_al_primero(): void
    {
        $racha = new RachaDeAlumno;

        // Un fin de semana (hoy y anteayer, hueco de 1 día) NO rompe la racha.
        $this->attemptEn($this->ana, now());
        $this->attemptEn($this->ana, now()->copy()->subDays(2));
        $conHueco = $racha->calcular($this->ana->id);
        $this->assertTrue($conHueco['viva'], 'Un hueco de un día rompió la racha.');
        $this->assertSame(2, $conHueco['dias']);

        // Tres días naturales sin actividad SÍ la rompen: el último fue hace 3.
        $otro = User::factory()->create();
        $this->attemptEn($otro, now()->copy()->subDays(3));
        $rota = $racha->calcular($otro->id);
        $this->assertFalse($rota['viva'], 'La racha sobrevivió a tres días de silencio.');
        $this->assertSame(0, $rota['dias']);
    }

    /** El invitado no tiene racha: cero, sin consultar filas de nadie. */
    public function test_el_invitado_no_tiene_racha(): void
    {
        $r = (new RachaDeAlumno)->calcular(null);
        $this->assertSame(['dias' => 0, 'viva' => false], $r);
    }

    private function attemptEn(User $u, \DateTimeInterface $cuando): void
    {
        $item = PracticeItem::create([
            'objective_id' => LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id,
            'kind' => 'hueco', 'lengua' => 'it', 'statement' => ['es' => 'x'], 'params' => [],
            'solucion' => ['lengua' => 'it', 'textos' => ['x']], 'seq' => random_int(1, 99999),
            'reviewed_at' => now(),
        ]);
        PracticeAttempt::create([
            'item_id' => $item->id, 'user_id' => $u->id, 'attempt_no' => 1,
            'seed' => str_repeat('a', 64), 'params' => [], 'respuesta' => ['texto' => 'x'],
            'is_correct' => true, 'created_at' => $cuando, 'updated_at' => $cuando,
        ]);
    }

    /**
     * EL BANCO REAL DE CARLOS siembra limpio. No es «el comando corre»: es que
     * el contenido del curso entero de italiano —lecciones e ítems de los
     * cuatro tipos escritos, sin audio todavía— pasa el validador de bloques y
     * el guardián `saving` de cada tipo. Un banco que reviente en producción
     * tras el merge es justo lo que este oráculo caza antes.
     */
    public function test_el_banco_de_italiano_de_carlos_siembra_sin_reventar(): void
    {
        $this->artisan('lenguas:sembrar')->assertSuccessful();

        // Sembró ítems de italiano sobre descriptores del MCER, sin firmar
        // (la puerta) y con su lengua.
        $items = PracticeItem::where('lengua', 'it')->get();
        $this->assertGreaterThan(10, $items->count(), 'El banco de Carlos no sembró ítems.');
        $this->assertTrue($items->every(fn ($i) => $i->reviewed_at === null),
            'El banco nació firmado: nada se publica sin que un docente lo firme.');
    }

    // ================= el cabo suelto de #28 =================

    /**
     * `/destreza` filtra recursos por lengua, CERRADO EN LAS DOS DIRECCIONES —
     * con DOS lenguas sembradas, porque una sola hace pasar cualquier test de
     * separación. Antes era seguro solo porque no había francés.
     */
    public function test_destreza_filtra_recursos_por_lengua_en_las_dos_direcciones(): void
    {
        $descriptor = LearningObjective::where('native_code', 'A1.IO.3')->firstOrFail();

        $it = $this->recursoDeLengua($descriptor, 'it', 'CENTINELA-RECURSO-ITALIANO');
        $de = $this->recursoDeLengua($descriptor, 'de', 'CENTINELA-RECURSO-ALEMAN');

        // Pidiendo italiano: solo la lección italiana.
        $this->get("/destreza/{$descriptor->id}?lengua=it")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('leccion.id', $it->id));

        // Pidiendo alemán: solo la alemana — no la italiana.
        $this->get("/destreza/{$descriptor->id}?lengua=de")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('leccion.id', $de->id));

        // SIN lengua: ninguna lección de lengua (solo contenido sin lengua).
        $this->get("/destreza/{$descriptor->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('leccion', null));

        // Y una lengua fuera de la lista es 422, no una lengua nueva.
        $this->get("/destreza/{$descriptor->id}?lengua=klingon")->assertStatus(422);
    }

    private function recursoDeLengua(LearningObjective $descriptor, string $lengua, string $centinela): Resource
    {
        $recurso = Resource::create([
            'slug' => "leccion-{$lengua}-".substr(md5($centinela), 0, 8),
            'kind' => Resource::LECTURA, 'origen' => Resource::GENERADO, 'lengua' => $lengua,
            'status' => 'published', 'title' => ['es' => "Lección {$lengua}"],
        ]);
        $version = ResourceVersion::create([
            'resource_id' => $recurso->id, 'semver' => '1.0.0',
            'config' => ['bloque' => 'A1.IO.'.$lengua, 'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => $centinela]],
            ]],
            'published_at' => now(), 'reviewed_at' => now(),
        ]);
        $recurso->update(['current_version_id' => $version->id]);
        $descriptor->resources()->attach($recurso->id, ['role' => 'primary']);

        return $recurso;
    }
}
