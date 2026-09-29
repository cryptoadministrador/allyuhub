<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Docente\Docencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PR 20 · DOCENTE POR WEB, SIN MOODLE.
 *
 * Sin un Moodle conectado nadie era docente, y nadie podía revisar ni firmar.
 * La segunda puerta: una cuenta creada A MANO (`docente:web`) que entra por
 * `/entrar` con correo y contraseña. Los oráculos fijan que es una puerta y no
 * un agujero:
 *
 *  - solo la cruza una cuenta con `docente_web` (un usuario LTI no, aunque
 *    acierte la contraseña);
 *  - el fallo no revela si el correo existe;
 *  - cinco intentos por minuto y (correo, IP);
 *  - la sesión se regenera al entrar y se invalida al salir;
 *  - `--quitar` cierra la puerta sin borrar al usuario (sus firmas conservan
 *    autor).
 */
class AccesoWebDocenteTest extends TestCase
{
    use RefreshDatabase;

    private function docenteWeb(string $email = 'profe@colegio.test', string $password = 'clave-larga-de-prueba'): User
    {
        $u = User::factory()->create(['email' => $email, 'password' => $password, 'name' => 'Profe Web']);
        $u->forceFill(['docente_web' => true])->save();

        return $u;
    }

    public function test_una_cuenta_docente_web_entra_y_llega_a_revisar(): void
    {
        $profe = $this->docenteWeb();

        $this->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'clave-larga-de-prueba'])
            ->assertRedirect('/docente/revisar');
        $this->assertAuthenticatedAs($profe);

        // Es docente sin tener ningún curso LTI.
        $this->assertTrue(Docencia::es($profe));
        $this->get('/docente/revisar')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('docente-revisar')
                ->where('auth.es_docente', true)
                ->where('auth.contextos', []));
    }

    public function test_un_usuario_lti_no_entra_por_la_web_aunque_acierte_la_contrasena(): void
    {
        User::factory()->create(['email' => 'alumno@colegio.test', 'password' => 'la-que-sea-123']);

        $this->post('/entrar', ['email' => 'alumno@colegio.test', 'password' => 'la-que-sea-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_el_fallo_no_revela_si_el_correo_existe(): void
    {
        $this->docenteWeb();

        $mal = $this->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'otra'])
            ->assertSessionHasErrors('email');
        $mensajeMal = session('errors')->first('email');

        $this->post('/entrar', ['email' => 'nadie@colegio.test', 'password' => 'otra'])
            ->assertSessionHasErrors('email');

        $this->assertSame($mensajeMal, session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_cinco_intentos_por_minuto_y_el_sexto_espera(): void
    {
        $this->docenteWeb();

        foreach (range(1, 5) as $_) {
            $this->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'mal']);
        }

        // Ni siquiera con la contraseña buena: primero hay que esperar.
        $this->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'clave-larga-de-prueba'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
        $this->assertGuest();

        RateLimiter::clear('acceso-web:profe@colegio.test|127.0.0.1');
        $this->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'clave-larga-de-prueba'])
            ->assertRedirect('/docente/revisar');
    }

    public function test_la_sesion_se_regenera_al_entrar_y_salir_la_cierra(): void
    {
        $this->docenteWeb();
        $this->get('/entrar')->assertOk();
        $antes = session()->getId();

        $this->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'clave-larga-de-prueba']);
        $this->assertNotSame($antes, session()->getId(), 'La sesión no se regeneró al entrar (fijación de sesión).');

        $this->post('/salir')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/docente/revisar')->assertStatus(403);
    }

    public function test_la_pagina_de_entrar_ofrece_la_puerta_docente(): void
    {
        $this->get('/entrar')->assertOk()
            ->assertSee('¿Eres docente del colegio?')
            ->assertSee('name="password"', false)
            ->assertSee('Ir al aula virtual');
    }

    public function test_el_comando_crea_la_cuenta_y_su_contrasena_sirve(): void
    {
        $this->assertSame(0, Artisan::call('docente:web', ['email' => 'Carlos@Colegio.test', '--nombre' => 'Carlos Heredia']));
        $salida = Artisan::output();
        $this->assertMatchesRegularExpression('/Contraseña \(se muestra UNA sola vez\): (\S{24})/', $salida);
        preg_match('/Contraseña \(se muestra UNA sola vez\): (\S{24})/', $salida, $m);

        $u = User::where('email', 'carlos@colegio.test')->sole();
        $this->assertTrue($u->docente_web);
        $this->assertSame('Carlos Heredia', $u->name);
        // Nunca en claro en la base.
        $this->assertNotSame($m[1], $u->getAttributes()['password']);

        $this->post('/entrar', ['email' => 'carlos@colegio.test', 'password' => $m[1]])
            ->assertRedirect('/docente/revisar');
    }

    public function test_quitar_cierra_la_puerta_sin_borrar_al_usuario(): void
    {
        $profe = $this->docenteWeb();

        Artisan::call('docente:web', ['email' => 'profe@colegio.test', '--quitar' => true]);
        $profe->refresh();

        $this->assertFalse($profe->docente_web);
        $this->assertFalse(Docencia::es($profe));
        $this->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'clave-larga-de-prueba'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNotNull(User::find($profe->id), 'Quitar el acceso no borra al usuario.');
    }

    public function test_un_alumno_lti_sigue_sin_ser_docente(): void
    {
        $alumno = User::factory()->create();
        $this->actingAs($alumno)->get('/docente/revisar')->assertStatus(403);
        $this->assertFalse(Docencia::es($alumno));
    }

    // ================= lo que cazó la auditoría =================

    /**
     * GRAVE: el correo de Moodle es un DATO del alumno, no su identidad. Si
     * coincidía con el de la cuenta a dar de alta, el comando convertía en
     * docente al alumno (y su siguiente launch entraba con permiso de firma).
     */
    public function test_el_comando_se_niega_a_tocar_una_cuenta_lti(): void
    {
        $alumno = User::factory()->create(['email' => 'profe@colegio.test']);
        $alumno->forceFill(['lti_iss' => 'https://moodle.test', 'lti_sub' => 'alumno-42'])->save();

        $this->assertSame(1, Artisan::call('docente:web', ['email' => 'PROFE@colegio.test']));
        $this->assertStringContainsString('LTI', Artisan::output());

        $alumno->refresh();
        $this->assertFalse($alumno->docente_web);
        $this->assertFalse(Docencia::es($alumno));
        $this->assertSame(1, User::whereRaw('lower(email) = ?', ['profe@colegio.test'])->count(), 'Se duplicó la cuenta.');
    }

    /** Y aunque una fila LTI tuviera la marca por error, por la web no entra. */
    public function test_una_fila_lti_con_la_marca_no_entra_por_la_web(): void
    {
        $u = $this->docenteWeb();
        $u->forceFill(['lti_iss' => 'https://moodle.test', 'lti_sub' => 'x'])->save();

        $this->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'clave-larga-de-prueba'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_el_correo_se_normaliza_a_minusculas(): void
    {
        $this->docenteWeb();

        $this->post('/entrar', ['email' => '  Profe@Colegio.TEST ', 'password' => 'clave-larga-de-prueba'])
            ->assertRedirect('/docente/revisar');
        $this->assertAuthenticated();
    }

    /**
     * La sesión anterior NO se hereda: `regenerate` cambia el id pero conserva
     * los datos, y «las piezas vistas» de la revisión o un launch LTI de otra
     * persona pasaban a la cuenta que entraba en un navegador compartido.
     */
    public function test_entrar_no_hereda_los_datos_de_la_sesion_anterior(): void
    {
        $this->docenteWeb();

        $this->withSession(['revision.vistas' => ['item:ajeno'], 'lti.launch_id' => 'lanzamiento-ajeno', 'url.intended' => '/docente/revisar?lengua=en'])
            ->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'clave-larga-de-prueba'])
            ->assertRedirect('/docente/revisar?lengua=en');   // a dónde iba SÍ se conserva

        $this->assertNull(session('revision.vistas'));
        $this->assertNull(session('lti.launch_id'));
    }

    /** Veinte fallos por IP a secas cierran la puerta aunque cada correo sea distinto. */
    public function test_no_se_pueden_sondear_muchos_correos_desde_una_ip(): void
    {
        $this->docenteWeb();

        foreach (range(1, 20) as $i) {
            $this->post('/entrar', ['email' => "sondeo{$i}@colegio.test", 'password' => 'x']);
        }

        $this->post('/entrar', ['email' => 'profe@colegio.test', 'password' => 'clave-larga-de-prueba'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_quitar_cierra_sus_sesiones_abiertas(): void
    {
        $profe = $this->docenteWeb();
        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => 'sesion-abierta', 'user_id' => $profe->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'x', 'payload' => '', 'last_activity' => time(),
        ]);

        Artisan::call('docente:web', ['email' => 'profe@colegio.test', '--quitar' => true]);

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $profe->id)->count());
    }
}
