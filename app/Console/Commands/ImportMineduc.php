<?php

namespace App\Console\Commands;

use App\Models\CurNode;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Importa destrezas con criterios de desempeño desde los documentos curriculares
 * oficiales del MINEDEC (PDF o texto plano ya extraído).
 *
 *   php artisan mineduc:import storage/curriculo/CCNN_COMPLETO.pdf --dry-run
 *   php artisan mineduc:import storage/curriculo/CCNN_COMPLETO.pdf --official
 *
 * Cómo funciona:
 *  1. PDF → texto con pdftotext en modo RAW (o lee .txt directamente). Si el
 *     texto llega en formato -layout, se reconstruyen las columnas por
 *     posición X antes de nada: ver reconstruirColumnas().
 *  2. Regex sobre los códigos oficiales: CN.4.3.5 · CN.F.5.1.12 · M.3.1.13 ·
 *     LL.5.1.2 · CS.4.2.3 · ECA.2.1.4 · EF.3.1.8 · EG.5.1.1 · CS.H.5.1.1 …
 *     El texto de la destreza es lo que sigue al código hasta el próximo código.
 *  3. Ubica cada destreza en el grafo por (subnivel del código, asignatura):
 *     el dígito tras el prefijo es el subnivel (1 Preparatoria, 2 Elemental,
 *     3 Media, 4 Superior, 5 Bachillerato). Las destrezas de subnivel se
 *     replican en cada grado del subnivel (así funciona el currículo 2016:
 *     las destrezas son POR SUBNIVEL, los grados las secuencian en el PCA).
 *  4. Sustituye los marcadores del seeder (is_verified=false) de esa asignatura;
 *     con --official marca is_verified=true y guarda el sha256 del archivo en
 *     framework_versions.source_sha256 para trazabilidad.
 *
 * REGLA DE HONESTIDAD: sin --official, lo importado queda is_verified=false
 * (sirve para probar con fixtures o borradores sin contaminar la verdad).
 */
class ImportMineduc extends Command
{
    protected $signature = 'mineduc:import
        {file : Ruta al PDF o TXT del documento curricular}
        {--official : Marca las destrezas como verificadas y registra el sha256}
        {--dry-run : Muestra lo que haría sin escribir en la base}
        {--min-len=25 : Longitud mínima del enunciado para aceptarlo}
        {--force-coverage : Permite retirar más marcadores de los que se rescatan (currículos priorizados)}';

    protected $description = 'Importa destrezas del currículo oficial MINEDEC al grafo';

    /** Por debajo de este % de enunciados válidos, --official aborta. */
    public const UMBRAL_CALIDAD = 95.0;

    /** Prefijo de código → native_code de la asignatura en el grafo del seeder. */
    private const AREA_MAP = [
        'CN.F' => 'CN.F', 'CN.Q' => 'CN.Q', 'CN.B' => 'CN.B', 'CN' => 'CN',
        'CS.H' => 'CS.H', 'CS.F' => 'CS.FL', 'CS.C' => 'CS.EC', 'CS' => 'CS',
        'M' => 'M', 'LL' => 'LL', 'ECA' => 'ECA', 'EF' => 'EF', 'EG' => 'EG',
    ];

    /** Subnivel (dígito del código) → grados del seeder que lo componen. */
    private const SUBNIVEL_GRADOS = [
        1 => ['g1'],
        2 => ['g2', 'g3', 'g4'],
        3 => ['g5', 'g6', 'g7'],
        4 => ['g8', 'g9', 'g10'],
        5 => ['g11', 'g12', 'g13'],
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("No existe el archivo: {$file}");

            return self::FAILURE;
        }

        $text = str_ends_with(strtolower($file), '.pdf')
            ? $this->pdfToText($file)
            : file_get_contents($file);

        if ($text === null || mb_strlen($text) < 60) {
            $this->error('No se pudo extraer texto útil del archivo.');

            return self::FAILURE;
        }

        $found = $this->parse($text);
        if ($found->isEmpty()) {
            $this->error('No se encontró ningún código de destreza (¿es el documento correcto?).');

            return self::FAILURE;
        }

        $this->info(sprintf('Encontradas %d destrezas en el documento.', $found->count()));
        $this->table(
            ['código', 'asignatura', 'subnivel', 'enunciado (inicio)'],
            $found->take(8)->map(fn ($d) => [
                $d['code'], $d['area'], $d['subnivel'], mb_substr($d['text'], 0, 60).'…',
            ])->all(),
        );

        // ---------- El oráculo de calidad ----------
        $invalidos = $found->map(fn ($d) => self::evaluarCalidad($d['text']) + ['code' => $d['code'], 'text' => $d['text']])
            ->filter(fn ($e) => ! $e['valido'])->values();
        $pct = $found->count() === 0 ? 0.0 : round(100 * ($found->count() - $invalidos->count()) / $found->count(), 1);

        $this->info(sprintf(
            'Calidad de la extracción: %s %% (%d de %d enunciados válidos).',
            $pct, $found->count() - $invalidos->count(), $found->count(),
        ));

        if ($invalidos->isNotEmpty()) {
            $this->warn('Enunciados que NO pasan el oráculo (muestra):');
            $this->table(['código', 'motivo', 'enunciado (inicio)'], $invalidos->take(10)->map(fn ($e) => [
                $e['code'], $e['motivo'], mb_substr($e['text'], 0, 60).'…',
            ])->all());
            $this->line('  Motivos: '.$invalidos->countBy('motivo')
                ->map(fn ($n, $motivo) => "{$motivo} ({$n})")->implode(' · '));
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry-run: no se escribió nada.');

            return self::SUCCESS;
        }

        // Mejor no importar que importar basura: por debajo del umbral, el
        // documento no entra al currículo como verificado. Punto.
        if ($this->option('official') && $pct < self::UMBRAL_CALIDAD) {
            $this->error(sprintf(
                'ABORTADO: la calidad (%s %%) está por debajo del umbral del %s %% exigido para --official. '
                .'Revisa la extracción antes de marcar esto como currículo oficial.',
                $pct, self::UMBRAL_CALIDAD,
            ));

            return self::FAILURE;
        }

        // Con --official, además, las que no pasan el oráculo NO se importan:
        // marcar un enunciado corrupto como verificado es mentirle al colegio.
        if ($this->option('official') && $invalidos->isNotEmpty()) {
            $codigosMalos = $invalidos->pluck('code')->all();
            $found = $found->reject(fn ($d) => in_array($d['code'], $codigosMalos, true))->values();
            $this->warn(sprintf('Se omiten %d destreza(s) que no pasan el oráculo.', count($codigosMalos)));
        }

        $version = FrameworkVersion::whereHas('framework', fn ($q) => $q->where('code', 'EC-MINEDEC'))
            ->latest('created_at')->firstOrFail();

        $stats = ['nuevas' => 0, 'actualizadas' => 0, 'sin_nodo' => 0, 'reemplazadas' => 0];

        try {
            DB::transaction(function () use ($found, $version, &$stats) {
                foreach ($found as $d) {
                    $grados = self::SUBNIVEL_GRADOS[$d['subnivel']] ?? [];
                    foreach ($grados as $gid) {
                        // El bloque destino: primer bloque de la asignatura de ese grado.
                        // (El PDF trae la destreza por subnivel; la asignación fina a
                        //  bloques/unidades es trabajo editorial posterior en la plataforma.)
                        $bloque = CurNode::where('version_id', $version->id)
                            ->where('node_type', 'bloque')
                            ->whereHas('parent', fn ($q) => $q->where('native_code', $d['area'])
                                ->whereHas('parent', fn ($g) => $g->where('native_code', $gid)))
                            ->orderBy('seq')->first();

                        if (! $bloque) {
                            $stats['sin_nodo']++;

                            continue;
                        }

                        $existing = LearningObjective::where('version_id', $version->id)
                            ->where('native_code', $d['code'])
                            ->whereHas('node.parent', fn ($q) => $q
                                ->whereHas('parent', fn ($g) => $g->where('native_code', $gid)))
                            ->first();

                        $payload = [
                            'statement' => ['es' => $d['text']],
                            'attrs' => ['imported_from' => basename($this->argument('file')),
                                'imported_at' => now()->toDateString()],
                        ];
                        if ($this->option('official')) {
                            $payload['is_verified'] = true;   // sin --official NUNCA se degrada lo ya verificado
                        }

                        if ($existing) {
                            // Una importación no oficial tampoco pisa el enunciado de una destreza verificada.
                            if (! $existing->is_verified || $this->option('official')) {
                                $existing->update($payload);
                            }
                            $stats['actualizadas']++;
                        } else {
                            LearningObjective::create($payload + [
                                'node_id' => $bloque->id, 'version_id' => $version->id,
                                'native_code' => $d['code'], 'is_essential' => null,
                                'is_verified' => (bool) $this->option('official'),
                            ]);
                            $stats['nuevas']++;
                        }
                    }

                }

                /*
                 * Retira los marcadores del seeder que el currículo real NO trae —
                 * UNA sola vez, al FINAL, y excluyendo los códigos del documento.
                 *
                 * HALLAZGO 1 de la auditoría del PR #18 (el peor de nueve): este
                 * purgado vivía DENTRO del bucle y corría en la primera iteración,
                 * cuando el resto de destrezas del propio documento aún eran
                 * marcadores sin `imported_from`. Las borraba y el bucle las
                 * recreaba después CON OTRO UUID — y por cascada se perdía todo lo
                 * colgado del UUID viejo: track_phase_objectives, alignments,
                 * resource_objectives, practice_items→attempts, masteries. El
                 * primer --official real habría churneado la identidad de casi
                 * toda el área. La regla que este bloque encarna: un código que el
                 * documento trae JAMÁS se borra — se actualiza en su sitio.
                 */
                if ($this->option('official')) {
                    foreach ($found->pluck('area')->unique() as $area) {
                        // GUARDA DE COBERTURA (auditoría PR #18): el oráculo de
                        // calidad mide PRECISIÓN (lo extraído está limpio) pero no
                        // COBERTURA. Un layout distinto (M, LL) que pierda CÓDIGOS
                        // importaría una fracción del área con «calidad 100 %» y
                        // retiraría todos los marcadores igual. Si lo rescatado no
                        // llega ni a la mitad de lo que se va a retirar, se aborta:
                        // mejor no importar que dejar el área medio vacía.
                        $rescatadas = $found->where('area', $area)->count();
                        $aRetirar = LearningObjective::where('version_id', $version->id)
                            ->where('is_verified', false)
                            ->where('native_code', 'like', $area.'.%')
                            ->whereNotIn('native_code', $found->where('area', $area)->pluck('code'))
                            ->whereRaw("(attrs->>'imported_from') IS NULL")
                            ->whereHas('node.parent', fn ($q) => $q->where('native_code', $area))
                            ->count();
                        if ($aRetirar > 0 && $rescatadas < $aRetirar && ! $this->option('force-coverage')) {
                            throw new \RuntimeException(sprintf(
                                'Cobertura sospechosa en %s: %d destrezas rescatadas contra %d marcadores a retirar. '.
                                'El layout de este PDF puede estar perdiendo códigos. Revisa con --dry-run; '.
                                'si la baja cobertura es real (currículo priorizado), repite con --force-coverage.',
                                $area, $rescatadas, $aRetirar,
                            ));
                        }

                        $stats['reemplazadas'] += LearningObjective::where('version_id', $version->id)
                            ->where('is_verified', false)
                            ->where('native_code', 'like', $area.'.%')
                            ->whereNotIn('native_code', $found->where('area', $area)->pluck('code'))
                            ->whereRaw("(attrs->>'imported_from') IS NULL")
                            ->whereHas('node.parent', fn ($q) => $q->where('native_code', $area))
                            ->delete();
                    }
                }

                if ($this->option('official')) {
                    $version->update(['source_sha256' => hash_file('sha256', $this->argument('file'))]);
                }
            });
        } catch (\RuntimeException $e) {
            // La guarda de cobertura aborta DENTRO de la transacción: nada se
            // escribió. Se reporta como fallo limpio, no como stack trace.
            $this->error('ABORTADO: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Importación completa: %d nuevas · %d actualizadas · %d marcadores retirados · %d sin nodo destino.',
            $stats['nuevas'], $stats['actualizadas'], $stats['reemplazadas'], $stats['sin_nodo'],
        ));
        if (! $this->option('official')) {
            $this->comment('Importadas SIN verificar (sin --official). Repite con --official cuando el archivo sea el PDF oficial.');
        }

        return self::SUCCESS;
    }

    /**
     * PDF → texto. Modo RAW (sin -layout) A PROPÓSITO: el currículo son tablas
     * de dos columnas, y `-layout` las pone en la MISMA línea, de modo que unir
     * una palabra cortada con guion al final de línea la pega con la columna de
     * al lado («gimnos-» + «problemas…» = «gimproblemas»). En raw, pdftotext ya
     * entrega cada columna en orden de lectura y sin cortes.
     */
    private function pdfToText(string $file): ?string
    {
        $out = tempnam(sys_get_temp_dir(), 'mineduc').'.txt';
        // -raw DE VERDAD, no el modo por defecto: el modo por defecto reordena
        // por «reading order» y en las tablas de dos columnas ENTRELAZA la
        // columna de la destreza con la de objetivos/perfil («…mediante la
        // relaambiente físico»). Medido sobre el PDF oficial: por defecto, 44
        // enunciados salen distintos del oficial; con -raw, 7.
        $p = new Process(['pdftotext', '-raw', '-enc', 'UTF-8', $file, $out]);
        $p->setTimeout(300)->run();

        return $p->isSuccessful() ? file_get_contents($out) : null;
    }

    /**
     * Si el texto viene de `pdftotext -layout` (o de un .txt ya extraído así),
     * las columnas comparten línea separadas por huecos de espacios. Aquí se
     * reconstruyen por POSICIÓN X — el mismo patrón que `parseCai` usa en el
     * importador EPJA — para que cada columna vuelva a ser un bloque continuo
     * y las palabras cortadas se unan con SU continuación, no con la vecina.
     */
    public static function reconstruirColumnas(string $text): string
    {
        $lineas = preg_split('/\R/u', $text);

        // ¿Es texto multicolumna? Solo si una parte apreciable de las líneas
        // tiene un hueco interno de 3+ espacios (en raw no hay ninguno).
        $conHueco = 0;
        $noVacias = 0;
        foreach ($lineas as $l) {
            if (trim($l) === '') {
                continue;
            }
            $noVacias++;
            if (preg_match('/\S {3,}\S/u', $l)) {
                $conHueco++;
            }
        }
        if ($noVacias === 0 || $conHueco / $noVacias < 0.15) {
            return $text;   // una sola columna: nada que reconstruir
        }

        // Trocea cada línea en segmentos con su columna de inicio (en CARACTERES,
        // no bytes: el castellano lleva tildes de 2 bytes y desplazaría la X).
        $segmentos = [];
        foreach ($lineas as $n => $linea) {
            $x = 0;
            foreach (preg_split('/( {3,})/u', $linea, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $parte) {
                if ($i % 2 === 0 && trim($parte) !== '') {
                    $sangria = mb_strlen($parte) - mb_strlen(ltrim($parte));
                    $segmentos[] = ['x' => $x + $sangria, 'linea' => $n, 'texto' => trim($parte)];
                }
                $x += mb_strlen($parte);
            }
        }

        // Agrupa las X parecidas en columnas (tolerancia de 4 caracteres).
        $xs = collect($segmentos)->pluck('x')->unique()->sort()->values();
        $centros = [];
        foreach ($xs as $x) {
            $ultimo = end($centros);
            if ($ultimo === false || $x - $ultimo > 4) {
                $centros[] = $x;
            }
        }

        $columnas = array_fill(0, count($centros), []);
        foreach ($segmentos as $s) {
            $mejor = 0;
            foreach ($centros as $i => $c) {
                if (abs($s['x'] - $c) < abs($s['x'] - $centros[$mejor])) {
                    $mejor = $i;
                }
            }
            $columnas[$mejor][] = $s;
        }

        // Segunda guarda: reconstruir SOLO si de verdad hay dos columnas con
        // peso. Un texto de una columna con listas indentadas también produce
        // varios centros, pero casi todos los segmentos caen en uno — y
        // reordenarlo teletransportaría los ítems de la lista al final del
        // documento (auditoría). Se exige que las dos mayores tengan ≥20 %.
        $pesos = collect($columnas)->map(fn ($c) => count($c))->sortDesc()->values();
        $totalSeg = max(1, count($segmentos));
        if ($pesos->count() < 2 || ($pesos[1] / $totalSeg) < 0.20) {
            return $text;
        }

        // Cada columna, de arriba abajo; las columnas, de izquierda a derecha.
        $bloques = [];
        foreach ($columnas as $col) {
            usort($col, fn ($a, $b) => $a['linea'] <=> $b['linea']);
            $bloques[] = implode("\n", array_column($col, 'texto'));
        }

        return implode("\n\n", $bloques);
    }

    /**
     * Une las palabras cortadas con guion al final de línea. Conservador a
     * propósito: solo minúscula-guion-salto-minúscula. Ya se aplica DESPUÉS de
     * reconstruir columnas, así que la continuación es la de verdad.
     */
    public static function unirGuiones(string $text): string
    {
        return preg_replace('/(\p{Ll})-\R\s*(\p{Ll})/u', '$1$2', $text);
    }

    /**
     * Corta el enunciado en cuanto invade un código que NO es una destreza:
     * objetivos (OG./O.), criterios de evaluación (CE.), indicadores (I.CN.)
     * o los elementos del perfil de salida (J.3., I.2., S.4.) que en las
     * tablas del PDF viven en la columna de al lado.
     */
    public static function cortarInvasores(string $stmt): string
    {
        $stmt = preg_replace('/\s*\(?\bRef\.?\s*[A-Z.]*CE\.[A-Z.\d]+\)?\.?.*$/su', '', $stmt);
        // Sin \b: el troceado pega el código a la palabra anterior sin espacio
        // («…y expeOG.CN.10. Apreciar…»), y ahí no hay frontera de palabra.
        $stmt = preg_replace('/\s*(?:OG|CE|I|O)\.[A-Z]{2,3}\.[\d.]+.*$/su', '', $stmt);
        $stmt = preg_replace('/\s*\(?\b[JIS]\.\d+\.[,)\s].*$/su', '', $stmt);
        // Otra destreza pegada detrás (dos códigos en la misma celda).
        $stmt = preg_replace('/\s*\b(?:CN\.[FQB]|CS\.[HFC]|CN|CS|LL|M|ECA|EF|EG)\.\d\.\d{1,2}\.\d{1,3}\..*$/su', '', $stmt);

        // Mobiliario de página: el enunciado termina y detrás viene el
        // encabezado o el pie de la siguiente («… degradación. Bloque 3»,
        // «… especie. 156 Educación General Básica Superior CIENCIAS…»).
        $stmt = preg_replace(
            '/\s*\b(?:Bloque\s+\d|Indicadores?\s+para\s+la\s+evaluaci[óo]n|Elementos\s+del\s+perfil'
            .'|Objetivos\s+generales|Objetivos\s+del\s+subnivel|Criterios?\s+de\s+evaluaci[óo]n'
            .'|Educaci[óo]n\s+General\s+B[áa]sica|Bachillerato\s+General\s+Unificado'
            .'|CIENCIAS\s+NATURALES|DESTREZAS\s+CON\s+CRITERIOS)\b.*$/sui',
            '', $stmt,
        );

        $stmt = rtrim(trim($stmt), ' ·|-');

        // Lo que quede DESPUÉS de la última frase completa no es enunciado: es
        // cola de troceado. DOS guardas, ambas necesarias:
        //  - solo si el enunciado NO termina ya en puntuación; si no, este
        //    recorte se comía la última frase de todo enunciado de varias.
        //  - el punto de corte exige espacio y luego mayúscula o cifra, para
        //    no partir un decimal («precisión de 0.5 gramos»).
        if (! preg_match('/[.!?…)»”"’]$/u', $stmt)
            && preg_match('/^(.*[.!?…])\s+[\p{Lu}\d].*$/su', $stmt, $m)) {
            $stmt = $m[1];
        }

        return rtrim(trim($stmt), ' ·|-');
    }

    /**
     * EL ORÁCULO DE CALIDAD. Un enunciado importado es válido si no arrastra
     * códigos de otra columna, no tiene palabras partidas, tiene longitud de
     * enunciado y termina donde debe. Mejor no importar que importar basura:
     * `--official` aborta si el porcentaje de válidos baja del umbral.
     *
     * @return array{valido: bool, motivo: ?string}
     */
    public static function evaluarCalidad(string $stmt): array
    {
        // (a) fragmentos de código curricular ajeno.
        if (preg_match('/\b(?:OG|CE|I|O)\.[A-Z]{2,3}\.\d/u', $stmt)
            || preg_match('/\b(?:CN|CS|LL|M|ECA|EF|EG)\.\d\.\d{1,2}\.\d{1,3}\b/u', $stmt)) {
            return ['valido' => false, 'motivo' => 'arrastra un código curricular'];
        }

        // (b) palabras partidas: guion suelto («expli- car») o una PALABRA
        //     pegada a una mayúscula («semillaCN»). Se exigen 3+ minúsculas
        //     antes de la mayúscula porque si no el criterio se lleva por
        //     delante la notación científica del propio currículo: `pH`, `mL`,
        //     `kWh`, `mmHg`. Con la regla anterior, las dos destrezas de ácidos
        //     y bases de Química BGU se omitían en silencio (auditoría).
        if (preg_match('/\p{Ll}-\s+\p{Ll}/u', $stmt) || preg_match('/\p{Ll}{3,}\p{Lu}/u', $stmt)) {
            return ['valido' => false, 'motivo' => 'palabra partida o pegada'];
        }

        // (c) longitud de enunciado real.
        if (mb_strlen($stmt) < 30) {
            return ['valido' => false, 'motivo' => 'demasiado corto'];
        }

        // (d) termina donde debe (puntuación) y no a mitad de palabra.
        if (! preg_match('/[.!?…)»”"’]$/u', $stmt)) {
            return ['valido' => false, 'motivo' => 'no termina en puntuación'];
        }

        return ['valido' => true, 'motivo' => null];
    }

    /**
     * Extrae pares código→enunciado. Los códigos oficiales tienen la forma
     * PREFIJO.subnivel.bloque.numero (CN.4.3.5) o PREFIJO.SUB.5.b.n en BGU
     * (CN.F.5.1.12). El enunciado es el texto hasta el siguiente código.
     */
    public function parse(string $text): Collection
    {
        $text = self::unirGuiones(self::reconstruirColumnas($text));
        $text = preg_replace('/\s+/u', ' ', $text);

        // El lookbehind excluye los códigos que NO son destrezas y que
        // comparten sufijo: «I.CN.2.2.1» es un INDICADOR de evaluación, no la
        // destreza «CN.2.2.1». Sin esto se importaban como si lo fueran.
        $codeRe = '/(?<![A-Za-z]\.)\b((?:CN\.[FQB]|CS\.[HFC]|CN|CS|LL|M|ECA|EF|EG)\.(\d)\.(\d{1,2})\.(\d{1,3}))\.?\s/u';

        preg_match_all($codeRe, $text, $m, PREG_OFFSET_CAPTURE);
        $out = collect();

        foreach ($m[1] as $i => [$code, $offset]) {
            $start = $offset + strlen($m[0][$i][0]);
            $end = isset($m[0][$i + 1]) ? $m[0][$i + 1][1] : strlen($text);
            // Corte SEGURO en bytes: partir un carácter multibyte deja UTF-8
            // inválido y preg_replace /u devuelve NULL — la última destreza del
            // documento se descartaba en silencio (auditoría PR #18).
            $end = min($end, $start + strlen(mb_strcut($text, $start, 600)));
            $stmt = self::cortarInvasores(trim(substr($text, $start, $end - $start)));
            // Las ligaduras tipográficas del PDF (ﬁ, ﬂ…) rompen búsquedas y
            // comparaciones sin cambiar cómo se VE el texto: se normalizan.
            $stmt = str_replace(["\u{FB00}", "\u{FB01}", "\u{FB02}", "\u{FB03}", "\u{FB04}"], ['ff', 'fi', 'fl', 'ffi', 'ffl'], $stmt);

            if (mb_strlen($stmt) < (int) $this->option('min-len')) {
                continue;
            }

            // Prefijo → asignatura; dígito tras prefijo → subnivel.
            $prefix = preg_replace('/\.\d+\.\d+\.\d+$/', '', $code);
            $area = self::AREA_MAP[$prefix] ?? null;
            if (! $area) {
                continue;
            }

            $out->push([
                'code' => rtrim($code, '.'),
                'area' => $area,
                'subnivel' => (int) $m[2][$i][0],
                'text' => mb_substr($stmt, 0, 900),
            ]);
        }

        // El mismo código aparece varias veces (la tabla de destrezas y la
        // matriz de criterios). Se queda la ocurrencia de mejor CALIDAD, no la
        // más larga: la contaminada por el troceado de columnas es justamente
        // la más larga, así que «la más larga gana» premiaba la basura.
        // Primero la CALIDAD y, entre las válidas, la MÁS LARGA: la completa.
        // (Preferir la más corta premiaba al amasijo ya recortado, que también
        // pasa el oráculo pero es media destreza. OJO con sortBy([closure,
        // closure]): no ordena por los closures — compara null<=>null — así
        // que aquí va un comparador explícito.)
        return $out->groupBy('code')
            ->map(fn ($g) => $g->sort(fn ($a, $b) => [
                self::evaluarCalidad($a['text'])['valido'] ? 0 : 1, -mb_strlen($a['text']),
            ] <=> [
                self::evaluarCalidad($b['text'])['valido'] ? 0 : 1, -mb_strlen($b['text']),
            ])->first())
            ->values();
    }
}
