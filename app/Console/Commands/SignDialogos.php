<?php

namespace App\Console\Commands;

use App\Models\Dialogo;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Firma diálogos revisados para que se sirvan a los alumnos.
 *
 *   php artisan dialogos:firmar --lengua=it
 *   php artisan dialogos:firmar --lengua=it --slug=il-primo-giorno --docente=7
 *
 * Un diálogo sembrado nace SIN firmar y no se sirve (`Dialogo::published()` solo
 * devuelve lo firmado). Quien sabe la lengua lo revisa y lo abre. La firma es
 * POR LENGUA, como en práctica y lecciones.
 */
class SignDialogos extends Command
{
    protected $signature = 'dialogos:firmar
        {--lengua= : Lengua a publicar (it, fr, de, zh)}
        {--slug= : Un diálogo concreto por su slug (opcional)}
        {--docente= : Id del usuario que firma (queda en reviewed_by)}';

    protected $description = 'Firma diálogos del interlocutor revisados para que se sirvan a los alumnos';

    public function handle(): int
    {
        $lengua = $this->option('lengua');
        if ($lengua === null) {
            $this->error('Di QUÉ lengua firmas: --lengua=it. Quien sabe la lengua firma la lengua.');

            return self::FAILURE;
        }

        $docente = null;
        if ($this->option('docente') !== null) {
            $docente = User::find((int) $this->option('docente'));
            if ($docente === null) {
                $this->error('No existe el usuario '.$this->option('docente').'.');

                return self::FAILURE;
            }
        }

        $pendientes = Dialogo::query()
            ->whereNull('reviewed_at')
            ->where('lengua', $lengua)
            ->when($this->option('slug'), fn ($q, $slug) => $q->where('slug', $slug));

        $cuantos = (clone $pendientes)->count();
        if ($cuantos === 0) {
            $this->info("No hay diálogos pendientes de firma en «{$lengua}».");

            return self::SUCCESS;
        }

        $pendientes->update([
            'reviewed_at' => now(),
            'reviewed_by' => $docente?->id,
        ]);

        $this->info(sprintf(
            '%d diálogo(s) firmados en «%s»%s. Ya se sirven a los alumnos.',
            $cuantos, $lengua,
            $docente ? " por {$docente->name}" : ' (sin autoría registrada)',
        ));

        return self::SUCCESS;
    }
}
