<?php

namespace App\Console\Commands;

use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RuntimeException;

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
     * El `seq` que el banco ocupa en cada destreza.
     *
     * Es FIJO, no `100 + índice del array`. Con el índice, insertar una entrada
     * al principio del fichero desplazaba el `seq` de todas las siguientes y la
     * siembra dejaba un zombi por cada una: mismo contenido, `seq` viejo, sin
     * que nada lo borrara. La idempotencia solo valía con el fichero congelado,
     * que es justo cuando no hace falta.
     *
     * Con un `seq` fijo la clave natural (objetivo, seq) es estable pase lo que
     * pase en el fichero: cada destreza tiene como mucho UN ítem del banco, y
     * re-sembrar lo actualiza conservando su id — y con él los intentos que ya
     * apunten a ese ítem. Empieza alto para no pisar los 17 ítems de física que
     * viven en 0..2 sobre las destrezas de mecánica y óptica.
     */
    public const BASE_SEQ = 100;

    protected $signature = 'practica:sembrar
        {--marco=EC-MINEDEC : Código del marco curricular}
        {--incluir-no-verificadas : Siembra también sobre destrezas sin verificar (grafo de demostración)}
        {--podar : Borra los ítems del banco cuyo bloque ya no está en el fichero}
        {--dry-run : Cuenta lo que haría sin escribir}';

    protected $description = 'Siembra el banco de ítems de práctica (numéricos y de opción múltiple) sobre el currículo';

    public function handle(): int
    {
        $banco = require database_path('data/banco-practica.php');
        $marco = (string) $this->option('marco');

        // SOLO la versión vigente. Tomar todas las del marco significaría que
        // el día que convivan `2016` y `2016+2023` cada entrada del banco
        // sembraría por duplicado, una vez en cada currículo.
        $version = FrameworkVersion::query()
            ->whereIn('framework_id', Framework::where('code', $marco)->select('id'))
            ->latest('valid_from')
            ->first();

        if ($version === null) {
            $this->error("No existe el marco {$marco} o no tiene versiones.");

            return self::FAILURE;
        }
        $versiones = collect([$version->id]);

        $creados = 0;
        $actualizados = 0;
        $huecos = [];
        $sinVerificar = 0;

        $bloquesDelBanco = [];

        foreach ($banco as $entrada) {
            $prefijo = $entrada[0];
            $bloquesDelBanco[] = $prefijo;
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
                    ['objective_id' => $objetivo->id, 'seq' => self::BASE_SEQ],
                    $atributos,
                );
                $item->wasRecentlyCreated ? $creados++ : $actualizados++;
            }
        }

        $this->avisarDeHuerfanos($bloquesDelBanco);
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
                ...$this->conClavesRepartidas($opciones, $correcta, $prefijo),
                // Se anulan explícitamente: si una entrada pasa de `numeric` a
                // `choice` conservando su sitio, el `updateOrCreate` dejaría la
                // tolerancia y la unidad del ítem viejo colgando.
                'tolerance' => 0,
                'tolerance_kind' => 'abs',
                'answer_unit' => null,
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
     * Lo sembrado NO se publica solo: espera la firma de un docente.
     *
     * Sin este aviso, `practica:sembrar` diría «240 ítems creados» y el alumno
     * no vería ninguno, sin ninguna pista de por qué.
     */
    private function avisarDeLaFirma(): void
    {
        $pendientes = PracticeItem::whereNull('reviewed_at')->count();

        if ($pendientes === 0) {
            return;
        }

        $this->newLine();
        $this->warn("{$pendientes} ítem(s) pendiente(s) de firma: NO se sirven todavía.");
        $this->line('  Un ítem sin revisar no llega a un alumno. Para publicar un bloque');
        $this->line('  ya revisado:  php artisan practica:firmar --bloque=LL.4.1');
    }

    /**
     * Cuántas destrezas tienen ejercicios pero NO pueden alcanzar dominio.
     *
     * El dominio exige aciertos en dos ítems distintos (MasteryTracker), así
     * que una destreza con uno solo se practica y se corrige, pero no sella
     * `mastered_at` ni mueve la nota. Es correcto —el dominio de una destreza
     * con una única pregunta no significa nada— pero hay que decirlo: es el
     * hueco de contenido más accionable que deja este banco.
     */
    private function avisarDeLasQueNoSePuedenDominar(Collection $versiones): void
    {
        // Subconsulta y no `withCount()->having()`: sin `groupBy`, SQLite
        // rechaza el HAVING («non-aggregate query») y pgsql lo aceptaría — la
        // clase de divergencia que el CI dual existe para cazar.
        $conUnoSolo = LearningObjective::query()
            ->whereIn('version_id', $versiones)
            ->whereRaw('(select count(*) from practice_items where practice_items.objective_id = learning_objectives.id) = 1')
            ->count();

        if ($conUnoSolo === 0) {
            return;
        }

        $this->newLine();
        $this->warn("{$conUnoSolo} destreza(s) tienen un solo ítem: se practican, pero no");
        $this->line('  pueden alcanzar dominio ni calificar (hacen falta dos ítems distintos).');
        $this->line('  Es el hueco más accionable del banco: una pregunta más por bloque.');
    }

    /**
     * Ítems del banco cuyo bloque ya no está en el fichero.
     *
     * Con `seq` fijo no puede haber duplicados, pero sí RESTOS: si se borra una
     * entrada o se le cambia el prefijo, su ítem se queda con contenido viejo y
     * nadie lo toca. Se avisa —no se borra solo— porque puede tener intentos
     * de alumnos colgando: eso lo decide una persona, con `--podar`.
     */
    private function avisarDeHuerfanos(array $bloquesDelBanco): void
    {
        $huerfanos = PracticeItem::query()
            ->where('seq', self::BASE_SEQ)
            ->get(['id', 'attrs'])
            ->filter(fn (PracticeItem $i) => ! in_array(
                $i->attrs['revision']['bloque'] ?? null, $bloquesDelBanco, true,
            ));

        if ($huerfanos->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->warn($huerfanos->count().' ítem(s) del banco apuntan a un bloque que ya no está en el fichero.');

        if (! $this->option('podar')) {
            $this->line('  Quedan tal cual, con su contenido viejo. Para borrarlos: --podar');

            return;
        }

        $conIntentos = $huerfanos->filter(fn (PracticeItem $i) => $i->attempts()->exists());
        PracticeItem::whereIn('id', $huerfanos->pluck('id')->diff($conIntentos->pluck('id')))->delete();

        $this->line('  Podados '.($huerfanos->count() - $conIntentos->count()).'.');
        if ($conIntentos->isNotEmpty()) {
            $this->line('  '.$conIntentos->count().' se conservan: tienen intentos de alumnos colgando.');
        }
    }

    /**
     * Reparte las claves a..d entre las opciones, PERMUTADAS por el bloque.
     *
     * Aquí se cierra un agujero que era inofensivo por separado y catastrófico
     * junto: en el fichero de datos la correcta se escribe SIEMPRE la primera
     * —convención de autoría, para que un docente revise de un vistazo— y las
     * opciones viajan al cliente con su clave. Asignando las letras por
     * posición, `answer_key` salía `'a'` en las 60 preguntas del banco: la
     * respuesta quedaba en el `value` de cada radio, legible con «inspeccionar
     * elemento» y sin más herramienta. Barajar el orden de PINTADO no lo tapaba,
     * porque la clave viaja intacta — que es justo lo que hace imposible
     * calificar mal, y por eso no se toca.
     *
     * La permutación es determinista y sale del BLOQUE, no del contenido:
     * re-sembrar da la misma clave (los intentos guardados siguen teniendo
     * sentido) y corregir una errata de un enunciado no la mueve.
     *
     * @param  array<string, string>  $opciones  clave de autoría => texto
     * @return array{options: list<array{key: string, text: array<string, string>}>, answer_key: string}
     */
    private function conClavesRepartidas(array $opciones, string $correcta, string $prefijo): array
    {
        $letras = array_slice(['a', 'b', 'c', 'd', 'e', 'f'], 0, count($opciones));

        // Orden total derivado del bloque: reproducible entre ejecuciones y
        // entre máquinas. Nada de shuffle() ni rand(), como en todo el motor.
        $pesos = [];
        foreach ($letras as $letra) {
            $pesos[$letra] = hash('sha256', "{$prefijo}:clave:{$letra}");
        }
        asort($pesos);
        $repartidas = array_values(array_keys($pesos));

        $options = [];
        $answerKey = null;
        $i = 0;
        foreach ($opciones as $claveDeAutoria => $texto) {
            $letra = $repartidas[$i++];
            $options[] = ['key' => $letra, 'text' => ['es' => $texto]];

            if ((string) $claveDeAutoria === $correcta) {
                $answerKey = $letra;
            }
        }

        if ($answerKey === null) {
            // El fichero declara una correcta que no está entre sus opciones:
            // sembrarlo daría un ítem imposible de acertar, callando.
            throw new RuntimeException(
                "{$prefijo}: la clave correcta '{$correcta}' no está entre las opciones.",
            );
        }

        // Ordenadas POR CLAVE, no por autoría. Si se guardaran en el orden del
        // fichero, la correcta sería siempre el elemento 0 de `options`: con
        // `shuffle = false` —que la columna permite— volvería a pintarse la
        // primera, y leer el JSON de la columna la delataría igual.
        usort($options, fn (array $a, array $b) => $a['key'] <=> $b['key']);

        // El orden de PINTADO lo baraja la semilla en cada intento
        // (PracticeEngine::shuffleOptions) sobre esta base ya neutra.
        return ['options' => $options, 'answer_key' => $answerKey];
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
            ->withCount('todosLosPracticeItems as practice_items_count')
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

        // Dos avisos que la siembra tiene que dar, o deja 240 ítems invisibles y
        // un montón de destrezas que no pueden dominarse, sin que nadie lo sepa.
        $this->avisarDeLaFirma();
        $this->avisarDeLasQueNoSePuedenDominar($versiones);

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
