<?php

namespace Tests\Feature;

use App\Console\Commands\SeedPracticeBank;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\User;
use App\Services\Practice\MathExpression;
use App\Services\Practice\PracticeEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ORÁCULOS de la siembra del banco.
 *
 * Ojo con la relación que se usa para medir: `practiceItems` solo devuelve lo
 * FIRMADO, y lo que siembra este comando nace sin firmar a propósito. La
 * cobertura de aquí es la del banco —qué se ha sembrado— así que se mide con
 * `todosLosPracticeItems`. Que lo sembrado llegue o no al alumno es otra
 * pregunta, y vive en DominioYFirmaTest.
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

    /** El subnivel de un prefijo: su primer segmento numérico. */
    private function subnivelDe(string $prefijo): ?string
    {
        foreach (explode('.', $prefijo) as $parte) {
            if (is_numeric($parte)) {
                return $parte;
            }
        }

        return null;
    }

    /**
     * Y el mismo suelo para TODOS los ámbitos que el banco declara cubrir, no
     * solo Básica Superior: si un bloque del fichero se queda sin ítem donde su
     * grafo existe, es un hueco de cobertura, no un detalle.
     */
    public function test_todas_las_ramas_del_banco_estan_cubiertas_donde_existe_el_grafo(): void
    {
        $this->sembrarGrafo(
            ['CN.F' => [1, 2, 3, 4, 5], 'CS.H' => [1, 2, 3], 'CS.FL' => [1, 2, 3]],
            ['g11' => 5],
        );
        $this->artisan('practica:sembrar')->assertSuccessful();

        $ramas = collect(require database_path('data/banco-practica.php'))
            ->map(fn (array $e) => $e[0])
            ->filter(fn (string $p) => str_starts_with($p, 'CN.F.')
                || str_starts_with($p, 'CS.H.') || str_starts_with($p, 'CS.FL.'));

        $this->assertCount(11, $ramas, 'Cambió el número de bloques de rama del banco.');

        foreach ($ramas as $bloque) {
            $this->assertGreaterThan(0,
                LearningObjective::where('native_code', 'like', $bloque.'.%')
                    ->whereHas('todosLosPracticeItems')->count(),
                "El bloque de rama {$bloque} se quedó sin ítem.");
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
            ->whereHas('todosLosPracticeItems')
            ->count();

        $this->assertSame(3, $conItem, 'El bloque replicado no llegó a los tres grados.');
    }

    /** El ítem cae en la destreza de código más BAJO del bloque, no en cualquiera. */
    public function test_el_item_aterriza_en_la_primera_destreza_del_bloque(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        $conItem = LearningObjective::query()
            ->whereHas('todosLosPracticeItems')
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

    /**
     * IDEMPOTENCIA DE VERDAD: con el fichero EDITADO entre siembras.
     *
     * El test de arriba resiembra el banco idéntico, que es el caso en el que
     * cualquier implementación pasa. El caso real —alguien añade una pregunta
     * al principio del fichero— rompía la anterior: el `seq` salía del índice
     * del array, así que insertar una entrada desplazaba el de todas las
     * siguientes y cada una dejaba un zombi con su contenido viejo.
     */
    public function test_editar_el_banco_entre_siembras_no_duplica(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        $antes = PracticeItem::where('seq', SeedPracticeBank::BASE_SEQ)->count();
        $this->assertGreaterThan(0, $antes);

        // El fichero real no se toca: se simula la edición sembrando desde un
        // banco con una entrada DELANTE, que es lo que desplazaba los índices.
        $this->sembrarDesde(array_merge(
            [['M.4.2', 'choice', '¿Cuánto mide un ángulo recto?',
                ['a' => '90°', 'b' => '45°', 'c' => '180°'], 'a']],
            [['M.4.1', 'numeric', 'Resuelve x/{a} = {k}. ¿Cuánto vale x?',
                ['a' => ['min' => 2, 'max' => 9, 'step' => 1], 'k' => ['min' => 2, 'max' => 12, 'step' => 1]],
                'a * k', 0.001, 'abs', null]],
        ));

        // Una entrada por destreza y nada más: ni zombis ni duplicados.
        foreach (LearningObjective::whereHas('todosLosPracticeItems')->get() as $objetivo) {
            $delBanco = $objetivo->practiceItems()->where('seq', SeedPracticeBank::BASE_SEQ)->count();
            $this->assertLessThanOrEqual(1, $delBanco,
                "{$objetivo->native_code} acabó con {$delBanco} ítems del banco.");
        }
    }

    /** Borrar una entrada deja un resto, y el comando lo DICE (y lo poda si se pide). */
    public function test_los_items_de_un_bloque_retirado_se_avisan_y_se_pueden_podar(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();
        $total = PracticeItem::where('seq', SeedPracticeBank::BASE_SEQ)->count();

        // Se resiembra desde un banco con UNA sola entrada: el resto sobra.
        $this->sembrarDesde([['M.4.1', 'numeric', 'Suma {a} + {b}',
            ['a' => ['const' => 2], 'b' => ['const' => 3]], 'a + b', 0.01, 'abs', null]],
            ['--podar' => true]);

        $quedan = PracticeItem::where('seq', SeedPracticeBank::BASE_SEQ)->count();
        $this->assertLessThan($total, $quedan, 'La poda no retiró nada.');
        $this->assertGreaterThan(0, $quedan, 'La poda se llevó por delante lo que sí está en el banco.');
    }

    /** Y un ítem con intentos de alumnos NO se poda: eso lo decide una persona. */
    public function test_la_poda_respeta_los_items_con_intentos(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        $item = PracticeItem::where('seq', SeedPracticeBank::BASE_SEQ)->firstOrFail();
        PracticeAttempt::create([
            'item_id' => $item->id, 'user_id' => User::factory()->create()->id,
            'attempt_no' => 1, 'seed' => str_repeat('a', 64), 'params' => [],
            'answer_key' => 'a', 'is_correct' => true,
        ]);

        $this->sembrarDesde([['ZZ.9.9', 'numeric', 'Nada {a}',
            ['a' => ['const' => 1]], 'a', 0.01, 'abs', null]], ['--podar' => true]);

        $this->assertNotNull($item->fresh(), 'La poda borró un ítem con intentos colgando.');
    }

    /** Siembra desde un banco a medida, sin tocar el fichero real. */
    private function sembrarDesde(array $banco, array $opciones = []): void
    {
        $ruta = database_path('data/banco-practica.php');
        $original = file_get_contents($ruta);

        try {
            file_put_contents($ruta, '<?php return '.var_export($banco, true).';');
            $this->artisan('practica:sembrar', $opciones)->assertSuccessful();
        } finally {
            file_put_contents($ruta, $original);
        }
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
     * CONVENCIÓN DE AUTORÍA: en el fichero de datos la correcta se escribe
     * SIEMPRE la primera, para que un docente revise de un vistazo.
     *
     * Esta convención es segura SOLO porque el sembrador reparte las claves
     * permutadas por bloque (`conClavesRepartidas`). El test de abajo es el que
     * lo garantiza — y es el que faltaba: la versión anterior de este docblock
     * afirmaba que la convención «no filtra nada porque lo que se sirve son
     * posiciones», premisa que dejó de ser cierta cuando el contrato pasó a
     * servir la clave. Resultado: las 60 preguntas del banco nacían con
     * `answer_key = 'a'` y la respuesta viajaba en el `value` de cada radio.
     */
    public function test_la_opcion_correcta_se_escribe_siempre_primero_en_el_fichero(): void
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

    /**
     * EL ORÁCULO QUE FALTABA. La clave que se PERSISTE —y por tanto la que
     * viaja al cliente en cada opción— no puede heredar la posición de autoría.
     * Si lo hiciera, `answer_key` sería `'a'` en todo el banco y bastaría mirar
     * el `value` de los radios para acertar siempre, sin leer la pregunta.
     */
    public function test_la_clave_correcta_no_es_siempre_la_misma_letra(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        $claves = PracticeItem::where('kind', PracticeItem::CHOICE)
            ->pluck('answer_key');

        $this->assertGreaterThan(5, $claves->count(), 'Pocos ítems para medir la distribución.');

        $distintas = $claves->unique()->values()->sort()->all();
        $this->assertGreaterThanOrEqual(3, count($distintas),
            'La correcta cae casi siempre en la misma clave: se lee en el DOM sin resolver nada. '.
            'Repartidas: '.json_encode($claves->countBy()));

        // Y ninguna letra acapara: con 4 opciones, ninguna debería pasar del 60 %.
        foreach ($claves->countBy() as $letra => $veces) {
            $this->assertLessThan($claves->count() * 0.6, $veces,
                "La clave '{$letra}' es la correcta en {$veces} de {$claves->count()} ítems.");
        }
    }

    /**
     * Y la permutación tiene que ser ESTABLE: si cambiara al re-sembrar, los
     * `answer_key` ya registrados en `practice_attempts` pasarían a significar
     * otra opción y el historial del alumno quedaría reescrito en silencio.
     */
    public function test_la_clave_repartida_no_cambia_al_resembrar(): void
    {
        $this->sembrarBasicaSuperior();

        $this->artisan('practica:sembrar')->assertSuccessful();
        $antes = PracticeItem::where('kind', PracticeItem::CHOICE)
            ->orderBy('id')->pluck('answer_key', 'id');

        $this->artisan('practica:sembrar')->assertSuccessful();
        $despues = PracticeItem::where('kind', PracticeItem::CHOICE)
            ->orderBy('id')->pluck('answer_key', 'id');

        $this->assertEquals($antes, $despues);
    }

    /**
     * El texto de la opción correcta tampoco puede quedar identificable por su
     * sitio en `options`: si fuera siempre la primera del array, mirar el JSON
     * bastaría igualmente.
     */
    public function test_la_correcta_no_ocupa_siempre_la_misma_posicion_en_options(): void
    {
        $this->sembrarBasicaSuperior();
        $this->artisan('practica:sembrar')->assertSuccessful();

        $posiciones = PracticeItem::where('kind', PracticeItem::CHOICE)->get()
            ->map(fn (PracticeItem $i) => array_search(
                $i->answer_key, array_column($i->options, 'key'), true,
            ))
            ->countBy();

        $this->assertGreaterThanOrEqual(2, $posiciones->count(),
            'La correcta ocupa siempre la misma posición dentro de `options`.');
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
        //
        // El filtro mira el SUBNIVEL, que es el primer segmento numérico del
        // prefijo — no `explode(...)[1]`, que en las ramas (`CN.F.5.1`) es la
        // letra de la rama y excluía en silencio a Física, Química, Biología,
        // Historia, Ciudadanía y Filosofía enteras (auditoría).
        $delBanco = collect(require database_path('data/banco-practica.php'))
            ->map(fn (array $e) => $e[0])
            ->filter(fn (string $p) => $this->subnivelDe($p) === '4');

        $this->assertNotEmpty($delBanco, 'El banco no declara ningún bloque: el test no probaría nada.');

        foreach ($delBanco as $bloque) {
            $conItem = LearningObjective::query()
                ->where('native_code', 'like', $bloque.'.%')
                ->whereHas('todosLosPracticeItems')
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
            ->whereDoesntHave('todosLosPracticeItems')
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
            // Firmado: este fixture prueba el MOTOR, y un ítem sin
            // revisar no llega al motor (ver DominioYFirmaTest).
            'reviewed_at' => now(),
        ]);

        $this->artisan('practica:sembrar')->assertSuccessful();

        $this->assertSame('Ítem anterior {a}', $viejo->fresh()->statement['es']);
        $this->assertSame(PracticeItem::NUMERIC, $viejo->fresh()->kind);
    }
}
