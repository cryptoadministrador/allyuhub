<?php

namespace App\Console\Commands;

use App\Models\CurNode;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use Illuminate\Console\Command;
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
 *  1. PDF → texto con pdftotext -layout (o lee .txt directamente).
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
        {--min-len=25 : Longitud mínima del enunciado para aceptarlo}';

    protected $description = 'Importa destrezas del currículo oficial MINEDEC al grafo';

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

        if ($this->option('dry-run')) {
            $this->comment('Dry-run: no se escribió nada.');

            return self::SUCCESS;
        }

        $version = FrameworkVersion::whereHas('framework', fn ($q) => $q->where('code', 'EC-MINEDEC'))
            ->latest('created_at')->firstOrFail();

        $stats = ['nuevas' => 0, 'actualizadas' => 0, 'sin_nodo' => 0, 'reemplazadas' => 0];

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

                // Retira los marcadores del seeder de esa asignatura+subnivel que
                // nunca existieron en el currículo real (códigos sintéticos g{n}.b{x}.{y}).
                if ($this->option('official')) {
                    $stats['reemplazadas'] += LearningObjective::where('version_id', $version->id)
                        ->where('is_verified', false)
                        ->where('native_code', 'like', $d['area'].'.%')
                        ->whereRaw("(attrs->>'imported_from') IS NULL")
                        ->whereHas('node.parent', fn ($q) => $q->where('native_code', $d['area']))
                        ->delete();
                }
            }

            if ($this->option('official')) {
                $version->update(['source_sha256' => hash_file('sha256', $this->argument('file'))]);
            }
        });

        $this->info(sprintf(
            'Importación completa: %d nuevas · %d actualizadas · %d marcadores retirados · %d sin nodo destino.',
            $stats['nuevas'], $stats['actualizadas'], $stats['reemplazadas'], $stats['sin_nodo'],
        ));
        if (! $this->option('official')) {
            $this->comment('Importadas SIN verificar (sin --official). Repite con --official cuando el archivo sea el PDF oficial.');
        }

        return self::SUCCESS;
    }

    private function pdfToText(string $file): ?string
    {
        $out = tempnam(sys_get_temp_dir(), 'mineduc').'.txt';
        $p = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $file, $out]);
        $p->setTimeout(300)->run();

        return $p->isSuccessful() ? file_get_contents($out) : null;
    }

    /**
     * Extrae pares código→enunciado. Los códigos oficiales tienen la forma
     * PREFIJO.subnivel.bloque.numero (CN.4.3.5) o PREFIJO.SUB.5.b.n en BGU
     * (CN.F.5.1.12). El enunciado es el texto hasta el siguiente código.
     */
    private function parse(string $text): \Illuminate\Support\Collection
    {
        // Normaliza: une líneas partidas, colapsa espacios.
        $text = preg_replace('/-\n\s*/u', '', $text);          // palabras cortadas con guion
        $text = preg_replace('/\s+/u', ' ', $text);

        $codeRe = '/\b((?:CN\.[FQB]|CS\.[HFC]|CN|CS|LL|M|ECA|EF|EG)\.(\d)\.(\d{1,2})\.(\d{1,3}))\.?\s/u';

        preg_match_all($codeRe, $text, $m, PREG_OFFSET_CAPTURE);
        $out = collect();

        foreach ($m[1] as $i => [$code, $offset]) {
            $start = $offset + strlen($m[0][$i][0]);
            $end = isset($m[0][$i + 1]) ? $m[0][$i + 1][1] : min(strlen($text), $start + 600);
            $stmt = trim(substr($text, $start, $end - $start));

            // Limpia colas típicas de tabla (referencias a criterios: "Ref. CE.CN.4.5" etc.)
            $stmt = preg_replace('/\s*\(?Ref\.?\s*[A-Z.]*CE\.[A-Z.\d]+\)?\.?$/u', '', $stmt);
            $stmt = rtrim($stmt, " ·|");

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

        // El mismo código puede aparecer varias veces (tabla + anexos): quédate con el enunciado más largo.
        return $out->groupBy('code')
            ->map(fn ($g) => $g->sortByDesc(fn ($d) => mb_strlen($d['text']))->first())
            ->values();
    }
}
