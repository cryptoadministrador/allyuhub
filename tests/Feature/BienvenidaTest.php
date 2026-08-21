<?php

namespace Tests\Feature;

use App\Http\Controllers\App\BienvenidaController;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FRENTE 1 — la portada pública. Es la ÚNICA página de la app sin sesión, así
 * que no puede filtrar nada del grafo ni pegarle a la BD en cada visita.
 */
class BienvenidaTest extends TestCase
{
    use RefreshDatabase;

    private function sembrarCurriculo(): void
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $grado = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado', 'native_code' => 'g11',
            'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11', 'attrs' => ['corto' => '1.º BGU'],
        ]);
        CurNode::create([
            'version_id' => $version->id, 'parent_id' => $grado->id, 'node_type' => 'grado',
            'native_code' => 'g12', 'title' => ['es' => '2.º BGU'], 'path' => 'bgu.g12',
        ]);
        foreach (['CN.F.5.1.9' => true, 'CN.F.5.1.10' => true, 'M.5.1.1' => false] as $code => $verificada) {
            LearningObjective::create([
                'node_id' => $grado->id, 'version_id' => $version->id,
                'native_code' => $code, 'statement' => ['es' => 'Enunciado.'],
                'is_verified' => $verificada,
            ]);
        }
        // `Resource::SIMULACION`, no la cadena. El fixture decía 'simulator'
        // —que no está en el vocabulario de la migración y no lo escribe nadie—
        // y el controlador también, así que el test pasaba en verde mientras la
        // portada contaba cero simuladores para siempre. Dos erratas que se
        // daban la razón la una a la otra.
        Resource::create([
            'slug' => 'plano-inclinado', 'kind' => Resource::SIMULACION, 'status' => 'published',
            'title' => ['es' => 'Plano inclinado'],
        ]);
        Resource::create([
            'slug' => 'borrador', 'kind' => Resource::SIMULACION, 'status' => 'draft',
            'title' => ['es' => 'Borrador'],
        ]);
    }

    public function test_el_visitante_sin_sesion_ve_la_portada(): void
    {
        $this->sembrarCurriculo();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('bienvenida')
                ->where('cifras.destrezas', 3)
                ->where('cifras.verificadas', 2)
                ->where('cifras.grados', 2)
                ->where('cifras.simuladores', 1)   // solo los published
                ->has('entrar')
            );
    }

    public function test_el_alumno_con_sesion_va_a_su_inicio(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect('/inicio');
    }

    /**
     * ORÁCULO: la portada es pública y no puede recontar el currículo en cada
     * visita.
     *
     * Lo que se afirma es que no vuelve a TOCAR EL GRAFO, no que no haga cero
     * consultas: con `CACHE_STORE=database` (lo que traen .env.example y
     * deploy/env.production.example) leer la caché ES una consulta — de coste
     * fijo, eso sí, y no una que cuente 1010 destrezas. La suite corre con
     * `array` (phpunit.xml), donde además salen cero; afirmarlo así habría
     * hecho pasar por garantía algo que solo era cierto en el banco de pruebas
     * (auditoría del frente visual).
     */
    public function test_no_vuelve_a_contar_el_curriculo_en_cada_visita(): void
    {
        $this->sembrarCurriculo();

        $this->get('/')->assertOk();   // primera visita: calienta la caché

        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });

        $this->get('/')->assertOk();

        $delGrafo = array_filter($consultas, fn (string $sql) => (bool) preg_match(
            '/\b(learning_objectives|cur_nodes|resources|frameworks|framework_versions)\b/', $sql,
        ));
        $this->assertSame([], array_values($delGrafo),
            'La portada volvió a consultar el grafo: '.implode(' | ', $delGrafo));

        // Y con la caché en memoria (la de la suite), ni una consulta.
        $this->assertSame([], $consultas,
            'Con CACHE_STORE=array la portada no debería consultar nada: '.implode(' | ', $consultas));
    }

    /** Si el currículo cambia, la caché caduca (no es eterna). */
    public function test_las_cifras_se_cachean_con_caducidad(): void
    {
        $this->sembrarCurriculo();
        $this->get('/')->assertOk();

        LearningObjective::first()->delete();
        $this->get('/')->assertInertia(fn ($p) => $p->where('cifras.destrezas', 3));  // cacheado

        Cache::flush();
        $this->get('/')->assertInertia(fn ($p) => $p->where('cifras.destrezas', 2));
    }

    /** Una BD vacía no revienta la portada (primer despliegue). */
    public function test_sin_curriculo_sembrado_sigue_en_pie(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('bienvenida')
                ->where('cifras.destrezas', 0)
                ->where('cifras.grados', 0));
    }

    /**
     * No filtra NADA del grafo. El oráculo es de FORMA, no una lista de
     * cadenas prohibidas: la primera versión enumeraba «CN.F.5.1.9» y compañía,
     * y una mutación que añadiera `CurNode::pluck('native_code')` a las cifras
     * pasaba entera (bucle B, M16). Ahora `cifras` tiene exactamente cuatro
     * enteros: cualquier cosa que se cuele al payload público lo rompe.
     */
    public function test_no_expone_contenido_del_curriculo(): void
    {
        $this->sembrarCurriculo();

        $props = $this->get('/')->viewData('page')['props'];

        $this->assertSame(
            ['destrezas', 'verificadas', 'grados', 'simuladores'],
            array_keys($props['cifras']),
        );
        foreach ($props['cifras'] as $clave => $valor) {
            $this->assertIsInt($valor, "cifras.{$clave} no es un entero");
        }

        // La página pública solo lleva las cifras, la URL de entrada y lo que
        // comparte el layout: ninguna prop de contenido más.
        $this->assertSame(['cifras', 'entrar'], array_keys(array_diff_key(
            $props, array_flip(['auth', 'errors', 'flash', 'ziggy'])
        )));

        $json = json_encode($props, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('CN.F.5.1.9', $json);
        $this->assertStringNotContainsString('Enunciado.', $json);
        $this->assertStringNotContainsString('plano-inclinado', $json);
        $this->assertNull($props['auth']['user'] ?? null);
    }

    /**
     * EL VOCABULARIO DE `kind`, contra la migración que lo declara.
     *
     * La portada contaba `kind = 'simulator'`. No existe: la migración de
     * `resources` declara `simulation|lab|video|reading|practice_set|project` y
     * el Deep Linking usa `simulation`. Nadie escribe nunca 'simulator'… salvo
     * el fixture de este mismo fichero, que llevaba la misma errata. Por eso
     * pasaba en verde, y por eso la portada habría dicho «0 simuladores»
     * aunque la Fase 2 sembrara doscientos.
     *
     * El oráculo no compara con una lista que yo escriba aquí: la lee del
     * comentario de la migración, que es donde vive la verdad.
     */
    public function test_los_kinds_que_cuenta_la_portada_existen_en_el_vocabulario(): void
    {
        $migracion = file_get_contents(database_path(
            'migrations/2026_08_11_000002_create_tracks_and_resources.php',
        ));

        $this->assertSame(1, preg_match(
            '/\$table->string\(\'kind\'\);\s*\/\/ ([a-z_|]+)/', $migracion, $m,
        ), 'La migración dejó de declarar el vocabulario de kind junto a la columna.');

        $vocabulario = explode('|', $m[1]);
        $this->assertContains(Resource::LECTURA, $vocabulario);

        foreach (Resource::INTERACTIVOS as $kind) {
            $this->assertContains($kind, $vocabulario,
                "La portada cuenta '{$kind}', que no está en el vocabulario de la migración.");
        }
    }

    /**
     * La portada es la SEXTA puerta, y la más pública: cuenta recursos, así que
     * pasa por `published()` como todas las demás. Con la puerta atada a la
     * procedencia esto deja de ser inofensivo — un lote generado y sin firmar
     * inflaría la cifra que ve un visitante antes de que nadie lo haya mirado.
     */
    public function test_la_portada_no_cuenta_lo_generado_sin_firmar(): void
    {
        $this->sembrarCurriculo();

        $generado = Resource::create([
            'slug' => 'sim-generado', 'kind' => Resource::SIMULACION,
            'origen' => Resource::GENERADO, 'status' => 'published',
            'title' => ['es' => 'Simulador declarativo'],
        ]);
        $version = ResourceVersion::create([
            'resource_id' => $generado->id, 'semver' => '1.0.0', 'published_at' => now(),
        ]);
        $generado->update(['current_version_id' => $version->id]);

        // Sigue contando 1: el curado de siempre, no el recién generado.
        $this->get('/')->assertInertia(fn ($p) => $p->where('cifras.simuladores', 1));

        // Y en cuanto un docente lo firma, cuenta. La cifra está cacheada una
        // hora, así que sin vaciarla este control positivo mediría la caché.
        $version->update(['reviewed_at' => now()]);
        Cache::forget(BienvenidaController::CACHE_KEY);

        $this->get('/')->assertInertia(fn ($p) => $p->where('cifras.simuladores', 2));
    }
}
