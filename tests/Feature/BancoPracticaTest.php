<?php

namespace Tests\Feature;

use App\Console\Commands\SeedPracticeBank;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Services\Practice\MathExpression;
use App\Services\Practice\PracticeEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ORÁCULOS de la siembra del banco.
 *
 * Lo que se prueba aquí no es «el comando corre», sino tres cosas que un banco
 * de ejercicios de verdad tiene que cumplir: que cada asignatura × subnivel
 * queda cubierta, que re-sembrar no duplica ni revienta con los códigos que se
 * repiten, y que TODOS los ítems del fichero de datos son ejecutables —una
 * expresión mal escrita o una clave correcta que no está entre las opciones
 * serían un ejercicio roto delante de un alumno, no un test rojo.
 */
class BancoPracticaTest extends TestCase
{
    use RefreshDatabase;

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

                    // Varias destrezas por bloque: el ítem cae en la primera.
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
    private function sembrarBasicaSuperior(bool $verificadas = true): void
    {
        $this->sembrarGrafo(
            ['LL' => [1, 2, 3, 4, 5], 'M' => [1, 2, 3], 'CN' => [1, 2, 3, 4, 5], 'CS' => [1, 2, 3]],
            ['g8' => 4, 'g9' => 4, 'g10' => 4],
        );
    }

    // ================= ORÁCULO 6 — cobertura real =================

    public function test_cada_asignatura_por_subnivel_cubierto_tiene_al_menos_tres_items(): void
    {
        $this->sembrarBasicaSuperior();

        $this->artisan('practica:sembrar')->assertSuccessful();

        foreach (['LL', 'M', 'CN', 'CS'] as $asignatura) {
            $items = PracticeItem::query()
                ->whereHas('objective', fn ($q) => $q->where('native_code', 'like', $asignatura.'.4.%'))
                ->count();

            // 3 bloques cubiertos × 3 grados de Básica Superior = 9.
            $this->assertGreaterThanOrEqual(3, $items,
                "{$asignatura} · Básica Superior se quedó con {$items} ítems.");
        }
    }

    public function test_lengua_y_sociales_reciben_items_de_opcion_multiple(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        foreach (['LL', 'CS'] as $asignatura) {
            $choice = PracticeItem::query()
                ->where('kind', PracticeItem::CHOICE)
                ->whereHas('objective', fn ($q) => $q->where('native_code', 'like', $asignatura.'.%'))
                ->count();

            $this->assertGreaterThanOrEqual(3, $choice,
                "{$asignatura} no tiene ítems de opción múltiple, que es justo lo que le faltaba.");
        }
    }

    /**
     * El bloque replicado por grado es COBERTURA, no un error. Antes tumbaba la
     * siembra entera con «código ambiguo»; ahora los tres grados de Básica
     * Superior reciben su ítem.
     */
    public function test_un_bloque_replicado_en_tres_grados_siembra_los_tres(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        $conItem = LearningObjective::query()
            ->where('native_code', 'M.4.1.1')
            ->whereHas('practiceItems')
            ->count();

        $this->assertSame(3, $conItem, 'El bloque replicado no llegó a los tres grados.');
    }

    /** El ítem cae en la destreza de código más BAJO del bloque, no en cualquiera. */
    public function test_el_item_aterriza_en_la_primera_destreza_del_bloque(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        $conItem = LearningObjective::query()
            ->whereHas('practiceItems')
            ->where('native_code', 'like', 'M.4.1.%')
            ->pluck('native_code')->unique()->values()->all();

        // Orden CURRICULAR: la 1 va antes que la 10, aunque como cadena no.
        $this->assertSame(['M.4.1.1'], $conItem);
    }

    // ================= ORÁCULO 7 — idempotencia =================

    public function test_sembrar_dos_veces_no_duplica_ni_revienta(): void
    {
        $this->sembrarBasicaSuperior();

        $this->artisan('practica:sembrar')->assertSuccessful();
        $primera = PracticeItem::count();
        $ids = PracticeItem::orderBy('id')->pluck('id');
        $this->assertGreaterThan(0, $primera);

        $this->artisan('practica:sembrar')->assertSuccessful();

        $this->assertSame($primera, PracticeItem::count(), 'La segunda siembra duplicó ítems.');
        // Y no les cambió el id: los intentos ya registrados siguen apuntando
        // al mismo sitio.
        $this->assertEquals($ids, PracticeItem::orderBy('id')->pluck('id'));
    }

    /** Un banco sin dónde aterrizar informa el hueco y termina bien. */
    public function test_sin_destrezas_no_revienta_y_dice_que_falta(): void
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);

        $this->artisan('practica:sembrar')
            ->expectsOutputToContain('sin destreza donde aterrizar')
            ->assertSuccessful();

        $this->assertSame(0, PracticeItem::count());
    }

    /** Sobre marcadores sin verificar NO se siembra salvo que se pida. */
    public function test_no_siembra_sobre_destrezas_sin_verificar(): void
    {
        $this->sembrarGrafo(['LL' => [1, 3, 4]], ['g8' => 4], verificadas: false);

        $this->artisan('practica:sembrar')->assertSuccessful();
        $this->assertSame(0, PracticeItem::count());

        $this->artisan('practica:sembrar', ['--incluir-no-verificadas' => true])->assertSuccessful();
        $this->assertGreaterThan(0, PracticeItem::count());
    }

    public function test_un_marco_inexistente_falla_en_vez_de_no_hacer_nada(): void
    {
        $this->artisan('practica:sembrar', ['--marco' => 'NO-EXISTE'])->assertFailed();
    }

    // ================= El banco, entrada por entrada =================

    /**
     * Cada ítem del fichero de datos tiene que ser EJECUTABLE. Un `solution_expr`
     * con una función fuera de la lista blanca, o un parámetro sin rango, no da
     * un test rojo por su cuenta: da un ejercicio roto delante de un alumno.
     */
    public function test_todos_los_items_numericos_del_banco_se_evaluan(): void
    {
        $engine = new PracticeEngine;
        $banco = require database_path('data/banco-practica.php');

        $numericos = 0;
        foreach ($banco as $entrada) {
            if ($entrada[1] !== PracticeItem::NUMERIC) {
                continue;
            }
            [$prefijo, , $enunciado, $params, $expr] = $entrada;
            $numericos++;

            // Tres semillas distintas: los rangos tienen que dar resultados
            // finitos en todo el recorrido, no solo en el caso bonito.
            foreach (['s1', 's2', 's3'] as $semilla) {
                $valores = $engine->sampleParams($params, $semilla);
                $resultado = MathExpression::evaluate($expr, $valores);

                $this->assertIsFloat($resultado, "{$prefijo}: la expresión no evalúa a número");
                $this->assertTrue(is_finite($resultado),
                    "{$prefijo}: la expresión da un valor no finito con ".json_encode($valores));
            }

            // Toda variable del enunciado tiene su rango, y al revés.
            preg_match_all('/\{(\w+)\}/', $enunciado, $m);
            $this->assertEqualsCanonicalizing(array_unique($m[1]), array_keys($params),
                "{$prefijo}: el enunciado y los parámetros no cuadran");
        }

        $this->assertGreaterThanOrEqual(12, $numericos, 'El banco numérico se quedó corto.');
    }

    /**
     * En un ítem de opción múltiple hay dos formas silenciosas de romperlo: que
     * la clave correcta no esté entre las opciones (todo mal para siempre) o
     * que haya opciones repetidas (dos respuestas buenas, una marcada mala).
     */
    public function test_todos_los_items_de_opcion_multiple_del_banco_son_correctos(): void
    {
        $banco = require database_path('data/banco-practica.php');

        $choices = 0;
        foreach ($banco as $entrada) {
            if ($entrada[1] !== PracticeItem::CHOICE) {
                continue;
            }
            [$prefijo, , $enunciado, $opciones, $correcta] = $entrada;
            $choices++;

            $this->assertGreaterThanOrEqual(3, count($opciones), "{$prefijo}: menos de 3 opciones");
            $this->assertLessThanOrEqual(4, count($opciones), "{$prefijo}: más de 4 opciones");
            $this->assertArrayHasKey($correcta, $opciones,
                "{$prefijo}: la clave correcta '{$correcta}' no está entre las opciones");
            $this->assertSame(count($opciones), count(array_unique($opciones)),
                "{$prefijo}: hay opciones repetidas, así que habría dos respuestas buenas");

            // Nada de «todas las anteriores» ni «ninguna»: distractores de verdad.
            foreach ($opciones as $texto) {
                $this->assertDoesNotMatchRegularExpression(
                    '/todas las anteriores|ninguna de las anteriores/i', $texto,
                    "{$prefijo}: distractor de relleno",
                );
                $this->assertNotSame('', trim($texto), "{$prefijo}: opción vacía");
            }

            // El enunciado de un choice no lleva variables que nadie sustituye.
            $this->assertStringNotContainsString('{', $enunciado, "{$prefijo}: variable sin instanciar");
        }

        $this->assertGreaterThanOrEqual(20, $choices, 'El banco de opción múltiple se quedó corto.');
    }

    /**
     * CONVENCIÓN DE AUTORÍA: la opción correcta se escribe SIEMPRE la primera.
     * Es lo que hace el fichero revisable de un vistazo —un docente lee la
     * primera opción y ya sabe cuál se afirma que es la buena— y no filtra nada
     * porque lo que se sirve son posiciones barajadas, nunca estas claves.
     * Si alguien rompe la convención, este test lo dice.
     */
    public function test_la_opcion_correcta_se_escribe_siempre_primero(): void
    {
        foreach (require database_path('data/banco-practica.php') as $entrada) {
            if ($entrada[1] !== PracticeItem::CHOICE) {
                continue;
            }
            [$prefijo, , , $opciones, $correcta] = $entrada;

            $this->assertSame(array_key_first($opciones), $correcta,
                "{$prefijo}: la correcta no es la primera; el fichero deja de ser revisable de un vistazo");
        }
    }

    /** Lo sembrado nace marcado como pendiente de que un docente lo firme. */
    public function test_los_items_sembrados_declaran_su_procedencia(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        $item = PracticeItem::where('seq', '>=', SeedPracticeBank::BASE_SEQ)->firstOrFail();

        $this->assertSame('curado', $item->origen);
        $this->assertSame('bloque', $item->attrs['revision']['alineado_a']);
        $this->assertNotEmpty($item->attrs['revision']['bloque']);
    }

    /**
     * INVARIANTE DEL ESQUEMA: cada ítem puebla la vía de SU tipo y deja la otra
     * vacía. Rellenar con 0.0 o con cadena vacía haría que un bug en la
     * bifurcación por `kind` pasara inadvertido en vez de reventar.
     */
    public function test_cada_item_puebla_solo_la_via_de_su_tipo(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        foreach (PracticeItem::where('seq', '>=', SeedPracticeBank::BASE_SEQ)->get() as $item) {
            if ($item->esChoice()) {
                $this->assertNull($item->solution_expr, "{$item->id}: choice con expresión");
                $this->assertNotEmpty($item->options);
                $this->assertNotNull($item->answer_key);

                continue;
            }

            $this->assertNotNull($item->solution_expr);
            $this->assertNull($item->options, "{$item->id}: numérico con opciones");
            $this->assertNull($item->answer_key, "{$item->id}: numérico con clave correcta");
        }
    }

    /**
     * La clave correcta NO puede acabar en `params` ni en `attrs`: los dos se
     * serializan al cliente en `next()`. Vive en su columna y solo ahí.
     */
    public function test_la_clave_correcta_no_se_cuela_en_params_ni_en_attrs(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        foreach (PracticeItem::where('kind', PracticeItem::CHOICE)->get() as $item) {
            $serializable = json_encode([$item->params, $item->attrs, $item->options], JSON_UNESCAPED_UNICODE);

            $this->assertStringNotContainsString('answer_key', $serializable);
            $this->assertStringNotContainsString('correcta', $serializable);

            // Y el texto de la opción buena no está marcado de ninguna forma:
            // aparece una vez, dentro de `options`, como los distractores.
            $buena = collect($item->options)->firstWhere('key', $item->answer_key);
            $this->assertSame(1, substr_count($serializable, $buena['text']['es']));
        }
    }

    // ============ ORÁCULO 4 (revisado) — cobertura medida en DESTREZAS ============

    /**
     * El suelo exigible: en cada asignatura × subnivel que el banco cubre,
     * NINGÚN bloque se queda sin ítem. Medirlo por ámbito («3 por asignatura ×
     * subnivel») pasaría en verde con ~60 ítems sobre 4.717 destrezas, o sea un
     * 1 % disfrazado de cobertura.
     */
    public function test_ningun_bloque_de_un_ambito_cubierto_se_queda_sin_item(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        // Los bloques que el banco declara cubrir en Básica Superior.
        $delBanco = collect(require database_path('data/banco-practica.php'))
            ->map(fn (array $e) => $e[0])
            ->filter(fn (string $p) => str_ends_with(explode('.', $p)[1] ?? '', '4'));

        foreach ($delBanco as $bloque) {
            $conItem = LearningObjective::query()
                ->where('native_code', 'like', $bloque.'.%')
                ->whereHas('practiceItems')
                ->count();

            $this->assertGreaterThan(0, $conItem, "El bloque {$bloque} se quedó sin ningún ítem.");
        }
    }

    /** Y el informe dice el porcentaje real de destrezas cubiertas, no un ámbito. */
    public function test_el_informe_mide_en_destrezas_y_lista_las_que_faltan(): void
    {
        $this->sembrarBasicaSuperior();

        $this->artisan('practica:sembrar')
            ->expectsOutputToContain('con al menos un ítem')
            ->expectsOutputToContain('SIN ningún ítem')
            ->assertSuccessful();

        // El grafo de prueba tiene 3 destrezas por bloque y el banco cubre una:
        // el informe tiene que reconocer que faltan las otras dos, no redondear.
        $sinItem = LearningObjective::query()
            ->where('is_verified', true)
            ->whereDoesntHave('practiceItems')
            ->count();
        $this->assertGreaterThan(0, $sinItem);

        $ruta = storage_path('app/practica-sin-cobertura.txt');
        $this->assertFileExists($ruta);
        $this->assertSame($sinItem, substr_count(file_get_contents($ruta), PHP_EOL));
    }

    /** Y no pisa los 17 ítems de física que ya estaban. */
    public function test_no_pisa_los_items_que_ya_existian(): void
    {
        $this->sembrarBasicaSuperior();

        $objetivo = LearningObjective::where('native_code', 'M.4.1.1')->first();
        $viejo = PracticeItem::create([
            'objective_id' => $objetivo->id, 'seq' => 0,
            'statement' => ['es' => 'Ítem anterior {a}'],
            'params' => ['a' => ['const' => 1]],
            'solution_expr' => 'a', 'tolerance' => 0.01, 'tolerance_kind' => 'abs',
        ]);

        $this->artisan('practica:sembrar')->assertSuccessful();

        $this->assertSame('Ítem anterior {a}', $viejo->fresh()->statement['es']);
        $this->assertSame(PracticeItem::NUMERIC, $viejo->fresh()->kind);
    }
}
