<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Services\Lesson\Bloques;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ORÁCULOS de la siembra de LECCIONES.
 *
 * Lo que se prueba no es «el comando corre». Son cuatro propiedades que un
 * banco de textos tiene que cumplir para no hacer daño:
 *
 *  1. COBERTURA: el subnivel que el banco declara cubierto lo está ENTERO. Un
 *     alumno que encuentra lección en tres bloques de cuatro aprende que la
 *     plataforma es un borrador.
 *  2. IDEMPOTENCIA: re-sembrar actualiza, no duplica ni huerfana; y el id del
 *     recurso no se mueve, así que un enlace compartido sigue vivo.
 *  3. CO-UBICACIÓN: la lección de un bloque aterriza en la MISMA destreza que
 *     su ejercicio. Es la propiedad que `DestinosDeBloque` existe para
 *     garantizar, y la que se rompería sola el día que alguien duplique la
 *     regla.
 *  4. EL BANCO ES EJECUTABLE: cada bloque del fichero de datos pasa el
 *     validador y cada fórmula se convierte en MathML. Una llave sin cerrar
 *     tiene que ser un test rojo, no una lección en blanco.
 */
class BancoLeccionesTest extends TestCase
{
    use RefreshDatabase;

    /** Los ámbitos que el banco declara cubrir, con TODOS sus bloques. */
    private const ALCANCE = ['LL' => [1, 2, 3, 4, 5], 'M' => [1, 2, 3], 'CN' => [1, 2, 3, 4, 5], 'CS' => [1, 2, 3]];

    private FrameworkVersion $version;

    /** Un grafo con los bloques que el banco espera, replicados por grado. */
    private function sembrarGrafo(array $asignaturas, array $grados, bool $verificadas = true): void
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $this->version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);

        foreach ($grados as $grado => $subnivel) {
            $nodoGrado = CurNode::create([
                'version_id' => $this->version->id, 'node_type' => 'grado',
                'native_code' => $grado, 'title' => ['es' => $grado], 'path' => "ec.{$grado}",
            ]);

            foreach ($asignaturas as $asignatura => $bloques) {
                $nodoAsig = CurNode::create([
                    'version_id' => $this->version->id, 'parent_id' => $nodoGrado->id,
                    'node_type' => 'asignatura', 'native_code' => $asignatura,
                    'title' => ['es' => $asignatura],
                    'path' => "ec.{$grado}.".str_replace('.', '_', strtolower($asignatura)),
                ]);

                foreach ($bloques as $bloque) {
                    $nodoBloque = CurNode::create([
                        'version_id' => $this->version->id, 'parent_id' => $nodoAsig->id,
                        'node_type' => 'bloque', 'title' => ['es' => "Bloque {$bloque}"],
                        'path' => $nodoAsig->path.".b{$bloque}",
                    ]);

                    // Varias destrezas por bloque: la lección cae en la primera.
                    foreach ([1, 2, 10] as $n) {
                        LearningObjective::create([
                            'node_id' => $nodoBloque->id, 'version_id' => $this->version->id,
                            'native_code' => "{$asignatura}.{$subnivel}.{$bloque}.{$n}",
                            'statement' => ['es' => "Destreza {$asignatura}.{$subnivel}.{$bloque}.{$n}"],
                            'is_verified' => $verificadas,
                        ]);
                    }
                }
            }
        }
    }

    /** Básica Superior entera: tres grados que comparten los mismos códigos. */
    private function sembrarBasicaSuperior(): void
    {
        $this->sembrarGrafo(self::ALCANCE, ['g8' => 4, 'g9' => 4, 'g10' => 4]);
    }

    private function banco(): array
    {
        return require database_path('data/lecciones.php');
    }

    // ================= 1. COBERTURA =================

    /**
     * EL SUELO. El banco declara cubrir Básica Superior en las cuatro áreas.
     * Este test no comprueba que «haya lecciones»: comprueba que NO FALTA
     * NINGÚN bloque de esos cuatro ámbitos. Añadir un área al currículo sin
     * añadir sus lecciones no puede pasar en verde.
     */
    public function test_basica_superior_esta_cubierta_bloque_a_bloque(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('lecciones:sembrar')->assertSuccessful();

        foreach (self::ALCANCE as $asignatura => $bloques) {
            foreach ($bloques as $b) {
                $prefijo = "{$asignatura}.4.{$b}";

                $conLeccion = LearningObjective::query()
                    ->where('native_code', 'like', $prefijo.'.%')
                    ->whereHas('resources', fn ($q) => $q->where('kind', Resource::LECTURA))
                    ->count();

                // Tres grados de Básica Superior comparten el bloque: los tres.
                $this->assertSame(3, $conLeccion,
                    "El bloque {$prefijo} se quedó sin lección en algún grado.");
            }
        }
    }

    /**
     * Cada destreza destino tiene su PROPIA lección, no una compartida.
     *
     * Este oráculo nació de un fallo real: el slug se construía con los 8
     * primeros caracteres del uuid del objetivo, y `HasUuids` genera uuid
     * ORDENADOS POR TIEMPO — ese prefijo es una marca de tiempo. Las tres
     * destrezas del mismo bloque, creadas en la misma milésima, compartían
     * prefijo, así que las tres lecciones colapsaban en un solo recurso
     * enganchado a las tres. La cobertura salía verde igualmente: contaba
     * destrezas con lección, y las tres la tenían. Lo que no salía era la
     * firma, porque solo había una fila que firmar.
     */
    public function test_cada_destreza_recibe_su_propia_leccion(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('lecciones:sembrar')->assertSuccessful();

        $entradas = count($this->banco());

        // 16 entradas del banco × 3 grados de Básica Superior.
        $this->assertSame($entradas * 3, Resource::where('kind', Resource::LECTURA)->count(),
            'Dos destrezas distintas acabaron compartiendo el mismo recurso.');

        // Y ninguna lección cuelga de más de una destreza.
        Resource::where('kind', Resource::LECTURA)->withCount('objectives')->get()
            ->each(fn (Resource $r) => $this->assertSame(1, $r->objectives_count,
                "La lección {$r->slug} está enganchada a varias destrezas."));
    }

    /** Y el banco no promete de más: cada entrada declara un bloque de esos ámbitos. */
    public function test_el_banco_declara_exactamente_los_bloques_de_basica_superior(): void
    {
        $declarados = collect($this->banco())->pluck('bloque')->sort()->values()->all();

        $esperados = collect(self::ALCANCE)
            ->flatMap(fn (array $bs, string $a) => array_map(fn ($b) => "{$a}.4.{$b}", $bs))
            ->sort()->values()->all();

        $this->assertSame($esperados, $declarados,
            'El banco y el alcance declarado en su cabecera dejaron de coincidir.');
    }

    /** Un ámbito sin lecciones NO se disimula: el informe lo cuenta y lo lista. */
    public function test_el_informe_no_esconde_las_destrezas_sin_leccion(): void
    {
        $this->sembrarBasicaSuperior();

        // `storage/app` es disco de verdad y no lo limpia `RefreshDatabase`:
        // sin este borrado, el test daba por bueno el fichero que había dejado
        // una ejecución anterior y pasaba en verde aunque el comando ya no lo
        // escribiera. Lo cazó una mutación, no una lectura.
        $ruta = storage_path('app/lecciones-sin-cobertura.txt');
        @unlink($ruta);

        $this->artisan('lecciones:sembrar')
            ->expectsOutputToContain('destreza(s) SIN lección.')
            ->assertSuccessful();

        $this->assertFileExists($ruta);

        // 16 bloques × 3 grados = 48 destrezas con lección; el resto, listadas.
        $sinLeccion = array_filter(explode(PHP_EOL, file_get_contents($ruta)));
        $total = LearningObjective::count();

        $this->assertCount($total - 48, $sinLeccion);
        $this->assertContains('M.4.1.2', $sinLeccion, 'La .2 no tiene lección y tiene que constar.');
    }

    // ================= 2. IDEMPOTENCIA =================

    public function test_sembrar_dos_veces_no_duplica_ni_cambia_los_ids(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('lecciones:sembrar')->assertSuccessful();

        $antes = Resource::where('kind', Resource::LECTURA)->pluck('id', 'slug')->sort()->all();
        $versionesAntes = ResourceVersion::count();

        $this->artisan('lecciones:sembrar')
            ->expectsOutputToContain('0 lección(es) creadas')
            ->assertSuccessful();

        $this->assertSame($antes, Resource::where('kind', Resource::LECTURA)->pluck('id', 'slug')->sort()->all(),
            'Re-sembrar duplicó recursos o les cambió el id: un enlace compartido moriría.');
        $this->assertSame($versionesAntes, ResourceVersion::count(), 'Se quedó una versión huérfana.');
    }

    /** Editar el texto ACTUALIZA la lección; no crea otra al lado. */
    public function test_editar_el_texto_actualiza_en_su_sitio(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('lecciones:sembrar')->assertSuccessful();

        $version = ResourceVersion::query()
            ->whereHas('resource', fn ($q) => $q->where('kind', Resource::LECTURA))
            ->first();
        $idVersion = $version->id;
        $cuantas = Resource::where('kind', Resource::LECTURA)->count();

        // Simulamos una edición del banco vaciando lo que hay en la base.
        $version->update(['config' => ['bloque' => 'X', 'bloques' => []]]);

        $this->artisan('lecciones:sembrar')->assertSuccessful();

        $version->refresh();
        $this->assertSame($idVersion, $version->id, 'La versión cambió de identidad.');
        $this->assertNotEmpty($version->config['bloques'], 'La edición no se propagó.');
        $this->assertSame($cuantas, Resource::where('kind', Resource::LECTURA)->count(),
            'La edición creó una lección nueva en vez de actualizar la de siempre.');
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $this->sembrarBasicaSuperior();

        $this->artisan('lecciones:sembrar --dry-run')
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();

        $this->assertSame(0, Resource::where('kind', Resource::LECTURA)->count());
    }

    // ================= 3. CO-UBICACIÓN =================

    /**
     * La propiedad que justifica que `DestinosDeBloque` sea un servicio y no
     * dos copias: leer y practicar el mismo bloque caen en la MISMA destreza.
     * Si divergen, el alumno lee en M.4.1.1 y practica en M.4.1.10, y nadie se
     * entera hasta que un profesor lo ve.
     */
    public function test_la_leccion_y_el_ejercicio_del_bloque_caen_en_la_misma_destreza(): void
    {
        $this->sembrarBasicaSuperior();
        $this->anclarAreasDelBancoDePractica($this->version);
        $this->artisan('practica:sembrar')->assertSuccessful();
        $this->artisan('lecciones:sembrar')->assertSuccessful();

        $conLeccion = LearningObjective::query()
            ->whereHas('resources', fn ($q) => $q->where('kind', Resource::LECTURA))
            ->pluck('native_code')->unique()->sort()->values();

        $conItem = LearningObjective::query()
            ->whereHas('todosLosPracticeItems')
            ->pluck('native_code')->unique()->all();

        // Todo lo que tiene lección tiene también su ejercicio, en la misma
        // destreza. (Al revés no: el banco de práctica cubre más bloques.)
        $this->assertNotEmpty($conLeccion);
        foreach ($conLeccion as $codigo) {
            $this->assertContains($codigo, $conItem,
                "{$codigo} tiene lección pero su ejercicio aterrizó en otra destreza.");
        }
    }

    /** Y aterriza en la de código más BAJO del bloque, en orden curricular. */
    public function test_la_leccion_aterriza_en_la_primera_destreza_del_bloque(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('lecciones:sembrar')->assertSuccessful();

        $conLeccion = LearningObjective::query()
            ->whereHas('resources', fn ($q) => $q->where('kind', Resource::LECTURA))
            ->where('native_code', 'like', 'M.4.1.%')
            ->pluck('native_code')->unique()->values()->all();

        // La 1 antes que la 10, aunque como cadena '10' vaya antes que '2'.
        $this->assertSame(['M.4.1.1'], $conLeccion);
    }

    // ================= 4. EL BANCO ES EJECUTABLE =================

    /**
     * Cada bloque del fichero de datos pasa el validador y cada fórmula se
     * convierte en MathML. Una llave sin cerrar o un comando inventado
     * revientan AQUÍ, no en la pantalla de un alumno.
     */
    public function test_todo_el_banco_pasa_el_validador(): void
    {
        $validador = new Bloques;
        $formulas = 0;

        foreach ($this->banco() as $entrada) {
            $bloques = $validador->validar($entrada['bloques'], $entrada['bloque']);

            foreach ($bloques as $bloque) {
                if ($bloque['tipo'] === 'formula') {
                    $this->assertSame('math', $bloque['mathml']['e']);
                    $formulas++;
                }
            }
        }

        $this->assertGreaterThan(0, $formulas, 'Ninguna lección lleva fórmula: algo se perdió.');
    }

    /** Y cada lección ENSEÑA: ejemplo resuelto y error típico, no solo párrafos. */
    public function test_cada_leccion_trae_ejemplo_resuelto_y_error_tipico(): void
    {
        foreach ($this->banco() as $entrada) {
            $tipos = array_column($entrada['bloques'], 'tipo');
            $variantes = array_column($entrada['bloques'], 'variante');

            $this->assertContains('ejemplo', $tipos,
                "{$entrada['slug']} no tiene ejemplo resuelto: enuncia, no enseña.");
            $this->assertContains('error-tipico', $variantes,
                "{$entrada['slug']} no avisa de ningún error típico.");
        }
    }

    // ================= La puerta =================

    public function test_las_lecciones_nacen_sin_firmar_y_no_se_sirven(): void
    {
        $this->sembrarBasicaSuperior();

        $this->artisan('lecciones:sembrar')
            ->expectsOutputToContain('pendiente(s) de firma')
            ->assertSuccessful();

        $this->assertSame(0, Resource::published()->where('kind', Resource::LECTURA)->count(),
            'Una lección sin firmar llegó al alumno.');
    }

    public function test_firmar_un_bloque_abre_solo_ese_bloque(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('lecciones:sembrar')->assertSuccessful();

        $this->artisan('lecciones:firmar --bloque=M.4.1')->assertSuccessful();

        $servidas = Resource::published()->where('kind', Resource::LECTURA)->pluck('slug');

        // Los tres grados del bloque M.4.1, y nada más.
        $this->assertCount(3, $servidas);
        foreach ($servidas as $slug) {
            $this->assertStringStartsWith('leccion-ecuaciones-primer-grado-', $slug);
        }
    }

    public function test_firmar_todo_exige_decir_que_se_ha_revisado(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('lecciones:sembrar')->assertSuccessful();

        $this->artisan('lecciones:firmar --todo')->assertFailed();

        $this->assertSame(0, Resource::published()->where('kind', Resource::LECTURA)->count());
    }

    /** Sin destrezas verificadas no se siembra nada: no se inventa dónde. */
    public function test_no_siembra_sobre_destrezas_sin_verificar(): void
    {
        $this->sembrarGrafo(['M' => [1, 2, 3]], ['g8' => 4], verificadas: false);

        $this->artisan('lecciones:sembrar')
            ->expectsOutputToContain('sin verificar')
            ->assertSuccessful();

        $this->assertSame(0, Resource::where('kind', Resource::LECTURA)->count());

        $this->artisan('lecciones:sembrar --incluir-no-verificadas')->assertSuccessful();
        $this->assertSame(3, Resource::where('kind', Resource::LECTURA)->count());
    }

    /** El ejercicio del bloque no se toca al sembrar lecciones. */
    public function test_sembrar_lecciones_no_altera_el_banco_de_practica(): void
    {
        $this->sembrarBasicaSuperior();
        $this->anclarAreasDelBancoDePractica($this->version);
        $this->artisan('practica:sembrar')->assertSuccessful();

        $antes = PracticeItem::orderBy('id')->pluck('id')->all();
        $this->artisan('lecciones:sembrar')->assertSuccessful();

        $this->assertSame($antes, PracticeItem::orderBy('id')->pluck('id')->all());
    }
}
