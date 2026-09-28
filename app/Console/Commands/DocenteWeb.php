<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * PR 20 · Da de alta (o de baja) una CUENTA DOCENTE WEB: la que entra en
 * /entrar con correo y contraseña y puede revisar y firmar contenido sin un
 * Moodle conectado.
 *
 *   php artisan docente:web carlos@colegio.edu.ec --nombre="Carlos Heredia"
 *   php artisan docente:web carlos@colegio.edu.ec --quitar
 *
 * La contraseña la genera el comando (24 caracteres) y se muestra UNA vez; no
 * se guarda en claro en ningún sitio. Repetir el alta genera otra (sirve para
 * restablecerla). `--quitar` retira la marca y cierra la puerta, sin borrar el
 * usuario: sus firmas y notas conservan autor.
 */
class DocenteWeb extends Command
{
    protected $signature = 'docente:web
        {email : Correo con el que entrará}
        {--nombre= : Nombre que se muestra y que queda en sus firmas}
        {--quitar : Retira el acceso docente por web}';

    protected $description = 'Crea o retira una cuenta docente que entra por web (sin Moodle)';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("«{$email}» no es un correo válido.");

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        // NUNCA sobre una cuenta LTI. El correo que manda Moodle es un DATO del
        // alumno, no su identidad (CLAUDE.md: la identidad es iss+sub): si
        // coincide con el que se da de alta, el comando convertía en docente al
        // ALUMNO — y su siguiente launch entraba ya con permisos de firma
        // (hallazgo de la auditoría del PR 20).
        if ($user !== null && $user->lti_iss !== null) {
            $this->error("{$email} pertenece a una cuenta que entra desde Moodle (LTI). "
                .'Una cuenta docente web necesita un correo propio que no use nadie en el aula.');

            return self::FAILURE;
        }

        if ($this->option('quitar')) {
            if ($user === null || ! $user->docente_web) {
                $this->warn("{$email} no tenía acceso docente por web.");

                return self::SUCCESS;
            }
            // Contraseña nueva que nadie conoce: además de la marca, la llave.
            $user->forceFill(['docente_web' => false, 'password' => Str::random(64)])->save();
            // Y las sesiones abiertas se cierran: sin esto seguía «dentro»
            // (ya sin permisos de docente, pero dentro).
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $this->info("Acceso docente por web retirado a {$email}.");

            return self::SUCCESS;
        }

        $password = Str::password(24, symbols: false);
        $user ??= new User(['email' => $email]);
        $user->forceFill([
            'name' => $this->option('nombre') ?: ($user->name ?: Str::before($email, '@')),
            'password' => $password,
            'docente_web' => true,
        ])->save();

        $this->info("Cuenta docente web lista: {$email}");
        $this->line("Contraseña (se muestra UNA sola vez): {$password}");
        $this->line('Entra en /entrar. Para cambiarla, vuelve a ejecutar este comando.');

        return self::SUCCESS;
    }
}
