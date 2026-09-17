<?php

namespace App\Services\Curso;

use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\PruebaUnidad;
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
    /** @var array<string, array{marco: string, unidades: array, productivas: array}> */
    private array $cursos;

    /** @var array<string, string> */
    private array $nombres;

    public function __construct()
    {
        $datos = require database_path('data/cursos-lenguas.php');
        $this->cursos = $datos['cursos'];
        $this->nombres = $datos['nombres'];
    }

    public function nombre(string $lengua): string
    {
        return $this->nombres[$lengua] ?? strtoupper($lengua);
    }

    /**
     * EL MARCO DE ESTE CURSO. Estaba escrito `'CEFR'` dentro de `contexto()`, y
     * era cierto solo porque las cuatro lenguas eran del MCER: el inglés de
     * Cambridge cuelga de CAIE. Lo declara el curso, no el servicio.
     */
    public function marco(string $lengua): string
    {
        return $this->cursos[$lengua]['marco'] ?? 'CEFR';
    }

    /**
     * Las unidades de ESTE curso: nueve para el MCER, tres Stages para el
     * inglés. El servicio ya no sabe cuántas hay.
     *
     * @return array<int, array{titulo: string, puede: string, descriptores: list<string>}>
     */
    public function unidades(string $lengua): array
    {
        return $this->cursos[$lengua]['unidades'] ?? [];
    }

    /**
     * Qué destreza admite tarea de escritura y cuál de voz, POR CURSO. Vacío =
     * este curso no tiene tarea de producción todavía.
     *
     * @return array<string, string>
     */
    public function productivas(string $lengua): array
    {
        return $this->cursos[$lengua]['productivas'] ?? [];
    }

    public function existeUnidad(string $lengua, int $n): bool
    {
        return isset($this->cursos[$lengua]['unidades'][$n]);
    }

    public function tituloUnidad(string $lengua, int $n): ?string
    {
        return $this->cursos[$lengua]['unidades'][$n]['titulo'] ?? null;
    }

    /**
     * El título de la unidad `n` en el curso de `$lengua`; sin lengua, el del
     * primer curso que tenga esa unidad. Lo necesita la cola de revisión, que
     * agrupa por unidad aunque no se haya filtrado por lengua.
     */
    public function tituloDeUnidad(?string $lengua, int $n): ?string
    {
        if ($lengua !== null) {
            return $this->tituloUnidad($lengua, $n);
        }

        foreach ($this->cursos as $curso) {
            if (isset($curso['unidades'][$n])) {
                return $curso['unidades'][$n]['titulo'];
            }
        }

        return null;
    }

    /**
     * Mapa descriptor → PRIMERA unidad que lo cubre.
     *
     * Un descriptor aparece en varias unidades (A1.PO.2 está en seis), así que
     * «la unidad de una pieza» necesita un criterio: la primera. Es arbitrario
     * pero DETERMINISTA, que es lo que hace falta para agrupar la revisión sin
     * que la misma pieza salga en seis sitios.
     *
     * Con `$lengua`, el mapa de ESE curso. Sin ella, la union de todos: la cola
     * de revision sin filtro tiene que poder colocar una pieza de cualquier
     * lengua, y dos cursos del mismo marco comparten descriptores.
     *
     * @return array<string, int>
     */
    public function unidadesPorDescriptor(?string $lengua = null): array
    {
        $cursos = $lengua === null
            ? $this->cursos
            : array_intersect_key($this->cursos, [$lengua => true]);

        $mapa = [];
        foreach ($cursos as $curso) {
            foreach ($curso['unidades'] as $n => $u) {
                foreach ($u['descriptores'] as $code) {
                    $mapa[$code] ??= (int) $n;
                }
            }
        }

        return $mapa;
    }

    /**
     * El estado completo del curso para la PORTADA.
     *
     * @return array<string, mixed>
     */
    public function portada(string $lengua, ?int $userId): array
    {
        $ctx = $this->contexto($lengua, $userId);

        $unidades = collect($this->unidades($lengua))->map(
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
        $u = $this->unidades($lengua)[$n];
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
            // Hay prueba si algún descriptor tiene ítems firmados; la unidad
            // enlaza a ella solo entonces (nunca un enlace a un 404).
            'tiene_prueba' => $puedo->contains(fn ($p) => $p['has_items']),
            'prueba_aprobada' => in_array($n, $ctx['unidadesAprobadas'], true),
            // «Vocabulario: 12 / 17 palabras» — solo tarjetas FIRMADAS; con
            // total 0 la unidad no enlaza al mazo (nunca un enlace a un 404).
            'vocabulario' => (new MazoDeVocabulario)->cuenta($lengua, $n, $userId),
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
        $codes = collect($this->unidades($lengua))->flatMap(fn ($u) => $u['descriptores'])->unique()->values();
        $versiones = DestinosDeBloque::versionesDe($this->marco($lengua));

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

        // Las unidades cuya PRUEBA aprobó el alumno: es la otra vía a
        // «completada» (la primera es dominar todos los descriptores). El
        // invitado no tiene pruebas guardadas — ni una consulta con user_id nulo.
        $unidadesAprobadas = $userId === null
            ? []
            : PruebaUnidad::query()
                ->where('user_id', $userId)
                ->where('lengua', $lengua)
                ->where('aprobada', true)
                ->pluck('unidad')
                ->unique()
                ->all();

        return compact('descriptores', 'itemsPorDescriptor', 'leccionPorDescriptor', 'masteryPorDescriptor', 'unidadesAprobadas');
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

        // «Completada» por DOS vías: todos los descriptores dominados, o la
        // PRUEBA de la unidad aprobada (≥ 8/10, PR 8) — que es lo que en Khan
        // cierra una unidad. Ninguna bloquea la siguiente.
        $estado = match (true) {
            in_array($n, $ctx['unidadesAprobadas'], true) => 'completada',
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
