<?php

namespace App\Console\Commands;

use App\Models\PracticeItem;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Publica ítems de práctica ya revisados por un docente.
 *
 *   php artisan practica:firmar --bloque=LL.4.1
 *   php artisan practica:firmar --bloque=LL.4.1 --docente=7
 *
 * Un ítem sembrado por `practica:sembrar` nace SIN firmar y no llega a ningún
 * alumno (`LearningObjective::practiceItems()` solo devuelve lo firmado). Esto
 * es lo que abre la puerta, bloque a bloque, cuando alguien los ha leído.
 *
 * NO hay un `--todo` a secas por diseño: firmar las 80 preguntas de golpe sería
 * exactamente lo que este parche vino a impedir. Para hacerlo hace falta
 * `--todo --lo-he-revisado`, que deja constancia de que alguien lo afirmó.
 */
class SignPracticeItems extends Command
{
    protected $signature = 'practica:firmar
        {--bloque= : Bloque a publicar, p. ej. LL.4.1}
        {--docente= : Id del usuario que firma (queda registrado en reviewed_by)}
        {--todo : Firma TODO lo pendiente (exige --lo-he-revisado)}
        {--lo-he-revisado : Confirma que alguien ha leído lo que va a publicar}';

    protected $description = 'Firma ítems de práctica revisados para que se sirvan a los alumnos';

    public function handle(): int
    {
        $bloque = $this->option('bloque');
        $todo = (bool) $this->option('todo');

        if ($bloque === null && ! $todo) {
            $this->error('Di QUÉ firmas: --bloque=LL.4.1 o --todo --lo-he-revisado.');

            return self::FAILURE;
        }

        if ($todo && ! $this->option('lo-he-revisado')) {
            $this->error('--todo publica el banco entero a alumnos reales.');
            $this->line('  Si de verdad lo has revisado, dilo: --todo --lo-he-revisado');

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

        $pendientes = PracticeItem::query()
            ->whereNull('reviewed_at')
            ->when($bloque !== null, fn ($q) => $q->where(
                'attrs->revision->bloque', $bloque,
            ));

        $cuantos = (clone $pendientes)->count();
        if ($cuantos === 0) {
            $this->info('No hay nada pendiente de firma'.($bloque ? " en {$bloque}." : '.'));

            return self::SUCCESS;
        }

        $pendientes->update([
            'reviewed_at' => now(),
            // Sin --docente queda NULL: nadie en concreto lo firmó. Inventar una
            // autoría sería peor que dejarla vacía (misma regla que el crosswalk).
            'reviewed_by' => $docente?->id,
        ]);

        $this->info(sprintf(
            '%d ítem(s) firmados%s%s. Ya se sirven a los alumnos.',
            $cuantos,
            $bloque ? " de {$bloque}" : '',
            $docente ? " por {$docente->name}" : ' (sin autoría registrada)',
        ));

        return self::SUCCESS;
    }
}
