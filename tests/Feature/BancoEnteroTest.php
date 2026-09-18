<?php

namespace Tests\Feature;

use App\Models\Dialogo;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\Tarjeta;
use Database\Seeders\CefrSeeder;
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

    public const LECCIONES = 60;

    public const ITEMS = 484;

    public const DIALOGOS = 36;

    public const TARJETAS = 624;

    public const CURSOS = ['it', 'fr', 'de', 'zh'];

    public const UNIDADES = 9;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
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
}
