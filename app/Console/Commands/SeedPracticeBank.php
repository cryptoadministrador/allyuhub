<?php

namespace App\Console\Commands;

use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Llena el banco de práctica sobre el currículo REAL ya importado.
 *
 *   php artisan practica:sembrar [--marco=EC-MINEDEC] [--incluir-no-verificadas] [--dry-run]
 *
 * Cada entrada de database/data/banco-practica.php se ancla a un BLOQUE por el
 * prefijo del código (`LL.4.3` = Lengua · Básica Superior · Lectura) y aterriza
 * en la PRIMERA destreza de ese bloque en cada nodo donde el bloque exista. Eso
 * último es lo que convierte el viejo problema en cobertura: el importador
 * replica los códigos por grado dentro de un subnivel, así que un mismo bloque
 * vive en 8.º, 9.º y 10.º — y los tres reciben su ítem, en vez de que el seeder
 * aborte gritando «código ambiguo» como hacía con CN.F.5.1.9.
 *
 * IDEMPOTENTE: la clave natural es (objetivo, seq), y el `seq` sale del índice
 * de la entrada en el banco + BASE_SEQ. Re-ejecutar actualiza el contenido sin
 * duplicar ítems ni cambiarles el id (los intentos ya registrados siguen
 * apuntando al mismo sitio).
 *
 * NO REVIENTA NUNCA por datos: un bloque sin destrezas se apunta como hueco y
 * se informa al final. Sembrar medio banco y decir cuál falta es más útil que
 * no sembrar nada.
 */
class SeedPracticeBank extends Command
{
    /**
     * Los `seq` del banco empiezan alto para no pisar los 17 ítems de física
     * que ya viven en 0..2 sobre las destrezas verificadas de mecánica y óptica.
     */
    public const BASE_SEQ = 100;

    protected $signature = 'practica:sembrar
        {--marco=EC-MINEDEC : Código del marco curricular}
        {--incluir-no-verificadas : Siembra también sobre destrezas sin verificar (grafo de demostración)}
        {--dry-run : Cuenta lo que haría sin escribir}';

    protected $description = 'Siembra el banco de ítems de práctica (numéricos y de opción múltiple) sobre el currículo';

    public function handle(): int
    {
        $banco = require database_path('data/banco-practica.php');
        $marco = (string) $this->option('marco');

        $versiones = FrameworkVersion::query()
            ->whereIn('framework_id', Framework::where('code', $marco)->select('id'))
            ->pluck('id');

        if ($versiones->isEmpty()) {
            $this->error("No existe el marco {$marco} o no tiene versiones.");

            return self::FAILURE;
        }

        $creados = 0;
        $actualizados = 0;
        $huecos = [];
        $sinVerificar = 0;

        foreach ($banco as $i => $entrada) {
            $prefijo = $entrada[0];
            $destinos = $this->destinosDe($prefijo, $versiones, $sinVerificar);

            if ($destinos->isEmpty()) {
                $huecos[] = $prefijo;

                continue;
            }

            foreach ($destinos as $objetivo) {
                $atributos = $this->atributosDe($entrada, $prefijo);

                if ($this->option('dry-run')) {
                    $creados++;

                    continue;
                }

                $item = PracticeItem::updateOrCreate(
                    ['objective_id' => $objetivo->id, 'seq' => self::BASE_SEQ + $i],
                    $atributos,
                );
                $item->wasRecentlyCreated ? $creados++ : $actualizados++;
            }
        }

        $this->informar($creados, $actualizados, $huecos, $sinVerificar, count($banco), $versiones);

        return self::SUCCESS;
    }

    /**
     * Las destrezas donde aterriza una entrada: la de código más bajo del
     * bloque, UNA POR NODO. Un bloque replicado en tres grados da tres
     * destinos, que es justo lo que se quiere.
     *
     * @return Collection<int, LearningObjective>
     */
    private function destinosDe(string $prefijo, Collection $versiones, int &$sinVerificar): Collection
    {
        $candidatas = LearningObjective::query()
            ->whereIn('version_id', $versiones)
            ->where('native_code', 'like', $prefijo.'.%')
            ->get(['id', 'node_id', 'native_code', 'is_verified']);

        if (! $this->option('incluir-no-verificadas')) {
            $sinVerificar += $candidatas->where('is_verified', false)->count();
            $candidatas = $candidatas->where('is_verified', true);
        }

        return $candidatas
            ->groupBy('node_id')
            ->map(fn (Collection $delNodo) => $delNodo
                // Orden CURRICULAR, no de cadena: M.4.1.2 antes que M.4.1.10.
                // Comparador EXPLÍCITO: `sortBy([closure, closure])` NO ordena
                // por los closures —compara null con null y deja el orden de
                // llegada—, y aquí eso hacía que el ítem aterrizara en la .10.
                ->sort(fn ($a, $b) => [mb_strlen($a->native_code), $a->native_code]
                    <=> [mb_strlen($b->native_code), $b->native_code])
                ->first())
            ->values();
    }

    /** @return array<string, mixed> */
    private function atributosDe(array $entrada, string $prefijo): array
    {
        $revision = [
            // Hasta dónde llega la garantía del anclaje: el ítem está alineado
            // al BLOQUE, no cotejado contra el enunciado oficial de la destreza
            // concreta. Un docente tiene que firmarlo. Esto SÍ puede vivir en
            // `attrs` porque no dice nada de cuál es la respuesta.
            'alineado_a' => 'bloque',
            'bloque' => $prefijo,
        ];

        if ($entrada[1] === PracticeItem::CHOICE) {
            [, , $enunciado, $opciones, $correcta] = $entrada;

            return [
                'kind' => PracticeItem::CHOICE,
                'statement' => ['es' => $enunciado],
                'params' => [],
                'solution_expr' => null,
                // Las opciones NO llevan marca de correcta. La clave va a su
                // columna, que no se serializa nunca.
                'options' => array_map(
                    fn (string $clave, string $texto) => ['key' => $clave, 'text' => ['es' => $texto]],
                    array_keys($opciones), array_values($opciones),
                ),
                'answer_key' => $correcta,
                'shuffle' => true,
                'origen' => 'curado',
                'attrs' => ['revision' => $revision],
            ];
        }

        [, , $enunciado, $params, $expr, $tolerancia, $tipoTolerancia, $unidad] = $entrada;

        return [
            'kind' => PracticeItem::NUMERIC,
            'statement' => ['es' => $enunciado],
            'params' => $params,
            'options' => null,
            'answer_key' => null,
            'solution_expr' => $expr,
            'tolerance' => $tolerancia,
            'tolerance_kind' => $tipoTolerancia,
            'answer_unit' => $unidad,
            'origen' => 'curado',
            'attrs' => ['revision' => $revision],
        ];
    }

    /**
     * `LL.4.3.1` o `LL.4.3` → `LL · subnivel 4`. El área es todo lo anterior al
     * primer segmento numérico, así que `CN.F.5.1.9` da `CN.F · subnivel 5`:
     * Física no es una asignatura, es una rama de Ciencias Naturales en BGU.
     */
    private function ambitoDe(string $codigo): string
    {
        $partes = explode('.', $codigo);
        $area = [];
        foreach ($partes as $parte) {
            if (is_numeric($parte)) {
                return implode('.', $area)." · subnivel {$parte}";
            }
            $area[] = $parte;
        }

        return implode('.', $area).' · sin subnivel';
    }

    /**
     * El informe se mide en DESTREZAS, no en ámbitos.
     *
     * «3 ítems por asignatura × subnivel» sonaría bien y no significaría nada:
     * son ~60 ítems sobre 4.717 destrezas, un 1 % disfrazado de cobertura. Lo
     * que Carlos necesita saber es qué porcentaje de las destrezas VERIFICADAS
     * tiene algo que practicar, y cuáles no — con nombre y apellido.
     */
    private function informar(int $creados, int $actualizados, array $huecos, int $sinVerificar, int $total, Collection $versiones): void
    {
        $this->info(sprintf(
            '%s%d ítem(s) creados · %d actualizados · %d entradas en el banco.',
            $this->option('dry-run') ? '[dry-run] ' : '', $creados, $actualizados, $total,
        ));

        if ($huecos !== []) {
            $this->newLine();
            $this->warn('Bloques del banco sin destreza donde aterrizar ('.count($huecos).'):');
            $this->line('  '.implode(', ', $huecos));
            $this->line('  (o el área no está importada todavía, o no está verificada)');
        }

        if ($sinVerificar > 0) {
            $this->newLine();
            $this->warn("Se saltaron {$sinVerificar} destreza(s) sin verificar.");
            $this->line('  Un ítem sobre un marcador provisional practicaría un enunciado que no es');
            $this->line('  el del Ministerio. Con --incluir-no-verificadas se siembran igual (demo).');
        }

        if ($this->option('dry-run')) {
            return;
        }

        // Se mide el MISMO universo que se sembró. Medir solo las verificadas
        // después de sembrar con --incluir-no-verificadas daría un 100 % que no
        // significa nada: el número tiene que doler donde falta cobertura.
        $soloVerificadas = ! $this->option('incluir-no-verificadas');

        $destrezas = LearningObjective::query()
            ->whereIn('version_id', $versiones)
            ->when($soloVerificadas, fn ($q) => $q->where('is_verified', true))
            ->withCount('practiceItems')
            ->get(['id', 'native_code']);

        if ($destrezas->isEmpty()) {
            $this->newLine();
            $this->warn('No hay destrezas en este marco: nada que medir.');

            return;
        }

        $porAmbito = [];
        $sinItem = [];
        foreach ($destrezas as $d) {
            $ambito = $this->ambitoDe($d->native_code);
            $porAmbito[$ambito] ??= ['total' => 0, 'con' => 0];
            $porAmbito[$ambito]['total']++;

            if ($d->practice_items_count > 0) {
                $porAmbito[$ambito]['con']++;
            } else {
                $sinItem[] = $d->native_code;
            }
        }
        ksort($porAmbito);

        $conItem = $destrezas->where('practice_items_count', '>', 0)->count();
        $this->newLine();
        $this->line(sprintf(
            'Destrezas%s con al menos un ítem: %d de %d (%.1f %%).',
            $soloVerificadas ? ' verificadas' : ' (verificadas o no)',
            $conItem, $destrezas->count(), $conItem / $destrezas->count() * 100,
        ));

        $this->table(
            ['Asignatura · subnivel', 'Destrezas', 'Con ítem', '%'],
            array_map(fn (string $k, array $v) => [
                $k, $v['total'], $v['con'], sprintf('%.1f', $v['con'] / $v['total'] * 100),
            ], array_keys($porAmbito), $porAmbito),
        );

        if ($sinItem !== []) {
            sort($sinItem);
            $ruta = storage_path('app/practica-sin-cobertura.txt');
            file_put_contents($ruta, implode(PHP_EOL, $sinItem).PHP_EOL);

            $this->newLine();
            $this->warn(count($sinItem).' destreza(s) SIN ningún ítem.');
            $this->line('  Primeras: '.implode(', ', array_slice($sinItem, 0, 12)).'…');
            $this->line("  La lista completa está en {$ruta}");
        }

    }
}
