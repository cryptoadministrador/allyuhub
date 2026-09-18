<?php

namespace App\Console\Commands;

use App\Models\Dialogo;
use App\Models\LearningObjective;
use App\Services\Audio\AlmacenDeAudio;
use App\Services\Audio\ClipsDeclarados;
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
        // El trato del clip (clave siempre, ruta si el fichero está, pendientes
        // al final) vive en `ClipsDeclarados`: lo comparte con `vocabulario:sembrar`.
        $clips = new ClipsDeclarados(new AlmacenDeAudio, $this->option('audio') ?: database_path('data/audio-lenguas'));
        $seco = (bool) $this->option('dry-run');

        $creados = 0;
        $actualizados = 0;

        try {
            DB::transaction(function () use ($banco, $clips, $seco, &$creados, &$actualizados) {
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
                    $nodos = $this->resolverClips($entrada['nodos'], $clips);

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

        // Los clips que faltan se DICEN, no se esconden: el guion ya los pide.
        $faltan = $clips->pendientes();
        if ($faltan !== []) {
            $this->warn(count($faltan).' clip(s) declarados sin fichero todavía (el guion se juega leyendo):');
            foreach ($faltan as $clip) {
                $this->line("  - {$clip}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Cambia cada `clip` (clave) por un `audio` (ruta pública), publicando el
     * fichero si está. Sin clips, no toca nada.
     *
     * UN CLIP QUE FALTA NO REVIENTA AQUÍ, y es la única excepción a la regla
     * del banco de lenguas —donde un clip ausente sí aborta la siembra entera—.
     * La diferencia es qué pasa sin el fichero: en un ítem de ESCUCHA sin audio
     * no hay ejercicio (tiene que reventar); en un DIÁLOGO el audio es un añadido
     * sobre un guion que se juega entero leyendo, y reventar obligaría a grabar
     * antes de poder escribir. Así que la CLAVE se conserva siempre y la RUTA se
     * rellena solo si el fichero está (`ClipsDeclarados`). Re-sembrar después de
     * grabar la completa sin tocar el banco.
     */
    private function resolverClips(array $nodos, ClipsDeclarados $clips): array
    {
        return array_map(function (array $nodo) use ($clips) {
            $clip = $nodo['clip'] ?? null;
            if ($clip === null) {
                return $nodo;
            }

            $nodo['clip'] = $clip;
            $audio = $clips->publicar($clip);
            if ($audio !== null) {
                $nodo['audio'] = $audio;
            }

            return $nodo;
        }, $nodos);
    }
}
