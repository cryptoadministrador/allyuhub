<?php

namespace App\Console\Commands;

use App\Models\Tarjeta;
use App\Models\User;
use App\Services\Practice\Lenguas;
use App\Services\Revision\Firma;
use Illuminate\Console\Command;

/**
 * Firma tarjetas de vocabulario revisadas para que se sirvan a los alumnos.
 *
 *   php artisan vocabulario:firmar --lengua=it
 *   php artisan vocabulario:firmar --lengua=zh --unidad=3 --docente=7
 *
 * Una tarjeta sembrada nace SIN firmar y no se sirve (`Tarjeta::published()`).
 * La firma es POR LENGUA, como en todo lo demás: quien sabe la lengua firma la
 * lengua. QUÉ escribe una firma lo dice `Firma::columnas` — el mismo sitio que
 * usan los otros comandos y la pantalla `/docente/revisar`.
 */
class SignVocabulario extends Command
{
    protected $signature = 'vocabulario:firmar
        {--lengua= : Lengua a publicar (it, fr, de, zh)}
        {--unidad= : Solo las tarjetas de una unidad (opcional)}
        {--docente= : Id del usuario que firma (queda en reviewed_by)}';

    protected $description = 'Firma tarjetas de vocabulario revisadas para que se sirvan a los alumnos';

    public function handle(): int
    {
        $lengua = $this->option('lengua');
        if ($lengua === null || ! in_array($lengua, Lenguas::LISTA, true)) {
            $this->error('Di QUÉ lengua firmas, de la lista: --lengua='.implode('|', Lenguas::LISTA).'.');

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

        $pendientes = Tarjeta::query()
            ->whereNull('reviewed_at')
            ->where('lengua', $lengua)
            ->when($this->option('unidad') !== null, fn ($q) => $q->where('unidad', (int) $this->option('unidad')));

        $cuantas = (clone $pendientes)->count();
        if ($cuantas === 0) {
            $this->info("No hay tarjetas pendientes de firma en «{$lengua}».");

            return self::SUCCESS;
        }

        $pendientes->update(Firma::columnas($docente));

        $this->info(sprintf(
            '%d tarjeta(s) firmadas en «%s»%s. Ya se sirven a los alumnos.',
            $cuantas, $lengua,
            $docente ? " por {$docente->name}" : ' (sin autoría registrada)',
        ));

        return self::SUCCESS;
    }
}
