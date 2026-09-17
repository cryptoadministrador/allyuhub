<?php

namespace Tests\Feature;

use App\Models\PracticeItem;
use App\Models\Resource;
use App\Services\Audio\AlmacenDeAudio;
use App\Services\Lesson\Bloques;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * PR 11 · HUECOS DECLARADOS: un audio o un vídeo de una lección que todavía no
 * está grabado entra como lo que es (`pendiente => true`, transcripción
 * delante), nunca como un reproductor a un fichero que no existe. Re-sembrar
 * con el fichero lo engancha sin tocar el banco. Los ÍTEMS de escucha/dictado
 * no cambian: sin clip no hay ejercicio.
 */
class HuecosDeclaradosTest extends TestCase
{
    use RefreshDatabase;

    private string $rutaBanco;

    private string $dirAudio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
        $this->rutaBanco = sys_get_temp_dir().'/banco-huecos-'.getmypid().'.php';
        $this->dirAudio = sys_get_temp_dir().'/audio-huecos-'.getmypid();
        @mkdir($this->dirAudio.'/it/u1', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->rutaBanco);
        foreach (glob($this->dirAudio.'/it/u1/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dirAudio.'/it/u1');
        @rmdir($this->dirAudio.'/it');
        @rmdir($this->dirAudio);
        foreach (glob(AlmacenDeAudio::directorio().'/*') ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function sembrar(array $banco)
    {
        file_put_contents($this->rutaBanco, '<?php return '.var_export($banco, true).';');

        return $this->artisan('lenguas:sembrar', ['--banco' => $this->rutaBanco, '--audio' => $this->dirAudio]);
    }

    private function leccion(array $bloques): array
    {
        return [
            'lengua' => 'it', 'descriptor' => 'A1.CO.1', 'slug' => 'saludos',
            'titulo' => 'Los saludos', 'resumen' => 'Buongiorno y compañía.',
            'bloques' => [['tipo' => 'parrafo', 'texto' => ['es' => 'Así se saluda en Italia.']], ...$bloques],
        ];
    }

    private function bloquesServidos(): array
    {
        return Resource::firstOrFail()->currentVersion->config['bloques'];
    }

    // ================= el validador =================

    public function test_un_audio_pendiente_entra_sin_src_y_con_su_clave(): void
    {
        $b = (new Bloques)->validar([
            ['tipo' => 'audio', 'pendiente' => true, 'clip' => 'it/u1/saludo', 'texto' => ['it' => 'Buongiorno!']],
        ]);
        $this->assertSame(['tipo' => 'audio', 'texto' => ['it' => 'Buongiorno!'], 'pendiente' => true, 'clip' => 'it/u1/saludo'], $b[0]);
        $this->assertArrayNotHasKey('src', $b[0]);
    }

    public function test_un_video_solo_existe_como_hueco(): void
    {
        $b = (new Bloques)->validar([
            ['tipo' => 'video', 'pendiente' => true, 'texto' => ['it' => 'Ciao a tutti!', 'es' => '¡Hola a todos!']],
        ]);
        $this->assertSame(['tipo' => 'video', 'texto' => ['it' => 'Ciao a tutti!', 'es' => '¡Hola a todos!'], 'pendiente' => true], $b[0]);
    }

    public function test_lo_que_un_hueco_no_puede_ser(): void
    {
        foreach ([
            'audio pendiente con src' => ['tipo' => 'audio', 'pendiente' => true, 'src' => '/audio/aabbccddeeff0011.mp3', 'texto' => ['it' => 'Ciao']],
            'audio pendiente sin transcripción' => ['tipo' => 'audio', 'pendiente' => true, 'texto' => []],
            'audio sin src y sin pendiente' => ['tipo' => 'audio', 'texto' => ['it' => 'Ciao']],
            'pendiente que no es true' => ['tipo' => 'audio', 'pendiente' => 'sí', 'texto' => ['it' => 'Ciao']],
            'vídeo sin pendiente' => ['tipo' => 'video', 'texto' => ['it' => 'Ciao']],
            'vídeo con src' => ['tipo' => 'video', 'pendiente' => true, 'src' => '/video/x.mp4', 'texto' => ['it' => 'Ciao']],
            'vídeo sin transcripción' => ['tipo' => 'video', 'pendiente' => true],
            'clave de clip rara' => ['tipo' => 'audio', 'pendiente' => true, 'clip' => '../etc/passwd', 'texto' => ['it' => 'Ciao']],
        ] as $caso => $bloque) {
            $revento = false;
            try {
                (new Bloques)->validar([$bloque]);
            } catch (InvalidArgumentException) {
                $revento = true;
            }
            $this->assertTrue($revento, "«{$caso}» pasó el validador.");
        }
    }

    // ================= la siembra =================

    public function test_un_hueco_declarado_se_siembra_sin_fichero_y_se_dice(): void
    {
        $this->sembrar(['lecciones' => [$this->leccion([
            ['tipo' => 'audio', 'pendiente' => true, 'clip' => 'it/u1/saludo', 'texto' => ['it' => 'Buongiorno!']],
            ['tipo' => 'video', 'pendiente' => true, 'clip' => 'it/u1/presentacion', 'texto' => ['it' => 'Mi chiamo Sofía.']],
        ])]])
            ->expectsOutputToContain('hueco(s) declarados sin fichero')
            ->expectsOutputToContain('it/u1/saludo')
            ->expectsOutputToContain('it/u1/presentacion')
            ->assertSuccessful();

        $this->assertSame(1, Resource::count());
        [, $audio, $video] = $this->bloquesServidos();
        $this->assertTrue($audio['pendiente']);
        $this->assertSame('it/u1/saludo', $audio['clip']);
        $this->assertArrayNotHasKey('src', $audio, 'Un hueco no puede llevar reproductor.');
        $this->assertTrue($video['pendiente']);
        $this->assertSame('video', $video['tipo']);
    }

    /** Re-sembrar con el fichero lo engancha: src con hash, y ya no es pendiente. El banco no se toca. */
    public function test_resembrar_con_el_fichero_quita_el_pendiente_sin_tocar_el_banco(): void
    {
        $banco = ['lecciones' => [$this->leccion([
            ['tipo' => 'audio', 'pendiente' => true, 'clip' => 'it/u1/saludo', 'texto' => ['it' => 'Buongiorno!']],
        ])]];
        $this->sembrar($banco)->assertSuccessful();
        $this->assertTrue($this->bloquesServidos()[1]['pendiente']);

        file_put_contents($this->dirAudio.'/it/u1/saludo.mp3', 'CLIP-SALUDO');
        $this->sembrar($banco)->assertSuccessful();

        $audio = $this->bloquesServidos()[1];
        $this->assertArrayNotHasKey('pendiente', $audio);
        $this->assertArrayNotHasKey('clip', $audio);
        $this->assertMatchesRegularExpression('~^/audio/[0-9a-f]{16}\.mp3$~', $audio['src']);
        $this->assertSame(['it' => 'Buongiorno!'], $audio['texto']);
        $this->assertSame(1, Resource::count(), 'Re-sembrar duplicó la lección.');
    }

    /** Sin `pendiente`, la regla de siempre: un clip que falta revienta la siembra entera. */
    public function test_sin_pendiente_un_clip_que_falta_sigue_reventando(): void
    {
        $this->sembrar(['lecciones' => [$this->leccion([
            ['tipo' => 'audio', 'clip' => 'it/u1/no-existe', 'texto' => ['it' => 'Buongiorno!']],
        ])]])
            ->expectsOutputToContain('it/u1/no-existe')
            ->assertFailed();
        $this->assertSame(0, Resource::count());
    }

    /** Un ÍTEM de escucha no admite huecos: `pendiente` en el ítem no cambia nada. */
    public function test_un_item_de_escucha_sin_clip_revienta_aunque_diga_pendiente(): void
    {
        $this->sembrar(['items' => [[
            'tipo' => 'escucha', 'descriptor' => 'A1.CO.1', 'lengua' => 'it', 'seq' => 1,
            'clip' => 'it/u1/no-existe', 'pendiente' => true,
            'consigna' => ['es' => 'Escucha y elige.'], 'transcripcion' => 'Buongiorno!',
            'opciones' => [['clave' => 'a', 'texto' => ['it' => 'Buongiorno']], ['clave' => 'b', 'texto' => ['it' => 'Buonanotte']]],
            'correcta' => 'a',
        ]]])
            ->expectsOutputToContain('it/u1/no-existe')
            ->assertFailed();
        $this->assertSame(0, PracticeItem::count());
    }

    /** El hueco llega al alumno TAL CUAL (pendiente + transcripción) por la ruta de la lección firmada. */
    public function test_la_leccion_con_hueco_se_sirve_con_el_hueco_a_la_vista(): void
    {
        $this->sembrar(['lecciones' => [$this->leccion([
            ['tipo' => 'audio', 'pendiente' => true, 'clip' => 'it/u1/saludo', 'texto' => ['it' => 'Buongiorno!']],
        ])]])->assertSuccessful();
        $this->artisan('lecciones:firmar', ['--bloque' => 'A1.CO.it'])->assertSuccessful();

        $recurso = Resource::firstOrFail();
        $this->get("/recurso/{$recurso->id}")->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('recurso.bloques.1.tipo', 'audio')
                ->where('recurso.bloques.1.pendiente', true)
                ->where('recurso.bloques.1.texto.it', 'Buongiorno!')
                ->missing('recurso.bloques.1.src'));
    }
}
