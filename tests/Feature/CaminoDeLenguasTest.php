<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\User;
use App\Services\Practice\Tipos\Registro;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * EL CAMINO DEL CONTENIDO DE LENGUAS: de un fichero de banco a la pantalla de
 * un alumno de italiano. Tras #26 y #27 el motor sabía siete tipos y no había
 * MANERA de sembrar cinco de ellos — la regla existía en el motor y no en su
 * hermano el sembrador.
 *
 * Tres decisiones que este fichero fija:
 *
 *  1. LA LENGUA ES DEL CONTENIDO, NO DEL MARCO: `A1.IO.1` es el mismo
 *     descriptor en italiano y en alemán. La columna `lengua` (ítems y
 *     recursos) separa; se pide con `?lengua=` de lista cerrada, y SIN lengua
 *     solo se sirve contenido sin lengua — cerrado por defecto.
 *  2. EL AUDIO SE NOMBRA POR CLAVE (`it/u1/saludo`), no por hash: quien
 *     escribe el banco no puede calcular el hash de un clip que aún no
 *     existe. El sembrador publica y sustituye; un clip que falta REVIENTA
 *     nombrando clave y entrada.
 *  3. CADA TIPO DECLARA CÓMO SE LEE SU ENTRADA (`Tipo::desdeBanco`), igual
 *     que declara cómo se corrige: el octavo tipo no toca el sembrador.
 */
class CaminoDeLenguasTest extends TestCase
{
    use RefreshDatabase;

    private string $rutaBanco;

    private string $dirAudio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);

        $this->rutaBanco = sys_get_temp_dir().'/banco-lenguas-'.getmypid().'.php';
        $this->dirAudio = sys_get_temp_dir().'/audio-lenguas-'.getmypid();
        @mkdir($this->dirAudio.'/it/u1', 0777, true);
        file_put_contents($this->dirAudio.'/it/u1/saludo.mp3', 'CLIP-SALUDO-ITALIANO');
    }

    protected function tearDown(): void
    {
        @unlink($this->rutaBanco);
        foreach ([
            $this->dirAudio.'/it/u1/saludo.mp3', $this->dirAudio.'/it/u1', $this->dirAudio.'/it',
        ] as $ruta) {
            @unlink($ruta) || @rmdir($ruta);
        }
        @rmdir($this->dirAudio);
        foreach (glob(\App\Services\Audio\AlmacenDeAudio::directorio().'/*') ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function escribirBanco(array $banco): void
    {
        file_put_contents($this->rutaBanco, '<?php return '.var_export($banco, true).';');
    }

    private function sembrar(array $opciones = [])
    {
        return $this->artisan('lenguas:sembrar', $opciones + [
            '--banco' => $this->rutaBanco, '--audio' => $this->dirAudio,
        ]);
    }

    /** Una entrada mínima de banco POR KIND — el brazo default es un fail. */
    private function entradaDeBanco(string $kind, string $lengua = 'it', int $seq = 1): array
    {
        $base = ['tipo' => $kind, 'descriptor' => 'A1.IO.1', 'lengua' => $lengua, 'seq' => $seq];

        return match ($kind) {
            PracticeItem::NUMERIC => [...$base,
                'consigna' => ['es' => 'Escribe en cifras: venti + dieci'],
                'params' => ['a' => ['const' => 20], 'b' => ['const' => 10]],
                'expresion' => 'a + b', 'tolerancia' => 0.01, 'tolerancia_tipo' => 'abs'],
            PracticeItem::CHOICE => [...$base,
                'consigna' => ['es' => '¿Cómo se saluda por la mañana?'],
                'opciones' => [
                    ['clave' => 'a', 'texto' => ['it' => 'Buongiorno']],
                    ['clave' => 'b', 'texto' => ['it' => 'Buonanotte']],
                ],
                'correcta' => 'a'],
            PracticeItem::ESCUCHA => [...$base,
                'consigna' => ['es' => 'Escucha el saludo. ¿Qué dice?'],
                'opciones' => [
                    ['clave' => 'a', 'texto' => ['es' => 'Buenos días']],
                    ['clave' => 'b', 'texto' => ['es' => 'Buenas noches']],
                ],
                'correcta' => 'a', 'clip' => 'it/u1/saludo',
                'transcripcion' => 'Buongiorno!'],
            PracticeItem::HUECO => [...$base,
                'consigna' => ['es' => 'Completa: « Io ___ Marco. » (ser)'],
                'aceptadas' => ['sono', 'CENTINELA-HUECO-BANCO']],
            PracticeItem::DICTADO => [...$base,
                'consigna' => ['es' => 'Escucha y escribe lo que oyes.'],
                'aceptadas' => ['buongiorno!'], 'clip' => 'it/u1/saludo',
                'transcripcion' => 'Buongiorno!'],
            PracticeItem::ORDEN => [...$base,
                'consigna' => ['es' => 'Ordena la presentación.'],
                'palabras' => [
                    ['clave' => 'w1', 'texto' => ['it' => 'io']],
                    ['clave' => 'w2', 'texto' => ['it' => 'sono']],
                    ['clave' => 'w3', 'texto' => ['it' => 'Marco']],
                ],
                'secuencias' => [['w1', 'w2', 'w3']]],
            PracticeItem::PARES => [...$base,
                'consigna' => ['es' => 'Empareja el saludo con su momento.'],
                'elementos' => [
                    ['clave' => 'i1', 'col' => 'a', 'texto' => ['it' => 'buongiorno']],
                    ['clave' => 'i2', 'col' => 'a', 'texto' => ['it' => 'buonanotte']],
                    ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'por el día']],
                    ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'al acostarse']],
                ],
                'parejas' => [['i1', 'e1'], ['i2', 'e2']]],
            default => $this->fail(
                "El kind «{$kind}» está en el Registro y NO tiene entrada de banco en este ".
                'fixture. Todo tipo nuevo declara su formato de banco (Tipo::desdeBanco) '.
                'Y su entrada aquí — sin esto, el tipo existe en el motor y no se puede sembrar.',
            ),
        };
    }

    private function descriptor(string $code = 'A1.IO.1'): LearningObjective
    {
        return LearningObjective::where('native_code', $code)->firstOrFail();
    }

    // ========== ORÁCULO 6 — los siete tipos se siembran, por el Registro ==========

    /**
     * El sembrador entiende TODOS los kinds del Registro — la familia de
     * defecto de la semana era exactamente esta: el motor sabía siete y el
     * sembrador dos. El oráculo recorre `Registro::kinds()`, no una lista a
     * mano: el octavo tipo nace dentro. Y cada ítem sembrado SE SIRVE y SE
     * CORRIGE por el circuito real (billete incluido), no solo se inserta.
     */
    public function test_los_siete_tipos_se_siembran_se_sirven_y_se_corrigen(): void
    {
        $this->escribirBanco(array_map(
            fn (string $kind, int $i) => $this->entradaDeBanco($kind, seq: $i + 1),
            Registro::kinds(), array_keys(Registro::kinds()),
        ));

        $this->sembrar()->assertSuccessful();

        $this->assertSame(count(Registro::kinds()),
            PracticeItem::count(), 'Algún kind del Registro no llegó a sembrarse.');

        // Se firma todo (la puerta se prueba aparte) y se recorre el circuito.
        PracticeItem::query()->update(['reviewed_at' => now()]);

        $respuestas = [
            PracticeItem::NUMERIC => fn () => ['answer' => 30],
            PracticeItem::CHOICE => fn () => ['answer_key' => 'a'],
            PracticeItem::ESCUCHA => fn () => ['answer_key' => 'a'],
            PracticeItem::HUECO => fn () => ['respuesta' => ['texto' => 'sono']],
            PracticeItem::DICTADO => fn () => ['respuesta' => ['texto' => 'Buongiorno!']],
            PracticeItem::ORDEN => fn () => ['respuesta' => ['ids' => ['w1', 'w2', 'w3']]],
            PracticeItem::PARES => fn () => ['respuesta' => ['parejas' => [['i1', 'e1'], ['i2', 'e2']]]],
        ];

        foreach (Registro::kinds() as $kind) {
            $item = PracticeItem::where('kind', $kind)->firstOrFail();

            $this->assertSame('it', $item->lengua, "El ítem {$kind} no lleva su lengua.");

            $cuerpo = ($respuestas[$kind] ?? $this->fail("Sin respuesta de fixture para {$kind}."))();
            $this->postJson("/api/v1/practice/items/{$item->id}/attempts", [
                ...$cuerpo, 'billete' => $this->billete($item->id),
            ])->assertOk()->assertJsonPath('is_correct', true);
        }
    }

    // ========== ORÁCULO 3 — cada lengua a lo suyo ==========

    /**
     * Un descriptor con contenido de DOS lenguas sembradas A LA VEZ: pedir
     * italiano no devuelve ni un ítem de alemán. Y la regla es cerrada: sin
     * `lengua` en la petición solo se sirve contenido SIN lengua — el
     * contenido de un curso de idioma siempre se pide declarando el idioma.
     */
    public function test_pedir_italiano_no_devuelve_ni_un_item_de_aleman(): void
    {
        $it = $this->entradaDeBanco('hueco', 'it');
        $de = [...$this->entradaDeBanco('hueco', 'de'),
            'consigna' => ['es' => 'Completa: « Ich ___ Anna. » (ser)'],
            'aceptadas' => ['bin', 'CENTINELA-HUECO-ALEMAN']];
        $this->escribirBanco([$it, $de]);
        $this->sembrar()->assertSuccessful();
        PracticeItem::query()->update(['reviewed_at' => now()]);

        $descriptor = $this->descriptor();

        // Con las DOS sembradas, el italiano solo ve italiano — se pide veinte
        // veces para que la rotación de invitado recorra el banco entero.
        foreach (range(1, 20) as $intento) {
            $json = $this->getJson(
                "/api/v1/objectives/{$descriptor->id}/practice/next?lengua=it&intento={$intento}",
            )->assertOk()->json();

            $item = PracticeItem::findOrFail($json['item_id']);
            $this->assertSame('it', $item->lengua, 'Un ítem alemán se sirvió pidiendo italiano.');
        }

        // Una lengua fuera de la lista cerrada es 422, no una lengua nueva.
        $this->getJson("/api/v1/objectives/{$descriptor->id}/practice/next?lengua=klingon")
            ->assertStatus(422);

        // Y SIN lengua no se sirve contenido de lenguas: cerrado por defecto.
        $this->getJson("/api/v1/objectives/{$descriptor->id}/practice/next")
            ->assertNotFound();
    }

    // ========== ORÁCULO 4 — el clip que falta revienta con nombre ==========

    public function test_un_clip_que_falta_revienta_nombrando_clave_y_entrada(): void
    {
        $this->escribirBanco([
            [...$this->entradaDeBanco('escucha'), 'clip' => 'it/u1/no-existe'],
        ]);

        $this->sembrar()
            ->expectsOutputToContain('it/u1/no-existe')
            ->expectsOutputToContain('A1.IO.1')
            ->assertFailed();

        // Y no sembró NADA: un audio roto delante de un alumno parece su
        // teléfono, no nuestro fallo.
        $this->assertSame(0, PracticeItem::count());
    }

    public function test_una_leccion_con_clip_que_falta_no_entra_a_medias(): void
    {
        $this->escribirBanco([
            'lecciones' => [[
                'lengua' => 'it', 'descriptor' => 'A1.CO.1', 'slug' => 'saludos',
                'titulo' => 'Los saludos', 'resumen' => 'Buongiorno y compañía.',
                'bloques' => [
                    ['tipo' => 'parrafo', 'texto' => ['es' => 'Así se saluda en Italia.']],
                    ['tipo' => 'audio', 'clip' => 'it/u1/tampoco-existe', 'texto' => ['it' => 'Buongiorno!']],
                ],
            ]],
        ]);

        $this->sembrar()
            ->expectsOutputToContain('it/u1/tampoco-existe')
            ->assertFailed();

        $this->assertSame(0, Resource::count(), 'La lección entró a medias, con el audio roto.');
    }

    /** El camino feliz del clip: clave → almacén → src con hash, y se oye. */
    public function test_el_clip_se_publica_y_el_item_lo_referencia_por_hash(): void
    {
        $this->escribirBanco([$this->entradaDeBanco('escucha')]);
        $this->sembrar()->assertSuccessful();

        $item = PracticeItem::firstOrFail();
        $this->assertMatchesRegularExpression('#^/audio/[a-f0-9]{16}\.mp3$#', $item->audio_src);

        // Y el clip publicado se sirve de verdad por la ruta inmutable.
        $this->get($item->audio_src)->assertOk();
    }

    // ========== ORÁCULO 5 — idempotencia en las tres vías ==========

    public function test_resembrar_no_duplica_ni_item_ni_leccion_ni_clip(): void
    {
        $this->escribirBanco([
            'lecciones' => [[
                'lengua' => 'it', 'descriptor' => 'A1.CO.1', 'slug' => 'saludos',
                'titulo' => 'Los saludos', 'resumen' => 'Buongiorno y compañía.',
                'bloques' => [
                    ['tipo' => 'parrafo', 'texto' => ['es' => 'Así se saluda en Italia.']],
                    ['tipo' => 'audio', 'clip' => 'it/u1/saludo', 'texto' => ['it' => 'Buongiorno!']],
                ],
            ]],
            'items' => [$this->entradaDeBanco('escucha')],
        ]);

        $this->sembrar()->assertSuccessful();

        $antes = [
            PracticeItem::count(), PracticeItem::orderBy('id')->pluck('id')->all(),
            Resource::count(), Resource::orderBy('id')->pluck('id')->all(),
            count(glob(\App\Services\Audio\AlmacenDeAudio::directorio().'/*') ?: []),
        ];

        $this->sembrar()->assertSuccessful();

        $this->assertSame($antes, [
            PracticeItem::count(), PracticeItem::orderBy('id')->pluck('id')->all(),
            Resource::count(), Resource::orderBy('id')->pluck('id')->all(),
            count(glob(\App\Services\Audio\AlmacenDeAudio::directorio().'/*') ?: []),
        ], 'Re-sembrar duplicó un ítem, una lección o un clip — o cambió un id.');
    }

    // ========== ORÁCULO 7 — la solución no se filtra, por el Registro ==========

    public function test_ningun_tipo_sembrado_filtra_su_solucion(): void
    {
        $this->escribirBanco(array_map(
            fn (string $kind, int $i) => $this->entradaDeBanco($kind, seq: $i + 1),
            Registro::kinds(), array_keys(Registro::kinds()),
        ));
        $this->sembrar()->assertSuccessful();
        PracticeItem::query()->update(['reviewed_at' => now()]);

        $descriptor = $this->descriptor();

        // Centinelas de lo que JAMÁS viaja, sin acentos (#25). El control
        // positivo: el hueco revela su esperado tras responder.
        $centinelas = ['CENTINELA-HUECO-BANCO', 'Buongiorno!', 'solucion',
            'transcripcion', 'answer_key', 'solution_expr', 'secuencias'];

        foreach (range(1, 20) as $intento) {
            $cuerpo = $this->getJson(
                "/api/v1/objectives/{$descriptor->id}/practice/next?lengua=it&intento={$intento}",
            )->assertOk()->getContent();

            foreach ($centinelas as $centinela) {
                $this->assertStringNotContainsString($centinela, $cuerpo,
                    "«{$centinela}» viajó en next (intento {$intento}).");
            }
        }

        // Control positivo: el veredicto SÍ revela.
        $hueco = PracticeItem::where('kind', 'hueco')->firstOrFail();
        $this->postJson("/api/v1/practice/items/{$hueco->id}/attempts", [
            'respuesta' => ['texto' => 'no'], 'billete' => $this->billete($hueco->id),
        ])->assertOk()->assertJsonPath('esperado', 'sono');
    }

    // ========== ORÁCULO 8 — lo sembrado nace sin firmar, en las dos vías ==========

    public function test_lo_sembrado_nace_sin_firmar_y_no_llega_a_nadie(): void
    {
        $this->escribirBanco([
            'lecciones' => [[
                'lengua' => 'it', 'descriptor' => 'A1.CO.1', 'slug' => 'saludos',
                'titulo' => 'Los saludos', 'resumen' => 'Buongiorno y compañía.',
                'bloques' => [['tipo' => 'parrafo', 'texto' => ['es' => 'CENTINELA-LECCION-SIN-FIRMA']]],
            ]],
            'items' => [$this->entradaDeBanco('hueco')],
        ]);

        $this->sembrar()
            ->expectsOutputToContain('pendiente')
            ->assertSuccessful();

        // El ítem no llega al selector…
        $this->assertNull(PracticeItem::firstOrFail()->reviewed_at);
        $this->getJson('/api/v1/objectives/'.$this->descriptor()->id.'/practice/next?lengua=it')
            ->assertNotFound();

        // …y la lección no se sirve (misma puerta de firma de siempre).
        $leccion = Resource::firstOrFail();
        $this->assertSame(Resource::GENERADO, $leccion->origen);
        $this->get("/recurso/{$leccion->id}")->assertNotFound();

        // Y se firma con los comandos de SIEMPRE, por bloque de área+lengua.
        $this->artisan('practica:firmar', ['--bloque' => 'A1.IO.it'])->assertSuccessful();
        $this->assertNotNull(PracticeItem::firstOrFail()->fresh()->reviewed_at);
    }

    // ========== ORÁCULO 9 — la regla de oro ==========

    public function test_el_invitado_responde_un_item_de_lengua_sin_dejar_rastro(): void
    {
        Queue::fake();

        $this->escribirBanco([$this->entradaDeBanco('hueco')]);
        $this->sembrar()->assertSuccessful();
        PracticeItem::query()->update(['reviewed_at' => now()]);

        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        $item = PracticeItem::firstOrFail();
        $this->postJson("/api/v1/practice/items/{$item->id}/attempts", [
            'respuesta' => ['texto' => 'sono'], 'billete' => $this->billete($item->id),
        ])->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('se_guarda', false);

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()]);
        Queue::assertNotPushed(PushLtiScore::class);
    }

    // ========== la errata de área, también en este banco ==========

    public function test_un_descriptor_con_area_inexistente_revienta(): void
    {
        $this->escribirBanco([
            [...$this->entradaDeBanco('hueco'), 'descriptor' => 'A1.XX.1'],
        ]);

        $this->sembrar()
            ->expectsOutputToContain('A1.XX')
            ->assertFailed();
    }

    /** El área existe pero el descriptor concreto no: hueco, se avisa. */
    public function test_un_descriptor_hueco_dentro_de_un_area_real_avisa(): void
    {
        $this->escribirBanco([
            [...$this->entradaDeBanco('hueco'), 'descriptor' => 'A1.IO.99'],
        ]);

        $this->sembrar()
            ->expectsOutputToContain('A1.IO.99')
            ->assertSuccessful();

        $this->assertSame(0, PracticeItem::count());
    }

    /** Y una lengua fuera de la lista cerrada no se siembra. */
    public function test_una_lengua_fuera_de_la_lista_no_se_siembra(): void
    {
        $this->escribirBanco([
            [...$this->entradaDeBanco('hueco'), 'lengua' => 'xx'],
        ]);

        $this->sembrar()->assertFailed();
        $this->assertSame(0, PracticeItem::count());
    }
}
