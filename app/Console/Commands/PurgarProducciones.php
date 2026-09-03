<?php

namespace App\Console\Commands;

use App\Models\Produccion;
use App\Services\Produccion\AlmacenDeProducciones;
use App\Services\Produccion\AnioLectivo;
use Illuminate\Console\Command;

/**
 * BORRA LA GRABACIÓN de las producciones de años lectivos ya CERRADOS, y
 * conserva la nota del docente. La retención de voz/texto de menores es de un
 * año lectivo; la evaluación (rúbrica + comentario) sobrevive.
 *
 *   php artisan producciones:purgar --dry-run     # lista lo que borraría
 *   php artisan producciones:purgar               # borra los años cerrados
 *   php artisan producciones:purgar --anio=2025-2026
 *
 * Sin `--anio` purga TODOS los años estrictamente anteriores al actual (los
 * cerrados). Con `--anio`, solo ese. `--dry-run` LISTA antes de tocar nada:
 * borrar voz de menores no se hace a ciegas.
 */
class PurgarProducciones extends Command
{
    protected $signature = 'producciones:purgar
        {--anio= : Año lectivo concreto (YYYY-YYYY). Por defecto, todos los cerrados.}
        {--dry-run : Lista lo que borraría sin borrar nada.}';

    protected $description = 'Borra la grabación de producciones de años cerrados; conserva la nota del docente.';

    public function handle(AlmacenDeProducciones $almacen): int
    {
        $anio = $this->option('anio');
        $seco = (bool) $this->option('dry-run');
        $actual = AnioLectivo::actual();

        $query = Produccion::query()
            // Aún tiene grabación que borrar (no purgada ya).
            ->whereNull('purgada_en')
            ->where(fn ($q) => $q->whereNotNull('archivo')->orWhereNotNull('texto'));

        if ($anio !== null) {
            if ($anio === $actual) {
                $this->warn("«{$actual}» es el año EN CURSO: no se purga hasta que cierre.");

                return self::INVALID;
            }
            $query->where('anio_lectivo', $anio);
        } else {
            // Todos los años estrictamente anteriores al actual (cerrados).
            $query->where('anio_lectivo', '<', $actual);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No hay grabaciones que purgar.');

            return self::SUCCESS;
        }

        $this->line(($seco ? '[dry-run] ' : '')."Grabaciones a purgar: {$total}");
        $this->table(
            ['año', 'tipo', 'estado', 'creada'],
            (clone $query)->orderBy('anio_lectivo')->limit(50)->get()
                ->map(fn (Produccion $p) => [
                    $p->anio_lectivo, $p->tipo, $p->estado, $p->created_at->toDateString(),
                ])->all(),
        );
        if ($total > 50) {
            $this->line('… y '.($total - 50).' más.');
        }

        if ($seco) {
            $this->comment('Nada borrado (--dry-run). La nota del docente se conservaría en todas.');

            return self::SUCCESS;
        }

        $purgadas = 0;
        (clone $query)->chunkById(200, function ($lote) use ($almacen, &$purgadas) {
            foreach ($lote as $p) {
                $p->purgarGrabacion($almacen);
                $purgadas++;
            }
        });

        $this->info("Purgadas {$purgadas} grabaciones. La nota del docente sigue viva en todas.");

        return self::SUCCESS;
    }
}
