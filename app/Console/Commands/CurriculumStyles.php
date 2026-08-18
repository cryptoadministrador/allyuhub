<?php

namespace App\Console\Commands;

use App\Models\CurNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Escribe la identidad visual de cada asignatura (icono y color) en el grafo,
 * desde database/data/curriculo-semilla.json.
 *
 *   php artisan curriculo:estilos
 *
 * QUIRÚRGICO a propósito: en producción el currículo REAL ya está importado
 * encima de la semilla, así que este comando solo actualiza `attrs` de nodos
 * asignatura que YA existen — no crea nodos, no toca destrezas, y respeta lo
 * que hubiera en attrs. Es idempotente: si nada cambia, no reescribe la fila.
 */
class CurriculumStyles extends Command
{
    protected $signature = 'curriculo:estilos
        {--dry-run : Muestra lo que haría sin escribir}';

    protected $description = 'Guarda el icono y el color de cada asignatura en el grafo curricular';

    public function handle(): int
    {
        $ruta = database_path('data/curriculo-semilla.json');
        if (! is_file($ruta)) {
            $this->error("No existe {$ruta}");

            return self::FAILURE;
        }

        $json = json_decode(file_get_contents($ruta), true);

        // codigo de asignatura → [icon, color]. El JSON repite la asignatura en
        // cada grado con los mismos valores; basta con la primera aparición.
        $estilos = [];
        foreach ($json['grados'] ?? [] as $g) {
            foreach ($g['asignaturas'] ?? [] as $a) {
                if (isset($a['codigo'], $a['icon'], $a['color'])) {
                    $estilos[$a['codigo']] ??= ['icon' => $a['icon'], 'color' => $a['color']];
                }
            }
        }

        if ($estilos === []) {
            $this->error('El JSON no trae iconos ni colores de asignatura.');

            return self::FAILURE;
        }

        $tocadas = 0;
        $saltadas = 0;

        DB::transaction(function () use ($estilos, &$tocadas, &$saltadas) {
            CurNode::query()
                ->where('node_type', 'asignatura')
                ->whereIn('native_code', array_keys($estilos))
                ->orderBy('id')
                ->chunkById(200, function ($nodos) use ($estilos, &$tocadas, &$saltadas) {
                    foreach ($nodos as $nodo) {
                        $estilo = $estilos[$nodo->native_code];
                        $attrs = $nodo->attrs ?? [];

                        if (($attrs['icon'] ?? null) === $estilo['icon']
                            && ($attrs['color'] ?? null) === $estilo['color']) {
                            $saltadas++;

                            continue;   // ya está: no se reescribe la fila
                        }

                        if (! $this->option('dry-run')) {
                            $nodo->update(['attrs' => [...$attrs, ...$estilo]]);
                        }
                        $tocadas++;
                    }
                });
        });

        $this->info(sprintf(
            '%s%d asignatura(s) con icono y color · %d ya estaban al día · %d estilos en el JSON.',
            $this->option('dry-run') ? '[dry-run] ' : '', $tocadas, $saltadas, count($estilos),
        ));

        return self::SUCCESS;
    }
}
