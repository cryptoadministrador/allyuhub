<?php

namespace App\Services\Curso;

use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Services\Lesson\DestinosDeBloque;
use Illuminate\Support\Collection;

/**
 * EL ESTADO DEL CURSO, calculado en el SERVIDOR.
 *
 * Un mapa de unidades, una barra de dominio y una racha son HTML: la lógica de
 * «qué unidad está abierta, cuál terminada, cuál es la única cosa que hacer
 * ahora» vive aquí y llega a la página como props. React pinta. Es la misma
 * decisión que MathML-en-el-servidor: mantiene el cascarón en ~pocos KB de JS
 * y pone la regla de oro (el invitado ve la forma con avance a cero) en un solo
 * sitio, donde ya está `Practitioner`.
 *
 * La LENGUA es cerrada: solo se pregunta por las de `Practice\Lenguas::LISTA`,
 * y el contenido de una unidad es el de ESA lengua sobre sus descriptores —
 * nunca se mezcla italiano con alemán ni con el contenido sin lengua de MINEDEC.
 */
class CursoDeLenguas
{
    /** @var array<int, array{titulo: string, puede: string, descriptores: list<string>}> */
    private array $unidades;

    /** @var array<string, string> */
    private array $nombres;

    public function __construct()
    {
        $datos = require database_path('data/cursos-lenguas.php');
        $this->unidades = $datos['unidades'];
        $this->nombres = $datos['nombres'];
    }

    public function nombre(string $lengua): string
    {
        return $this->nombres[$lengua] ?? strtoupper($lengua);
    }

    public function existeUnidad(int $n): bool
    {
        return isset($this->unidades[$n]);
    }

    /**
     * El estado completo del curso para la PORTADA.
     *
     * @return array<string, mixed>
     */
    public function portada(string $lengua, ?int $userId): array
    {
        $ctx = $this->contexto($lengua, $userId);

        $unidades = collect($this->unidades)->map(
            fn (array $u, int $n) => $this->estadoDeUnidad($n, $u, $lengua, $ctx),
        )->values();

        return [
            'lengua' => $lengua,
            'nombre' => $this->nombre($lengua),
            'unidades' => $unidades->all(),
            'siguiente' => $this->siguientePaso($lengua, $unidades),
        ];
    }

    /**
     * La UNIDAD: sus «Puedo…» como objetivos del alumno, con su dominio y sus
     * enlaces a practicar.
     *
     * @return array<string, mixed>
     */
    public function unidad(string $lengua, int $n, ?int $userId): array
    {
        $u = $this->unidades[$n];
        $ctx = $this->contexto($lengua, $userId);

        $puedo = collect($u['descriptores'])
            ->unique()
            ->map(function (string $code) use ($lengua, $ctx) {
                $obj = $ctx['descriptores']->get($code);
                if ($obj === null) {
                    return null;
                }
                $tieneItems = ($ctx['itemsPorDescriptor'][$obj->id] ?? 0) > 0;
                $mastery = $ctx['masteryPorDescriptor'][$obj->id] ?? null;

                return [
                    'descriptor_id' => $obj->id,
                    'code' => $code,
                    'statement' => $obj->statement['es'] ?? '',
                    'dominio' => $mastery === null ? 0.0 : round((float) $mastery->mastery, 2),
                    'dominado' => $mastery?->mastered_at !== null,
                    'has_items' => $tieneItems,
                    'url_practicar' => $tieneItems ? "/practicar/{$obj->id}?lengua={$lengua}" : null,
                    'has_leccion' => ($ctx['leccionPorDescriptor'][$obj->id] ?? null) !== null,
                    'leccion_url' => ($ctx['leccionPorDescriptor'][$obj->id] ?? null)
                        ? '/recurso/'.$ctx['leccionPorDescriptor'][$obj->id]
                        : null,
                ];
            })
            ->filter()
            ->values();

        $estado = $this->estadoDeUnidad($n, $u, $lengua, $ctx);

        return [
            'lengua' => $lengua,
            'nombre' => $this->nombre($lengua),
            'unidad' => ['n' => $n, 'titulo' => $u['titulo'], 'resumen' => $u['puede']],
            'estado' => $estado['estado'],
            'dominio' => $estado['dominio'],
            'puedo' => $puedo->all(),
            'siguiente' => $puedo->first(fn ($p) => $p['has_items'] && ! $p['dominado']),
        ];
    }

    /**
     * Todo lo que la portada y la unidad necesitan, en consultas ACOTADAS —
     * el coste no crece con el número de unidades ni de descriptores.
     *
     * @return array{descriptores: Collection, itemsPorDescriptor: array, leccionPorDescriptor: array, masteryPorDescriptor: array}
     */
    private function contexto(string $lengua, ?int $userId): array
    {
        $codes = collect($this->unidades)->flatMap(fn ($u) => $u['descriptores'])->unique()->values();
        $versiones = DestinosDeBloque::versionesDe('CEFR');

        $descriptores = $versiones === null
            ? collect()
            : LearningObjective::query()
                ->whereIn('version_id', $versiones)
                ->whereIn('native_code', $codes)
                ->get()
                ->keyBy('native_code');

        $ids = $descriptores->pluck('id');

        // Ítems FIRMADOS de ESTA lengua, por descriptor (has_items del catálogo).
        $itemsPorDescriptor = PracticeItem::query()
            ->whereIn('objective_id', $ids)
            ->where('lengua', $lengua)
            ->whereNotNull('reviewed_at')
            ->selectRaw('objective_id, count(*) as total')
            ->groupBy('objective_id')
            ->pluck('total', 'objective_id')
            ->all();

        // Lección FIRMADA de esta lengua, por descriptor (id del recurso).
        $leccionPorDescriptor = Resource::query()
            ->published()
            ->where('kind', Resource::LECTURA)
            ->where('lengua', $lengua)
            ->whereHas('objectives', fn ($q) => $q->whereIn('objective_id', $ids))
            ->with(['objectives' => fn ($q) => $q->whereIn('objective_id', $ids)])
            ->get()
            ->flatMap(fn ($r) => $r->objectives->map(fn ($o) => [$o->id, $r->id]))
            ->reduce(function ($acc, $par) {
                $acc[$par[0]] ??= $par[1];   // la primera lección por descriptor

                return $acc;
            }, []);

        // El dominio del alumno; el invitado no tiene ninguno (cero consultas
        // con un id que no casa con nadie — la regla de oro no depende de un
        // WHERE bien escrito, sino de que no haya consulta).
        $masteryPorDescriptor = $userId === null
            ? []
            : ObjectiveMastery::query()
                ->where('user_id', $userId)
                ->whereIn('objective_id', $ids)
                ->get()
                ->keyBy('objective_id')
                ->all();

        return compact('descriptores', 'itemsPorDescriptor', 'leccionPorDescriptor', 'masteryPorDescriptor');
    }

    /**
     * El estado de UNA unidad:
     *  - `proximamente`: ningún descriptor tiene contenido firmado de la lengua.
     *  - `completada`:   todos los descriptores CON contenido están dominados.
     *  - `en-curso`:     hay contenido y algo de avance.
     *  - `disponible`:   hay contenido y aún no se ha tocado.
     *
     * @return array<string, mixed>
     */
    private function estadoDeUnidad(int $n, array $u, string $lengua, array $ctx): array
    {
        $conContenido = collect($u['descriptores'])->unique()
            ->map(fn ($code) => $ctx['descriptores']->get($code))
            ->filter()
            ->filter(fn ($obj) => ($ctx['itemsPorDescriptor'][$obj->id] ?? 0) > 0
                || ($ctx['leccionPorDescriptor'][$obj->id] ?? null) !== null);

        $base = [
            'n' => $n,
            'titulo' => $u['titulo'],
            'resumen' => $u['puede'],
            'url' => "/corso/{$lengua}/u{$n}",
        ];

        if ($conContenido->isEmpty()) {
            return [...$base, 'estado' => 'proximamente', 'dominio' => 0.0];
        }

        $masteries = $conContenido->map(fn ($obj) => $ctx['masteryPorDescriptor'][$obj->id] ?? null);
        $dominio = round((float) $masteries
            ->map(fn ($m) => $m === null ? 0.0 : (float) $m->mastery)
            ->avg(), 2);

        $dominados = $masteries->filter(fn ($m) => $m?->mastered_at !== null)->count();
        $tocados = $masteries->filter(fn ($m) => $m !== null)->count();

        $estado = match (true) {
            $dominados === $conContenido->count() => 'completada',
            $tocados > 0 => 'en-curso',
            default => 'disponible',
        };

        return [...$base, 'estado' => $estado, 'dominio' => $dominio];
    }

    /**
     * LA ÚNICA COSA QUE HACER AHORA: la primera unidad no completada con
     * contenido, y dentro de ella el primer descriptor practicable no dominado.
     * Un alumno que entra y ve nueve unidades iguales no elige: se va.
     *
     * @return array<string, mixed>|null
     */
    private function siguientePaso(string $lengua, Collection $unidades): ?array
    {
        $unidad = $unidades->first(fn ($u) => in_array($u['estado'], ['disponible', 'en-curso'], true));
        if ($unidad === null) {
            return null;
        }

        $detalle = $this->unidad($lengua, $unidad['n'], null);   // solo para la forma
        $descriptor = collect($detalle['puedo'])->first(fn ($p) => $p['has_items']);

        return $descriptor === null ? null : [
            'lengua' => $lengua,
            'unidad' => $unidad['n'],
            'titulo' => $unidad['titulo'],
            'url' => "/corso/{$lengua}/u{$unidad['n']}",
        ];
    }
}
