<?php

namespace Tests\Feature;

use App\Models\LearningObjective;
use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\ObjectiveMastery;
use App\Models\Produccion;
use App\Models\User;
use App\Services\Produccion\AlmacenDeProducciones;
use App\Services\Produccion\AnioLectivo;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * PR 3 · QUE EL ALUMNO PRODUZCA — escritura y voz de un menor.
 *
 * La producción es CONTENIDO DE UN MENOR, así que cada oráculo aquí protege una
 * de las cinco reglas de retención/visibilidad que tomó la misión:
 *
 *  1. Se guarda un año lectivo y se borra al cierre; la nota del docente vive.
 *  2. La ve el alumno que la hizo y los docentes de SU curso; nadie más (403).
 *  3. Nunca en el almacén público: almacén propio, servido con auth + policy.
 *  4. El alumno borra la suya mientras no esté corregida.
 *  5. No sale del sistema.
 *
 * Y la regla de oro: el invitado ve la tarea pero no escribe ni una fila.
 */
class ProduccionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
    }

    protected function tearDown(): void
    {
        // Las grabaciones de prueba viven en storage/app/producciones (no
        // versionado): se limpian aquí para no dejar ficheros sueltos.
        $dir = storage_path('app/producciones');
        if (is_dir($dir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($dir);
        }
        parent::tearDown();
    }

    // ---- helpers ----

    /** @return array{0: LtiContext, 1: User, 2: User} [contexto, docente, alumno] */
    private function curso(string $ctx = 'c-1'): array
    {
        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.test', 'client_id' => 'c'.$ctx,
            'deployment_ids' => ['d1'], 'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        $context = LtiContext::create([
            'platform_id' => $platform->id, 'context_id' => $ctx, 'title' => 'Italiano '.$ctx,
        ]);
        $docente = User::factory()->create();
        $alumno = User::factory()->create();
        LtiContextMembership::create(['lti_context_id' => $context->id, 'user_id' => $docente->id, 'role' => 'instructor']);
        LtiContextMembership::create(['lti_context_id' => $context->id, 'user_id' => $alumno->id, 'role' => 'learner']);

        return [$context, $docente, $alumno];
    }

    private function objEE(): string
    {
        return LearningObjective::where('native_code', 'A1.EE.2')->firstOrFail()->id;
    }

    private function objPO(): string
    {
        return LearningObjective::where('native_code', 'A1.PO.1')->firstOrFail()->id;
    }

    private function crearEscritura(User $alumno, array $extra = []): Produccion
    {
        return Produccion::create(array_merge([
            'user_id' => $alumno->id, 'objective_id' => $this->objEE(), 'unidad' => 2,
            'lengua' => 'it', 'tipo' => 'escritura', 'texto' => 'Mi chiamo Ana. Sono di Quito.',
            'anio_lectivo' => AnioLectivo::actual(), 'estado' => 'pendiente',
        ], $extra));
    }

    // ================= regla de oro =================

    public function test_el_invitado_ve_la_tarea_pero_no_puede_producir(): void
    {
        Queue::fake();

        // VE la tarea (abierta).
        $this->get('/corso/it/u2/producir')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('producir'));

        // Pero NO escribe ni una fila.
        $antes = Produccion::count();
        $this->postJson('/api/v1/producciones', [
            'objective_id' => $this->objEE(), 'unidad' => 2, 'lengua' => 'it',
            'tipo' => 'escritura', 'texto' => 'Un intento de invitado que no debe guardarse.',
        ])->assertStatus(401);

        $this->assertSame($antes, Produccion::count());
        Queue::assertNothingPushed();
    }

    // ================= crear =================

    public function test_la_escritura_se_guarda_pendiente_y_atada_a_la_sesion(): void
    {
        [, , $alumno] = $this->curso();

        $this->actingAs($alumno)->postJson('/api/v1/producciones', [
            'objective_id' => $this->objEE(), 'unidad' => 2, 'lengua' => 'it',
            'tipo' => 'escritura', 'texto' => 'Mi chiamo Ana e sono di Quito.',
        ])->assertCreated()->assertJsonPath('estado', 'pendiente');

        $p = Produccion::firstOrFail();
        $this->assertSame($alumno->id, $p->user_id);
        $this->assertSame('pendiente', $p->estado);
        $this->assertNotNull($p->texto);
        $this->assertNull($p->archivo);
        $this->assertSame(AnioLectivo::actual(), $p->anio_lectivo);
    }

    public function test_un_user_id_en_el_request_es_422(): void
    {
        [, , $alumno] = $this->curso();

        $this->actingAs($alumno)->postJson('/api/v1/producciones', [
            'user_id' => 999, 'objective_id' => $this->objEE(), 'unidad' => 2,
            'lengua' => 'it', 'tipo' => 'escritura', 'texto' => 'Texto suficiente para pasar.',
        ])->assertStatus(422);
    }

    public function test_la_escritura_contra_una_destreza_no_productiva_es_422(): void
    {
        [, , $alumno] = $this->curso();
        $co = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id;

        $this->actingAs($alumno)->postJson('/api/v1/producciones', [
            'objective_id' => $co, 'unidad' => 1, 'lengua' => 'it',
            'tipo' => 'escritura', 'texto' => 'Esto no es una destreza de escritura.',
        ])->assertStatus(422);

        $this->assertSame(0, Produccion::count());
    }

    public function test_la_voz_se_guarda_fuera_del_almacen_publico(): void
    {
        [, , $alumno] = $this->curso();

        $this->actingAs($alumno)->postJson('/api/v1/producciones', [
            'objective_id' => $this->objPO(), 'unidad' => 2, 'lengua' => 'it', 'tipo' => 'voz',
            'archivo' => UploadedFile::fake()->create('grabacion.webm', 80, 'audio/webm'),
        ])->assertCreated();

        $p = Produccion::firstOrFail();
        $this->assertNull($p->texto);
        $this->assertNotNull($p->archivo);

        // Vive bajo producciones/, NO en el almacén público direccionable por
        // contenido — la ruta /audio/* no la reconoce siquiera.
        $this->assertStringStartsWith('producciones/', $p->archivo);
        $this->assertFalse(\App\Services\Audio\AlmacenDeAudio::esRutaPublicada('/audio/'.basename($p->archivo)));
        $this->assertNotNull(AlmacenDeProducciones::resolver($p->archivo));
    }

    // ================= visibilidad: solo el alumno y su docente =================

    public function test_la_voz_solo_se_sirve_al_alumno_y_a_su_docente(): void
    {
        [, $docente, $alumno] = $this->curso('c-1');
        [, $docenteAjeno] = $this->curso('c-2');

        // Se crea SIN actingAs (que persiste toda la prueba): el fichero real va
        // por el almacén y la fila directa, para que la primera petición sea de
        // verdad de un invitado y no del alumno recién autenticado.
        $almacen = app(AlmacenDeProducciones::class);
        $rel = $almacen->guardar(UploadedFile::fake()->create('g.webm', 60, 'audio/webm'), AnioLectivo::actual());
        $p = Produccion::create([
            'user_id' => $alumno->id, 'objective_id' => $this->objPO(), 'unidad' => 2,
            'lengua' => 'it', 'tipo' => 'voz', 'archivo' => $rel,
            'anio_lectivo' => AnioLectivo::actual(), 'estado' => 'pendiente',
        ]);

        $url = "/api/v1/producciones/{$p->id}/audio";

        // Invitado: 401, jamás el fichero (ruta api/*, sin 302 a /entrar).
        $this->getJson($url)->assertStatus(401);

        // Docente de OTRO curso: 403.
        $this->actingAs($docenteAjeno)->get($url)->assertStatus(403);

        // El dueño: 200.
        $this->actingAs($alumno)->get($url)->assertOk();

        // Su docente: 200.
        $this->actingAs($docente)->get($url)->assertOk();
    }

    // ================= el alumno borra la suya =================

    public function test_el_alumno_borra_la_suya_solo_si_no_esta_corregida(): void
    {
        [, , $alumno] = $this->curso();
        $otro = User::factory()->create();

        // Pendiente y suya: se borra.
        $p = $this->crearEscritura($alumno);
        $this->actingAs($alumno)->deleteJson("/api/v1/producciones/{$p->id}")->assertOk();
        $this->assertDatabaseMissing('producciones', ['id' => $p->id]);

        // Corregida: 403, sigue en pie.
        $corregida = $this->crearEscritura($alumno, ['estado' => 'corregida']);
        $this->actingAs($alumno)->deleteJson("/api/v1/producciones/{$corregida->id}")->assertStatus(403);
        $this->assertDatabaseHas('producciones', ['id' => $corregida->id]);

        // De otro alumno: 403.
        $ajena = $this->crearEscritura($alumno);
        $this->actingAs($otro)->deleteJson("/api/v1/producciones/{$ajena->id}")->assertStatus(403);
        $this->assertDatabaseHas('producciones', ['id' => $ajena->id]);
    }

    // ================= la cola del docente =================

    public function test_la_cola_del_docente_trae_solo_a_sus_alumnos(): void
    {
        [, $docente1, $alumno1] = $this->curso('c-1');
        [, , $alumno2] = $this->curso('c-2');

        $this->crearEscritura($alumno1);
        $this->crearEscritura($alumno2);                              // de otro curso
        $this->crearEscritura($alumno1, ['estado' => 'corregida']);   // ya corregida

        // Solo UNA: la pendiente de SU alumno. Ni la del otro curso ni la ya
        // corregida de su propio alumno.
        $this->actingAs($docente1)->get('/docente/producciones')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('docente-producciones')
                ->has('producciones', 1)
                ->where('producciones.0.alumno', $alumno1->name));
    }

    public function test_corregir_guarda_la_nota_sin_tocar_dominio_ni_ags(): void
    {
        Queue::fake();
        [, $docente, $alumno] = $this->curso();
        $p = $this->crearEscritura($alumno);

        $this->actingAs($docente)->post("/docente/producciones/{$p->id}", [
            'rubrica' => ['tarea' => 2, 'vocabulario' => 1, 'gramatica' => 1, 'ortografia' => 2],
            'comentario' => 'Muy bien la presentación. Cuida los artículos.',
        ])->assertRedirect();

        $p->refresh();
        $this->assertSame('corregida', $p->estado);
        $this->assertSame($docente->id, $p->corregida_por);
        $this->assertSame(2, $p->rubrica['tarea']);
        $this->assertNotNull($p->comentario);

        // El motor NO corrige producción: ni dominio ni nota al aula.
        $this->assertSame(0, ObjectiveMastery::count());
        Queue::assertNothingPushed();
    }

    public function test_un_docente_de_otro_curso_no_corrige(): void
    {
        [, , $alumno] = $this->curso('c-1');
        [, $docenteAjeno] = $this->curso('c-2');
        $p = $this->crearEscritura($alumno);

        $this->actingAs($docenteAjeno)->post("/docente/producciones/{$p->id}", [
            'rubrica' => ['tarea' => 2, 'vocabulario' => 2, 'gramatica' => 2, 'ortografia' => 2],
            'comentario' => 'Un docente ajeno no debería poder corregir esto.',
        ])->assertStatus(403);

        $this->assertSame('pendiente', $p->refresh()->estado);
    }

    // ================= lengua cerrada en las dos direcciones =================

    public function test_la_cola_del_docente_es_cerrada_por_lengua(): void
    {
        [, $docente, $alumno] = $this->curso();

        $this->crearEscritura($alumno, ['lengua' => 'it']);
        $this->crearEscritura($alumno, ['lengua' => 'de']);

        $this->actingAs($docente)->get('/docente/producciones?lengua=it')
            ->assertInertia(fn (Assert $p) => $p->has('producciones', 1)
                ->where('producciones.0.lengua', 'it'));

        $this->actingAs($docente)->get('/docente/producciones?lengua=de')
            ->assertInertia(fn (Assert $p) => $p->has('producciones', 1)
                ->where('producciones.0.lengua', 'de'));

        // Una lengua fuera de la lista es 422 también aquí.
        $this->actingAs($docente)->getJson('/docente/producciones?lengua=klingon')->assertStatus(422);
    }

    // ================= retención: la purga =================

    public function test_la_purga_borra_la_grabacion_de_un_ano_cerrado_y_conserva_la_nota(): void
    {
        [, , $alumno] = $this->curso();
        $almacen = app(AlmacenDeProducciones::class);

        // Una voz CORREGIDA de un año ya cerrado, con fichero real en disco.
        $rel = $almacen->guardar(UploadedFile::fake()->create('vieja.webm', 40, 'audio/webm'), '2024-2025');
        $vieja = Produccion::create([
            'user_id' => $alumno->id, 'objective_id' => $this->objPO(), 'unidad' => 2,
            'lengua' => 'it', 'tipo' => 'voz', 'archivo' => $rel, 'anio_lectivo' => '2024-2025',
            'estado' => 'corregida', 'rubrica' => ['tarea' => 2, 'vocabulario' => 1, 'fluidez' => 1, 'pronunciacion' => 2],
            'comentario' => 'Buen ritmo, cuida las vocales.', 'corregida_por' => $alumno->id, 'corregida_en' => now(),
        ]);
        $this->assertNotNull(AlmacenDeProducciones::resolver($rel));

        // Una del año EN CURSO: no se toca.
        $nueva = $this->crearEscritura($alumno, ['anio_lectivo' => AnioLectivo::actual()]);

        $this->artisan('producciones:purgar')->assertExitCode(0);

        $vieja->refresh();
        $this->assertNull($vieja->archivo, 'La grabación del año cerrado no se borró.');
        $this->assertNull(AlmacenDeProducciones::resolver($rel), 'El fichero sigue en disco.');
        $this->assertNotNull($vieja->purgada_en);
        // La NOTA del docente sobrevive.
        $this->assertSame(2, $vieja->rubrica['tarea']);
        $this->assertNotNull($vieja->comentario);

        // La del año en curso, intacta.
        $this->assertNotNull($nueva->refresh()->texto);
    }

    public function test_la_purga_en_seco_lista_y_no_borra(): void
    {
        [, , $alumno] = $this->curso();
        $this->crearEscritura($alumno, ['anio_lectivo' => '2024-2025']);

        $this->artisan('producciones:purgar --dry-run')->assertExitCode(0);

        // Nada purgado: el texto sigue en pie.
        $this->assertDatabaseMissing('producciones', ['texto' => null]);
    }

    // ================= las columnas fallan cerradas =================

    public function test_una_escritura_con_archivo_revienta_al_guardar(): void
    {
        [, , $alumno] = $this->curso();

        $this->expectException(RuntimeException::class);
        Produccion::create([
            'user_id' => $alumno->id, 'objective_id' => $this->objEE(), 'unidad' => 2,
            'lengua' => 'it', 'tipo' => 'escritura', 'texto' => 'Con texto y también',
            'archivo' => 'producciones/2026-2027/x.webm', 'anio_lectivo' => AnioLectivo::actual(),
        ]);
    }

    public function test_una_voz_con_texto_revienta_al_guardar(): void
    {
        [, , $alumno] = $this->curso();

        $this->expectException(RuntimeException::class);
        Produccion::create([
            'user_id' => $alumno->id, 'objective_id' => $this->objPO(), 'unidad' => 2,
            'lengua' => 'it', 'tipo' => 'voz', 'archivo' => 'producciones/2026-2027/x.webm',
            'texto' => 'Una voz no lleva texto.', 'anio_lectivo' => AnioLectivo::actual(),
        ]);
    }

    public function test_el_ano_lectivo_agrupa_por_la_frontera_de_agosto(): void
    {
        // Agosto ya cuenta para el curso que empieza; julio, para el anterior.
        $this->assertSame('2026-2027', AnioLectivo::de(\Illuminate\Support\Carbon::parse('2026-08-01')));
        $this->assertSame('2025-2026', AnioLectivo::de(\Illuminate\Support\Carbon::parse('2026-07-31')));
    }

    public function test_el_almacen_no_resuelve_rutas_de_traversal(): void
    {
        // Un fichero REAL fuera del almacén: si `resolver` no validara la FORMA,
        // un '..' llegaría hasta él. La prueba lo pone a existir a propósito para
        // que el único motivo de que salga null sea la FORMA, no que falte.
        $fuera = storage_path('app/secret-probe.webm');
        file_put_contents($fuera, 'x');
        try {
            $this->assertNull(AlmacenDeProducciones::resolver('producciones/2024-2025/../../secret-probe.webm'));
            $this->assertNull(AlmacenDeProducciones::resolver('../../etc/passwd'));
            $this->assertNull(AlmacenDeProducciones::resolver('audio/abcdef0123456789.mp3'));
        } finally {
            @unlink($fuera);
        }
    }

    public function test_el_estado_por_defecto_es_pendiente(): void
    {
        [, , $alumno] = $this->curso();

        // Sin declarar estado: nace 'pendiente' (el seguro), no 'corregida'.
        $p = Produccion::create([
            'user_id' => $alumno->id, 'objective_id' => $this->objEE(), 'unidad' => 2,
            'lengua' => 'it', 'tipo' => 'escritura', 'texto' => 'Sin declarar estado.',
            'anio_lectivo' => AnioLectivo::actual(),
        ]);

        $this->assertSame('pendiente', $p->refresh()->estado);
    }
}
