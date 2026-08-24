<?php

namespace App\Console\Commands;

use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Publica lecciones ya revisadas por un docente.
 *
 *   php artisan lecciones:firmar --bloque=M.4.1
 *   php artisan lecciones:firmar --bloque=M.4.1 --docente=7
 *
 * Una lección sembrada por `lecciones:sembrar` nace SIN firmar y no la ve nadie
 * (`Resource::published()` exige `reviewed_at` en la versión vigente). Esto es
 * lo que abre la puerta, bloque a bloque, cuando alguien la ha leído.
 *
 * Mismo trato que `practica:firmar`, y por una razón más fuerte: una pregunta
 * mal escrita se falla y se corrige; un texto mal escrito se cree.
 */
class SignLessons extends Command
{
    protected $signature = 'lecciones:firmar
        {--bloque= : Bloque a publicar, p. ej. M.4.1}
        {--docente= : Id del usuario que firma (queda registrado en reviewed_by)}
        {--todo : Firma TODO lo pendiente (exige --lo-he-revisado)}
        {--lo-he-revisado : Confirma que alguien ha leído lo que va a publicar}';

    protected $description = 'Firma lecciones revisadas para que se sirvan a los alumnos';

    public function handle(): int
    {
        $bloque = $this->option('bloque');
        $todo = (bool) $this->option('todo');

        if ($bloque === null && ! $todo) {
            $this->error('Di QUÉ firmas: --bloque=M.4.1 o --todo --lo-he-revisado.');

            return self::FAILURE;
        }

        if ($todo && ! $this->option('lo-he-revisado')) {
            $this->error('--todo publica todas las lecciones a alumnos reales.');
            $this->line('  Si de verdad las has leído, dilo: --todo --lo-he-revisado');

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

        $pendientes = ResourceVersion::query()
            ->whereNull('reviewed_at')
            ->whereHas('resource', fn ($q) => $q->where('kind', Resource::LECTURA))
            ->when($bloque !== null, fn ($q) => $q->where('config->bloque', $bloque));

        $cuantas = (clone $pendientes)->count();
        if ($cuantas === 0) {
            $this->info('No hay lecciones pendientes de firma'.($bloque ? " en {$bloque}." : '.'));

            return self::SUCCESS;
        }

        $pendientes->update([
            'reviewed_at' => now(),
            // Sin --docente queda NULL: nadie en concreto la firmó. Inventar una
            // autoría sería peor que dejarla vacía (misma regla que el crosswalk).
            'reviewed_by' => $docente?->id,
        ]);

        $this->info(sprintf(
            '%d lección(es) firmadas%s%s. Ya se leen.',
            $cuantas,
            $bloque ? " de {$bloque}" : '',
            $docente ? " por {$docente->name}" : ' (sin autoría registrada)',
        ));

        return self::SUCCESS;
    }
}
