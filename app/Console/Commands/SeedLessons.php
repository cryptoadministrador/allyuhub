<?php

namespace App\Console\Commands;

use App\Models\LearningObjective;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Services\Lesson\Bloques;
use App\Services\Lesson\DestinosDeBloque;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Siembra el banco de LECCIONES sobre el currículo real.
 *
 *   php artisan lecciones:sembrar [--marco=EC-MINEDEC] [--incluir-no-verificadas] [--dry-run]
 *
 * Cada entrada de database/data/lecciones.php se ancla a un BLOQUE por el
 * prefijo del código y aterriza en la MISMA destreza que el ejercicio de ese
 * bloque —las dos siembras usan `DestinosDeBloque`—, para que leer y practicar
 * no acaben en destrezas distintas.
 *
 * IDEMPOTENTE por clave natural (destreza, slug): re-sembrar actualiza el
 * contenido conservando el id del recurso, así que un enlace ya compartido no
 * se rompe. Editar el texto ACTUALIZA la lección; no crea otra ni huerfana la
 * anterior.
 *
 * Las lecciones nacen SIN FIRMAR: no las ve ningún alumno hasta que alguien las
 * publica con `lecciones:firmar`. Un texto mal escrito hace más daño que una
 * pregunta mal escrita — la pregunta se falla y se corrige; el texto se cree.
 */
class SeedLessons extends Command
{
    protected $signature = 'lecciones:sembrar
        {--marco=EC-MINEDEC : Código del marco curricular}
        {--incluir-no-verificadas : Siembra también sobre destrezas sin verificar (grafo de demostración)}
        {--dry-run : Cuenta lo que haría sin escribir}';

    protected $description = 'Siembra las lecciones de lectura sobre las destrezas del currículo';

    public function handle(): int
    {
        $banco = require database_path('data/lecciones.php');
        $marco = (string) $this->option('marco');
        $validador = new Bloques;

        $versiones = DestinosDeBloque::versionesDe($marco);
        if ($versiones === null) {
            $this->error("No existe el marco {$marco} o no tiene versiones.");

            return self::FAILURE;
        }

        $creadas = 0;
        $actualizadas = 0;
        $huecos = [];
        $sinVerificar = 0;

        foreach ($banco as $entrada) {
            $prefijo = $entrada['bloque'];

            // La validación ocurre AQUÍ, una vez por entrada y antes de tocar la
            // base: un bloque mal formado revienta donde lo ve quien escribe la
            // lección, no se pinta vacío delante de un alumno.
            try {
                $bloques = $validador->validar($entrada['bloques'], $prefijo);
            } catch (InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $destinos = DestinosDeBloque::para(
                $prefijo, $versiones,
                soloVerificadas: ! $this->option('incluir-no-verificadas'),
                sinVerificar: $sinVerificar,
            );

            if ($destinos->isEmpty()) {
                $huecos[] = $prefijo;

                continue;
            }

            foreach ($destinos as $objetivo) {
                if ($this->option('dry-run')) {
                    $this->line("  [dry-run] {$prefijo} → {$objetivo->native_code}: {$entrada['titulo']}");
                    $creadas++;

                    continue;
                }

                $this->sembrarUna($entrada, $bloques, $objetivo) ? $creadas++ : $actualizadas++;
            }
        }

        $this->informar($creadas, $actualizadas, $huecos, $sinVerificar, count($banco), $versiones);

        return self::SUCCESS;
    }

    /**
     * Crea o actualiza la lección de una destreza. Devuelve true si es nueva.
     *
     * La clave natural es (destreza, slug), materializada en el `slug` del
     * recurso: así una destreza puede tener más de una lección el día que haga
     * falta, y re-sembrar no duplica ni cambia el id.
     */
    private function sembrarUna(array $entrada, array $bloques, LearningObjective $objetivo): bool
    {
        // El discriminante sale de un HASH del id, no de un prefijo del id.
        // `HasUuids` genera UUID ORDENADOS POR TIEMPO: sus primeros caracteres
        // son una marca de tiempo en milisegundos, así que las tres destrezas
        // del mismo bloque —creadas en la misma milésima— compartían prefijo y
        // las tres lecciones colapsaban en un único recurso. El hash no tiene
        // esa estructura, y sigue siendo estable entre ejecuciones.
        $slug = "leccion-{$entrada['slug']}-".substr(hash('sha256', $objetivo->id), 0, 12);

        $recurso = Resource::firstOrNew(['slug' => $slug]);
        $esNueva = ! $recurso->exists;

        $recurso->fill([
            'kind' => Resource::LECTURA,
            'title' => ['es' => $entrada['titulo']],
            'summary' => ['es' => $entrada['resumen']],
            'duration_min' => $entrada['minutos'] ?? null,
            'status' => 'published',
            'a11y' => ['wcag' => '2.2AA', 'keyboard' => true, 'screenreader' => true],
        ])->save();

        // Una sola versión que se actualiza, no una cadena de semvers: la
        // lección es contenido editorial, no un bundle inmutable en un CDN. Si
        // algún día hace falta historial, se añade aquí y no en el modelo.
        $version = ResourceVersion::firstOrNew([
            'resource_id' => $recurso->id, 'semver' => '1.0.0',
        ]);

        // Editar el texto NO devuelve la lección a «sin firmar» por sí solo,
        // pero tampoco la firma: una lección nueva nace con reviewed_at nulo y
        // una ya firmada conserva su firma. Republicar una revisión de fondo es
        // decisión de quien edita, con `lecciones:firmar`.
        $version->fill([
            // El bloque curricular viaja en el config para que la firma pueda
            // ir bloque a bloque, igual que en la práctica: publicar de golpe
            // todo lo sembrado es justo lo que la puerta viene a impedir.
            'config' => ['bloque' => $entrada['bloque'], 'bloques' => $bloques],
            'bundle_url' => null,   // una lección NO vive en un CDN externo
            'published_at' => $version->published_at ?? now(),
        ])->save();

        $recurso->update(['current_version_id' => $version->id]);
        $recurso->objectives()->syncWithoutDetaching([
            $objetivo->id => ['role' => 'primary'],
        ]);

        return $esNueva;
    }

    private function informar(
        int $creadas, int $actualizadas, array $huecos, int $sinVerificar,
        int $total, Collection $versiones,
    ): void {
        $this->info(sprintf(
            '%s%d lección(es) creadas · %d actualizadas · %d entradas en el banco.',
            $this->option('dry-run') ? '[dry-run] ' : '', $creadas, $actualizadas, $total,
        ));

        if ($huecos !== []) {
            $this->newLine();
            $this->warn('Bloques del banco sin destreza donde aterrizar ('.count($huecos).'):');
            $this->line('  '.implode(', ', $huecos));
        }

        if ($sinVerificar > 0) {
            $this->newLine();
            $this->warn("Se saltaron {$sinVerificar} destreza(s) sin verificar.");
            $this->line('  Con --incluir-no-verificadas se siembran igual (grafo de demostración).');
        }

        if ($this->option('dry-run')) {
            return;
        }

        $this->avisarDeLaFirma();
        $this->cobertura($versiones);
    }

    /** Lo sembrado no se publica solo: espera la firma de un docente. */
    private function avisarDeLaFirma(): void
    {
        $pendientes = ResourceVersion::query()
            ->whereNull('reviewed_at')
            ->whereHas('resource', fn ($q) => $q->where('kind', Resource::LECTURA))
            ->count();

        if ($pendientes === 0) {
            return;
        }

        $this->newLine();
        $this->warn("{$pendientes} lección(es) pendiente(s) de firma: NO se sirven todavía.");
        $this->line('  Para publicar un bloque ya revisado:');
        $this->line('    php artisan lecciones:firmar --bloque=M.4.1');
    }

    /**
     * La cobertura se mide en DESTREZAS, no en asignaturas.
     *
     * «Las cuatro áreas tienen lecciones» sonaría redondo y no diría nada: 16
     * lecciones sobre 4.717 destrezas. Lo que hace falta saber es qué
     * porcentaje tiene lección, desglosado, y cuáles se quedan fuera — con
     * nombre y apellido, en un fichero que se pueda ir tachando.
     */
    private function cobertura(Collection $versiones): void
    {
        $soloVerificadas = ! $this->option('incluir-no-verificadas');

        $destrezas = LearningObjective::query()
            ->whereIn('version_id', $versiones)
            ->when($soloVerificadas, fn ($q) => $q->where('is_verified', true))
            ->withCount(['resources as lecciones' => fn ($q) => $q->where('kind', Resource::LECTURA)])
            ->get(['id', 'native_code']);

        if ($destrezas->isEmpty()) {
            return;
        }

        $porAmbito = [];
        $sinLeccion = [];
        foreach ($destrezas as $d) {
            $ambito = DestinosDeBloque::ambito($d->native_code);
            $porAmbito[$ambito] ??= ['total' => 0, 'con' => 0];
            $porAmbito[$ambito]['total']++;

            if ($d->lecciones > 0) {
                $porAmbito[$ambito]['con']++;
            } else {
                $sinLeccion[] = $d->native_code;
            }
        }
        ksort($porAmbito);

        $con = $destrezas->where('lecciones', '>', 0)->count();
        $this->newLine();
        $this->line(sprintf(
            'Destrezas%s con lección: %d de %d (%.1f %%).',
            $soloVerificadas ? ' verificadas' : '',
            $con, $destrezas->count(), $con / $destrezas->count() * 100,
        ));

        $this->table(
            ['Asignatura · subnivel', 'Destrezas', 'Con lección', '%'],
            array_map(fn (string $k, array $v) => [
                $k, $v['total'], $v['con'], sprintf('%.1f', $v['con'] / $v['total'] * 100),
            ], array_keys($porAmbito), $porAmbito),
        );

        if ($sinLeccion !== []) {
            sort($sinLeccion);
            $ruta = storage_path('app/lecciones-sin-cobertura.txt');
            file_put_contents($ruta, implode(PHP_EOL, $sinLeccion).PHP_EOL);

            $this->newLine();
            $this->warn(count($sinLeccion).' destreza(s) SIN lección.');
            $this->line('  Primeras: '.implode(', ', array_slice($sinLeccion, 0, 10)).'…');
            $this->line("  La lista completa está en {$ruta}");
        }
    }
}
