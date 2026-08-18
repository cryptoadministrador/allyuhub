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

        // La clave lleva la VERSIÓN además del path: dos versiones del mismo
        // marco reutilizan los paths («bgu.g1» existe en las dos), así que
        // agrupar solo por path sumaba las destrezas de ambas en cada tarjeta
        // (auditoría). Hoy el catálogo pinta una sola versión y no se notaba.
        // [version|path => ['t' => total, 'v' => verificadas, 'p' => practicables]]
        $porPath = [];
        $clave = fn ($version, $ruta) => $version.'|'.$ruta;

        foreach ($base()->groupBy('cn.version_id', 'cn.path', 'lo.is_verified')
            ->selectRaw('cn.version_id as version, cn.path as ruta, lo.is_verified as verificada, count(*) as n')
            ->get() as $fila) {
            $k = $clave($fila->version, $fila->ruta);
            $porPath[$k]['t'] = ($porPath[$k]['t'] ?? 0) + (int) $fila->n;
            if ((bool) $fila->verificada) {
                $porPath[$k]['v'] = ($porPath[$k]['v'] ?? 0) + (int) $fila->n;
            }
        }

        foreach ($base()
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('practice_items')
                ->whereColumn('practice_items.objective_id', 'lo.id'))
            ->groupBy('cn.version_id', 'cn.path')
            ->selectRaw('cn.version_id as version, cn.path as ruta, count(*) as n')
            ->get() as $fila) {
            $porPath[$clave($fila->version, $fila->ruta)]['p'] = (int) $fila->n;
        }

        $resultado = [];
        foreach ($nodos as $nodo) {
            // El punto cierra el prefijo: sin él, «bgu.g1» se tragaría «bgu.g11».
            $prefijo = $clave($nodo->version_id, $nodo->path).'.';
            $propio = $clave($nodo->version_id, $nodo->path);
            $suma = ['destrezas' => 0, 'verificadas' => 0, 'practicables' => 0];

            foreach ($porPath as $k => $cuentas) {
                if ($k !== $propio && ! str_starts_with($k, $prefijo)) {
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
