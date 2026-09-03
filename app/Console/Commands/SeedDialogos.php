<?php

namespace App\Console\Commands;

use App\Models\Dialogo;
use App\Models\LearningObjective;
use App\Services\Audio\AlmacenDeAudio;
use App\Services\Audio\ClipCurricular;
use App\Services\Dialogo\Nodos;
use App\Services\Lesson\DestinosDeBloque;
use App\Services\Practice\Lenguas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siembra los guiones del interlocutor desde `database/data/dialogos-lenguas.php`.
 *
 *   php artisan dialogos:sembrar [--dry-run]
 *
 * Idempotente por clave natural (descriptor, lengua, slug): re-sembrar
 * actualiza el guion sin duplicar ni CAMBIAR LA FIRMA — un diálogo ya firmado
 * sigue firmado. Nace SIN firmar; se publica con `dialogos:firmar`.
 *
 * Falla ANTES de escribir nada (una transacción): lengua fuera de lista,
 * descriptor que no existe en el grafo, grafo de nodos roto o un clip que falta
 * revientan nombrando la entrada. Un diálogo a medias delante de un alumno es
 * peor que ninguno.
 */
class SeedDialogos extends Command
{
    protected $signature = 'dialogos:sembrar
        {--banco= : Ruta a un banco alternativo (por defecto database/data/dialogos-lenguas.php)}
        {--audio= : Directorio de clips fuente (por defecto database/data/audio-lenguas)}
        {--dry-run : Valida el banco entero y no escribe nada}';

    protected $description = 'Siembra los diálogos guionizados del interlocutor (nacen sin firmar)';

    public function handle(): int
    {
        $banco = require ($this->option('banco') ?: database_path('data/dialogos-lenguas.php'));
        $almacen = new AlmacenDeAudio;
        $seco = (bool) $this->option('dry-run');

        $creados = 0;
        $actualizados = 0;

        try {
            DB::transaction(function () use ($banco, $almacen, $seco, &$creados, &$actualizados) {
                foreach ($banco as $entrada) {
                    $quien = $entrada['slug'] ?? '¿?';

                    if (! in_array($entrada['lengua'], Lenguas::LISTA, true)) {
                        throw new RuntimeException("El diálogo «{$quien}» trae una lengua fuera de lista: «{$entrada['lengua']}».");
                    }

                    $versiones = DestinosDeBloque::versionesDe('CEFR');
                    if ($versiones === null) {
                        throw new RuntimeException('No hay marco CEFR sembrado: corre antes el CefrSeeder.');
                    }

                    $objetivo = LearningObjective::query()
                        ->whereIn('version_id', $versiones)
                        ->where('native_code', $entrada['objective'])
                        ->first();
                    if ($objetivo === null) {
                        throw new RuntimeException("El descriptor «{$entrada['objective']}» del diálogo «{$quien}» no existe en el grafo CEFR.");
                    }

                    Nodos::validar($entrada['nodos'], $quien);
                    $nodos = $this->resolverClips($entrada['nodos'], $almacen, $quien);

                    if ($seco) {
                        continue;
                    }

                    $dialogo = Dialogo::updateOrCreate(
                        [
                            'objective_id' => $objetivo->id,
                            'lengua' => $entrada['lengua'],
                            'slug' => $entrada['slug'],
                        ],
                        // reviewed_at NO va aquí: re-sembrar no toca la firma.
                        [
                            'unidad' => (int) $entrada['unidad'],
                            'titulo' => $entrada['titulo'],
                            'nodos' => $nodos,
                        ],
                    );
                    $dialogo->wasRecentlyCreated ? $creados++ : $actualizados++;
                }

                if ($seco) {
                    throw new DryRunOk;   // valida y deshace, sin escribir.
                }
            });
        } catch (DryRunOk) {
            $this->info('Banco válido (--dry-run): '.count($banco).' diálogo(s). Nada escrito.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Diálogos: {$creados} nuevo(s), {$actualizados} actualizado(s). Nacen SIN firmar: dialogos:firmar.");

        return self::SUCCESS;
    }

    /**
     * Cambia cada `clip` (clave) por un `audio` (ruta pública), publicando el
     * fichero. Un clip que falta revienta ANTES de escribir. Sin clips, no toca
     * nada.
     */
    private function resolverClips(array $nodos, AlmacenDeAudio $almacen, string $quien): array
    {
        $dir = $this->option('audio') ?: database_path('data/audio-lenguas');

        return array_map(function (array $nodo) use ($almacen, $dir, $quien) {
            $clip = $nodo['clip'] ?? null;
            if ($clip === null) {
                return $nodo;
            }

            $ruta = null;
            foreach (array_keys(AlmacenDeAudio::TIPOS) as $ext) {
                $candidato = "{$dir}/{$clip}.{$ext}";
                if (is_file($candidato)) {
                    $ruta = $candidato;
                    break;
                }
            }
            if ($ruta === null) {
                throw new RuntimeException("Falta el clip «{$clip}» del diálogo «{$quien}»: no hay {$dir}/{$clip}.(mp3|ogg|m4a).");
            }

            unset($nodo['clip']);
            $nodo['audio'] = $almacen->publicar(new ClipCurricular($ruta));

            return $nodo;
        }, $nodos);
    }
}

/** Señal interna para deshacer la transacción de un --dry-run sin marcar error. */
class DryRunOk extends RuntimeException {}
