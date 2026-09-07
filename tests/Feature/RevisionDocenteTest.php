<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\LearningObjective;
use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\Revision;
use App\Models\User;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PR 5 · LA REVISIÓN DOCENTE EN PANTALLA.
 *
 * Hasta ahora firmar era `practica:firmar` por SSH, sin autoría y sin vuelta
 * atrás. La regla de la casa —«antes del primer alumno lo lee un profesor»— no
 * se podía cumplir. Estos oráculos fijan lo que la hace cierta:
 *
 *  1. Solo un DOCENTE entra; un alumno y un invitado reciben 403 (no una
 *     redirección: esto no es una puerta sin llave, es una sala ajena).
 *  2. Firmar deja AUTORÍA real, y rastro.
 *  3. Devolver y des-firmar EXIGEN NOTA. Nada se retira en silencio.
 *  4. Firmar la unidad entera exige haber ABIERTO todas sus piezas.
 *  5. Revisar no es practicar: corrige de verdad y no guarda NADA.
 */
class RevisionDocenteTest extends TestCase
{
    use RefreshDatabase;

    private User $docente;

    private User $alumno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);

        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.test', 'client_id' => 'c1',
            'deployment_ids' => ['d1'], 'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        $context = LtiContext::create([
            'platform_id' => $platform->id, 'context_id' => 'c-1', 'title' => 'Italiano 1A',
        ]);

        $this->docente = User::factory()->create(['name' => 'Prof. Rossi']);
        $this->alumno = User::factory()->create(['name' => 'Ana']);
        LtiContextMembership::create([
            'lti_context_id' => $context->id, 'user_id' => $this->docente->id, 'role' => 'instructor',
        ]);
        LtiContextMembership::create([
            'lti_context_id' => $context->id, 'user_id' => $this->alumno->id, 'role' => 'learner',
        ]);
    }

    // ---- helpers ----

    private function objetivo(string $code = 'A1.CO.2'): LearningObjective
    {
        return LearningObjective::where('native_code', $code)->firstOrFail();
    }

    private function item(string $lengua = 'it', ?string $code = null, bool $firmado = false): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => $this->objetivo($code ?? 'A1.CO.2')->id,
            'kind' => 'hueco', 'lengua' => $lengua,
            'statement' => ['es' => "Completa la frase ({$lengua})."],
            'params' => [], 'solucion' => ['lengua' => $lengua, 'textos' => ['ciao']],
            'seq' => random_int(1, 99999),
            'reviewed_at' => $firmado ? now() : null,
        ]);
    }

    private function leccion(string $lengua = 'it', bool $firmada = false): ResourceVersion
    {
        $recurso = Resource::create([
            'slug' => 'leccion-'.uniqid(), 'kind' => Resource::LECTURA,
            'title' => ['es' => 'Saludos'], 'summary' => ['es' => 'Hola'],
            'status' => 'published', 'origen' => Resource::GENERADO, 'lengua' => $lengua,
        ]);
        $version = ResourceVersion::create([
            'resource_id' => $recurso->id, 'semver' => '1.0.0',
            'config' => ['bloques' => [['tipo' => 'parrafo', 'texto' => 'Ciao.']]],
            'reviewed_at' => $firmada ? now() : null,
        ]);
        $recurso->update(['current_version_id' => $version->id]);
        $recurso->objectives()->attach($this->objetivo()->id, ['role' => 'primary']);

        return $version;
    }

    // ================= quién entra =================

    public function test_un_invitado_recibe_403_no_una_redireccion(): void
    {
        $this->get('/docente/revisar')->assertStatus(403);
    }

    public function test_un_alumno_recibe_403(): void
    {
        $this->actingAs($this->alumno)->get('/docente/revisar')->assertStatus(403);
    }

    public function test_un_docente_ve_lo_pendiente_agrupado_por_unidad(): void
    {
        $this->item();
        $this->leccion();

        $this->actingAs($this->docente)->get('/docente/revisar?lengua=it')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('docente-revisar')
                ->where('total', 2)
                ->where('docente.name', 'Prof. Rossi')
                // A1.CO.2 vive en la unidad 1 del curso.
                ->where('unidades.0.n', 1));
    }

    /** Un docente revisa TODAS las lenguas: no existe «profesor de italiano». */
    public function test_la_cola_es_cerrada_por_lengua_con_tres_lenguas(): void
    {
        $this->item('it');
        $this->item('fr');
        $this->item('de');

        $this->actingAs($this->docente)->get('/docente/revisar?lengua=fr')
            ->assertInertia(fn (Assert $p) => $p->where('total', 1)
                ->where('unidades.0.descriptores.0.piezas.0.lengua', 'fr'));

        // Sin lengua las ve todas: la lengua filtra, su ausencia no esconde.
        $this->actingAs($this->docente)->get('/docente/revisar')
            ->assertInertia(fn (Assert $p) => $p->where('total', 3));

        // Fuera de la lista, 422 (no una lengua inventada por un typo).
        $this->actingAs($this->docente)->get('/docente/revisar?lengua=klingon')->assertStatus(422);
    }

    // ================= firmar deja autoría y rastro =================

    public function test_firmar_deja_autoria_real_y_rastro(): void
    {
        $item = $this->item();

        $this->actingAs($this->docente)
            ->post("/docente/revisar/item/{$item->id}/firmar")
            ->assertRedirect();

        $item->refresh();
        $this->assertNotNull($item->reviewed_at, 'La pieza no quedó firmada.');
        $this->assertSame($this->docente->id, $item->reviewed_by,
            'La firma quedó sin autoría: era justo lo que esta pantalla venía a arreglar.');

        $this->assertDatabaseHas('revisiones', [
            'practice_item_id' => $item->id,
            'user_id' => $this->docente->id,
            'accion' => Revision::FIRMAR,
        ]);
    }

    public function test_firmar_una_leccion_firma_su_version_vigente(): void
    {
        $version = $this->leccion();

        $this->actingAs($this->docente)
            ->post("/docente/revisar/leccion/{$version->id}/firmar")
            ->assertRedirect();

        $version->refresh();
        $this->assertNotNull($version->reviewed_at);
        $this->assertSame($this->docente->id, $version->reviewed_by);
    }

    // ================= devolver y des-firmar EXIGEN nota =================

    public function test_devolver_exige_nota_y_no_firma(): void
    {
        $item = $this->item();

        // Sin nota: rechazado y la pieza intacta. Estos POST vienen del
        // formulario Inertia, así que el rechazo es una vuelta atrás con el
        // error puesto (que es como `useForm` lo pinta), no un 422 JSON.
        $this->actingAs($this->docente)
            ->post("/docente/revisar/item/{$item->id}/devolver", [])
            ->assertRedirect()
            ->assertSessionHasErrors('nota');
        $this->assertNull($item->refresh()->reviewed_at);
        $this->assertSame(0, Revision::count(), 'Se apuntó una devolución sin nota.');

        // Con nota: sigue SIN firmar, y la nota queda.
        $this->actingAs($this->docente)
            ->post("/docente/revisar/item/{$item->id}/devolver", ['nota' => 'El ejemplo 3 está mal.'])
            ->assertRedirect();

        $this->assertNull($item->refresh()->reviewed_at, 'Devolver firmó la pieza.');
        $this->assertDatabaseHas('revisiones', [
            'practice_item_id' => $item->id, 'accion' => Revision::DEVOLVER,
            'nota' => 'El ejemplo 3 está mal.',
        ]);

        // Y la nota se VE en la cola, para quien la corrija.
        $this->actingAs($this->docente)->get('/docente/revisar?lengua=it')
            ->assertInertia(fn (Assert $p) => $p
                ->where('unidades.0.descriptores.0.piezas.0.nota.nota', 'El ejemplo 3 está mal.'));
    }

    /** NADA SE DES-FIRMA SIN NOTA. Un clic para retirar, pero con motivo. */
    public function test_desfirmar_exige_nota_y_deja_rastro(): void
    {
        $item = $this->item(firmado: true);

        $this->actingAs($this->docente)
            ->post("/docente/revisar/item/{$item->id}/desfirmar", [])
            ->assertRedirect()
            ->assertSessionHasErrors('nota');
        $this->assertNotNull($item->refresh()->reviewed_at, 'Se des-firmó sin nota.');
        $this->assertSame(0, Revision::count(), 'Se apuntó una retirada sin nota.');

        $this->actingAs($this->docente)
            ->post("/docente/revisar/item/{$item->id}/desfirmar", ['nota' => 'Errata en el enunciado.'])
            ->assertRedirect();

        $item->refresh();
        $this->assertNull($item->reviewed_at, 'La pieza sigue publicada tras retirarla.');
        $this->assertNull($item->reviewed_by);
        $this->assertDatabaseHas('revisiones', [
            'practice_item_id' => $item->id, 'accion' => Revision::DESFIRMAR,
            'nota' => 'Errata en el enunciado.',
        ]);
    }

    // ================= el atajo exige haber MIRADO =================

    public function test_firmar_la_unidad_entera_exige_abrir_todas_las_piezas(): void
    {
        $a = $this->item();
        $b = $this->item();

        // Sin abrir nada: no se firma NINGUNA.
        $this->actingAs($this->docente)
            ->post('/docente/revisar/unidad', ['unidad' => 1, 'lengua' => 'it'])
            ->assertStatus(422);
        $this->assertNull($a->refresh()->reviewed_at);
        $this->assertNull($b->refresh()->reviewed_at);

        // Abriendo SOLO una, tampoco.
        $this->actingAs($this->docente)->get("/docente/revisar/item/{$a->id}")->assertOk();
        $this->actingAs($this->docente)
            ->post('/docente/revisar/unidad', ['unidad' => 1, 'lengua' => 'it'])
            ->assertStatus(422);
        $this->assertNull($a->refresh()->reviewed_at);

        // Abiertas las dos: ahora sí, y las dos con autoría.
        $this->actingAs($this->docente)->get("/docente/revisar/item/{$b->id}")->assertOk();
        $this->actingAs($this->docente)
            ->post('/docente/revisar/unidad', ['unidad' => 1, 'lengua' => 'it'])
            ->assertRedirect();

        $this->assertNotNull($a->refresh()->reviewed_at);
        $this->assertNotNull($b->refresh()->reviewed_at);
        $this->assertSame($this->docente->id, $a->reviewed_by);
    }

    public function test_abrir_una_pieza_la_marca_como_vista(): void
    {
        $item = $this->item();

        $this->actingAs($this->docente)->get('/docente/revisar?lengua=it')
            ->assertInertia(fn (Assert $p) => $p
                ->where('unidades.0.descriptores.0.piezas.0.vista', false));

        $this->actingAs($this->docente)->get("/docente/revisar/item/{$item->id}")->assertOk();

        $this->actingAs($this->docente)->get('/docente/revisar?lengua=it')
            ->assertInertia(fn (Assert $p) => $p
                ->where('unidades.0.descriptores.0.piezas.0.vista', true));
    }

    // ================= la pieza se abre como la ve el alumno =================

    public function test_la_leccion_sin_firmar_se_abre_con_la_pagina_del_alumno(): void
    {
        $version = $this->leccion();

        $this->actingAs($this->docente)->get("/docente/revisar/leccion/{$version->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('docente-revisar-pieza')
                // La MISMA forma que /recurso: se pinta con Recurso.jsx.
                ->has('recurso.bloques', 1)
                ->where('pieza.firmada', false));
    }

    /**
     * UNA LECCIÓN CURADA NO ENTRA EN LA COLA, aunque su versión esté sin firmar.
     *
     * `Resource::published()` solo le exige firma a lo GENERADO: una lección
     * curada la ve el alumno con `reviewed_at` nulo. Si apareciera aquí, la
     * pantalla diría «pendiente · no se ve» de algo que ya se está viendo, y
     * «retirar la firma» no la escondería. La cola enseña lo que la firma
     * gobierna de verdad.
     */
    public function test_una_leccion_curada_no_entra_en_la_cola_porque_ya_se_ve(): void
    {
        $generada = $this->leccion();           // GENERADO: sin firmar, no se ve
        $curada = $this->leccion();
        $curada->resource->update(['origen' => Resource::CURADO]);

        // La curada YA es visible para el alumno pese a no tener firma.
        $this->assertTrue(
            Resource::published()->whereKey($curada->resource_id)->exists(),
            'Una lección curada debería verse sin firma; si no, este oráculo mide otra cosa.',
        );

        $this->actingAs($this->docente)->get('/docente/revisar?lengua=it')
            ->assertInertia(fn (Assert $p) => $p->where('total', 1)
                ->where('unidades.0.descriptores.0.piezas.0.id', $generada->id));
    }

    /**
     * Las invariantes del rastro se imponen EN EL MODELO, no solo en el
     * controlador: si mañana alguien apunta una revisión desde otro sitio (un
     * comando, un import), «una vía» y «retirar exige nota» siguen en pie. Sin
     * esto, las dos guardas del modelo son decorativas — el `validate` del
     * controlador llega siempre antes y las tapa.
     */
    public function test_el_rastro_falla_cerrado_aunque_no_pase_por_el_controlador(): void
    {
        $item = $this->item();

        // Ni de las dos piezas a la vez, ni de ninguna.
        //
        // OJO con el `try/catch` de manual aquí: `PHPUnit\Framework\Exception`
        // extiende `RuntimeException`, así que un `catch (RuntimeException)` se
        // TRAGA el `$this->fail()` de dentro y el oráculo pasa siempre — una
        // mutación que quitaba esta guarda sobrevivió justo por eso. Se captura
        // en una bandera y se afirma fuera del try.
        foreach ([
            'ninguna vía' => [],
            'las dos vías' => ['practice_item_id' => $item->id, 'resource_version_id' => $this->leccion()->id],
        ] as $caso => $vias) {
            $reventó = false;
            try {
                Revision::create([...$vias, 'user_id' => $this->docente->id, 'accion' => Revision::FIRMAR]);
            } catch (\RuntimeException) {
                $reventó = true;
            }
            $this->assertTrue($reventó, "Se apuntó una revisión con {$caso}.");
        }

        // Y retirar sin nota (aunque sea en blanco) revienta.
        $this->expectException(\RuntimeException::class);
        Revision::create([
            'practice_item_id' => $item->id, 'user_id' => $this->docente->id,
            'accion' => Revision::DESFIRMAR, 'nota' => '   ',
        ]);
    }

    // ================= revisar NO es practicar =================

    public function test_solo_un_docente_pide_un_item_sin_firmar(): void
    {
        $item = $this->item();

        $this->getJson("/api/v1/revision/items/{$item->id}/next")->assertStatus(403);
        $this->actingAs($this->alumno)
            ->getJson("/api/v1/revision/items/{$item->id}/next")->assertStatus(403);

        $this->actingAs($this->docente)
            ->getJson("/api/v1/revision/items/{$item->id}/next")
            ->assertOk()
            ->assertJsonPath('item_id', $item->id)
            ->assertJsonPath('revision', true);
    }

    public function test_responder_en_revision_corrige_pero_no_guarda_nada(): void
    {
        Queue::fake();
        $item = $this->item();

        $siguiente = $this->actingAs($this->docente)
            ->getJson("/api/v1/revision/items/{$item->id}/next")->assertOk()->json();

        $this->actingAs($this->docente)
            ->postJson("/api/v1/revision/items/{$item->id}/attempts", [
                'respuesta' => ['texto' => 'ciao'],
                'billete' => $siguiente['billete'],
            ])
            ->assertOk()
            ->assertJsonPath('is_correct', true);

        // Corrigió de verdad, y no dejó NADA: ni intento, ni dominio, ni nota.
        $this->assertSame(0, PracticeAttempt::count());
        $this->assertSame(0, ObjectiveMastery::count());
        Queue::assertNothingPushed();
    }

    /**
     * El billete de REVISIÓN no vale para escribir un intento de verdad: va
     * atado a otra identidad (`revision:<id>`), así que el endpoint real lo
     * rechaza. Sin esto, revisar sería una vía para inyectar intentos.
     */
    public function test_el_billete_de_revision_no_sirve_en_la_practica_real(): void
    {
        $item = $this->item(firmado: true);

        $siguiente = $this->actingAs($this->docente)
            ->getJson("/api/v1/revision/items/{$item->id}/next")->assertOk()->json();

        $this->actingAs($this->docente)
            ->postJson("/api/v1/practice/items/{$item->id}/attempts", [
                'respuesta' => ['texto' => 'ciao'],
                'billete' => $siguiente['billete'],
            ])
            ->assertStatus(422);

        $this->assertSame(0, PracticeAttempt::count());
    }
}
