<?php

namespace App\Console\Commands;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\Track;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Importa el Currículo Integrado de Alfabetización y Postalfabetización PRIORIZADO
 * (Acuerdo MINEDUC-2025-00034-A, que derogó al 2017-00040-A) al grafo curricular.
 *
 *   php artisan epja:import storage/curriculo/curriculo-alfabetizacion-priorizado.pdf --dry-run
 *   php artisan epja:import storage/curriculo/curriculo-alfabetizacion-priorizado.pdf --official
 *
 * Qué hace:
 *  1. PDF → texto con pdftotext (o lee .txt directamente).
 *  2. Parsea los códigos PROPIOS de este currículo (¡no usa los del 2016!):
 *       A.RS.n / A.ET.n / A.CC.n   destrezas de Alfabetización por bloque integrador
 *       P.RS.n / P.ET.n / P.CC.n   destrezas de Postalfabetización
 *       CE.A.n / CE.P.n            criterios de evaluación
 *       I.A.x.y / I.P.x.y          indicadores (ligados al criterio x)
 *       O.A.n / O.P.n              objetivos generales del nivel
 *       CAI.JA.b.n                 inserción de Cívica y Acompañamiento Integral
 *  3. Crea (idempotente) el marco EC-EPJA con su versión «2025-00034-A» y el árbol
 *     programa → nivel (alfabetizacion|postalfabetizacion) → bloque (rs|et|cc),
 *     más la rama transversal cai → bloque 1..3. Las destrezas entran como
 *     learning_objectives; criterios, indicadores y objetivos generales viajan en
 *     attrs del nodo nivel (referencia, no unidades atómicas).
 *  4. Reancla los trayectos PCEI-ALFA y PCEI-POST: sus fases sueltan el
 *     'mapeo-interno' (equivalencia por grado del currículo ordinario) y pasan a
 *     apuntar a las destrezas EPJA reales con source='acuerdo-2025-00034-A'.
 *
 * LO QUE ESTE DOCUMENTO NO TRAE (verificado contra el PDF completo, 2026-08):
 *  - Dosificación por módulos: el acuerdo define ALFA = módulos 1-2 y POST = 3-6,
 *    pero el currículo asigna destrezas SOLO al nivel. Por eso cada fase del track
 *    recibe el nivel completo; repartir por módulo es decisión pedagógica del
 *    docente/plataforma, no del importador.
 *  - Carga horaria por asignatura (el currículo es integrado: no hay asignaturas).
 *  - La numeración de destrezas tiene HUECOS a propósito: es un currículo
 *    priorizado (subconjunto). No valides continuidad.
 *
 * REGLA DE HONESTIDAD: sin --official todo queda is_verified=false y no se
 * registra sha256. La numeración puede traer ruido de OCR («I. P.23.2.»):
 * el parser lo normaliza, pero cotejar una muestra contra el PDF sigue siendo
 * tarea humana antes de correr con --official.
 */
class ImportEpja extends Command
{
    protected $signature = 'epja:import
        {file : Ruta al PDF o TXT del currículo priorizado}
        {--official : Marca las destrezas como verificadas y registra el sha256}
        {--dry-run : Muestra lo que haría sin escribir en la base}
        {--min-len=25 : Longitud mínima del enunciado para aceptarlo}';

    protected $description = 'Importa el currículo EPJA priorizado (Acuerdo 2025-00034-A) al grafo';

    private const BLOQUES = [
        'RS' => 'Recuperación de saberes',
        'ET' => 'Educación y trabajo',
        'CC' => 'Convivencia y ciudadanía',
    ];

    private const NIVELES = [
        'A' => ['slug' => 'alfabetizacion', 'label' => 'Alfabetización', 'modulos' => [1, 2], 'track' => 'PCEI-ALFA'],
        'P' => ['slug' => 'postalfabetizacion', 'label' => 'Postalfabetización', 'modulos' => [3, 4, 5, 6], 'track' => 'PCEI-POST'],
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("No existe el archivo: {$file}");

            return self::FAILURE;
        }

        // Dos extracciones: el flujo simple va bien para A/P (códigos anclados a
        // inicio de línea), pero la tabla CAI es de 4 columnas y solo se puede
        // reconstruir con -layout. Un .txt de fixture sirve para ambas pasadas.
        $isPdf = str_ends_with(strtolower($file), '.pdf');
        $text = $isPdf ? $this->pdfToText($file, layout: false) : file_get_contents($file);
        $layout = $isPdf ? $this->pdfToText($file, layout: true) : $text;

        if ($text === null || mb_strlen($text) < 60) {
            $this->error('No se pudo extraer texto útil del archivo.');

            return self::FAILURE;
        }

        $parsed = $this->parse($text);
        // Las CAI del flujo simple salen truncadas/mal asociadas: se descartan y
        // se sustituyen por la pasada con columnas.
        $parsed['destrezas'] = $parsed['destrezas']->reject(fn ($d) => $d['nivel'] === 'CAI')
            ->concat($this->parseCai($layout ?? ''))
            ->values();

        if ($parsed['destrezas']->isEmpty()) {
            $this->error('No se encontró ninguna destreza A.*/P.*/CAI.* (¿es el documento correcto?).');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Encontrado: %d destrezas (%d ALFA · %d POST · %d CAI) · %d criterios · %d indicadores · %d objetivos.',
            $parsed['destrezas']->count(),
            $parsed['destrezas']->where('nivel', 'A')->count(),
            $parsed['destrezas']->where('nivel', 'P')->count(),
            $parsed['destrezas']->where('nivel', 'CAI')->count(),
            $parsed['criterios']->count(),
            $parsed['indicadores']->count(),
            $parsed['objetivos']->count(),
        ));
        $this->table(
            ['código', 'nivel', 'bloque', 'enunciado (inicio)'],
            $parsed['destrezas']->take(8)->map(fn ($d) => [
                $d['code'], $d['nivel'], $d['bloque'], mb_substr($d['text'], 0, 60).'…',
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $this->comment('Dry-run: no se escribió nada.');

            return self::SUCCESS;
        }

        $stats = ['nuevas' => 0, 'actualizadas' => 0, 'reancladas' => 0];

        DB::transaction(function () use ($parsed, &$stats) {
            $ver = $this->frameworkVersion();
            $nodes = $this->buildTree($ver, $parsed);
            $this->importDestrezas($ver, $nodes, $parsed['destrezas'], $stats);
            $this->rewireTracks($ver, $stats);

            if ($this->option('official')) {
                $ver->update(['source_sha256' => hash_file('sha256', $this->argument('file'))]);
            }
        });

        $this->info(sprintf(
            'Importación completa: %d nuevas · %d actualizadas · %d destrezas reancladas a los tracks PCEI.',
            $stats['nuevas'], $stats['actualizadas'], $stats['reancladas'],
        ));
        if (! $this->option('official')) {
            $this->comment('Importadas SIN verificar (sin --official). Repite con --official cuando el archivo sea el PDF oficial.');
        }

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- parseo

    private function pdfToText(string $file, bool $layout): ?string
    {
        $out = tempnam(sys_get_temp_dir(), 'epja').'.txt';
        $args = array_values(array_filter(['pdftotext', $layout ? '-layout' : null, '-enc', 'UTF-8', $file, $out]));
        $p = new Process($args);
        $p->setTimeout(300)->run();

        return $p->isSuccessful() ? file_get_contents($out) : null;
    }

    /**
     * Parsea las destrezas CAI desde el texto CON columnas (-layout).
     * La tabla es CÓDIGO | DESTREZA | HABILIDADES | INDICADORES: el enunciado es
     * la segunda columna, que empieza tras el código y termina donde arranca la
     * tercera (dos o más espacios). Las líneas de continuación conservan la misma
     * columna, así que se recorta por posición y se corta en el primer hueco.
     */
    private function parseCai(string $layout): Collection
    {
        $destrezas = collect();
        $lines = preg_split('/\R/u', $layout);
        $current = null;      // ['code' => ..., 'col' => posición de la columna, 'text' => ...]

        $flush = function () use (&$current, $destrezas) {
            if ($current && mb_strlen($current['text']) >= (int) $this->option('min-len')) {
                $destrezas->put($current['code'], [
                    'code' => $current['code'], 'nivel' => 'CAI',
                    'bloque' => 'b'.explode('.', $current['code'])[2],
                    'text' => $this->dehyphenate(trim(preg_replace('/\s+/u', ' ', $current['text']))),
                ]);
            }
            $current = null;
        };

        foreach ($lines as $line) {
            if (preg_match('/^(\s*)CAI\.JA\.(\d+)\.(\d+)\.?\s+(\S.*)$/u', $line, $m)) {
                $flush();
                $col = mb_strlen($m[1]) + mb_strlen('CAI.JA.'.$m[2].'.'.$m[3]);
                $col += mb_strlen($line) - $col - mb_strlen(mb_ltrim(mb_substr($line, $col)));
                $current = [
                    'code' => sprintf('CAI.JA.%d.%d', (int) $m[2], (int) $m[3]),
                    'col' => $col,
                    'text' => preg_split('/\s{2,}/u', $m[4])[0],
                ];

                continue;
            }
            if ($current === null) {
                continue;
            }
            // Línea de continuación: texto que empieza (±2) en la columna del enunciado.
            $cell = mb_substr($line, $current['col']);
            $indent = mb_strlen($line) - mb_strlen(mb_ltrim($line));
            if (trim($cell) === '' || abs($indent - $current['col']) > 2) {
                // Celda vacía o texto de otra columna: si el enunciado ya cerró
                // con punto, la destreza está completa.
                if (str_ends_with(rtrim($current['text']), '.')) {
                    $flush();
                }

                continue;
            }
            $current['text'] .= ' '.preg_split('/\s{2,}/u', trim($cell))[0];
        }
        $flush();

        return $destrezas->values();
    }

    /**
     * Trocea el texto por códigos anclados a inicio de línea. Cada segmento va
     * del código hasta el siguiente código, limpiando encabezados de tabla
     * repetidos por salto de página y números de página sueltos.
     *
     * @return array{destrezas: Collection, criterios: Collection, indicadores: Collection, objetivos: Collection}
     */
    private function parse(string $text): array
    {
        // Normaliza el ruido de OCR conocido: «I. P.23.2.» → «I.P.23.2.»
        $text = preg_replace('/^I\.\s+([AP])\./mu', 'I.$1.', $text);

        $pattern = '/^(?:'
            .'(?<destreza>(?<dn>[AP])\.(?<db>RS|ET|CC)\.(?<dnum>\d+)\.?)'
            .'|(?<cai>CAI\.JA\.(?<cb>\d+)\.(?<cn>\d+)\.?)'
            .'|(?<criterio>CE\.(?<ce_n>[AP])\.(?<ce_num>\d+)\.?)'
            .'|(?<indicador>I\.(?<in>[AP])\.(?<i_ce>\d+)\.(?<i_num>\d+)\.?)'
            .'|(?<objetivo>O\.(?<on>[AP])\.(?<o_num>\d+)\.?)'
            .')\s*/mu';

        preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $destrezas = collect();
        $criterios = collect();
        $indicadores = collect();
        $objetivos = collect();
        $minLen = (int) $this->option('min-len');

        foreach ($m as $i => $hit) {
            $start = $hit[0][1] + strlen($hit[0][0]);
            $end = isset($m[$i + 1]) ? $m[$i + 1][0][1] : strlen($text);
            $body = $this->cleanStatement(substr($text, $start, $end - $start));

            if (mb_strlen($body) < $minLen) {
                continue;
            }

            if (($hit['destreza'][1] ?? -1) >= 0 && $hit['destreza'][0] !== '') {
                $code = sprintf('%s.%s.%d', $hit['dn'][0], $hit['db'][0], (int) $hit['dnum'][0]);
                // Una destreza puede repetirse bajo varios criterios: gana la primera.
                if (! $destrezas->has($code)) {
                    $destrezas->put($code, [
                        'code' => $code, 'nivel' => $hit['dn'][0],
                        'bloque' => $hit['db'][0], 'text' => $body,
                    ]);
                }
            } elseif (($hit['cai'][1] ?? -1) >= 0 && $hit['cai'][0] !== '') {
                $code = sprintf('CAI.JA.%d.%d', (int) $hit['cb'][0], (int) $hit['cn'][0]);
                if (! $destrezas->has($code)) {
                    $destrezas->put($code, [
                        'code' => $code, 'nivel' => 'CAI',
                        'bloque' => 'b'.(int) $hit['cb'][0], 'text' => $body,
                    ]);
                }
            } elseif (($hit['criterio'][1] ?? -1) >= 0 && $hit['criterio'][0] !== '') {
                $criterios->push([
                    'code' => sprintf('CE.%s.%d', $hit['ce_n'][0], (int) $hit['ce_num'][0]),
                    'nivel' => $hit['ce_n'][0], 'text' => $body,
                ]);
            } elseif (($hit['indicador'][1] ?? -1) >= 0 && $hit['indicador'][0] !== '') {
                $indicadores->push([
                    'code' => sprintf('I.%s.%d.%d', $hit['in'][0], (int) $hit['i_ce'][0], (int) $hit['i_num'][0]),
                    'nivel' => $hit['in'][0], 'criterio' => (int) $hit['i_ce'][0], 'text' => $body,
                ]);
            } elseif (($hit['objetivo'][1] ?? -1) >= 0 && $hit['objetivo'][0] !== '') {
                $objetivos->push([
                    'code' => sprintf('O.%s.%d', $hit['on'][0], (int) $hit['o_num'][0]),
                    'nivel' => $hit['on'][0], 'text' => $body,
                ]);
            }
        }

        return [
            'destrezas' => $destrezas->values(),
            'criterios' => $criterios->unique('code')->values(),
            'indicadores' => $indicadores->unique('code')->values(),
            'objetivos' => $objetivos->unique('code')->values(),
        ];
    }

    /** Limpia encabezados de tabla repetidos, números de página y espacios. */
    private function cleanStatement(string $raw): string
    {
        $lines = preg_split('/\R/u', $raw);
        $keep = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || preg_match('/^\d{1,3}$/', $t)) {
                continue;   // línea vacía o número de página
            }
            if (preg_match('/^(Criterios? de evaluaci|Destrezas con criterios|Indicadores de evaluaci|CÓDIGO|HABILIDADES SOCIOEMOCIONALES|Mapa curricular)/iu', $t)) {
                continue;   // encabezado de tabla repetido por salto de página
            }
            $keep[] = $t;
        }

        return $this->dehyphenate(trim(preg_replace('/\s+/u', ' ', implode(' ', $keep))));
    }

    /** Repara los cortes silábicos del PDF: «imparciali- dad» → «imparcialidad». */
    private function dehyphenate(string $text): string
    {
        return preg_replace('/(\p{Ll})-\s+(\p{Ll})/u', '$1$2', $text);
    }

    // ------------------------------------------------------------- escritura

    private function frameworkVersion(): FrameworkVersion
    {
        $fw = Framework::firstOrCreate(
            ['code' => 'EC-EPJA'],
            [
                'authority' => 'MINEDEC', 'kind' => 'national', 'country' => 'EC',
                'label' => ['es' => 'Currículo EPJA — personas con escolaridad inconclusa'],
            ],
        );

        return FrameworkVersion::firstOrCreate(
            ['framework_id' => $fw->id, 'label' => '2025-00034-A'],
            [
                'valid_from' => '2025-08-25',
                'source_url' => 'https://educacion.gob.ec/wp-content/uploads/downloads/2025/09/curriculo-alfabetizacion-priorizado.pdf',
            ],
        );
    }

    /** @return array<string, CurNode> nodos bloque indexados por «nivel|bloque» */
    private function buildTree(FrameworkVersion $ver, array $parsed): array
    {
        $programa = CurNode::updateOrCreate(
            ['version_id' => $ver->id, 'path' => 'epja'],
            [
                'node_type' => 'programa',
                'title' => ['es' => 'Alfabetización y Postalfabetización priorizado'],
                'seq' => 0,
                'attrs' => ['acuerdo' => 'MINEDUC-2025-00034-A', 'deroga' => 'MINEDUC-2017-00040-A'],
            ],
        );

        $nodes = [];
        $seq = 0;
        foreach (self::NIVELES as $letra => $nivel) {
            $nivelNode = CurNode::updateOrCreate(
                ['version_id' => $ver->id, 'path' => 'epja.'.$nivel['slug']],
                [
                    'parent_id' => $programa->id, 'node_type' => 'nivel',
                    'native_code' => $letra, 'title' => ['es' => $nivel['label']],
                    'seq' => $seq++,
                    'attrs' => [
                        // Los módulos del acuerdo: la dosificación fina no existe en el
                        // documento, así que viven aquí como dato normativo, no como nodos.
                        'modulos' => $nivel['modulos'],
                        'dias_por_modulo' => 100,
                        'objetivos_generales' => $parsed['objetivos']->where('nivel', $letra)
                            ->map(fn ($o) => [$o['code'], $o['text']])->values()->all(),
                        'criterios_evaluacion' => $parsed['criterios']->where('nivel', $letra)
                            ->map(fn ($c) => [$c['code'], $c['text']])->values()->all(),
                        'indicadores' => $parsed['indicadores']->where('nivel', $letra)
                            ->map(fn ($i) => [$i['code'], $i['text']])->values()->all(),
                    ],
                ],
            );

            $bseq = 0;
            foreach (self::BLOQUES as $sigla => $nombre) {
                $nodes[$letra.'|'.$sigla] = CurNode::updateOrCreate(
                    ['version_id' => $ver->id, 'path' => 'epja.'.$nivel['slug'].'.'.strtolower($sigla)],
                    [
                        'parent_id' => $nivelNode->id, 'node_type' => 'bloque',
                        'native_code' => $sigla, 'title' => ['es' => $nombre], 'seq' => $bseq++,
                    ],
                );
            }
        }

        // Rama transversal: Cívica y Acompañamiento Integral (una hora pedagógica).
        $cai = CurNode::updateOrCreate(
            ['version_id' => $ver->id, 'path' => 'epja.cai'],
            [
                'parent_id' => $programa->id, 'node_type' => 'insercion',
                'native_code' => 'CAI.JA',
                'title' => ['es' => 'Cívica y Acompañamiento Integral en el aula'],
                'seq' => $seq++, 'attrs' => ['transversal' => true],
            ],
        );
        foreach ($parsed['destrezas']->where('nivel', 'CAI')->pluck('bloque')->unique() as $b) {
            $nodes['CAI|'.$b] = CurNode::updateOrCreate(
                ['version_id' => $ver->id, 'path' => 'epja.cai.'.$b],
                [
                    'parent_id' => $cai->id, 'node_type' => 'bloque',
                    'native_code' => strtoupper($b),
                    'title' => ['es' => 'Bloque curricular '.substr($b, 1)],
                    'seq' => (int) substr($b, 1),
                ],
            );
        }

        return $nodes;
    }

    private function importDestrezas(FrameworkVersion $ver, array $nodes, Collection $destrezas, array &$stats): void
    {
        foreach ($destrezas as $d) {
            $node = $nodes[$d['nivel'].'|'.$d['bloque']] ?? null;
            if (! $node) {
                continue;
            }

            $payload = [
                'statement' => ['es' => $d['text']],
                'attrs' => [
                    'fuente' => 'acuerdo-2025-00034-A',
                    'imported_from' => basename($this->argument('file')),
                    'imported_at' => now()->toDateString(),
                ],
            ];
            if ($this->option('official')) {
                $payload['is_verified'] = true;   // sin --official NUNCA se degrada lo verificado
            }

            $existing = LearningObjective::where('version_id', $ver->id)
                ->where('native_code', $d['code'])->first();

            if ($existing) {
                if (! $existing->is_verified || $this->option('official')) {
                    $existing->update($payload);
                }
                $stats['actualizadas']++;
            } else {
                LearningObjective::create($payload + [
                    'node_id' => $node->id, 'version_id' => $ver->id,
                    'native_code' => $d['code'],
                    'is_verified' => (bool) $this->option('official'),
                ]);
                $stats['nuevas']++;
            }
        }
    }

    /**
     * Sustituye el mapeo-interno de las fases PCEI-ALFA/POST por las destrezas
     * EPJA reales. Sin dosificación oficial por módulo, cada fase del track recibe
     * el nivel completo (las CAI van a todas: son transversales).
     */
    private function rewireTracks(FrameworkVersion $ver, array &$stats): void
    {
        foreach (self::NIVELES as $letra => $nivel) {
            $track = Track::where('code', $nivel['track'])->first();
            if (! $track) {
                continue;   // BD sin el seeder de tracks: importar el grafo igual
            }

            $ids = LearningObjective::where('version_id', $ver->id)
                ->where(fn ($q) => $q
                    ->where('native_code', 'like', $letra.'.%')
                    ->orWhere('native_code', 'like', 'CAI.%'))
                ->pluck('id');

            foreach ($track->phases()->where('is_propedeutic', false)->get() as $phase) {
                $phase->objectives()->wherePivot('source', 'mapeo-interno')->detach();
                $phase->objectives()->syncWithoutDetaching(
                    $ids->mapWithKeys(fn ($id) => [$id => ['source' => 'acuerdo-2025-00034-A']])->all()
                );
                $stats['reancladas'] += $ids->count();
            }
        }
    }
}
