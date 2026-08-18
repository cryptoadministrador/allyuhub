<?php

namespace App\Services\Catalog;

use App\Models\CurNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuántas destrezas cuelgan del SUBÁRBOL de cada nodo, para las tarjetas del
 * catálogo (un grado no tiene destrezas propias: cuelgan de sus bloques).
 *
 * Dos consultas para TODOS los nodos que se le pasen, pase lo que pase — nada
 * de una consulta por tarjeta. Se agrega por `path` porque es lo único que
 * relaciona un nodo con su subárbol sin recursión, y se suma en PHP para que
 * funcione igual en pgsql (ltree) y en sqlite (texto).
 */
class SubtreeCounts
{
    /**
     * @param  Collection<int, CurNode>|array<int, CurNode>  $nodos
     * @return array<string, array{destrezas:int, verificadas:int, practicables:int}>
     */
    public static function para($nodos): array
    {
        $nodos = collect($nodos)->filter(fn ($n) => $n instanceof CurNode && $n->path !== null);
        if ($nodos->isEmpty()) {
            return [];
        }

        $versiones = $nodos->pluck('version_id')->unique()->values()->all();

        $base = fn () => DB::table('learning_objectives as lo')
            ->join('cur_nodes as cn', 'cn.id', '=', 'lo.node_id')
            ->whereIn('cn.version_id', $versiones);

        // [path => ['t' => total, 'v' => verificadas, 'p' => practicables]]
        $porPath = [];

        foreach ($base()->groupBy('cn.path', 'lo.is_verified')
            ->selectRaw('cn.path as ruta, lo.is_verified as verificada, count(*) as n')->get() as $fila) {
            $porPath[$fila->ruta]['t'] = ($porPath[$fila->ruta]['t'] ?? 0) + (int) $fila->n;
            if ((bool) $fila->verificada) {
                $porPath[$fila->ruta]['v'] = ($porPath[$fila->ruta]['v'] ?? 0) + (int) $fila->n;
            }
        }

        foreach ($base()
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('practice_items')
                ->whereColumn('practice_items.objective_id', 'lo.id'))
            ->groupBy('cn.path')->selectRaw('cn.path as ruta, count(*) as n')->get() as $fila) {
            $porPath[$fila->ruta]['p'] = (int) $fila->n;
        }

        $resultado = [];
        foreach ($nodos as $nodo) {
            $prefijo = $nodo->path.'.';
            $suma = ['destrezas' => 0, 'verificadas' => 0, 'practicables' => 0];

            foreach ($porPath as $ruta => $cuentas) {
                if ($ruta !== $nodo->path && ! str_starts_with($ruta, $prefijo)) {
                    continue;
                }
                $suma['destrezas'] += $cuentas['t'] ?? 0;
                $suma['verificadas'] += $cuentas['v'] ?? 0;
                $suma['practicables'] += $cuentas['p'] ?? 0;
            }

            $resultado[$nodo->id] = $suma;
        }

        return $resultado;
    }
}
