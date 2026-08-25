<?php

namespace Tests\Feature;

use App\Services\Audio\AlmacenDeAudio;
use App\Services\Audio\ClipCurricular;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * DÓNDE VIVEN LOS FICHEROS DE AUDIO — la decisión, fijada en tests.
 *
 * - El audio NO viaja dentro del JSON: son cientos de clips por curso y un
 *   base64 en `config` reventaría la carga de cualquier página.
 * - Nombre ESTABLE Y DERIVADO DEL CONTENIDO (sha256): re-sembrar no rompe
 *   enlaces ni duplica ficheros, y por eso mismo se puede cachear inmutable —
 *   si el contenido cambia, cambia el nombre.
 * - Ruta servida por la app (`/audio/{fichero}`), abierta como el resto del
 *   contenido, con `Cache-Control: immutable`: el mismo clip se oye veinte
 *   veces y solo debe viajar una.
 * - Sin fichero, la LECCIÓN sigue en pie: el bloque cae a su transcripción en
 *   el cliente. Aquí se fija la mitad del servidor: el 404 del clip no tumba
 *   nada más.
 */
class AudioTest extends TestCase
{
    use RefreshDatabase;

    private AlmacenDeAudio $almacen;

    private string $temporal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->almacen = new AlmacenDeAudio;
        $this->temporal = sys_get_temp_dir().'/audio-test-'.getmypid();
        @mkdir($this->temporal);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporal.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->temporal);
        // Los clips publicados por los tests se retiran: storage es disco real
        // y RefreshDatabase no lo limpia (lección del fichero de cobertura).
        foreach (glob(AlmacenDeAudio::directorio().'/*') ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function clip(string $contenido, string $nombre = 'clip.mp3'): ClipCurricular
    {
        $ruta = $this->temporal.'/'.$nombre;
        file_put_contents($ruta, $contenido);

        // La declaración es parte del contrato: `publicar()` no acepta un
        // string. Estos SÍ son clips curriculares — son los del test.
        return new ClipCurricular($ruta);
    }

    /**
     * EL TIPO ES LA REGLA. El almacén sirve público, sin auth y cacheado
     * inmutable un año — solo vale para audio curricular, y eso lo dice la
     * FIRMA de `publicar()`, no un docblock que en dos PR nadie recuerda.
     * El día que un alumno se grabe (frente D), su voz no puede entrar por
     * aquí sin escribir `new ClipCurricular(...)` sobre una grabación de un
     * menor — una mentira visible en el diff. Si alguien ensancha la firma de
     * vuelta a string, este oráculo se pone rojo.
     */
    public function test_publicar_exige_declarar_el_clip_como_curricular(): void
    {
        $parametro = (new \ReflectionMethod(AlmacenDeAudio::class, 'publicar'))
            ->getParameters()[0];

        $this->assertSame(ClipCurricular::class, (string) $parametro->getType(),
            'publicar() volvió a aceptar un string: la restricción curricular ya no la obliga el tipo.');
    }

    // ================= el almacén =================

    public function test_el_nombre_deriva_del_contenido_y_es_estable(): void
    {
        $src1 = $this->almacen->publicar($this->clip('AUDIO-UNO'));
        $src2 = $this->almacen->publicar($this->clip('AUDIO-UNO', 'otro-nombre.mp3'));

        // Mismo contenido → mismo nombre, aunque el fichero de origen se llame
        // distinto: re-sembrar no duplica ni rompe enlaces ya compartidos.
        $this->assertSame($src1, $src2);
        $this->assertMatchesRegularExpression('#^/audio/[a-f0-9]{16}\.mp3$#', $src1);

        // Contenido distinto → nombre distinto: es lo que hace SEGURO el
        // `immutable` de la caché.
        $src3 = $this->almacen->publicar($this->clip('AUDIO-DOS'));
        $this->assertNotSame($src1, $src3);
    }

    public function test_una_extension_fuera_de_la_lista_revienta(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->almacen->publicar($this->clip('lo-que-sea', 'clip.exe'));
    }

    // ================= la ruta =================

    public function test_sirve_el_clip_con_cache_inmutable(): void
    {
        $src = $this->almacen->publicar($this->clip('CONTENIDO-DEL-CLIP'));

        $respuesta = $this->get($src)->assertOk();

        // El nombre deriva del contenido, así que un año de caché es seguro:
        // si el clip cambia, cambia la URL y la caché vieja no estorba.
        $cache = (string) $respuesta->headers->get('Cache-Control');
        $this->assertStringContainsString('immutable', $cache);
        $this->assertStringContainsString('max-age=31536000', $cache);
        $this->assertSame('audio/mpeg', $respuesta->headers->get('Content-Type'));
    }

    /** Y sin sesión igual: el audio es parte del contenido abierto. */
    public function test_el_invitado_oye_sin_sesion_y_sin_escribir_nada(): void
    {
        $src = $this->almacen->publicar($this->clip('CLIP-ABIERTO'));

        $this->assertGuest();
        $this->get($src)->assertOk();

        $this->assertSame(0, \App\Models\PracticeAttempt::count());
        $this->assertSame(0, \App\Models\User::count());
    }

    public function test_un_clip_que_no_existe_es_404_no_un_500(): void
    {
        $this->get('/audio/'.str_repeat('0', 16).'.mp3')->assertNotFound();
    }

    /**
     * La ruta no es un `readfile` de lo que pidan: el nombre tiene una forma
     * cerrada (hash + extensión de la lista) y lo demás no existe. Un path
     * traversal no llega ni a mirar el disco.
     */
    /**
     * Y la MISMA forma se comprueba en `resolver()`, no solo en el `where` de
     * la ruta. Lo destapó una mutación: con FORMA abierta a cualquier cosa, el
     * test de abajo seguía verde porque el router paraba los nombres malos —
     * pero el siguiente consumidor de `resolver()` que no pase por esa ruta
     * habría heredado un path traversal. Dos puertas, y cada una con su test.
     */
    public function test_resolver_rechaza_los_nombres_fuera_de_forma_por_si_mismo(): void
    {
        foreach ([
            // Los niveles EXACTOS hasta ficheros que existen de verdad: un
            // traversal que apunta a un fichero inexistente devuelve null por
            // casualidad (is_file falla) y el oráculo pasa mintiendo — la
            // primera versión subía dos niveles y el .env está a tres.
            '../../../.env',
            '../../../composer.json',
            'clip.php',
            'AABBCCDDEEFF0011.mp3',            // mayúsculas
            str_repeat('0', 15).'.mp3',        // hash corto
            str_repeat('0', 16).'.exe',        // extensión fuera de la lista
        ] as $nombre) {
            $this->assertNull(AlmacenDeAudio::resolver($nombre),
                "resolver() aceptó «{$nombre}».");
        }

        // Control positivo: un nombre EN forma con fichero real sí resuelve.
        $src = $this->almacen->publicar($this->clip('CONTROL-POSITIVO'));
        $this->assertNotNull(AlmacenDeAudio::resolver(basename($src)));
    }

    public function test_un_nombre_fuera_de_forma_es_404(): void
    {
        foreach ([
            '/audio/..%2f..%2f.env',
            '/audio/%2e%2e%2fcomposer.json',
            '/audio/clip.php',
            '/audio/AABBCCDDEEFF0011.mp3',   // mayúsculas: fuera de forma
        ] as $ruta) {
            $this->get($ruta)->assertNotFound();
        }
    }
}
