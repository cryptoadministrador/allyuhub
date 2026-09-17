<?php

namespace App\Services\Prueba;

use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\PruebaUnidad;
use App\Services\Curso\CursoDeLenguas;
use App\Services\Lesson\DestinosDeBloque;
use Illuminate\Database\Eloquent\Collection as ColeccionEloquent;
use Illuminate\Support\Collection;

/**
 * QUÉ DIEZ ÍTEMS ENTRAN EN LA PRUEBA DE UNA UNIDAD, y en qué orden.
 *
 * Reglas (misión §1, decididas):
 *
 *  - Se eligen entre los ítems FIRMADOS de los descriptores de la unidad
 *    (`cursos-lenguas.php`), de ESA lengua. Lengua cerrada en las dos
 *    direcciones: una prueba de `/corso/it/u3` no contiene ni un ítem de `fr`.
 *  - REPARTIDOS entre descriptores: se saca uno de cada descriptor por vuelta
 *    (round-robin), así que ningún descriptor acapara más de la mitad mientras
 *    otro tenga ítems que dar. Con un solo descriptor con ítems, se lleva la
 *    prueba entera — no hay a quién repartir.
 *  - SIN REPETIR ítem dentro de la prueba: cada descriptor es un mazo del que
 *    se saca sin reposición.
 *  - SEMILLA por (lengua, unidad, quién, intento), como `seedFor` en práctica:
 *    dos alumnos no ven la misma prueba en el mismo orden, y el mismo alumno
 *    que repite ve otra. Con la misma semilla, la misma prueba —es lo que hace
 *    que `entregar` pueda comprobar que lo que llega es lo que se sirvió.
 *
 * El orden es determinista como en `PracticeEngine::shuffleOptions`: el peso
 * de cada ítem es un hash de (semilla, id), y el desempate es por id. Aquí
 * `shuffle()` estaría tan prohibido como `rand()`.
 */
final class PruebaDeUnidad
{
    public function __construct(private readonly CursoDeLenguas $curso) {}

    /** La semilla de la prueba: la MISMA fórmula para servir y para entregar. */
    public static function semilla(string $lengua, int $unidad, int|string $quien, int $intento): string
    {
        return hash('sha256', "prueba:{$lengua}:{$unidad}:{$quien}:{$intento}");
    }

    /**
     * Los ítems de la prueba, en orden. Vacío si la unidad no tiene nada
     * firmado en esa lengua.
     *
     * @return ColeccionEloquent<int, PracticeItem>
     */
    public function componer(string $lengua, int $unidad, int|string $quien, int $intento): ColeccionEloquent
    {
        $codes = collect($this->curso->unidades($lengua)[$unidad]['descriptores'] ?? [])->unique()->values();
        $versiones = DestinosDeBloque::versionesDe($this->curso->marco($lengua));
        if ($versiones === null || $codes->isEmpty()) {
            return new ColeccionEloquent;
        }

        $descriptores = LearningObjective::query()
            ->whereIn('version_id', $versiones)
            ->whereIn('native_code', $codes)
            ->pluck('id');

        // Ítems FIRMADOS de ESTA lengua, agrupados por descriptor. El orden de
        // llegada no importa: el peso lo pone la semilla y el desempate el id.
        $mazos = PracticeItem::query()
            ->whereIn('objective_id', $descriptores)
            ->where('lengua', $lengua)
            ->whereNotNull('reviewed_at')
            ->with('objective:id,native_code,statement')
            ->get()
            ->groupBy('objective_id');

        $semilla = self::semilla($lengua, $unidad, $quien, $intento);
        $peso = fn (string $id) => hash('sha256', "{$semilla}:item:{$id}");

        // Cada mazo barajado por la semilla, y los propios mazos también (para
        // que el descriptor que abre la prueba no sea siempre el mismo).
        $barajados = $mazos
            ->map(fn (Collection $items) => $items
                ->sortBy(fn (PracticeItem $i) => [$peso($i->id), $i->id])
                ->values())
            ->sortBy(fn (Collection $items, string $objId) => [hash('sha256', "{$semilla}:mazo:{$objId}"), $objId])
            ->values()
            ->all();

        // Round-robin: uno de cada mazo por vuelta, sin reposición, hasta diez
        // o hasta que no quede nada que sacar.
        $elegidos = [];
        $punteros = array_fill(0, count($barajados), 0);
        while (count($elegidos) < PruebaUnidad::TAMANO) {
            $sacoAlgo = false;
            foreach ($barajados as $k => $mazo) {
                if ($punteros[$k] >= $mazo->count()) {
                    continue;
                }
                $elegidos[] = $mazo[$punteros[$k]++];
                $sacoAlgo = true;
                if (count($elegidos) >= PruebaUnidad::TAMANO) {
                    break;
                }
            }
            if (! $sacoAlgo) {
                break;
            }
        }

        return new ColeccionEloquent($elegidos);
    }
}
