<?php

namespace Tests\Feature;

use App\Models\Dialogo;
use App\Models\Framework;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\Tarjeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * ORÁCULOS 11 y 14 de la misión curso 3: EL BANCO DE CARLOS SIEMBRA ENTERO, con
 * la cuenta EXACTA, y los cuatro cursos existentes no cambian de forma.
 *
 * Los números están ESCRITOS a propósito (60 lecciones, 484 ítems, 36 guiones,
 * 624 tarjetas): son un cable trampa. Si Carlos añade contenido, este test
 * cae y se actualiza el número — que es exactamente lo que se quiere. Si el
 * sembrador se salta una entrada con un aviso, este test cae y NADIE actualiza
 * nada. Un conteo relativo al fichero (`count($banco)`) no distingue las dos.
 *
 * Corre en SQLite y también contra PostgreSQL en el CI: el esquema dual se
 * valida sembrando de verdad, no solo migrando.
 */
class BancoEnteroTest extends TestCase
{
    use RefreshDatabase;

    // 60 del MCER + 33 del inglés 0861 (PR 18: 11 por Stage).
    public const LECCIONES = 93;

    // 484 + 99 (3 por descriptor inglés).
    public const ITEMS = 583;

    // 36 + 3 (un guion por Stage).
    public const DIALOGOS = 39;

    // 624 + 45 (15 por Stage).
    public const TARJETAS = 669;

    public const CURSOS = ['it', 'fr', 'de', 'zh'];

    public const UNIDADES = 9;

    protected function setUp(): void
    {
        parent::setUp();
        // El inglés se ancla en SU marco (AH-EN0861), que cuelga de Cambridge.
        $this->sembrarMarcosDeLenguas();
    }

    public function test_el_banco_de_carlos_siembra_entero_con_la_cuenta_exacta(): void
    {
        $this->artisan('lenguas:sembrar')->assertSuccessful();
        $this->artisan('dialogos:sembrar')->assertSuccessful();
        $this->artisan('vocabulario:sembrar')->assertSuccessful();

        $this->assertSame(self::LECCIONES, Resource::whereNotNull('lengua')->count(), 'Lecciones: cuenta distinta de la esperada.');
        $this->assertSame(self::ITEMS, PracticeItem::whereNotNull('lengua')->count(), 'Ítems: cuenta distinta de la esperada.');
        $this->assertSame(self::DIALOGOS, Dialogo::count(), 'Diálogos: cuenta distinta de la esperada.');
        $this->assertSame(self::TARJETAS, Tarjeta::count(), 'Tarjetas: cuenta distinta de la esperada.');

        // Todo nace SIN firmar: nada se publica sin que lo firme un docente.
        $this->assertSame(0, PracticeItem::whereNotNull('lengua')->whereNotNull('reviewed_at')->count());
        $this->assertSame(0, Dialogo::whereNotNull('reviewed_at')->count());
        $this->assertSame(0, Tarjeta::whereNotNull('reviewed_at')->count());

        // Y re-sembrar no duplica ni una fila (idempotencia de los tres).
        $this->artisan('lenguas:sembrar')->assertSuccessful();
        $this->artisan('dialogos:sembrar')->assertSuccessful();
        $this->artisan('vocabulario:sembrar')->assertSuccessful();
        $this->assertSame(self::ITEMS, PracticeItem::whereNotNull('lengua')->count());
        $this->assertSame(self::DIALOGOS, Dialogo::count());
        $this->assertSame(self::TARJETAS, Tarjeta::count());
    }

    /** Oráculo 11: it/fr/de/zh, nueve unidades cada uno, un guion por unidad, vocabulario en las nueve. */
    public function test_los_cuatro_cursos_existentes_no_cambian(): void
    {
        $this->artisan('dialogos:sembrar')->assertSuccessful();
        $this->artisan('vocabulario:sembrar')->assertSuccessful();

        foreach (self::CURSOS as $lengua) {
            $this->get("/corso/{$lengua}")->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('corso')->has('unidades', self::UNIDADES));

            $unidadesConGuion = Dialogo::where('lengua', $lengua)->distinct()->pluck('unidad')->sort()->values()->all();
            $this->assertSame(range(1, self::UNIDADES), $unidadesConGuion, "«{$lengua}» no tiene un guion por unidad.");
            $this->assertSame(self::UNIDADES, Dialogo::where('lengua', $lengua)->count(), "«{$lengua}» tiene más de un guion en alguna unidad.");

            $unidadesConVocab = Tarjeta::where('lengua', $lengua)->distinct()->pluck('unidad')->sort()->values()->all();
            $this->assertSame(range(1, self::UNIDADES), $unidadesConVocab, "«{$lengua}» no tiene vocabulario en las nueve unidades.");

            foreach (range(1, self::UNIDADES) as $n) {
                $this->get("/corso/{$lengua}/u{$n}")->assertOk();
            }
        }

        // Solo el chino lleva lectura (pinyin), y la lleva ENTERO.
        $this->assertSame(0, Tarjeta::where('lengua', '!=', 'zh')->whereNotNull('lectura')->count());
        $this->assertSame(0, Tarjeta::where('lengua', 'zh')->whereNull('lectura')->count());
    }

    /**
     * PR 18 · EL INGLÉS 0861 ENTRA ENTERO. Tres Stages (u7-u9), cada uno con
     * una lección por descriptor, TRES ítems por descriptor (≥2 es lo que
     * piden el dominio y el repaso: con uno se aprendería el ítem, no la
     * destreza), vocabulario y un guion. Todo anclado en AH-EN0861, nada en
     * el MCER.
     */
    public function test_el_ingles_entra_entero_y_en_su_marco(): void
    {
        $this->artisan('lenguas:sembrar')->assertSuccessful();
        $this->artisan('dialogos:sembrar')->assertSuccessful();
        $this->artisan('vocabulario:sembrar')->assertSuccessful();

        $internos = LearningObjective::whereIn('version_id',
            Framework::where('code', 'AH-EN0861')->firstOrFail()->versions()->select('id'))->get();
        $this->assertCount(33, $internos);

        foreach ($internos as $d) {
            $this->assertSame(3, PracticeItem::where('objective_id', $d->id)->where('lengua', 'en')->count(),
                "«{$d->native_code}» no tiene sus tres ítems.");
            $this->assertSame(1, Resource::where('lengua', 'en')
                ->whereHas('objectives', fn ($q) => $q->where('objective_id', $d->id))->count(),
                "«{$d->native_code}» no tiene su lección.");
        }

        // Ni un ítem inglés colgado del MCER, ni uno de otra lengua en AH-EN0861.
        $this->assertSame(99, PracticeItem::where('lengua', 'en')->whereIn('objective_id', $internos->pluck('id'))->count());
        $this->assertSame(0, PracticeItem::where('lengua', '!=', 'en')->whereIn('objective_id', $internos->pluck('id'))->count());

        foreach ([7, 8, 9] as $n) {
            $this->assertSame(1, Dialogo::where('lengua', 'en')->where('unidad', $n)->count(), "Stage {$n} sin guion.");
            $this->assertSame(15, Tarjeta::where('lengua', 'en')->where('unidad', $n)->count(), "Stage {$n}: vocabulario.");
            $this->get("/corso/en/u{$n}")->assertOk()
                ->assertInertia(fn (Assert $p) => $p->has('puedo', 11));
        }

        // Sin firmar, el curso sigue «próximamente»: nada llega al alumno sin docente.
        $this->get('/corso/en')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('unidades.0.estado', 'proximamente'));

        // Firmado un Stage entero, deja de estarlo.
        $stage7 = $internos->filter(fn ($d) => str_starts_with($d->native_code, 'EN7.'))->pluck('id');
        PracticeItem::whereIn('objective_id', $stage7)->update(['reviewed_at' => now()]);
        $this->get('/corso/en')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->whereNot('unidades.0.estado', 'proximamente'));
    }
}
