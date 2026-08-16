<?php

namespace App\Console\Commands;

use App\Models\CurNode;
use App\Models\Track;
use App\Services\Blueprint\CourseBlueprint;
use App\Services\Blueprint\YamlWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Exporta el blueprint de un curso al repo `cursos-moodle` (proyecto
 * e-learnium): `curso.yaml` + `COBERTURA.md`.
 *
 *   php artisan curso:blueprint CN.F --grado=g11 --track=ORD --out=../cursos-moodle/fisica-1bgu
 *
 * Es IDEMPOTENTE y determinista: mismo grafo → mismos bytes. El compilador
 * decide si recompilar comparando `fingerprint`.
 */
class CourseBlueprintExport extends Command
{
    protected $signature = 'curso:blueprint
        {nodo : uuid, path del grafo o native_code del nodo (p. ej. CN.F)}
        {--grado= : native_code del grado, para desambiguar un código repetido entre grados}
        {--marco= : código del framework (p. ej. EC-MINEDEC) si el código existe en varios}
        {--track= : código del track (ORD, PCEI-BSI…) para traer pesos y fase}
        {--idnumber= : idnumber Moodle a la fuerza (por defecto AH-<codigo>-<grado>)}
        {--out= : carpeta destino; sin ella imprime el YAML por pantalla}';

    protected $description = 'Genera curso.yaml y COBERTURA.md de un nodo del grafo para el pipeline de cursos Moodle';

    public function handle(CourseBlueprint $builder, YamlWriter $yaml): int
    {
        $node = $this->resolveNode($this->argument('nodo'));
        if (! $node) {
            return self::FAILURE;
        }

        $track = null;
        if ($code = $this->option('track')) {
            $track = Track::where('code', $code)->first();
            if (! $track) {
                $this->error("No existe el track «{$code}». Disponibles: ".Track::pluck('code')->implode(', '));

                return self::FAILURE;
            }
        }

        $blueprint = $builder->forNode($node, $track, $this->option('idnumber'));
        $body = $yaml->dump($blueprint);

        if (! $out = $this->option('out')) {
            $this->line($body);

            return self::SUCCESS;
        }

        if (! is_dir($out) && ! mkdir($out, 0o775, true) && ! is_dir($out)) {
            $this->error("No se pudo crear la carpeta {$out}");

            return self::FAILURE;
        }

        file_put_contents(rtrim($out, '/').'/curso.yaml', $body);
        file_put_contents(rtrim($out, '/').'/COBERTURA.md', $this->cobertura($blueprint));

        $r = $blueprint['resumen'];
        $this->info("Blueprint de «{$blueprint['curso']['titulo']}» ({$blueprint['curso']['idnumber']}) → {$out}");
        $this->line("  unidades: {$r['unidades']} · destrezas: {$r['destrezas']} · "
            ."practicables en AllyuHub: {$r['destrezas_practicables']}");
        if ($r['destrezas_sin_verificar'] > 0) {
            $this->warn("  ojo: {$r['destrezas_sin_verificar']} destreza(s) sin verificar contra el PDF oficial.");
        }
        $this->line('  fingerprint: '.substr($blueprint['fingerprint'], 0, 16));

        return self::SUCCESS;
    }

    /**
     * Acepta uuid, path o native_code. Si el código está repetido (Cambridge
     * recicla códigos; MINEDEC replica por subnivel) NO adivina: exige
     * --grado/--marco y lista los candidatos. Regla 2 de CLAUDE.md.
     */
    private function resolveNode(string $ref): ?CurNode
    {
        if (Str::isUuid($ref) && $node = CurNode::find($ref)) {
            return $node;
        }

        $query = CurNode::query()
            ->where(fn ($q) => $q->where('path', $ref)->orWhere('native_code', $ref))
            ->when($this->option('marco'), fn ($q, $marco) => $q->whereHas(
                'version.framework', fn ($f) => $f->where('code', $marco)
            ));

        $candidates = $query->with('version.framework')->get();

        if ($grade = $this->option('grado')) {
            $candidates = $candidates->filter(
                fn (CurNode $n) => $n->ancestors()->concat([$n])
                    ->contains(fn (CurNode $a) => $a->node_type === 'grado' && $a->native_code === $grade)
            )->values();
        }

        if ($candidates->isEmpty()) {
            $this->error("No encontré ningún nodo con «{$ref}»".
                ($this->option('grado') ? " bajo el grado {$this->option('grado')}." : '.'));

            return null;
        }

        if ($candidates->count() > 1) {
            $this->error("«{$ref}» es ambiguo ({$candidates->count()} nodos). Afina con --grado o --marco:");
            $this->table(['id', 'marco', 'tipo', 'path'], $candidates->map(fn (CurNode $n) => [
                $n->id, $n->version->framework->code, $n->node_type, $n->path,
            ])->all());

            return null;
        }

        return $candidates->first();
    }

    /**
     * Tabla DCD ↔ contenido: la exige el contrato del repo de cursos (cada DCD
     * ≥1 lección y ≥3 preguntas). AllyuHub genera el ESQUELETO con las filas ya
     * puestas; quien produce el contenido rellena las dos últimas columnas.
     */
    private function cobertura(array $blueprint): string
    {
        $c = $blueprint['curso'];
        $md = "# COBERTURA — {$c['titulo']} ({$c['idnumber']})\n\n"
            ."Generado por AllyuHub desde el grafo curricular. **No editar las columnas\n"
            ."de código y destreza**: se regeneran. Rellena `Lecciones` y `Preguntas`.\n\n"
            ."- Marco: `{$c['marco']}` · versión `{$c['version_marco']}`\n"
            .'- Nodo: `'.$c['nodo']['path']."`\n"
            .($c['track'] ? "- Track: `{$c['track']}`\n" : '')
            .'- Fingerprint del currículo: `'.$blueprint['fingerprint']."`\n"
            .'- Mínimos: '.CourseBlueprint::MIN_LESSONS.' lección y '
            .CourseBlueprint::MIN_QUESTIONS." preguntas por DCD\n\n";

        foreach ($blueprint['unidades'] as $unit) {
            $md .= "## {$unit['slug']} — {$unit['titulo']}\n\n"
                ."| DCD | Destreza | Práctica AllyuHub | Lecciones | Preguntas |\n"
                ."|---|---|---|---|---|\n";

            foreach ($unit['destrezas'] as $d) {
                $statement = str_replace(['|', "\n"], ['\\|', ' '], $d['enunciado']);
                $practice = $d['practicable'] ? "{$d['items']} ítem(s)" : '—';
                $md .= "| `{$d['codigo']}` | {$statement} | {$practice} |  |  |\n";
            }

            $md .= "\n";
        }

        return $md;
    }
}
