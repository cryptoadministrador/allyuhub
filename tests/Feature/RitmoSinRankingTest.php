<?php

namespace Tests\Feature;

use App\Models\LearningObjective;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\User;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR 14 · EL RITMO ES DEL ALUMNO CONSIGO MISMO. Decisión de colegio, no de
 * producto: ningún ranking, ninguna tabla de clasificación, ningún «vas por
 * detrás de». Este oráculo es un cable trampa: las props de las pantallas de
 * práctica, repaso y prueba, y las respuestas de la API de ritmo, NO llevan
 * nada de otro alumno — ni su nombre, ni su id, ni su cuenta.
 *
 * Y el ritmo (progreso de la tanda, seguidos, puntos de la sesión) NO se
 * guarda en base: vive en la tanda y muere con ella.
 */
class RitmoSinRankingTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
        $this->ana = User::factory()->create(['name' => 'Ana Zurita']);
        $this->otro = User::factory()->create(['name' => 'CENTINELA-OTRO-ALUMNO']);

        $obj = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id;
        foreach ([1, 2] as $seq) {
            $item = PracticeItem::create([
                'objective_id' => $obj, 'kind' => 'hueco', 'lengua' => 'it',
                'statement' => ['es' => "Completa #{$seq}"], 'params' => [],
                'solucion' => ['lengua' => 'it', 'textos' => ['ciao']], 'seq' => $seq, 'reviewed_at' => now(),
            ]);
            // El otro alumno ha practicado MUCHO: si algo filtrara, sería él.
            foreach (range(1, 5) as $n) {
                PracticeAttempt::create([
                    'item_id' => $item->id, 'user_id' => $this->otro->id, 'attempt_no' => $n,
                    'seed' => str_repeat('a', 64), 'params' => [], 'respuesta' => ['texto' => 'ciao'], 'is_correct' => true,
                ]);
            }
        }
    }

    public function test_ninguna_pantalla_de_practica_lleva_a_otro_alumno(): void
    {
        $obj = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail();

        foreach ([
            "/practicar/{$obj->id}?lengua=it",
            '/corso/it/repaso',
            '/corso/it/u1/prueba',
            '/corso/it',
        ] as $url) {
            $cuerpo = $this->actingAs($this->ana)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('CENTINELA-OTRO-ALUMNO', $cuerpo, "{$url} lleva el nombre de otro alumno.");
            $this->assertStringNotContainsString('"user_id":'.$this->otro->id, $cuerpo, "{$url} lleva el id de otro alumno.");
            foreach (['ranking', 'clasificacion', 'posicion', 'leaderboard'] as $palabra) {
                $this->assertStringNotContainsStringIgnoringCase($palabra, $cuerpo, "{$url} habla de «{$palabra}».");
            }
        }
    }

    public function test_la_api_del_ritmo_solo_habla_del_alumno_de_la_sesion(): void
    {
        foreach (['/api/v1/practice/racha', '/api/v1/practice/repaso-diario?lengua=it', '/api/v1/practice/mastery'] as $url) {
            $cuerpo = $this->actingAs($this->ana)->getJson($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('CENTINELA-OTRO-ALUMNO', $cuerpo);
            $this->assertStringNotContainsString((string) $this->otro->id, json_encode(json_decode($cuerpo, true)['racha'] ?? []));
        }

        // La racha de Ana es la suya (cero): la del otro, que practica a diario, no se le pega.
        $this->actingAs($this->ana)->getJson('/api/v1/practice/racha')->assertJsonPath('dias', 0);
    }

    /** El ritmo de la tanda no deja tabla ni columna: nada nuevo en el esquema por él. */
    public function test_el_ritmo_no_se_guarda_en_base(): void
    {
        foreach (['rachas_sesion', 'puntos', 'ritmo', 'tandas', 'rankings'] as $tabla) {
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable($tabla), "Existe una tabla «{$tabla}»: el ritmo no se guarda.");
        }
        foreach (['seguidos', 'puntos', 'ranking'] as $columna) {
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('practice_attempts', $columna));
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('users', $columna));
        }
    }
}
