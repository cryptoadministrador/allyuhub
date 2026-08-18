<?php

namespace App\Services\Catalog;

use App\Models\CurNode;
use Illuminate\Support\Collection;

/**
 * El acento visual de una asignatura (icono y color) tal y como lo hereda todo
 * lo que cuelga de ella: bloques, destrezas, ejercicios.
 *
 * Vive aquí y no en un controlador porque lo usan tres páginas —catálogo,
 * ficha de destreza e inicio— y la regla de herencia («la asignatura más
 * cercana subiendo por el árbol») tiene que ser LA MISMA en las tres.
 */
class AcentoDeAsignatura
{
    /**
     * @param  Collection<int, CurNode>  $cadena  De la raíz al nodo, incluido.
     * @return array{id: string, title: string, icon: ?string, color: ?string}|null
     */
    public static function deCadena(Collection $cadena): ?array
    {
        $asignatura = $cadena->last(fn (CurNode $n) => $n->node_type === 'asignatura');

        if ($asignatura === null) {
            return null;
        }

        return [
            'id' => $asignatura->id,
            'title' => $asignatura->title['es'] ?? '',
            'icon' => $asignatura->attrs['icon'] ?? null,
            'color' => $asignatura->attrs['color'] ?? null,
        ];
    }

    /** El acento de un nodo suelto: sube por sus ancestros hasta la asignatura. */
    public static function deNodo(?CurNode $node): ?array
    {
        if ($node === null) {
            return null;
        }

        if ($node->node_type === 'asignatura') {
            return self::deCadena(collect([$node]));   // sin consultar ancestros
        }

        return self::deCadena($node->ancestors()->concat([$node]));
    }
}
