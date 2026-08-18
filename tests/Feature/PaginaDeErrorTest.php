<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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
     * La API de práctica NO puede recibir HTML: el bucle de práctica hace
     * fetch y se comería una página entera creyendo que es JSON.
     */
    public function test_la_api_sigue_devolviendo_json(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/objectives/'.Str::uuid().'/practice/next')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
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
