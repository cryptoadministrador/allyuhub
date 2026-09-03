<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\Revision;
use App\Models\User;
use App\Services\Curso\CursoDeLenguas;
use App\Services\Docente\Docencia;
use App\Services\Practice\Lenguas;
use App\Services\Revision\Firma;
use App\Services\Revision\Pieza;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * LA REVISIÓN DOCENTE EN PANTALLA.
 *
 * Hasta ahora firmar contenido era `php artisan practica:firmar --bloque=…` por
 * SSH. Ningún profesor de italiano va a hacer eso, así que «antes del primer
 * alumno lo lee un profesor» era una regla que no se podía cumplir. Esta
 * pantalla la hace cierta.
 *
 * Tres decisiones que gobiernan todo lo de aquí:
 *
 *  1. **La pieza se abre TAL COMO LA VE EL ALUMNO**: se reutilizan `Recurso.jsx`
 *     y `Practicar.jsx`, no hay un visor de revisión. Un visor propio revisaría
 *     una cosa distinta de la que se publica, que es el fallo clásico.
 *  2. **La firma no se reimplementa**: `Firma` es el único sitio que sabe qué
 *     escribe una firma, y de ahí tiran también los dos comandos.
 *  3. **Firmar la unidad entera exige haber MIRADO**: no es un `--todo`. Las
 *     piezas vistas se apuntan EN LA SESIÓN (servidor) al abrirlas, y el atajo
 *     comprueba que están todas. La sesión es literalmente «esa sesión».
 *
 * Autorización: un docente (instructor en algún contexto LTI activo) revisa
 * TODAS las lenguas — no existe el rol «profesor de italiano» y no se inventa.
 * Un alumno y un invitado reciben **403, no una redirección**: por eso estas
 * rutas no cuelgan del grupo `auth`, que mandaría al invitado a /entrar.
 */
class RevisionController extends Controller
{
    /** Dónde se apunta lo que el docente ha abierto en esta sesión. */
    private const VISTAS = 'revision.vistas';

    public function __construct(
        private readonly Firma $firma,
        private readonly CursoDeLenguas $curso,
    ) {}

    /** GET /docente/revisar?lengua=it&estado=pendientes|firmadas */
    public function index(Request $request)
    {
        $docente = $this->docente($request);

        // Lengua cerrada también aquí, y validada a mano: en una ruta `web` un
        // `$request->validate` fallido REDIRIGE (302) en vez de dar 422.
        $lengua = $request->query('lengua');
        abort_unless($lengua === null || in_array($lengua, Lenguas::LISTA, true), 422,
            'Lengua fuera de la lista.');

        $estado = $request->query('estado') === 'firmadas' ? 'firmadas' : 'pendientes';
        $firmadas = $estado === 'firmadas';

        $piezas = $this->piezasDe($lengua, $firmadas);
        $notas = $this->ultimasNotas($piezas);
        $vistas = $this->vistas();

        $porDescriptor = $this->curso->unidadesPorDescriptor();
        $unidades = [];

        foreach ($piezas as $pieza) {
            $descriptor = $pieza->descriptor();
            $code = $descriptor?->native_code ?? '—';
            // Una pieza cuyo descriptor no pertenece a ninguna unidad del curso
            // (contenido MINEDEC, o un descriptor nuevo) NO se esconde: cae en
            // el cajón 0, «Sin unidad». Esconderla sería perderla.
            $n = $porDescriptor[$code] ?? 0;

            $unidades[$n]['n'] = $n;
            $unidades[$n]['titulo'] = $n === 0 ? 'Sin unidad' : ($this->curso->tituloUnidad($n) ?? "Unidad {$n}");
            $unidades[$n]['descriptores'][$code]['code'] = $code;
            $unidades[$n]['descriptores'][$code]['statement'] = $descriptor?->statement['es'] ?? '';
            $unidades[$n]['descriptores'][$code]['piezas'][] = [
                'tipo' => $pieza->tipo,
                'id' => $pieza->id(),
                'titulo' => \Illuminate\Support\Str::limit($pieza->titulo(), 120),
                'lengua' => $pieza->lengua(),
                'kind' => $pieza->tipo === Pieza::ITEM ? $pieza->modelo->kind : 'reading',
                'url' => "/docente/revisar/{$pieza->tipo}/{$pieza->id()}",
                'nota' => $notas["{$pieza->tipo}:{$pieza->id()}"] ?? null,
                'vista' => in_array("{$pieza->tipo}:{$pieza->id()}", $vistas, true),
            ];
        }

        // Orden explícito por número de unidad: las claves son enteros y el
        // «Sin unidad» (0) va primero para que no se pierda de vista.
        ksort($unidades);
        $lista = [];
        foreach ($unidades as $n => $u) {
            ksort($u['descriptores']);
            $u['descriptores'] = array_values($u['descriptores']);
            $u['total'] = array_sum(array_map(fn ($d) => count($d['piezas']), $u['descriptores']));
            // Solo se puede firmar la unidad entera si TODO está visto.
            $u['todo_visto'] = collect($u['descriptores'])
                ->flatMap(fn ($d) => $d['piezas'])
                ->every(fn ($p) => $p['vista']);
            $lista[] = $u;
        }

        return Inertia::render('docente-revisar', [
            'lengua' => $lengua,
            'lenguas' => Lenguas::LISTA,
            'estado' => $estado,
            'docente' => ['id' => $docente->id, 'name' => $docente->name],
            'unidades' => $lista,
            'total' => $piezas->count(),
        ]);
    }

    /** GET /docente/revisar/{tipo}/{id} — la pieza TAL COMO LA VE EL ALUMNO. */
    public function pieza(Request $request, string $tipo, string $id)
    {
        $this->docente($request);
        $pieza = $this->localizar($tipo, $id);

        // Queda apuntada como VISTA en esta sesión: es lo que habilita el atajo
        // de firmar la unidad entera. Se apunta al ABRIRLA, en el servidor.
        $this->marcarVista($pieza);

        $comun = [
            'pieza' => [
                'tipo' => $pieza->tipo,
                'id' => $pieza->id(),
                'firmada' => $pieza->estaFirmada(),
                'lengua' => $pieza->lengua(),
                'titulo' => $pieza->titulo(),
                'code' => $pieza->descriptor()?->native_code,
            ],
            'notas' => $this->historial($pieza),
        ];

        if ($pieza->tipo === Pieza::LECCION) {
            $recurso = $pieza->recurso();
            $version = $pieza->modelo;

            return Inertia::render('docente-revisar-pieza', [
                ...$comun,
                // MISMA forma que /recurso: se pinta con `Recurso.jsx`.
                'recurso' => [
                    'id' => $recurso->id,
                    'slug' => $recurso->slug,
                    'kind' => $recurso->kind,
                    'title' => $recurso->title['es'] ?? $recurso->slug,
                    'summary' => $recurso->summary['es'] ?? null,
                    'duration_min' => $recurso->duration_min,
                    'bloques' => $version->config['bloques'] ?? [],
                    'bundle_url' => $recurso->esLeccion() ? null : $version->bundle_url,
                ],
                'destrezas' => $recurso->objectives()
                    ->get(['learning_objectives.id', 'native_code', 'statement'])
                    ->map(fn (LearningObjective $o) => [
                        'id' => $o->id,
                        'native_code' => $o->native_code,
                        'statement' => $o->statement['es'] ?? '',
                    ])->values(),
                'objective' => null,
            ]);
        }

        $objetivo = $pieza->modelo->objective;

        return Inertia::render('docente-revisar-pieza', [
            ...$comun,
            'recurso' => null,
            // MISMA forma que /practicar: se pinta con `Practicar.jsx`, que
            // pedirá el ejercicio a la API de revisión (el ítem aún no está
            // firmado, así que la API de práctica no lo serviría).
            'objective' => [
                'id' => $objetivo?->id,
                'native_code' => $objetivo?->native_code,
                'statement' => $objetivo?->statement['es'] ?? '',
                'has_items' => true,
            ],
            'destrezas' => [],
        ]);
    }

    /** POST /docente/revisar/{tipo}/{id}/firmar */
    public function firmar(Request $request, string $tipo, string $id)
    {
        $docente = $this->docente($request);
        $pieza = $this->localizar($tipo, $id);
        $data = $request->validate(['nota' => 'nullable|string|max:1000']);

        $this->firma->firmar($pieza, $docente, $data['nota'] ?? null);

        return back();
    }

    /** POST /docente/revisar/{tipo}/{id}/devolver — nota OBLIGATORIA. */
    public function devolver(Request $request, string $tipo, string $id)
    {
        $docente = $this->docente($request);
        $pieza = $this->localizar($tipo, $id);
        $data = $request->validate(['nota' => 'required|string|min:3|max:1000']);

        $this->firma->devolver($pieza, $docente, $data['nota']);

        return back();
    }

    /** POST /docente/revisar/{tipo}/{id}/desfirmar — nota OBLIGATORIA. */
    public function desfirmar(Request $request, string $tipo, string $id)
    {
        $docente = $this->docente($request);
        $pieza = $this->localizar($tipo, $id);
        $data = $request->validate(['nota' => 'required|string|min:3|max:1000']);

        $this->firma->desfirmar($pieza, $docente, $data['nota']);

        return back();
    }

    /**
     * POST /docente/revisar/unidad — el atajo: firmar TODO lo pendiente de una
     * unidad, y solo si TODO está visto en esta sesión.
     */
    public function firmarUnidad(Request $request)
    {
        $docente = $this->docente($request);

        $data = $request->validate([
            'unidad' => 'required|integer|min:0|max:99',
            'lengua' => ['nullable', 'string', \Illuminate\Validation\Rule::in(Lenguas::LISTA)],
        ]);

        $porDescriptor = $this->curso->unidadesPorDescriptor();
        $vistas = $this->vistas();

        $delaUnidad = $this->piezasDe($data['lengua'] ?? null, firmadas: false)
            ->filter(function (Pieza $p) use ($porDescriptor, $data) {
                $code = $p->descriptor()?->native_code ?? '—';

                return ($porDescriptor[$code] ?? 0) === (int) $data['unidad'];
            });

        abort_if($delaUnidad->isEmpty(), 404, 'No hay nada pendiente en esa unidad.');

        // EL ATAJO EXIGE HABER MIRADO. Si falta una sola pieza por abrir, no se
        // firma ninguna: firmar a ciegas es justo lo que esta pantalla evita.
        $sinVer = $delaUnidad->reject(fn (Pieza $p) => in_array("{$p->tipo}:{$p->id()}", $vistas, true));
        abort_if($sinVer->isNotEmpty(), 422,
            'Quedan '.$sinVer->count().' pieza(s) sin abrir en esta unidad: ábrelas antes de firmarla entera.');

        foreach ($delaUnidad as $pieza) {
            $this->firma->firmar($pieza, $docente);
        }

        return back();
    }

    // ---- piezas ----

    /**
     * Las piezas de una lengua, pendientes o firmadas.
     *
     * @return Collection<int, Pieza>
     */
    private function piezasDe(?string $lengua, bool $firmadas): Collection
    {
        $items = PracticeItem::query()
            ->when($firmadas, fn ($q) => $q->whereNotNull('reviewed_at'),
                fn ($q) => $q->whereNull('reviewed_at'))
            ->when($lengua !== null, fn ($q) => $q->where('lengua', $lengua))
            // Sin lengua se revisa TODO lo pendiente, incluido lo que no tiene
            // lengua (MINEDEC). La lengua filtra; su ausencia no esconde.
            ->with('objective:id,native_code,statement')
            // `seq` es NOT NULL pero el orden se declara igual: un orden
            // implícito sobre nullables diverge entre SQLite y PostgreSQL.
            ->orderBy('objective_id')->orderBy('seq')
            ->get()
            ->map(fn (PracticeItem $i) => Pieza::deItem($i));

        $lecciones = Resource::query()
            ->where('kind', Resource::LECTURA)
            // SOLO lo GENERADO. `Resource::published()` exige firma únicamente a
            // lo generado: una lección CURADA (la dio de alta un operador, ya
            // pasó por unos ojos) se ve con `reviewed_at` nulo o sin él. Si
            // entrara aquí, la pantalla diría «pendiente · no se ve» de algo que
            // el alumno YA está viendo, y «retirar la firma» no la escondería.
            // La cola muestra lo que la firma de verdad gobierna.
            ->where('origen', Resource::GENERADO)
            ->when($lengua !== null, fn ($q) => $q->where('lengua', $lengua))
            ->whereHas('currentVersion', fn ($v) => $firmadas
                ? $v->whereNotNull('reviewed_at')
                : $v->whereNull('reviewed_at'))
            ->with(['currentVersion', 'objectives'])
            ->orderBy('slug')
            ->get()
            ->filter(fn (Resource $r) => $r->currentVersion !== null)
            ->map(fn (Resource $r) => Pieza::deLeccion($r->currentVersion));

        return $lecciones->concat($items)->values();
    }

    private function localizar(string $tipo, string $id): Pieza
    {
        abort_unless(in_array($tipo, Pieza::TIPOS, true), 404);
        $pieza = Pieza::localizar($tipo, $id);
        abort_if($pieza === null, 404);

        return $pieza;
    }

    /**
     * La última nota de DEVOLUCIÓN de cada pieza: lo que quien la corrija tiene
     * que leer. Una consulta para todas, no una por pieza.
     *
     * @param  Collection<int, Pieza>  $piezas
     * @return array<string, array{nota: string, docente: ?string, cuando: string}>
     */
    private function ultimasNotas(Collection $piezas): array
    {
        $itemIds = $piezas->where('tipo', Pieza::ITEM)->map(fn ($p) => $p->id())->all();
        $versionIds = $piezas->where('tipo', Pieza::LECCION)->map(fn ($p) => $p->id())->all();

        if ($itemIds === [] && $versionIds === []) {
            return [];
        }

        $notas = [];
        Revision::query()
            ->where('accion', Revision::DEVOLVER)
            ->where(fn ($q) => $q
                ->whereIn('practice_item_id', $itemIds)
                ->orWhereIn('resource_version_id', $versionIds))
            ->with('docente:id,name')
            ->orderBy('created_at')   // la ÚLTIMA gana: se recorre en orden
            ->get()
            ->each(function (Revision $r) use (&$notas) {
                $clave = $r->practice_item_id !== null
                    ? Pieza::ITEM.':'.$r->practice_item_id
                    : Pieza::LECCION.':'.$r->resource_version_id;
                $notas[$clave] = [
                    'nota' => $r->nota,
                    'docente' => $r->docente?->name,
                    'cuando' => $r->created_at->toDateString(),
                ];
            });

        return $notas;
    }

    /** @return list<array{accion: string, nota: ?string, docente: ?string, cuando: string}> */
    private function historial(Pieza $pieza): array
    {
        return Revision::query()
            ->when($pieza->tipo === Pieza::ITEM,
                fn ($q) => $q->where('practice_item_id', $pieza->id()),
                fn ($q) => $q->where('resource_version_id', $pieza->id()))
            ->with('docente:id,name')
            ->orderByDesc('created_at')->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (Revision $r) => [
                'accion' => $r->accion,
                'nota' => $r->nota,
                'docente' => $r->docente?->name,
                'cuando' => $r->created_at->toDateString(),
            ])->all();
    }

    // ---- sesión y autorización ----

    /** @return list<string> */
    private function vistas(): array
    {
        return array_values(array_unique(session()->get(self::VISTAS, [])));
    }

    private function marcarVista(Pieza $pieza): void
    {
        $clave = "{$pieza->tipo}:{$pieza->id()}";
        $vistas = session()->get(self::VISTAS, []);
        if (! in_array($clave, $vistas, true)) {
            $vistas[] = $clave;
            session()->put(self::VISTAS, $vistas);
        }
    }

    /**
     * Un DOCENTE, o 403. No una redirección: un alumno que llega aquí no tiene
     * que ir a ningún sitio, tiene que saber que esto no es suyo. Y el invitado,
     * lo mismo — por eso estas rutas no cuelgan del grupo `auth`.
     */
    private function docente(Request $request): User
    {
        $user = $request->user();
        abort_unless(Docencia::es($user), 403, 'Solo un docente puede revisar contenido.');

        return $user;
    }
}
