<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Dialogo;
use App\Models\Framework;
use App\Models\LearningObjective;
use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\PracticeItem;
use App\Models\User;
use Database\Seeders\CambridgeEnglishSeeder;
use Database\Seeders\CefrSeeder;
use Database\Seeders\InglesInternoSeeder;
use Database\Seeders\InternationalFrameworksSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * PR 16 · EL INGLÉS TIENE DÓNDE ATERRIZAR.
 *
 * `/corso/en` no podía tener contenido nunca: 0861 entró sin objetivos (su
 * marco es de descarga protegida) y un ítem se ancla a un descriptor. Aquí
 * entran descriptores PROPIOS, en un marco PROPIO (`AH-EN0861`), que dicen lo
 * que son. Los oráculos fijan las dos mitades:
 *
 *  1. **Honradez**: ningún código parece de Cambridge, nada entra verificado ni
 *     oficial, y cada descriptor cuelga de un nodo PÚBLICO de 0861 que existe
 *     — si no existe, revienta ANTES de escribir.
 *  2. **Sirve**: el curso los pinta y `lenguas:sembrar` ancla el inglés en SU
 *     marco sin mover el anclaje de las lenguas del MCER.
 */
class InglesInternoTest extends TestCase
{
    use RefreshDatabase;

    private const TOTAL = 33;   // 3 stages × (4 R + 4 W + 3 SL), escrito a mano

    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function sembrarGrafo(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CambridgeEnglishSeeder::class);
        $this->seed(InglesInternoSeeder::class);
    }

    /** @return \Illuminate\Support\Collection<int, LearningObjective> */
    private function descriptoresInternos()
    {
        $fw = Framework::where('code', 'AH-EN0861')->first();

        return $fw === null ? collect() : LearningObjective::query()
            ->whereIn('version_id', $fw->versions()->select('id'))
            ->get();
    }

    /** Una copia del fichero de datos con un cambio, en un temporal (nunca el versionado). */
    private function datosCon(callable $cambio): string
    {
        $data = require database_path('data/ingles-0861-interno.php');
        $data = $cambio($data);
        $ruta = sys_get_temp_dir().'/ingles-0861-'.getmypid().'-'.count($this->temporales).'.php';
        file_put_contents($ruta, '<?php return '.var_export($data, true).';');
        $this->temporales[] = $ruta;

        return $ruta;
    }

    private function revientaCon(string $ruta, string $esperado): void
    {
        $revento = false;
        try {
            (new InglesInternoSeeder)->sembrarDesde($ruta);
        } catch (RuntimeException $e) {
            $revento = true;
            $this->assertStringContainsString($esperado, $e->getMessage());
        }
        // Bandera FUERA del try: un $this->fail() dentro lo tragaría el catch.
        $this->assertTrue($revento, "Debía reventar nombrando «{$esperado}».");
        $this->assertSame(0, $this->descriptoresInternos()->count(), 'Reventó, pero dejó descriptores a medias.');
    }

    // ================= 1. honradez =================

    public function test_entran_los_descriptores_propios_colgados_de_nodos_publicos_de_0861(): void
    {
        $this->sembrarGrafo();

        $fw = Framework::where('code', 'AH-EN0861')->firstOrFail();
        $this->assertSame('internal', $fw->kind);

        $objs = $this->descriptoresInternos();
        $this->assertCount(self::TOTAL, $objs);

        foreach ($objs as $o) {
            $this->assertFalse((bool) $o->is_verified, "«{$o->native_code}» entró verificado.");
            $this->assertFalse($o->attrs['oficial'] ?? true, "«{$o->native_code}» no declara oficial=false.");
            $ref = $o->attrs['cambridge_ref'] ?? '';
            $this->assertStringStartsWith('lsec.en0861', $ref);
            // La referencia es un NODO REAL del grafo de Cambridge, no un texto.
            $this->assertSame(1, CurNode::where('path', $ref)->count(), "«{$o->native_code}» apunta a «{$ref}», que no está.");
            $this->assertNotSame('', trim($o->statement['es'] ?? ''));
            $this->assertNotSame('', trim($o->statement['en'] ?? ''));
        }

        // Y Cambridge sigue SIN objetivos en 0861: lo nuestro no se coló en lo suyo.
        $en0861 = CurNode::where('path', 'lsec.en0861')->firstOrFail();
        $this->assertSame(0, LearningObjective::query()
            ->whereIn('node_id', CurNode::query()->descendantsOf($en0861)->pluck('id')->push($en0861->id))
            ->count());
    }

    public function test_ningun_codigo_se_parece_a_uno_de_cambridge(): void
    {
        $this->sembrarGrafo();

        foreach ($this->descriptoresInternos() as $o) {
            $this->assertMatchesRegularExpression('/^EN[789]\.(R|W|SL)\.\d+$/', $o->native_code);
        }
    }

    public function test_revienta_si_cambridge_no_esta_sembrado(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);   // SIN el injerto de inglés

        $this->revientaCon(database_path('data/ingles-0861-interno.php'), 'lsec.en0861');
        $this->assertNull(Framework::where('code', 'AH-EN0861')->first());
    }

    public function test_revienta_si_un_descriptor_apunta_a_un_nodo_que_no_existe(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CambridgeEnglishSeeder::class);

        $ruta = $this->datosCon(function ($d) {
            $d['stages'][8]['W'][2]['ref'] = 'lsec.en0861.writing.ss9';

            return $d;
        });

        $this->revientaCon($ruta, 'EN8.W.3');
    }

    public function test_revienta_un_codigo_con_forma_de_cambridge(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CambridgeEnglishSeeder::class);

        $ruta = $this->datosCon(function ($d) {
            $d['stages'][7]['R'][0]['code'] = '7Rv.01';

            return $d;
        });

        $this->revientaCon($ruta, '7Rv.01');
    }

    public function test_es_idempotente(): void
    {
        $this->sembrarGrafo();
        $this->seed(InglesInternoSeeder::class);

        $this->assertCount(self::TOTAL, $this->descriptoresInternos());
        $this->assertSame(1, Framework::where('code', 'AH-EN0861')->count());
    }

    // ================= 2. sirve =================

    public function test_la_unidad_pinta_sus_puedo_y_sigue_proximamente_sin_contenido(): void
    {
        $this->sembrarGrafo();

        $this->get('/corso/en/u7')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('puedo', 11)
                ->where('puedo.0.code', 'EN7.R.1')
                ->where('puedo.0.has_items', false)
                ->where('puedo.10.code', 'EN7.SL.3'));

        $this->get('/corso/en')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('unidades.0.estado', 'proximamente'));
    }

    public function test_lenguas_sembrar_ancla_cada_lengua_en_el_marco_de_su_curso(): void
    {
        $this->seed(CefrSeeder::class);
        $this->sembrarGrafo();

        $banco = [
            ['tipo' => PracticeItem::CHOICE, 'descriptor' => 'EN7.R.2', 'lengua' => 'en', 'seq' => 1,
                'consigna' => ['es' => 'Which detail is stated in the text?'],
                'opciones' => [
                    ['clave' => 'a', 'texto' => ['en' => 'The ship left at dawn.']],
                    ['clave' => 'b', 'texto' => ['en' => 'The captain was afraid.']],
                ],
                'correcta' => 'a'],
            ['tipo' => PracticeItem::CHOICE, 'descriptor' => 'A1.IO.1', 'lengua' => 'it', 'seq' => 1,
                'consigna' => ['es' => '¿Cómo se saluda por la mañana?'],
                'opciones' => [
                    ['clave' => 'a', 'texto' => ['it' => 'Buongiorno']],
                    ['clave' => 'b', 'texto' => ['it' => 'Buonanotte']],
                ],
                'correcta' => 'a'],
        ];
        $ruta = sys_get_temp_dir().'/banco-en-'.getmypid().'.php';
        file_put_contents($ruta, '<?php return '.var_export($banco, true).';');
        $this->temporales[] = $ruta;

        $this->artisan('lenguas:sembrar', ['--banco' => $ruta])->assertSuccessful();

        $en = PracticeItem::where('lengua', 'en')->sole();
        $it = PracticeItem::where('lengua', 'it')->sole();
        $this->assertSame('EN7.R.2', $en->objective->native_code);
        $this->assertSame('AH-EN0861', $en->objective->node->version->framework->code);
        // El italiano no se movió de su marco.
        $this->assertSame('CEFR', $it->objective->node->version->framework->code);

        // Firmado, el Stage 7 deja de estar «próximamente».
        $en->update(['reviewed_at' => now()]);
        $this->get('/corso/en')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->whereNot('unidades.0.estado', 'proximamente'));
    }

    public function test_una_errata_de_area_en_ingles_revienta_la_siembra(): void
    {
        $this->seed(CefrSeeder::class);
        $this->sembrarGrafo();

        $banco = [['tipo' => PracticeItem::CHOICE, 'descriptor' => 'EN7.X.1', 'lengua' => 'en', 'seq' => 1,
            'consigna' => ['es' => '?'],
            'opciones' => [['clave' => 'a', 'texto' => ['en' => 'a']], ['clave' => 'b', 'texto' => ['en' => 'b']]],
            'correcta' => 'a']];
        $ruta = sys_get_temp_dir().'/banco-en-errata-'.getmypid().'.php';
        file_put_contents($ruta, '<?php return '.var_export($banco, true).';');
        $this->temporales[] = $ruta;

        $this->artisan('lenguas:sembrar', ['--banco' => $ruta])->assertFailed();
        $this->assertSame(0, PracticeItem::count());
    }

    // ================= 3. lo que cazó la auditoría =================

    /** Un ítem mínimo sin firmar sobre un descriptor, en una lengua. */
    private function itemSobre(string $code, string $lengua): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => LearningObjective::where('native_code', $code)->firstOrFail()->id,
            'kind' => 'hueco', 'lengua' => $lengua,
            'statement' => ['es' => "Completa ({$lengua})."],
            'params' => [], 'solucion' => ['lengua' => $lengua, 'textos' => ['x']],
            'seq' => random_int(1, 99999),
        ]);
    }

    private function docente(): User
    {
        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.test', 'client_id' => 'c1',
            'deployment_ids' => ['d1'], 'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        $context = LtiContext::create(['platform_id' => $platform->id, 'context_id' => 'c-1', 'title' => 'Inglés 8A']);
        $docente = User::factory()->create();
        LtiContextMembership::create(['lti_context_id' => $context->id, 'user_id' => $docente->id, 'role' => 'instructor']);

        return $docente;
    }

    /**
     * SIN FILTRO DE LENGUA, el Stage 9 inglés NO se mete en la «unidad 9» del
     * MCER. Antes salía titulado «Repaso y proyecto» junto a A1.IO.1 italiano,
     * y «firmar la unidad 9» sin lengua firmaba los dos cursos a la vez.
     */
    public function test_la_cola_de_revision_no_mezcla_el_ingles_con_el_mcer(): void
    {
        $this->seed(CefrSeeder::class);
        $this->sembrarGrafo();
        $docente = $this->docente();
        $en = $this->itemSobre('EN9.R.1', 'en');
        $it = $this->itemSobre('A1.IO.1', 'it');   // A1.IO.1 vive en la unidad 9 del MCER

        $this->actingAs($docente)->get('/docente/revisar')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('unidades', 2)
                ->where('unidades.0.n', 9)->where('unidades.0.lengua', null)
                ->where('unidades.0.titulo', 'Repaso y proyecto')
                ->where('unidades.0.descriptores.0.code', 'A1.IO.1')
                ->where('unidades.1.n', 9)->where('unidades.1.lengua', 'en')
                ->where('unidades.1.titulo', 'Stage 9')
                ->where('unidades.1.descriptores.0.code', 'EN9.R.1'));

        // Vistas las dos, «firmar la unidad 9» SIN lengua firma solo el cajón compartido.
        $this->actingAs($docente)->get("/docente/revisar/item/{$en->id}")->assertOk();
        $this->actingAs($docente)->get("/docente/revisar/item/{$it->id}")->assertOk();
        $this->actingAs($docente)->post('/docente/revisar/unidad', ['unidad' => 9])->assertRedirect();
        $this->assertNotNull($it->refresh()->reviewed_at);
        $this->assertNull($en->refresh()->reviewed_at, 'Firmar la unidad 9 del MCER arrastró el Stage 9 inglés.');

        // Y el cajón inglés se firma con SU lengua.
        $this->actingAs($docente)->post('/docente/revisar/unidad', ['unidad' => 9, 'lengua' => 'en'])->assertRedirect();
        $this->assertNotNull($en->refresh()->reviewed_at);
    }

    /** `dialogos:sembrar` tenía el CEFR escrito a mano: el inglés no podía tener diálogo. */
    public function test_un_dialogo_de_ingles_se_ancla_en_su_marco(): void
    {
        $this->seed(CefrSeeder::class);
        $this->sembrarGrafo();

        $banco = [[
            'lengua' => 'en', 'unidad' => 7, 'objective' => 'EN7.SL.3', 'slug' => 'first-day', 'titulo' => 'First day',
            'nodos' => [
                ['id' => 'inicio', 'dice' => 'Hi! What do you think about the book?', 'respuestas' => [
                    ['texto' => 'I liked it, and I agree with what Ana said.', 'va' => 'fin'],
                    ['texto' => 'No.', 'va' => null, 'pista' => 'Da una razón y parte de lo que ya se dijo.'],
                ]],
                ['id' => 'fin', 'dice' => 'Good point. Thanks!', 'fin' => true, 'respuestas' => []],
            ],
        ]];
        $ruta = sys_get_temp_dir().'/dialogos-en-'.getmypid().'.php';
        file_put_contents($ruta, '<?php return '.var_export($banco, true).';');
        $this->temporales[] = $ruta;

        $this->artisan('dialogos:sembrar', ['--banco' => $ruta])->assertSuccessful();
        $d = Dialogo::where('lengua', 'en')->sole();
        $this->assertSame('AH-EN0861', $d->objective->node->version->framework->code);
    }

    /** Retirar del fichero un descriptor SIN contenido lo borra: nada de fantasmas. */
    public function test_un_descriptor_retirado_sin_contenido_se_borra(): void
    {
        $this->sembrarGrafo();
        $ruta = $this->datosCon(function ($d) {
            array_pop($d['stages'][9]['SL']);   // fuera EN9.SL.3

            return $d;
        });

        (new InglesInternoSeeder)->sembrarDesde($ruta);

        $this->assertCount(self::TOTAL - 1, $this->descriptoresInternos());
        $this->assertNull(LearningObjective::where('native_code', 'EN9.SL.3')->first());
    }

    /**
     * Retirar uno CON contenido revienta y no toca nada: las claves foráneas
     * borran en cascada, así que borrarlo se llevaría los ítems por delante.
     */
    public function test_un_descriptor_retirado_con_contenido_revienta_y_no_toca_nada(): void
    {
        $this->sembrarGrafo();
        $item = $this->itemSobre('EN9.SL.3', 'en');
        $ruta = $this->datosCon(function ($d) {
            array_pop($d['stages'][9]['SL']);
            $d['stages'][7]['R'][0]['es'] = 'CENTINELA-ENUNCIADO-NUEVO';

            return $d;
        });

        $revento = false;
        try {
            (new InglesInternoSeeder)->sembrarDesde($ruta);
        } catch (RuntimeException $e) {
            $revento = true;
            $this->assertStringContainsString('EN9.SL.3', $e->getMessage());
        }
        $this->assertTrue($revento);
        $this->assertNotNull($item->fresh(), 'El ítem se borró en cascada.');
        $this->assertCount(self::TOTAL, $this->descriptoresInternos());
        // La transacción entera se deshizo: tampoco entró el cambio de enunciado.
        $this->assertNotSame('CENTINELA-ENUNCIADO-NUEVO',
            LearningObjective::where('native_code', 'EN7.R.1')->first()->statement['es']);
    }

    /** Editar un enunciado se mueve a la base, y el sha256 de la fuente con él. */
    public function test_editar_el_fichero_actualiza_enunciado_y_sha(): void
    {
        $this->sembrarGrafo();
        $version = Framework::where('code', 'AH-EN0861')->first()->versions()->sole();
        $shaAntes = $version->source_sha256;

        $ruta = $this->datosCon(function ($d) {
            $d['stages'][7]['R'][0]['es'] = 'Puedo leer con fluidez (revisado).';

            return $d;
        });
        (new InglesInternoSeeder)->sembrarDesde($ruta);

        $this->assertSame(1, Framework::where('code', 'AH-EN0861')->first()->versions()->count(),
            'Editar el fichero creó otra versión y dejaría el contenido huérfano.');
        $this->assertNotSame($shaAntes, $version->fresh()->source_sha256);
        $this->assertSame('Puedo leer con fluidez (revisado).',
            LearningObjective::where('native_code', 'EN7.R.1')->first()->statement['es']);
    }

    /** La forma se comprueba ENTERA: ni `EN7.R.`, ni `EN7.R.uno`, ni `EN7.R.1.2`. */
    public function test_la_forma_del_codigo_se_comprueba_entera(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CambridgeEnglishSeeder::class);

        foreach (['EN7.R.', 'EN7.R.uno', 'EN7.R.1.2'] as $malo) {
            $ruta = $this->datosCon(function ($d) use ($malo) {
                $d['stages'][7]['R'][0]['code'] = $malo;

                return $d;
            });
            $this->revientaCon($ruta, $malo);
        }
    }
}
