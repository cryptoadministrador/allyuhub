<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

/**
 * PR 20 · LA ENTRADA POR WEB, solo para cuentas docentes.
 *
 * Un alumno entra SIEMPRE desde su aula (LTI): su identidad es (lti_iss,
 * lti_sub) y la nota tiene que volver a su curso. Esta puerta es para quien
 * revisa y firma contenido sin un Moodle conectado, y solo la cruza una cuenta
 * con `docente_web` y SIN identidad LTI — creada a mano por consola
 * (`php artisan docente:web`). No hay registro: nadie se da de alta solo.
 *
 * Lo que fijó la auditoría adversarial (cada punto con su test):
 * - El correo se normaliza a minúsculas: el comando guarda en minúsculas.
 * - Intento fallido y cuenta inexistente dan el MISMO mensaje y tardan lo
 *   MISMO (Timebox de 500 ms: bcrypt de producción tarda más que los 200 ms
 *   por defecto y el tiempo delataba qué correos tienen cuenta).
 * - Cinco intentos por (correo, IP) y veinte por IP a secas, por minuto: el
 *   primero protege una cuenta; el segundo impide sondear muchos correos.
 * - La sesión anterior NO se hereda: se invalida entera antes de entrar
 *   (`regenerate` cambiaba el id pero conservaba los datos — «piezas vistas»
 *   de la revisión o un launch LTI ajeno en un navegador compartido).
 */
class AccesoWebController extends Controller
{
    private const INTENTOS_CUENTA = 5;

    private const INTENTOS_IP = 20;

    private const TIMEBOX_MICROSEGUNDOS = 500_000;

    /** Bcrypt de una clave aleatoria descartada: el hash contra el que se compara cuando la cuenta no existe. */
    private const HASH_SEÑUELO = '$2y$12$kTevjxgmJoracyBXnGiBVOGw0wFRrSLfMFXxCdsPr0V20yzawYhjO';

    public function entrar(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = Str::lower(trim($data['email']));
        $porCuenta = 'acceso-web:'.$email.'|'.$request->ip();
        $porIp = 'acceso-web-ip:'.$request->ip();

        foreach ([$porCuenta => self::INTENTOS_CUENTA, $porIp => self::INTENTOS_IP] as $clave => $max) {
            if (RateLimiter::tooManyAttempts($clave, $max)) {
                throw ValidationException::withMessages([
                    'email' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($clave).' segundos.',
                ]);
            }
        }

        $user = (new Timebox)->call(function (Timebox $timebox) use ($email, $data) {
            // SOLO cuentas docentes web sin identidad LTI: un alumno LTI no
            // entra por aquí aunque su fila tuviera la marca por error.
            $user = User::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->where('docente_web', true)
                ->whereNull('lti_iss')
                ->first();

            // Se hashea SIEMPRE, exista o no la cuenta: mismo trabajo, mismo tiempo.
            $ok = Hash::check($data['password'], $user?->password ?? self::HASH_SEÑUELO);

            if ($ok && $user !== null) {
                $timebox->returnEarly();

                return $user;
            }

            return null;
        }, self::TIMEBOX_MICROSEGUNDOS);

        if ($user === null) {
            RateLimiter::hit($porCuenta, 60);
            RateLimiter::hit($porIp, 60);

            throw ValidationException::withMessages([
                'email' => 'Correo o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($porCuenta);

        // Sesión NUEVA y vacía (solo se conserva a dónde quería ir).
        $destino = $request->session()->pull('url.intended');
        $request->session()->invalidate();
        Auth::login($user);
        $request->session()->regenerate();
        if (is_string($destino)) {
            $request->session()->put('url.intended', $destino);
        }

        return redirect()->intended('/docente/revisar');
    }

    public function salir(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
