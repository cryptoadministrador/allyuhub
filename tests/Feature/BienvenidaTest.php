<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\Resource;
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
        Resource::create([
            'slug' => 'plano-inclinado', 'kind' => 'simulator', 'status' => 'published',
            'title' => ['es' => 'Plano inclinado'],
        ]);
        Resource::create([
            'slug' => 'borrador', 'kind' => 'simulator', 'status' => 'draft',
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

    /** ORÁCULO: la portada es pública; la segunda visita no toca la BD. */
    public function test_no_consulta_la_base_de_datos_en_cada_visita(): void
    {
        $this->sembrarCurriculo();

        $this->get('/')->assertOk();   // primera visita: calienta la caché

        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });

        $this->get('/')->assertOk();

        $this->assertSame([], $consultas,
            'La portada pública consultó la BD: '.implode(' | ', $consultas));
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
}
