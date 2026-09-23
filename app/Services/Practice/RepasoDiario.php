<?php

namespace App\Services\Practice;

use App\Models\LearningObjective;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Services\Curso\CursoDeLenguas;
use App\Services\Lesson\DestinosDeBloque;
use Illuminate\Support\Collection;

/**
 * «TU REPASO DE HOY»: hasta diez ítems, elegidos en este orden de prioridad
 * (misión 3, §2):
 *
 *  1. Descriptores VENCIDOS del repaso espaciado — los que `RepasoService`
 *     ya calcula por `repaso_en` (el más atrasado primero). Ojo: eso es
 *     «tocan repaso según el calendario», no literalmente «dominados hace
 *     más tiempo»: un descriptor entra en el calendario desde el primer
 *     intento, dominado o no. Se usa tal cual porque ES el repaso espaciado y
 *     inventar otra cola sería tener dos. Se sirve OTRO ítem del descriptor: el
 *     que el alumno tocó hace más tiempo, o nunca.
 *  2. Descriptores con FALLO RECIENTE (su último intento fue incorrecto). Se
 *     sirve el ítem fallado: volver a la pregunta que falló es lo que Khan hace.
 *  3. RELLENO con ítems no vistos de las unidades con contenido, hasta diez.
 *
 * Las prioridades 1 y 2 van con `repaso: true` en el billete (cuentan para el
 * dominio, NO para la nota AGS: es repaso). La 3 son ítems nuevos, y cuentan.
 *
 * Es «el repaso de HOY»: la semilla es (lengua, quién, fecha de Ecuador), así
 * que volver a abrirlo el mismo día trae el mismo repaso, y mañana otro. El
 * invitado no tiene historia: solo relleno (prioridad 3), y no escribe nada.
 */
final class RepasoDiario
{
    public const TAMANO = 10;

    public function __construct(
        private readonly RepasoService $repaso,
        private readonly CursoDeLenguas $curso,
    ) {}

    /** La semilla del día: el mismo repaso todo el día, otro mañana. */
    public static function semilla(string $lengua, int|string $quien): string
    {
        $fecha = now(RachaDeAlumno::ZONA)->toDateString();

        return hash('sha256', "repaso-diario:{$lengua}:{$quien}:{$fecha}");
    }

    /**
     * @return Collection<int, array{item: PracticeItem, prioridad: int, repaso: bool}>
     */
    public function componer(?int $userId, string $lengua): Collection
    {
        $semilla = self::semilla($lengua, $userId ?? Practitioner::CLAVE_INVITADO);
        $elegidos = collect();
        $usados = [];

        $anadir = function (PracticeItem $item, int $prioridad, bool $repaso) use (&$elegidos, &$usados) {
            if (isset($usados[$item->id]) || $elegidos->count() >= self::TAMANO) {
                return;
            }
            $usados[$item->id] = true;
            $elegidos->push(['item' => $item, 'prioridad' => $prioridad, 'repaso' => $repaso]);
        };

        if ($userId !== null) {
            // ---- 1. vencidos del repaso espaciado: OTRO ítem del descriptor ----
            foreach ($this->repaso->vencidos($userId, $lengua) as $objId) {
                $item = $this->itemMenosRecienteDe($objId, $userId, $lengua, array_keys($usados));
                if ($item !== null) {
                    $anadir($item, 1, true);
                }
            }

            // ---- 2. fallos recientes: el ítem que falló ----
            foreach ($this->itemsFalladosRecientes($userId, $lengua) as $item) {
                $anadir($item, 2, true);
            }
        }

        // ---- 3. relleno: no vistos de las unidades con contenido ----
        if ($elegidos->count() < self::TAMANO) {
            foreach ($this->noVistos($userId, $lengua, $semilla, array_keys($usados)) as $item) {
                $anadir($item, 3, false);
            }
        }

        return $elegidos->take(self::TAMANO)->values();
    }

    /**
     * De los ítems firmados de un descriptor en esta lengua, el que el alumno
     * tocó hace MÁS tiempo (o nunca). Sin reposición dentro del repaso.
     */
    private function itemMenosRecienteDe(string $objectiveId, int $userId, string $lengua, array $excluir): ?PracticeItem
    {
        $items = PracticeItem::query()
            ->where('objective_id', $objectiveId)
            ->where('lengua', $lengua)
            ->whereNotNull('reviewed_at')
            ->whereNotIn('id', $excluir)
            ->with('objective:id,native_code,statement')
            ->orderBy('seq')->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        $ultimoIntento = PracticeAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('item_id', $items->pluck('id'))
            ->selectRaw('item_id, max(created_at) as ultimo')
            ->groupBy('item_id')
            ->pluck('ultimo', 'item_id');

        // Nunca tocado primero; después el más antiguo. Desempate por seq/id,
        // que ya viene del orderBy: `sortBy` es estable.
        return $items->sortBy(fn (PracticeItem $i) => $ultimoIntento[$i->id] ?? '0000')->first();
    }

    /**
     * Los ítems cuyo ÚLTIMO intento del alumno (en esta lengua) fue incorrecto,
     * uno por descriptor, el fallo más reciente primero.
     *
     * @return Collection<int, PracticeItem>
     */
    private function itemsFalladosRecientes(int $userId, string $lengua): Collection
    {
        $itemsDeLengua = PracticeItem::query()
            ->where('lengua', $lengua)
            ->whereNotNull('reviewed_at')
            ->select('id');

        // El último intento por DESCRIPTOR se resuelve en PHP: la ventana en
        // SQL diverge entre SQLite y PostgreSQL. El volumen es el del alumno.
        $intentos = PracticeAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('item_id', $itemsDeLengua)
            // Solo PRIMEROS intentos (PR 13): un fallo que se salvó a la tercera
            // sigue siendo un fallo reciente — necesitó ayuda, vuelve mañana.
            ->whereNull('reintento')
            ->with('item:id,objective_id')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get(['id', 'item_id', 'is_correct', 'created_at']);

        $fallados = $intentos
            ->unique(fn (PracticeAttempt $a) => $a->item->objective_id)   // el último por descriptor
            ->filter(fn (PracticeAttempt $a) => ! $a->is_correct)
            ->pluck('item_id');

        if ($fallados->isEmpty()) {
            return collect();
        }

        $items = PracticeItem::query()
            ->whereIn('id', $fallados)
            ->with('objective:id,native_code,statement')
            ->get()
            ->keyBy('id');

        // En el orden del fallo (más reciente primero).
        return $fallados->map(fn ($id) => $items[$id] ?? null)->filter()->values();
    }

    /**
     * Ítems firmados de la lengua, de las unidades del curso, que el alumno no
     * ha intentado nunca (el invitado: todos), en orden determinista por la
     * semilla del día.
     *
     * @return Collection<int, PracticeItem>
     */
    private function noVistos(?int $userId, string $lengua, string $semilla, array $excluir): Collection
    {
        $codes = collect($this->curso->unidades($lengua))
            ->flatMap(fn ($u) => $u['descriptores'])->unique()->values();
        $versiones = DestinosDeBloque::versionesDe($this->curso->marco($lengua));
        if ($versiones === null || $codes->isEmpty()) {
            return collect();
        }

        $descriptores = LearningObjective::query()
            ->whereIn('version_id', $versiones)->whereIn('native_code', $codes)->pluck('id');

        $q = PracticeItem::query()
            ->whereIn('objective_id', $descriptores)
            ->where('lengua', $lengua)
            ->whereNotNull('reviewed_at')
            ->whereNotIn('id', $excluir)
            ->with('objective:id,native_code,statement');

        if ($userId !== null) {
            $q->whereNotIn('id', PracticeAttempt::query()->where('user_id', $userId)->select('item_id'));
        }

        return $q->get()
            ->sortBy(fn (PracticeItem $i) => [hash('sha256', "{$semilla}:item:{$i->id}"), $i->id])
            ->values();
    }
}
