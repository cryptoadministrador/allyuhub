<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\Revision;
use App\Models\Tarjeta;
use App\Models\User;
use App\Models\VocabEstado;
use App\Services\Audio\AlmacenDeAudio;
use App\Services\Practice\RachaDeAlumno;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PR 10 · EL VOCABULARIO POR UNIDAD: tarjetas, no un tipo de ítem.
 *
 * Se siembra entero o no se siembra (idempotente por (lengua, unidad, clave),
 * clip declarado sin fichero), nace sin firmar y se firma por lengua —desde el
 * comando o desde /docente/revisar con la MISMA pieza de firma—. El alumno dice
 * «la sé» / «todavía no»; el invitado juega en memoria y no escribe nada.
 */
class VocabularioTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    private string $rutaBanco;

    private string $dirAudio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
        $this->ana = User::factory()->create();

        $this->rutaBanco = sys_get_temp_dir().'/vocab-'.getmypid().'.php';
        $this->dirAudio = sys_get_temp_dir().'/vocab-audio-'.getmypid();
        @mkdir($this->dirAudio.'/it/u1/vocab', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->rutaBanco);
        foreach (glob($this->dirAudio.'/it/u1/vocab/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dirAudio.'/it/u1/vocab');
        @rmdir($this->dirAudio.'/it/u1');
        @rmdir($this->dirAudio.'/it');
        @rmdir($this->dirAudio);
        foreach (glob(AlmacenDeAudio::directorio().'/*') ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    // ---- helpers ----

    private function entrada(string $clave, string $lengua = 'it', int $unidad = 1, array $extra = []): array
    {
        return [
            'lengua' => $lengua, 'unidad' => $unidad, 'clave' => $clave,
            'palabra' => $clave, 'significado' => "significado de {$clave}",
            'ejemplo' => [$lengua => "Frase con {$clave}.", 'es' => "Frase con {$clave} (es)."],
            'clip' => "{$lengua}/u{$unidad}/vocab/{$clave}",
            ...$extra,
        ];
    }

    private function sembrar(array $banco, array $opciones = [])
    {
        file_put_contents($this->rutaBanco, '<?php return '.var_export($banco, true).';');

        return $this->artisan('vocabulario:sembrar', $opciones + ['--banco' => $this->rutaBanco, '--audio' => $this->dirAudio]);
    }

    private function tarjeta(string $clave, string $lengua = 'it', int $unidad = 1, bool $firmada = true, int $orden = 1): Tarjeta
    {
        return Tarjeta::create([
            ...$this->entrada($clave, $lengua, $unidad),
            'ejemplo' => [$lengua => "Frase con {$clave}.", 'es' => "Frase con {$clave} (es)."],
            'orden' => $orden, 'audio' => null,
            'reviewed_at' => $firmada ? now() : null,
        ]);
    }

    private function docente(): User
    {
        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.test', 'client_id' => 'c1',
            'deployment_ids' => ['d1'], 'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        $context = LtiContext::create(['platform_id' => $platform->id, 'context_id' => 'c-1', 'title' => 'Italiano 1A']);
        $docente = User::factory()->create(['name' => 'Prof. Rossi']);
        LtiContextMembership::create(['lti_context_id' => $context->id, 'user_id' => $docente->id, 'role' => 'instructor']);

        return $docente;
    }

    // ================= la siembra =================

    public function test_sembrar_es_idempotente_por_clave_y_no_toca_la_firma(): void
    {
        $this->sembrar([$this->entrada('ciao'), $this->entrada('grazie'), $this->entrada('bonjour', 'fr')])
            ->assertSuccessful();
        $this->assertSame(3, Tarjeta::count());
        $this->assertNull(Tarjeta::where('clave', 'ciao')->first()->reviewed_at, 'Nace firmada.');

        // Se firma una, se cambia el significado en el banco y se re-siembra.
        Tarjeta::where('clave', 'ciao')->update(['reviewed_at' => now()]);
        $this->sembrar([
            $this->entrada('ciao', extra: ['significado' => 'hola (corregido)']),
            $this->entrada('grazie'), $this->entrada('bonjour', 'fr'),
        ])->assertSuccessful();

        $this->assertSame(3, Tarjeta::count(), 'Re-sembrar duplicó tarjetas.');
        $ciao = Tarjeta::where('clave', 'ciao')->first();
        $this->assertSame('hola (corregido)', $ciao->significado);
        $this->assertNotNull($ciao->reviewed_at, 'Re-sembrar borró la firma.');
    }

    /** El orden del mazo es el del banco DENTRO de cada unidad, no global. */
    public function test_el_orden_es_por_unidad_y_sigue_al_banco(): void
    {
        $this->sembrar([
            $this->entrada('a', unidad: 1), $this->entrada('x', unidad: 2),
            $this->entrada('b', unidad: 1), $this->entrada('y', unidad: 2),
        ])->assertSuccessful();

        $this->assertSame(['a', 'b'], Tarjeta::deUnidad('it', 1)->pluck('clave')->all());
        $this->assertSame([1, 2], Tarjeta::deUnidad('it', 2)->pluck('orden')->all());
    }

    public function test_el_banco_entra_entero_o_no_entra(): void
    {
        // La segunda repite la clave de la primera; la tercera es de una unidad
        // que el curso no tiene; la cuarta, china sin pinyin. Cada una revienta
        // sola, y con cualquiera de ellas no entra NI la primera.
        foreach ([
            [$this->entrada('ciao'), $this->entrada('ciao')],
            [$this->entrada('ciao'), $this->entrada('x', unidad: 99)],
            [$this->entrada('ciao'), $this->entrada('nihao', 'zh')],
            [$this->entrada('ciao'), $this->entrada('hallo', 'de', extra: ['lectura' => 'no'])],
            [$this->entrada('ciao'), $this->entrada('klingon', 'tlh')],
            [$this->entrada('ciao'), $this->entrada('sin-es', extra: ['ejemplo' => ['it' => 'Ciao.']])],
        ] as $i => $banco) {
            $this->sembrar($banco)->assertExitCode(1);
            $this->assertSame(0, Tarjeta::count(), "El banco #{$i} entró a medias.");
        }

        $this->sembrar([$this->entrada('ciao'), $this->entrada('nihao', 'zh', extra: ['lectura' => 'nǐ hǎo'])])
            ->assertSuccessful();
        $this->assertSame(2, Tarjeta::count());
    }

    public function test_un_clip_sin_fichero_se_declara_y_uno_con_fichero_se_publica(): void
    {
        file_put_contents($this->dirAudio.'/it/u1/vocab/grazie.mp3', 'CLIP-GRAZIE');

        $this->sembrar([$this->entrada('ciao'), $this->entrada('grazie')])
            ->expectsOutputToContain('clip(s) declarados sin fichero')
            ->assertSuccessful();

        $ciao = Tarjeta::where('clave', 'ciao')->first();
        $this->assertSame('it/u1/vocab/ciao', $ciao->clip, 'La clave del clip no se conservó.');
        $this->assertNull($ciao->audio, 'Sin fichero no puede haber ruta.');

        $grazie = Tarjeta::where('clave', 'grazie')->first();
        $this->assertMatchesRegularExpression('~^/audio/[0-9a-f]{16}\.mp3$~', $grazie->audio);

        // Re-sembrar tras grabar engancha el que faltaba, sin tocar el banco.
        file_put_contents($this->dirAudio.'/it/u1/vocab/ciao.mp3', 'CLIP-CIAO');
        $this->sembrar([$this->entrada('ciao'), $this->entrada('grazie')])->assertSuccessful();
        $this->assertNotNull($ciao->fresh()->audio);
    }

    public function test_dry_run_valida_y_no_escribe(): void
    {
        $this->sembrar([$this->entrada('ciao')], ['--dry-run' => true])
            ->expectsOutputToContain('Nada escrito')->assertSuccessful();
        $this->assertSame(0, Tarjeta::count());
    }

    // ================= la firma =================

    public function test_la_firma_es_por_lengua_y_escribe_lo_que_dice_firma(): void
    {
        $this->sembrar([$this->entrada('ciao'), $this->entrada('bonjour', 'fr'), $this->entrada('grazie', unidad: 2)])
            ->assertSuccessful();
        $docente = User::factory()->create();

        $this->artisan('vocabulario:firmar')->assertExitCode(1);
        $this->artisan('vocabulario:firmar', ['--lengua' => 'klingon'])->assertExitCode(1);
        $this->artisan('vocabulario:firmar', ['--lengua' => 'it', '--unidad' => 1, '--docente' => $docente->id])->assertSuccessful();

        $this->assertNotNull(Tarjeta::where('clave', 'ciao')->first()->reviewed_at);
        $this->assertSame($docente->id, Tarjeta::where('clave', 'ciao')->first()->reviewed_by);
        $this->assertNull(Tarjeta::where('clave', 'grazie')->first()->reviewed_at, 'La unidad 2 no se pidió.');
        $this->assertNull(Tarjeta::where('clave', 'bonjour')->first()->reviewed_at, 'El francés no se pidió.');

        $this->artisan('vocabulario:firmar', ['--lengua' => 'it'])->assertSuccessful();
        $this->assertNotNull(Tarjeta::where('clave', 'grazie')->first()->reviewed_at);
        $this->assertNull(Tarjeta::where('clave', 'bonjour')->first()->reviewed_at);
    }

    /** Sin firmar no llega a ningún sitio: ni la página, ni la cuenta de la unidad, ni el estado. */
    public function test_una_tarjeta_sin_firmar_no_se_sirve_ni_se_cuenta(): void
    {
        $firmada = $this->tarjeta('ciao', orden: 1);
        $sinFirmar = $this->tarjeta('grazie', firmada: false, orden: 2);

        $this->get('/corso/it/u1/vocabulario')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('vocabulario')
                ->has('tarjetas', 1)->where('tarjetas.0.id', $firmada->id));
        $this->get('/corso/it/u1')->assertInertia(fn (Assert $p) => $p
            ->where('vocabulario.total', 1)->where('vocabulario.conocidas', 0));

        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$sinFirmar->id}/estado", ['conocida' => true])
            ->assertNotFound();

        // Y sin NINGUNA firmada, la página es 404 y la unidad no enlaza.
        $firmada->update(['reviewed_at' => null]);
        $this->get('/corso/it/u1/vocabulario')->assertNotFound();
        $this->get('/corso/it/u1')->assertInertia(fn (Assert $p) => $p->where('vocabulario.total', 0));
    }

    // ================= la pantalla del docente: la MISMA pieza de firma =================

    public function test_el_docente_ve_la_tarjeta_en_la_cola_y_la_firma_desde_la_pantalla(): void
    {
        $docente = $this->docente();
        $t = $this->tarjeta('ciao', firmada: false);

        $this->actingAs($docente)->get('/docente/revisar?lengua=it')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('docente-revisar')
                ->where('total', 1)
                ->where('unidades.0.n', 1)
                ->where('unidades.0.descriptores.0.code', 'Vocabulario')
                ->where('unidades.0.descriptores.0.piezas.0.tipo', 'vocabulario')
                ->where('unidades.0.descriptores.0.piezas.0.kind', 'vocabulario')
                ->where('unidades.0.descriptores.0.piezas.0.id', $t->id));

        // La pieza se abre TAL COMO LA VE EL ALUMNO: la misma forma que la página.
        $this->actingAs($docente)->get("/docente/revisar/vocabulario/{$t->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('docente-revisar-pieza')
                ->where('pieza.tipo', 'vocabulario')->where('pieza.firmada', false)
                ->where('vocabulario.tarjetas.0.palabra', 'ciao')
                ->where('vocabulario.se_guarda', false));

        // Devolver exige nota; firmar deja autoría y rastro con la tercera vía.
        $this->actingAs($docente)->post("/docente/revisar/vocabulario/{$t->id}/devolver", ['nota' => ''])
            ->assertSessionHasErrors('nota');
        $this->actingAs($docente)->post("/docente/revisar/vocabulario/{$t->id}/devolver", ['nota' => 'Falta el acento.'])
            ->assertRedirect();
        $this->actingAs($docente)->post("/docente/revisar/vocabulario/{$t->id}/firmar")->assertRedirect();

        $t->refresh();
        $this->assertNotNull($t->reviewed_at);
        $this->assertSame($docente->id, $t->reviewed_by);
        $this->assertSame(2, Revision::where('tarjeta_id', $t->id)->count());
        $this->assertSame(0, Revision::whereNotNull('practice_item_id')->orWhereNotNull('resource_version_id')->count());

        // Firmada, sale de pendientes y entra en firmadas. Y el alumno la ve.
        $this->actingAs($docente)->get('/docente/revisar?lengua=it')->assertInertia(fn (Assert $p) => $p->where('total', 0));
        $this->actingAs($docente)->get('/docente/revisar?lengua=it&estado=firmadas')->assertInertia(fn (Assert $p) => $p->where('total', 1));
        $this->get('/corso/it/u1/vocabulario')->assertOk();

        // Un alumno y un invitado: 403, no redirección.
        $this->actingAs($this->ana)->get("/docente/revisar/vocabulario/{$t->id}")->assertStatus(403);
        $this->get("/docente/revisar/vocabulario/{$t->id}")->assertStatus(403);
    }

    /** Firmar la unidad entera desde la pantalla también firma sus tarjetas, y solo si se abrieron. */
    public function test_firmar_la_unidad_entera_incluye_las_tarjetas_vistas(): void
    {
        $docente = $this->docente();
        $a = $this->tarjeta('ciao', firmada: false, orden: 1);
        $b = $this->tarjeta('grazie', firmada: false, orden: 2);

        $this->actingAs($docente)->get("/docente/revisar/vocabulario/{$a->id}")->assertOk();
        $this->actingAs($docente)->post('/docente/revisar/unidad', ['unidad' => 1, 'lengua' => 'it'])->assertStatus(422);

        $this->actingAs($docente)->get("/docente/revisar/vocabulario/{$b->id}")->assertOk();
        $this->actingAs($docente)->post('/docente/revisar/unidad', ['unidad' => 1, 'lengua' => 'it'])->assertRedirect();

        $this->assertNotNull($a->fresh()->reviewed_at);
        $this->assertNotNull($b->fresh()->reviewed_at);
    }

    // ================= el alumno =================

    public function test_la_se_saca_del_mazo_hasta_manana_y_cuenta_en_la_unidad(): void
    {
        $this->travelTo(Carbon::parse('2026-09-17 15:00', RachaDeAlumno::ZONA));
        $ciao = $this->tarjeta('ciao', orden: 1);
        $grazie = $this->tarjeta('grazie', orden: 2);

        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => true])
            ->assertCreated()->assertJson(['conocida' => true, 'se_guarda' => true]);
        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$grazie->id}/estado", ['conocida' => false])
            ->assertCreated()->assertJson(['conocida' => false, 'se_guarda' => true]);
        // Decirlo dos veces no duplica la fila.
        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => true])->assertCreated();
        $this->assertSame(2, VocabEstado::count());

        // Hoy: «ciao» sabida y FUERA del mazo; «grazie» dentro.
        $this->actingAs($this->ana)->get('/corso/it/u1/vocabulario')->assertInertia(fn (Assert $p) => $p
            ->where('tarjetas.0.conocida', true)->where('tarjetas.0.hoy', false)
            ->where('tarjetas.1.conocida', false)->where('tarjetas.1.hoy', true)
            ->where('se_guarda', true));
        $this->actingAs($this->ana)->get('/corso/it/u1')->assertInertia(fn (Assert $p) => $p
            ->where('vocabulario.total', 2)->where('vocabulario.conocidas', 1));

        // Mañana (Ecuador) vuelve al mazo, y sigue contando como sabida.
        $this->travelTo(Carbon::parse('2026-09-18 00:30', RachaDeAlumno::ZONA));
        $this->actingAs($this->ana)->get('/corso/it/u1/vocabulario')->assertInertia(fn (Assert $p) => $p
            ->where('tarjetas.0.conocida', true)->where('tarjetas.0.hoy', true));

        // «Todavía no» sobre una sabida la des-sabe: la cuenta baja.
        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => false])->assertCreated();
        $this->actingAs($this->ana)->get('/corso/it/u1')->assertInertia(fn (Assert $p) => $p->where('vocabulario.conocidas', 0));
    }

    /**
     * «Hoy» es el de Ecuador. «La sé» a las ocho de la tarde de Quito (01:00
     * UTC del día siguiente): a las 23:30 de Quito sigue fuera del mazo, y a la
     * una de la madrugada de Quito —ya es mañana AQUÍ, aunque en UTC sea el
     * mismo día que cuando la marcó— vuelve al mazo.
     */
    public function test_hoy_es_el_dia_de_ecuador(): void
    {
        $ciao = $this->tarjeta('ciao');
        $this->travelTo(Carbon::parse('2026-09-17 20:00', RachaDeAlumno::ZONA));
        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => true])->assertCreated();

        $this->travelTo(Carbon::parse('2026-09-17 23:30', RachaDeAlumno::ZONA));
        $this->actingAs($this->ana)->get('/corso/it/u1/vocabulario')->assertInertia(fn (Assert $p) => $p
            ->where('tarjetas.0.hoy', false));

        // 01:00 del 18 en Quito = 06:00 UTC del 18; la marcó a las 01:00 UTC del
        // 18. Con el día de UTC seguiría fuera; con el de Ecuador ha vuelto.
        $this->travelTo(Carbon::parse('2026-09-18 01:00', RachaDeAlumno::ZONA));
        $this->actingAs($this->ana)->get('/corso/it/u1/vocabulario')->assertInertia(fn (Assert $p) => $p
            ->where('tarjetas.0.hoy', true));
    }

    /** La cuenta de la unidad es del ALUMNO: lo que sabe otro no suma. */
    public function test_las_conocidas_son_del_alumno_no_de_todos(): void
    {
        $ciao = $this->tarjeta('ciao');
        $otro = User::factory()->create();
        $this->actingAs($otro)->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => true])->assertCreated();

        $this->actingAs($this->ana)->get('/corso/it/u1')->assertInertia(fn (Assert $p) => $p
            ->where('vocabulario.total', 1)->where('vocabulario.conocidas', 0));
        $this->actingAs($this->ana)->get('/corso/it/u1/vocabulario')->assertInertia(fn (Assert $p) => $p
            ->where('tarjetas.0.conocida', false)->where('tarjetas.0.hoy', true));
        $this->get('/corso/it/u1')->assertInertia(fn (Assert $p) => $p->where('vocabulario.conocidas', 0));
    }

    public function test_un_user_id_en_el_estado_es_422_y_conocida_es_obligatoria(): void
    {
        $ciao = $this->tarjeta('ciao');
        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => true, 'user_id' => 99])
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$ciao->id}/estado", [])
            ->assertStatus(422)->assertJsonValidationErrors('conocida');
        $this->assertSame(0, VocabEstado::count());
    }

    // ================= regla de oro =================

    public function test_el_invitado_ve_el_mazo_entero_y_no_escribe_nada(): void
    {
        Queue::fake();
        $ciao = $this->tarjeta('ciao', orden: 1);
        $this->tarjeta('grazie', orden: 2);
        $antes = [VocabEstado::count(), PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        $this->get('/corso/it/u1/vocabulario')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('vocabulario')
                ->has('tarjetas', 2)
                ->where('tarjetas.0.conocida', false)->where('tarjetas.0.hoy', true)
                ->where('se_guarda', false));

        $this->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => true])
            ->assertOk()->assertJson(['conocida' => true, 'se_guarda' => false]);

        $this->assertSame($antes, [VocabEstado::count(), PracticeAttempt::count(), ObjectiveMastery::count(), User::count()]);
        Queue::assertNotPushed(PushLtiScore::class);
    }

    // ================= no es un ítem, no da dominio =================

    public function test_decir_la_se_no_mueve_el_dominio_ni_la_racha(): void
    {
        $ciao = $this->tarjeta('ciao');
        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => true])->assertCreated();

        $this->assertSame(0, ObjectiveMastery::count());
        $this->assertSame(0, PracticeAttempt::count());
        $this->actingAs($this->ana)->getJson('/api/v1/practice/racha')->assertJsonPath('dias', 0);
    }

    // ================= lengua cerrada, con las cinco =================

    public function test_la_pagina_es_por_lengua_y_unidad_cerradas(): void
    {
        foreach (['it', 'fr', 'de', 'zh'] as $lengua) {
            $this->tarjeta("palabra-{$lengua}", $lengua);
        }
        $zh = Tarjeta::where('lengua', 'zh')->first();
        $zh->update(['palabra' => '你好', 'lectura' => 'nǐ hǎo']);

        foreach (['it', 'fr', 'de', 'zh'] as $lengua) {
            $this->get("/corso/{$lengua}/u1/vocabulario")->assertOk()
                ->assertInertia(fn (Assert $p) => $p->has('tarjetas', 1)->where('lengua', $lengua));
        }
        $this->get('/corso/zh/u1/vocabulario')->assertInertia(fn (Assert $p) => $p
            ->where('tarjetas.0.palabra', '你好')->where('tarjetas.0.lectura', 'nǐ hǎo'));
        $this->get('/corso/it/u1/vocabulario')->assertInertia(fn (Assert $p) => $p->where('tarjetas.0.lectura', null));

        $this->get('/corso/klingon/u1/vocabulario')->assertNotFound();
        $this->get('/corso/it/u99/vocabulario')->assertNotFound();
        $this->get('/corso/en/u1/vocabulario')->assertNotFound();   // el inglés son los Stages 7-9
        $this->get('/corso/it/u2/vocabulario')->assertNotFound();   // sin tarjetas firmadas
    }
}
