<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FRENTE 3 — un enlace muerto dentro del iframe de Moodle mostraba la pantalla
 * blanca de Symfony: sin marca, sin salida y con pinta de plataforma rota.
 * Ahora 404 y 403 se pintan con la app, SIN cambiar el código de estado (que
 * es lo que ven los buscadores, Moodle y los tests de siempre).
 */
class PaginaDeErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_404_se_pinta_con_la_marca_y_sigue_siendo_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/catalogo/'.Str::uuid())
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 404));
    }

    /**
     * REGRESIÓN (auditoría, hallazgo 1): el 404 MÁS COMÚN de la app —un id de
     * ruta que no existe— lo lanza SubstituteBindings desde dentro del
     * pipeline, así que el middleware que va detrás nunca corre. Con
     * HandleInertiaRequests al final, la página de error llegaba SIN `auth`:
     * un alumno con sesión veía la salida del visitante («entra desde tu aula
     * virtual» → /entrar, la pared) en vez de sus tres atajos. El test
     * anterior solo miraba `component` y `status` y lo daba por bueno.
     *
     * Ojo: se prueba en peticiones INDEPENDIENTES a propósito. Las props
     * compartidas de Inertia viven en un singleton que no se limpia entre
     * peticiones del mismo proceso, así que una visita previa a una página
     * normal habría dejado el `auth` puesto y el test habría pasado en falso.
     */
    #[DataProvider('rutasCon404DeBinding')]
    public function test_el_404_de_un_id_inexistente_conserva_la_sesion(string $ruta): void
    {
        $ana = User::factory()->create();

        $this->actingAs($ana)
            ->get(str_replace('{id}', (string) Str::uuid(), $ruta))
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('auth.user.id', $ana->id)
                ->where('auth.user.name', $ana->name));
    }

    public static function rutasCon404DeBinding(): array
    {
        return [
            'catálogo' => ['/catalogo/{id}'],
            'ficha de destreza' => ['/destreza/{id}'],
            'práctica' => ['/practicar/{id}'],
            'recurso' => ['/recurso/{id}'],
            'ruta inexistente' => ['/esto-no-existe-{id}'],
        ];
    }

    /**
     * REGRESIÓN (auditoría, hallazgo 3): esas mismas respuestas se quedaban
     * sin `frame-ancestors`. Antes daba casi igual (eran pantallas de Symfony
     * sin nada); ahora llevan la UI de la app CON ENLACES, así que una página
     * sin CSP es una superficie embebible desde cualquier origen.
     */
    public function test_la_pagina_de_error_tambien_declara_su_csp(): void
    {
        $csp = $this->actingAs(User::factory()->create())
            ->get('/catalogo/'.Str::uuid())
            ->headers->get('Content-Security-Policy');

        $this->assertSame("frame-ancestors 'self'", $csp);
    }

    public function test_el_403_tambien(): void
    {
        // Un id de contexto válido pero de otro docente: el panel aborta 403.
        $this->actingAs(User::factory()->create())
            ->get('/docente/'.Str::uuid())
            ->assertNotFound();   // sin membership, el panel no existe para él

        // El 403 explícito de la app se comprueba con un abort directo.
        Route::middleware('web')
            ->get('/_test_403', fn () => abort(403));

        $this->actingAs(User::factory()->create())
            ->get('/_test_403')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 403));
    }

    /**
     * El visitante SIN sesión también recibe la página con marca (la portada
     * es pública, así que un 404 público es posible).
     */
    public function test_el_visitante_sin_sesion_recibe_la_pagina_con_marca(): void
    {
        Route::middleware('web')
            ->get('/_test_404_publico', fn () => abort(404));

        $this->get('/_test_404_publico')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 404)
                ->where('auth.user', null));
    }

    /**
     * La API NO puede recibir HTML: el bucle de práctica hace fetch y se
     * comería una página entera creyendo que es JSON.
     *
     * REGRESIÓN (auditoría, hallazgo 2): la primera versión de esta prueba
     * usaba `getJson()`, que pone `Accept: application/json`, y así pasaba
     * con el fallo puesto. Lo que rompía era el cliente REAL sin cabecera
     * —curl, la monitorización, el consumidor de `/blueprint`— porque
     * `expectsJson()` es false con un `Accept` comodín o sin `Accept`. El prefijo
     * `api/*` es lo que manda, no lo que el cliente pida.
     */
    #[DataProvider('cabecerasDeCliente')]
    public function test_la_api_devuelve_json_con_cualquier_accept(array $cabeceras): void
    {
        $respuesta = $this->actingAs(User::factory()->create())
            ->get('/api/v1/objectives/'.Str::uuid().'/practice/next', $cabeceras);

        $respuesta->assertNotFound();
        $this->assertStringStartsWith('application/json',
            $respuesta->headers->get('content-type'));
        $this->assertStringNotContainsString('<!DOCTYPE', $respuesta->getContent());
    }

    public static function cabecerasDeCliente(): array
    {
        return [
            'sin Accept (curl)' => [[]],
            'Accept: */*' => [['Accept' => '*/*']],
            'Accept de navegador' => [['Accept' => 'text/html,application/xhtml+xml']],
            'Accept: application/json' => [['Accept' => 'application/json']],
        ];
    }

    /**
     * El fallback que hace posible pintar el 404 de una URL sin ruta es
     * quisquilloso y conviene tenerlo atado:
     *  - cubre TODOS los verbos (con el `Route::fallback()` de Laravel, que
     *    solo registra GET, un POST perdido devolvía 405 y de paso revelaba
     *    que en ese path había algo con otro verbo);
     *  - NO cubre /api/*, donde un POST a un endpoint de solo lectura tiene
     *    que seguir siendo 405 y no 404.
     */
    public function test_el_fallback_cubre_todos_los_verbos_menos_en_la_api(): void
    {
        $this->actingAs(User::factory()->create());

        // GET a una URL inexistente: 404 con marca (el enlace muerto del alumno).
        $this->get('/no-existe')->assertNotFound();

        // La API conserva su semántica: 405 donde el verbo no aplica…
        $this->postJson('/api/v1/practice/mastery')->assertStatus(405);
        // …y 404 JSON donde no hay nada.
        $this->getJson('/api/v1/no-existe')->assertNotFound();
    }

    /**
     * REGRESIÓN (auditoría PR #20, defecto 10): el catch-all era `Route::any()`
     * y se tragaba el preflight CORS — OPTIONS sobre cualquier ruta devolvía
     * 404 en vez del 200 autogenerado con su cabecera Allow, una mina para el
     * día que un consumidor externo llame con cabeceras personalizadas. El
     * catch-all ahora excluye OPTIONS. El test viejo solo probaba URLs
     * inexistentes con GET/POST y nunca vio la regresión.
     */
    public function test_el_catch_all_no_se_traga_el_preflight_options(): void
    {
        $r = $this->call('OPTIONS', '/catalogo');
        $this->assertSame(200, $r->getStatusCode(), 'OPTIONS debe autogenerar el preflight, no caer en el 404');
        $this->assertNotEmpty($r->headers->get('Allow'), 'el preflight trae la cabecera Allow');
    }

    /** Un 500 NO se disfraza: esconder el fallo real es peor que la pantalla fea. */
    public function test_un_error_del_servidor_no_se_pinta_de_bonito(): void
    {
        Route::middleware('web')
            ->get('/_test_500', fn () => throw new \RuntimeException('boom'));

        $this->withExceptionHandling()
            ->get('/_test_500')
            ->assertStatus(500);

        $this->assertStringNotContainsString(
            '"component":"error"',
            $this->get('/_test_500')->getContent(),
        );
    }
}
