<?php

namespace App\Console\Commands;

use App\Models\CurNode;
use App\Models\LearningObjective;
use App\Models\Track;
use App\Models\TrackPhase;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ata el trayecto ORDINARIO al grafo: una fase por SUBNIVEL con las destrezas
 * de los grados que lo componen.
 *
 *   php artisan curriculo:fases-ord
 *
 * Por qué por subnivel y no por grado: el currículo 2016 define las destrezas
 * POR SUBNIVEL (el importador las replica en cada grado; los grados las
 * secuencian en el PCA). Cinco fases dicen la verdad del documento; trece
 * fases-grado repetían la misma destreza tres veces sin añadir información.
 *
 * IDEMPOTENTE: se puede correr antes o después del importador oficial, y
 * recoge las destrezas que hayan aparecido entre medias.
 */
class FasesOrd extends Command
{
    // SIN --track a propósito: apuntado a un PCEI, este comando borraba su
    // fase 0 propedéutica —obligatoria por la reforma 2025-00010-A— y le
    // encajaba la dosificación de ORD. El trayecto es fijo (auditoría).
    protected $signature = 'curriculo:fases-ord
        {--force : Permite retirar fases antiguas que YA tengan destrezas atadas}';

    protected $description = 'Crea las fases por subnivel del trayecto ordinario y les ata sus destrezas';

    /** seq → [etiqueta, grados del subnivel]. El orden ES el del currículo. */
    private const SUBNIVELES = [
        1 => ['Preparatoria', ['g1']],
        2 => ['Elemental', ['g2', 'g3', 'g4']],
        3 => ['Media', ['g5', 'g6', 'g7']],
        4 => ['Superior', ['g8', 'g9', 'g10']],
        5 => ['Bachillerato', ['g11', 'g12', 'g13']],
    ];

    public function handle(): int
    {
        $track = Track::where('code', 'ORD')->first();
        if (! $track) {
            $this->error('No existe el trayecto ORD. ¿Falta sembrar el grafo?');

            return self::FAILURE;
        }

        // Guarda de seguridad: las fases que este comando retiraría son las
        // fases-grado VACÍAS que dejó el seeder. Si alguna tiene destrezas
        // atadas, alguien las mapeó a mano y borrarlas perdería ese trabajo.
        $sobrantes = $track->phases()->whereNotIn('seq', array_keys(self::SUBNIVELES))->get();
        $conDatos = $sobrantes->filter(fn (TrackPhase $f) => $f->objectives()->exists());
        if ($conDatos->isNotEmpty() && ! $this->option('force')) {
            $this->error(sprintf(
                'ABORTADO: %d fase(s) antiguas de ORD tienen destrezas atadas (seq: %s). '
                .'Repite con --force si de verdad quieres retirarlas.',
                $conDatos->count(), $conDatos->pluck('seq')->implode(', '),
            ));

            return self::FAILURE;
        }

        $total = 0;

        DB::transaction(function () use ($track, $sobrantes, &$total) {
            foreach (self::SUBNIVELES as $seq => [$etiqueta, $grados]) {
                $fase = TrackPhase::updateOrCreate(
                    ['track_id' => $track->id, 'seq' => $seq],
                    [
                        'label' => ['es' => $etiqueta],
                        // La fase abarca varios grados: no cuelga de un nodo.
                        'grade_node_id' => null,
                        'is_propedeutic' => false,
                    ],
                );

                $ids = $this->destrezasDe($grados);
                // sync() y no attach(): re-correr no duplica, y una destreza que
                // desapareciera del grafo tampoco se queda colgando en la fase.
                $fase->objectives()->sync(
                    $ids->mapWithKeys(fn ($id) => [$id => ['source' => 'mapeo-interno']])->all(),
                );

                $this->line(sprintf('  %-14s %d destreza(s)', $etiqueta, $ids->count()));
                $total += $ids->count();
            }

            // Las fases que este comando no gobierna (las de grado que dejó el
            // seeder) desaparecen: si no, el panel sumaría dos veces.
            foreach ($sobrantes as $fase) {
                $fase->objectives()->detach();
                $fase->delete();
            }
            if ($sobrantes->isNotEmpty()) {
                $this->comment(sprintf('  Retiradas %d fase(s) antiguas por grado.', $sobrantes->count()));
            }
        });

        $this->info(sprintf(
            'Trayecto %s: 5 fases por subnivel con %d destreza(s) en total.',
            $track->code, $total,
        ));

        return self::SUCCESS;
    }

    /**
     * Las destrezas que cuelgan de esos grados (el grado y todo su subárbol).
     *
     * @param  list<string>  $grados
     * @return Collection<int, string>
     */
    private function destrezasDe(array $grados): Collection
    {
        $nodos = CurNode::query()->where('node_type', 'grado')->whereIn('native_code', $grados)->get();

        $ids = collect();
        foreach ($nodos as $grado) {
            $ids = $ids->concat(
                CurNode::query()->descendantsOf($grado)->pluck('id')->push($grado->id),
            );
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        return LearningObjective::query()
            ->whereIn('node_id', $ids->unique())
            ->orderBy('native_code')
            ->pluck('id');
    }
}
