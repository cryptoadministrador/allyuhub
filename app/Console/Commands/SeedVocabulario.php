<?php

namespace App\Console\Commands;

use App\Models\Tarjeta;
use App\Services\Audio\AlmacenDeAudio;
use App\Services\Audio\ClipsDeclarados;
use App\Services\Curso\CursoDeLenguas;
use App\Services\Practice\Lenguas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siembra el vocabulario por unidad desde `database/data/vocabulario-lenguas.php`.
 *
 *   php artisan vocabulario:sembrar [--dry-run]
 *
 * Idempotente por clave natural (lengua, unidad, clave): re-sembrar actualiza
 * la tarjeta sin duplicar ni CAMBIAR LA FIRMA — una tarjeta ya firmada sigue
 * firmada. Nace SIN firmar; se publica con `vocabulario:firmar --lengua=` o
 * desde `/docente/revisar`.
 *
 * El banco entra ENTERO o no entra (una transacción): una lengua fuera de
 * lista, una unidad que el curso no tiene, una clave repetida, un chino sin
 * pinyin o un ejemplo sin su traducción revientan nombrando la entrada. El
 * clip, en cambio, se DECLARA sin fichero: la clave se conserva, la ruta se
 * rellena el día que haya audio, y las que faltan se dicen al final — mismo
 * trato que los diálogos (`ClipsDeclarados`).
 */
class SeedVocabulario extends Command
{
    protected $signature = 'vocabulario:sembrar
        {--banco= : Ruta a un banco alternativo (por defecto database/data/vocabulario-lenguas.php)}
        {--audio= : Directorio de clips fuente (por defecto database/data/audio-lenguas)}
        {--dry-run : Valida el banco entero y no escribe nada}';

    protected $description = 'Siembra las tarjetas de vocabulario por unidad (nacen sin firmar)';

    public function __construct(private readonly CursoDeLenguas $curso)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $banco = require ($this->option('banco') ?: database_path('data/vocabulario-lenguas.php'));
        $clips = new ClipsDeclarados(new AlmacenDeAudio, $this->option('audio') ?: database_path('data/audio-lenguas'));
        $seco = (bool) $this->option('dry-run');

        $creadas = 0;
        $actualizadas = 0;

        try {
            DB::transaction(function () use ($banco, $clips, $seco, &$creadas, &$actualizadas) {
                $vistas = [];
                $orden = [];

                foreach ($banco as $i => $entrada) {
                    $quien = sprintf('#%d (%s/u%s/%s)', $i + 1,
                        $entrada['lengua'] ?? '?', $entrada['unidad'] ?? '?', $entrada['clave'] ?? '?');
                    $this->validar($entrada, $quien);

                    $natural = "{$entrada['lengua']}:{$entrada['unidad']}:{$entrada['clave']}";
                    if (isset($vistas[$natural])) {
                        throw new RuntimeException("La tarjeta {$quien} repite la clave de otra entrada del banco.");
                    }
                    $vistas[$natural] = true;

                    // La posición dentro del mazo de SU unidad: el orden del banco.
                    $mazo = "{$entrada['lengua']}:{$entrada['unidad']}";
                    $orden[$mazo] = ($orden[$mazo] ?? 0) + 1;

                    $clip = $entrada['clip'] ?? null;
                    $audio = $clip === null ? null : $clips->publicar($clip);

                    if ($seco) {
                        continue;
                    }

                    $tarjeta = Tarjeta::updateOrCreate(
                        ['lengua' => $entrada['lengua'], 'unidad' => (int) $entrada['unidad'], 'clave' => $entrada['clave']],
                        // reviewed_at NO va aquí: re-sembrar no toca la firma.
                        [
                            'orden' => $orden[$mazo],
                            'palabra' => $entrada['palabra'],
                            'lectura' => $entrada['lectura'] ?? null,
                            'significado' => $entrada['significado'],
                            'ejemplo' => $entrada['ejemplo'],
                            'clip' => $clip,
                            'audio' => $audio,
                        ],
                    );
                    $tarjeta->wasRecentlyCreated ? $creadas++ : $actualizadas++;
                }

                if ($seco) {
                    throw new DryRunOk;   // valida y deshace, sin escribir.
                }
            });
        } catch (DryRunOk) {
            $this->info('Banco válido (--dry-run): '.count($banco).' tarjeta(s). Nada escrito.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Vocabulario: {$creadas} nueva(s), {$actualizadas} actualizada(s). Nacen SIN firmar: vocabulario:firmar --lengua=.");

        $faltan = $clips->pendientes();
        if ($faltan !== []) {
            $this->warn(count($faltan).' clip(s) declarados sin fichero todavía (la tarjeta se usa leyendo):');
            foreach ($faltan as $clip) {
                $this->line("  - {$clip}");
            }
        }

        return self::SUCCESS;
    }

    /** Lo que una tarjeta tiene que traer para entrar; revienta nombrando la entrada. */
    private function validar(array $entrada, string $quien): void
    {
        $lengua = $entrada['lengua'] ?? null;
        if (! in_array($lengua, Lenguas::LISTA, true)) {
            throw new RuntimeException("La tarjeta {$quien} trae una lengua fuera de lista: «{$lengua}».");
        }

        $unidad = $entrada['unidad'] ?? null;
        if (! is_int($unidad) || ! $this->curso->existeUnidad($lengua, $unidad)) {
            throw new RuntimeException("La tarjeta {$quien} apunta a una unidad que el curso de «{$lengua}» no tiene.");
        }

        foreach (['clave', 'palabra', 'significado'] as $campo) {
            if (! is_string($entrada[$campo] ?? null) || trim($entrada[$campo]) === '') {
                throw new RuntimeException("La tarjeta {$quien} no trae «{$campo}».");
            }
        }

        $ejemplo = $entrada['ejemplo'] ?? null;
        if (! is_array($ejemplo) || ! is_string($ejemplo[$lengua] ?? null) || ! is_string($ejemplo['es'] ?? null)) {
            throw new RuntimeException("La tarjeta {$quien} necesita un ejemplo en «{$lengua}» y su traducción «es».");
        }

        // La lectura (pinyin con tonos) es del chino, y en chino es obligatoria:
        // una tarjeta con solo el carácter no se puede leer en A1.
        $lectura = $entrada['lectura'] ?? null;
        if ($lengua === 'zh' && (! is_string($lectura) || trim($lectura) === '')) {
            throw new RuntimeException("La tarjeta {$quien} es de chino y no trae «lectura» (pinyin).");
        }
        if ($lengua !== 'zh' && $lectura !== null) {
            throw new RuntimeException("La tarjeta {$quien} trae «lectura», que solo existe en chino.");
        }

        $clip = $entrada['clip'] ?? null;
        if ($clip !== null && (! is_string($clip) || ! preg_match('~^[a-z]{2}/u[0-9]+/[a-z0-9_/-]+$~', $clip))) {
            throw new RuntimeException("La tarjeta {$quien} trae una clave de clip con forma rara: «{$clip}».");
        }
    }
}
