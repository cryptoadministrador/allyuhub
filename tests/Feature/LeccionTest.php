<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\User;
use App\Services\Lesson\Bloques;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * LA LECCIÓN. Hasta ahora la plataforma sabía evaluar y no sabía enseñar: un
 * alumno que abría una destreza y no sabía el tema no tenía nada que leer.
 *
 * Cuatro propiedades que no se negocian:
 *
 *  1. El contenido son BLOQUES TIPADOS, no HTML. Un texto con `<script>` se
 *     pinta como texto — y eso se comprueba sobre el HTML final, no sobre la
 *     intención.
 *  2. La lección pasa por la MISMA puerta que los ítems: sin `reviewed_at` no
 *     la ve nadie.
 *  3. La regla de oro del contenido abierto sigue intacta: un invitado lee la
 *     lección entera sin sesión y sin escribir una fila.
 *  4. Las fórmulas salen renderizadas del servidor. El bundle no crece.
 */
class LeccionTest extends TestCase
{
    use RefreshDatabase;

    private LearningObjective $objective;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $nodo = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g8', 'title' => ['es' => '8.º EGB'], 'path' => 'egb.sup.g8',
        ]);
        $this->objective = LearningObjective::create([
            'node_id' => $nodo->id, 'version_id' => $version->id,
            'native_code' => 'M.4.1.1', 'statement' => ['es' => 'Resolver ecuaciones de primer grado.'],
            'is_verified' => true,
        ]);
    }

    /**
     * Una lección anclada a la destreza. `$firmada` a null la deja pendiente de
     * revisión, que es como nace todo lo que siembra el comando.
     */
    private function leccion(array $bloques, ?string $firmada = 'ahora'): Resource
    {
        $recurso = Resource::create([
            'slug' => 'leccion-'.Str::random(8), 'kind' => Resource::LECTURA,
            // Como lo que sale del sembrador: generado, y por tanto sujeto a
            // firma. La puerta mira la PROCEDENCIA, no el kind.
            'origen' => Resource::GENERADO,
            'title' => ['es' => 'Ecuaciones de primer grado'],
            'summary' => ['es' => 'Qué es una ecuación y cómo se despeja.'],
            'status' => 'published',
        ]);
        $version = ResourceVersion::create([
            'resource_id' => $recurso->id, 'semver' => '1.0.0',
            'config' => ['bloques' => (new Bloques)->validar($bloques)],
            'published_at' => now(),
            'reviewed_at' => $firmada === null ? null : now(),
        ]);
        $recurso->update(['current_version_id' => $version->id]);
        $recurso->objectives()->attach($this->objective->id, ['role' => 'primary']);

        return $recurso->fresh();
    }

    private function bloquesDeEjemplo(): array
    {
        return [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Una ecuación es una igualdad con una incógnita.']],
            ['tipo' => 'formula', 'latex' => '2x + 3 = 11', 'etiqueta' => ['es' => 'Ecuación de ejemplo']],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Despejar paso a paso'], 'pasos' => [
                ['texto' => ['es' => 'Restamos 3 a los dos lados.'], 'latex' => '2x = 8'],
                ['texto' => ['es' => 'Dividimos entre 2.'], 'latex' => 'x = 4'],
            ]],
            ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                ['es' => 'Agrupa los términos con incógnita.'],
                ['es' => 'Despeja.'],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => [
                'es' => 'Cambiar de signo solo un lado de la igualdad.',
            ]],
        ];
    }

    // ================= ORÁCULO 1 — sin inyección =================

    /**
     * EL ORÁCULO SE MIDE SOBRE EL HTML FINAL, no sobre la intención. Un texto
     * con etiquetas tiene que llegar al alumno como texto: visible, legible y
     * sin ejecutarse. React escapa por defecto — lo que este test fija es que
     * nadie meta un `dangerouslySetInnerHTML` «para que se vea bonito».
     */
    public function test_un_texto_con_etiquetas_se_pinta_como_texto(): void
    {
        $veneno = '<script>alert(1)</script> y <img src=x onerror="alert(2)">';
        $recurso = $this->leccion([
            ['tipo' => 'parrafo', 'texto' => ['es' => "Cuidado con {$veneno} en clase."]],
        ]);

        $html = $this->get("/recurso/{$recurso->id}")->assertOk()->getContent();

        // Ni una etiqueta ejecutable en el documento…
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('onerror="alert(2)"', $html);
        // …y el texto sí está, escapado, dentro de las props de Inertia.
        $this->assertStringContainsString('alert(1)', $html);
    }

    public function test_ni_el_titulo_ni_el_alt_escapan_del_escapado(): void
    {
        $recurso = $this->leccion([
            ['tipo' => 'imagen', 'src' => '/img/recta.svg', 'alt' => ['es' => '<b>Recta</b> numérica']],
        ]);

        $html = $this->get("/recurso/{$recurso->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('<b>Recta</b>', $html);
    }

    // ================= ORÁCULO 2 — el bloque malo revienta al sembrar =================

    public function test_un_tipo_de_bloque_desconocido_revienta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/desconocido/');

        (new Bloques)->validar([['tipo' => 'video_de_youtube', 'texto' => ['es' => 'x']]]);
    }

    public function test_un_bloque_incompleto_revienta_con_su_sitio(): void
    {
        foreach ([
            [['tipo' => 'parrafo']],                                   // sin texto
            [['tipo' => 'parrafo', 'texto' => ['es' => '   ']]],       // texto vacío
            [['tipo' => 'formula']],                                   // sin latex
            [['tipo' => 'lista', 'items' => []]],                      // lista vacía
            [['tipo' => 'aviso', 'variante' => 'inventada', 'texto' => ['es' => 'x']]],
            [['tipo' => 'ejemplo', 'pasos' => []]],                    // ejemplo sin pasos
            [['tipo' => 'imagen', 'src' => 'https://ajeno.test/x.png', 'alt' => ['es' => 'x']]],
            [['tipo' => 'imagen', 'src' => 'javascript:alert(1)', 'alt' => ['es' => 'x']]],
        ] as $malos) {
            try {
                (new Bloques)->validar($malos, 'M.4.1');
                $this->fail('Pasó un bloque inválido: '.json_encode($malos));
            } catch (InvalidArgumentException $e) {
                // El mensaje dice DÓNDE, que es lo que hace útil el error.
                $this->assertStringContainsString('M.4.1', $e->getMessage());
            }
        }
    }

    public function test_una_leccion_vacia_revienta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Bloques)->validar([]);
    }

    public function test_una_formula_imposible_revienta_al_sembrar_no_al_pintarse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/subconjunto|Comando/');

        (new Bloques)->validar([['tipo' => 'formula', 'latex' => '\integral{x}']]);
    }

    // ================= ORÁCULO 3 — la puerta =================

    public function test_una_leccion_sin_firmar_no_se_sirve(): void
    {
        $recurso = $this->leccion($this->bloquesDeEjemplo(), firmada: null);

        $this->get("/recurso/{$recurso->id}")->assertNotFound();
    }

    /**
     * TODAS las puertas a la vez, no una por una.
     *
     * La versión anterior de este oráculo probaba `/recurso` y se daba por
     * satisfecha. Mientras tanto `GET /api/v1/resources/{slug}` comprobaba
     * `status === 'published'` escrito a mano —lo que `published()` significaba
     * el día que se escribió— y servía `currentVersion`, o sea el texto ÍNTEGRO
     * de la lección sin revisar. Probar puerta por puerta garantiza que la
     * próxima ruta que se añada no se pruebe.
     *
     * Y se mide buscando el TEXTO de la lección en el cuerpo entero, no con
     * `assertJsonMissing`: lo que no puede salir es el contenido, esté en el
     * campo que esté.
     */
    public function test_ninguna_ruta_sirve_una_leccion_sin_firmar(): void
    {
        // El centinela va SIN acentos a propósito. Con uno acentuado el test
        // pasaba en verde mintiendo: Inertia serializa las props como JSON con
        // escapes unicode, así que «ecuación» viaja como «ecuación» y la
        // búsqueda no lo encontraba ni cuando la lección SÍ estaba servida. Lo
        // delató el control positivo del final, que es para lo que está.
        $delator = 'CENTINELA DE LECCION SIN REVISAR';
        $recurso = $this->leccion([
            ['tipo' => 'parrafo', 'texto' => ['es' => $delator]],
            ...$this->bloquesDeEjemplo(),
        ], firmada: null);

        $rutas = [
            "/recurso/{$recurso->id}",
            "/api/v1/resources/{$recurso->slug}",
            '/api/v1/resources',
            "/destreza/{$this->objective->id}",
            "/api/v1/objectives/{$this->objective->id}",
            // La portada. Hoy solo lleva cifras agregadas, así que el centinela
            // no podría aparecer ni queriendo — pero está en la lista para que
            // el día que alguien ponga un «último contenido publicado» ahí
            // arriba, la puerta ya esté puesta y no haya que acordarse.
            '/',
        ];

        foreach ($rutas as $ruta) {
            $respuesta = $this->get($ruta);

            $this->assertStringNotContainsString($delator, $respuesta->getContent(),
                "{$ruta} sirvió el texto de una lección que nadie ha revisado.");
        }

        // Y en cuanto se firma, esas mismas rutas sí la dan: el oráculo mide la
        // puerta, no que la lección sea inalcanzable.
        $recurso->currentVersion->update(['reviewed_at' => now()]);

        $this->assertStringContainsString($delator,
            $this->get("/recurso/{$recurso->id}")->getContent());
    }

    /**
     * LA PUERTA MIRA LA PROCEDENCIA, NO EL TIPO.
     *
     * Esta condición decía `kind != 'reading'`, y era cierta por una
     * circunstancia y no por naturaleza: hoy lo único que se produce a escala
     * son lecciones. La Fase 2 son simuladores DECLARATIVOS generados por un
     * pipeline de IA — el esquema lo dice literalmente («config declarativa
     * validada»). Con la regla atada al tipo, ese lote habría entrado por
     * `kind = 'simulation'` y salido al alumno sin que nadie tocara la línea de
     * la puerta: el agujero se abría solo.
     *
     * Los dos casos van juntos a propósito. El primero solo demuestra que algo
     * se filtra; el segundo, que la puerta no se ha vuelto un muro.
     */
    public function test_la_firma_se_le_exige_a_lo_generado_no_a_un_kind(): void
    {
        // Un simulador GENERADO y sin firmar: no sale, aunque no sea lectura.
        // Es el caso de la Fase 2, escrito antes de que exista.
        $generado = Resource::create([
            'slug' => 'sim-declarativo', 'kind' => Resource::SIMULACION,
            'origen' => Resource::GENERADO, 'status' => 'published',
            'title' => ['es' => 'Simulador declarativo'],
        ]);
        $version = ResourceVersion::create([
            'resource_id' => $generado->id, 'semver' => '1.0.0',
            'bundle_url' => 'https://cdn.test/sims/x/1.0.0/', 'published_at' => now(),
        ]);
        $generado->update(['current_version_id' => $version->id]);
        $generado->objectives()->attach($this->objective->id, ['role' => 'primary']);

        $this->get("/recurso/{$generado->id}")->assertNotFound();

        // Una lectura CURADA —escrita a mano por un docente— sale sin firma:
        // ya pasó por unos ojos al darla de alta. Si este caso fallara, la
        // puerta habría dejado de ser una puerta para ser un muro.
        $curada = Resource::create([
            'slug' => 'ficha-del-profe', 'kind' => Resource::LECTURA,
            'origen' => Resource::CURADO, 'status' => 'published',
            'title' => ['es' => 'Ficha del profesor'],
        ]);
        $vCurada = ResourceVersion::create([
            'resource_id' => $curada->id, 'semver' => '1.0.0',
            'config' => ['bloques' => (new Bloques)->validar([
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Escrita a mano.']],
            ])],
            'published_at' => now(),
        ]);
        $curada->update(['current_version_id' => $vCurada->id]);

        $this->get("/recurso/{$curada->id}")->assertOk();

        // Y en cuanto alguien firma el generado, sale.
        $version->update(['reviewed_at' => now()]);
        $this->get("/recurso/{$generado->id}")->assertOk();
    }

    /** Lo que ya existía en producción es curado: la migración no lo esconde. */
    public function test_la_migracion_no_deja_de_servir_lo_que_ya_estaba(): void
    {
        // Un simulador dado de alta antes de que existiera la columna: la
        // migración lo marcó `curado`, así que se sigue sirviendo sin firma.
        // Sin ese backfill, esta migración habría vaciado el catálogo.
        $viejo = Resource::create([
            'slug' => 'plano-inclinado', 'kind' => Resource::SIMULACION,
            'status' => 'published', 'title' => ['es' => 'Plano inclinado'],
        ]);
        $v = ResourceVersion::create([
            'resource_id' => $viejo->id, 'semver' => '1.0.0',
            'bundle_url' => 'https://cdn.test/sims/plano/1.0.0/', 'published_at' => now(),
        ]);
        $viejo->update(['current_version_id' => $v->id]);

        $this->assertSame(Resource::CURADO, $viejo->fresh()->origen);
        $this->get("/recurso/{$viejo->id}")->assertOk();
    }

    public function test_una_leccion_sin_firmar_no_asoma_en_la_ficha(): void
    {
        $this->leccion($this->bloquesDeEjemplo(), firmada: null);

        $this->get("/destreza/{$this->objective->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('leccion', null)->has('resources', 0));
    }

    public function test_en_cuanto_se_firma_aparece(): void
    {
        $recurso = $this->leccion($this->bloquesDeEjemplo(), firmada: null);
        $this->get("/recurso/{$recurso->id}")->assertNotFound();

        $recurso->currentVersion->update(['reviewed_at' => now()]);

        $this->get("/recurso/{$recurso->id}")->assertOk();
        $this->get("/destreza/{$this->objective->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('leccion.id', $recurso->id));
    }

    /** REGRESIÓN: lo que ya existía antes de la puerta se sigue sirviendo. */
    public function test_la_migracion_firma_lo_que_ya_existia(): void
    {
        $migracion = require database_path(
            'migrations/2026_08_23_000001_add_review_gate_to_resource_versions.php',
        );

        $migracion->down();

        $recurso = Resource::create([
            'slug' => 'sim-de-siempre', 'kind' => 'simulation',
            'title' => ['es' => 'Simulador de siempre'], 'status' => 'published',
        ]);
        $versionId = (string) Str::uuid7();
        DB::table('resource_versions')->insert([
            'id' => $versionId, 'resource_id' => $recurso->id, 'semver' => '1.0.0',
            'bundle_url' => 'https://cdn.test/sims/x/1.0.0/', 'config' => '{}',
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $recurso->update(['current_version_id' => $versionId]);

        $migracion->up();

        $this->assertNotNull(
            DB::table('resource_versions')->where('id', $versionId)->value('reviewed_at'),
            'La migración dejó sin firmar un recurso que ya existía: habría desaparecido en silencio.',
        );
        $this->get("/recurso/{$recurso->id}")->assertOk();
    }

    // ================= ORÁCULO 4 — la regla de oro =================

    /**
     * Un INVITADO lee la lección entera —sin sesión— y no escribe una fila.
     * El contenido abierto es la decisión de producto del PR #21 y una lección
     * es contenido: si hiciera falta entrar para leer, la plataforma volvería a
     * ser una puerta cerrada con ejercicios detrás.
     */
    public function test_el_invitado_lee_la_leccion_entera_sin_escribir_nada(): void
    {
        $recurso = $this->leccion($this->bloquesDeEjemplo());

        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        $this->get("/recurso/{$recurso->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Recurso')
                ->where('auth.user', null)
                ->has('recurso.bloques', 5),
            );

        $this->get("/destreza/{$this->objective->id}")->assertOk();

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()],
            'Leer una lección escribió filas: la regla de oro está rota.');
    }

    // ================= ORÁCULO 5 — la fórmula viene del servidor =================

    public function test_la_formula_llega_ya_renderizada_como_arbol_mathml(): void
    {
        $recurso = $this->leccion([
            ['tipo' => 'formula', 'latex' => '\frac{1}{2}'],
        ]);

        $bloque = $this->get("/recurso/{$recurso->id}")->assertOk()
            ->viewData('page')['props']['recurso']['bloques'][0];

        $this->assertSame('formula', $bloque['tipo']);
        $this->assertSame('math', $bloque['mathml']['e']);
        $this->assertSame('mfrac', $bloque['mathml']['h'][0]['e']);
        // El LaTeX viaja también, para que un docente vea la fuente…
        $this->assertSame('\frac{1}{2}', $bloque['latex']);
        // …pero el cliente NO recibe nada que tenga que interpretar.
        $this->assertIsArray($bloque['mathml']);
    }

    // ================= El hub de la destreza =================

    /**
     * `/destreza` deja de ser una ficha y pasa a ser el hub: primero LEER,
     * luego practicar. Hoy la lección y la práctica serían dos sitios sin
     * relación, que es justo lo que hace que un alumno no encuentre el texto.
     */
    public function test_la_ficha_ofrece_la_leccion_antes_que_la_practica(): void
    {
        $recurso = $this->leccion($this->bloquesDeEjemplo());

        $this->get("/destreza/{$this->objective->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('leccion.id', $recurso->id)
                ->where('leccion.title', 'Ecuaciones de primer grado')
                ->where('leccion.summary', 'Qué es una ecuación y cómo se despeja.')
                ->where('leccion.bloques', 5),
            );
    }

    /** Sin lección, la ficha lo dice en vez de callar. */
    public function test_sin_leccion_la_ficha_lo_declara(): void
    {
        $this->get("/destreza/{$this->objective->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('leccion', null));
    }

    /** Un recurso que NO es lectura no se cuela como lección. */
    public function test_un_simulador_no_se_confunde_con_una_leccion(): void
    {
        $sim = Resource::create([
            'slug' => 'sim-plano', 'kind' => 'simulation',
            'title' => ['es' => 'Plano inclinado'], 'status' => 'published',
        ]);
        $v = ResourceVersion::create([
            'resource_id' => $sim->id, 'semver' => '1.0.0',
            'bundle_url' => 'https://cdn.test/x/', 'published_at' => now(), 'reviewed_at' => now(),
        ]);
        $sim->update(['current_version_id' => $v->id]);
        $sim->objectives()->attach($this->objective->id, ['role' => 'primary']);

        $this->get("/destreza/{$this->objective->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('leccion', null)
                ->has('resources', 1),
            );
    }

    /** Un recurso en borrador sigue sin servirse, firmado o no. */
    public function test_un_borrador_no_se_sirve_aunque_este_firmado(): void
    {
        $recurso = $this->leccion($this->bloquesDeEjemplo());
        $recurso->update(['status' => 'draft']);

        $this->get("/recurso/{$recurso->id}")->assertNotFound();
    }
}
