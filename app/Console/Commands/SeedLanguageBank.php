<?php

namespace App\Console\Commands;

use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Services\Audio\AlmacenDeAudio;
use App\Services\Audio\ClipCurricular;
use App\Services\Curso\CursoDeLenguas;
use App\Services\Lesson\Bloques;
use App\Services\Lesson\DestinosDeBloque;
use App\Services\Practice\Lenguas;
use App\Services\Practice\Tipos\Registro;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Siembra el banco de LENGUAS: lecciones e ítems anclados a descriptores MCER.
 *
 *   php artisan lenguas:sembrar [--marco=<forzar>] [--banco=…] [--audio=…] [--dry-run]
 *
 * Tres decisiones que este comando materializa:
 *
 *  1. EL ANCLAJE ES POR DESCRIPTOR EXACTO (`A1.IO.1`), no por prefijo de
 *     bloque como en MINEDEC: los códigos MCER no se replican por grado. La
 *     validación de erratas es la misma de `practica:sembrar`: un área que no
 *     existe REVIENTA (CS.FL por CS.F nos costó Filosofía entera); un
 *     descriptor hueco dentro de un área real AVISA.
 *  2. LA LENGUA ES DE LA ENTRADA, de lista cerrada (`Lenguas::LISTA`), y viaja
 *     a la columna `lengua` del ítem o recurso. `A1.IO.1` es el mismo
 *     descriptor en italiano y en alemán; lo que separa los cursos es esto.
 *  3. EL AUDIO SE NOMBRA POR CLAVE (`it/u1/saludo`): quien escribe el banco no
 *     puede calcular el hash de un clip que aún no existe. Este comando lo
 *     publica en el almacén (idempotente por contenido) y sustituye la clave
 *     por la ruta real. Un clip que falta revienta ANTES de escribir nada,
 *     nombrando clave y entrada — un audio roto delante de un alumno parece
 *     su teléfono, no nuestro fallo.
 *
 * Cada tipo declara cómo se lee su entrada (`Tipo::desdeBanco`): el octavo
 * tipo no toca este fichero. Y todo nace SIN FIRMAR: se publica bloque a
 * bloque con `practica:firmar --bloque=A1.IO.it` y `lecciones:firmar`, la
 * revisión es POR LENGUA — quien sabe italiano firma el italiano.
 */
class SeedLanguageBank extends Command
{
    protected $signature = 'lenguas:sembrar
        {--marco= : Fuerza un marco de anclaje para TODO el banco (por defecto, el que declara el curso de cada lengua)}
        {--banco= : Ruta de otro fichero de banco (por defecto database/data/banco-lenguas.php)}
        {--audio= : Directorio de clips fuente (por defecto database/data/audio-lenguas)}
        {--dry-run : Cuenta lo que haría sin escribir}';

    protected $description = 'Siembra lecciones e ítems de lenguas sobre los descriptores del marco de cada curso';

    /**
     * Versiones de anclaje POR MARCO. Era una sola (`--marco=CEFR` para todo el
     * banco) y era cierto solo mientras las cuatro lenguas fueran del MCER: una
     * entrada de inglés (marco `AH-EN0861`) se buscaba en el CEFR y reventaba
     * como errata. Ahora cada entrada se ancla en el marco que declara SU curso
     * (`CursoDeLenguas::marco`), la misma fuente que usa la portada del curso.
     *
     * @var array<string, Collection|null>
     */
    private array $versionesPorMarco = [];

    /** @var array<string, LearningObjective|null> */
    private array $descriptores = [];

    public function handle(): int
    {
        $rutaBanco = $this->option('banco') ?: database_path('data/banco-lenguas.php');
        if (! is_file($rutaBanco)) {
            $this->error("No existe el fichero de banco {$rutaBanco}.");

            return self::FAILURE;
        }

        $banco = require $rutaBanco;
        // Dos formas admitidas: lista plana de ítems, o {lecciones, items}.
        $lecciones = $banco['lecciones'] ?? [];
        $items = array_is_list($banco) ? $banco : ($banco['items'] ?? []);

        // Un --marco explícito que no existe se dice ANTES del pre-pase.
        if ($this->option('marco') && DestinosDeBloque::versionesDe((string) $this->option('marco')) === null) {
            $this->error('No existe el marco '.$this->option('marco').' o no tiene versiones.');

            return self::FAILURE;
        }

        // ===== PRE-PASE: TODO se valida antes de escribir NADA =====
        // Lenguas de lista cerrada, erratas de área, y clips presentes. Una
        // lección o un banco no entran a medias: o todo el pre-pase en verde,
        // o nada tocó la base.
        $huecos = [];
        foreach ([...$lecciones, ...$items] as $entrada) {
            if ($this->validarEntrada($entrada, $huecos) === false) {
                return self::FAILURE;
            }
        }

        // ===== SIEMBRA =====
        // En UNA transacción: el pre-pase valida lengua, área y clips, pero la
        // FORMA de cada ítem la valida el guardián `saving` al escribir — y si
        // el ítem 7 revienta, los 6 anteriores y las lecciones no pueden
        // quedarse dentro. El banco entra entero o no entra. (Los clips ya
        // publicados en disco no son transaccionales, y no hace falta: son
        // ficheros direccionados por contenido, inertes sin quien los nombre.)
        $creados = ['items' => 0, 'lecciones' => 0];
        $actualizados = ['items' => 0, 'lecciones' => 0];

        \Illuminate\Support\Facades\DB::transaction(function () use ($lecciones, $items, &$creados, &$actualizados) {
        foreach ($lecciones as $entrada) {
            if ($this->descriptorDe($entrada['descriptor'], $entrada['lengua']) === null) {
                continue;   // hueco ya avisado
            }
            if ($this->option('dry-run')) {
                $this->line("  [dry-run] lección {$entrada['slug']} ({$entrada['lengua']}) → {$entrada['descriptor']}");
                $creados['lecciones']++;

                continue;
            }
            $this->sembrarLeccion($entrada) ? $creados['lecciones']++ : $actualizados['lecciones']++;
        }

        foreach ($items as $entrada) {
            if ($this->descriptorDe($entrada['descriptor'], $entrada['lengua']) === null) {
                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("  [dry-run] ítem {$entrada['tipo']} ({$entrada['lengua']}) → {$entrada['descriptor']}");
                $creados['items']++;

                continue;
            }
            $this->sembrarItem($entrada) ? $creados['items']++ : $actualizados['items']++;
        }
        });

        $this->informar($creados, $actualizados, $huecos);
        $this->informarPendientes();

        return self::SUCCESS;
    }

    /** @param  list<string>  $huecos */
    private function validarEntrada(array $entrada, array &$huecos): bool
    {
        $descriptor = (string) ($entrada['descriptor'] ?? '');
        $lengua = (string) ($entrada['lengua'] ?? '');
        $quien = $entrada['slug'] ?? $entrada['tipo'] ?? '?';

        if (! in_array($lengua, Lenguas::LISTA, true)) {
            $this->error(
                "La entrada «{$quien}» ({$descriptor}) declara la lengua «{$lengua}», que no está ".
                'en la lista cerrada ('.implode(', ', Lenguas::LISTA).'). Una lengua nueva se añade '.
                'en App\\Services\\Practice\\Lenguas, no por typo.',
            );

            return false;
        }

        $versiones = $this->versionesPara($lengua);
        if ($versiones === null) {
            $this->error(
                "La entrada «{$quien}» es de «{$lengua}», cuyo curso se ancla en el marco ".
                "«{$this->marcoDe($lengua)}», que no está sembrado. ¿Corrió su seeder?",
            );

            return false;
        }

        // La MISMA distinción que cazó CS.FL por CS.F: área inexistente =
        // errata que revienta; descriptor hueco en área real = aviso.
        if (! DestinosDeBloque::areaExiste($descriptor, $versiones)) {
            $area = DestinosDeBloque::area($descriptor);
            $this->error(
                "La entrada «{$quien}» pide el descriptor {$descriptor} y NINGÚN descriptor del ".
                "grafo empieza por el área «{$area}»: eso es una errata en el banco, no un hueco.",
            );

            return false;
        }

        if ($this->descriptorDe($descriptor, $lengua) === null) {
            $huecos[] = "{$descriptor} ({$quien})";
        }

        // Los clips, TODOS, antes de escribir nada: el del ítem y los de los
        // bloques de audio de una lección. EXCEPTO los que una lección declara
        // `pendiente => true` (PR 11): ese bloque entra como hueco honesto
        // —transcripción y «audio pendiente»— y se engancha al re-sembrar
        // cuando el fichero esté. El clip de un ÍTEM nunca es pendiente: sin
        // audio no hay ejercicio de escucha, y `pendiente` en un ítem se ignora.
        $clips = array_filter([
            $entrada['clip'] ?? null,
            ...array_map(
                fn (array $b) => ($b['pendiente'] ?? false) === true ? null : ($b['clip'] ?? null),
                $entrada['bloques'] ?? [],
            ),
        ]);
        foreach ($clips as $clip) {
            if ($this->rutaDeClip($clip) === null) {
                // En dos líneas: la CLAVE que falta y DÓNDE se pidió — es lo
                // primero que hay que saber para arreglarlo.
                $this->error("Falta el clip «{$clip}»: no hay ningún fichero ".
                    $this->dirAudio()."/{$clip}.(mp3|ogg|m4a).");
                $this->error("Lo pide la entrada «{$quien}» ({$descriptor}). Un audio roto delante ".
                    'de un alumno parece su teléfono: no se siembra nada hasta que esté.');

                return false;
            }
        }

        return true;
    }

    /** Crea o actualiza un ítem. Devuelve true si es nuevo. */
    private function sembrarItem(array $entrada): bool
    {
        $descriptor = $this->descriptorDe($entrada['descriptor'], $entrada['lengua']);
        $entrada = $this->resolverClip($entrada);

        $columnas = Registro::de($entrada['tipo'])->desdeBanco($entrada);

        $item = PracticeItem::updateOrCreate(
            // Clave natural: (descriptor, lengua, seq) — re-sembrar actualiza
            // el contenido sin duplicar ni cambiar el id.
            [
                'objective_id' => $descriptor->id,
                'lengua' => $entrada['lengua'],
                'seq' => (int) $entrada['seq'],
            ],
            [
                // `params` es NOT NULL en el esquema (herencia del numérico):
                // los tipos que no lo usan lo dejan vacío, no ausente.
                'params' => [],
                ...$columnas,
                'kind' => $entrada['tipo'],
                'origen' => 'curado',
                'attrs' => ['revision' => [
                    'alineado_a' => 'descriptor',
                    // La firma va por área + LENGUA: quien sabe italiano firma
                    // el italiano. `practica:firmar --bloque=A1.IO.it`.
                    'bloque' => DestinosDeBloque::area($entrada['descriptor']).'.'.$entrada['lengua'],
                ]],
            ],
        );

        return $item->wasRecentlyCreated;
    }

    /** Crea o actualiza una lección. Devuelve true si es nueva. */
    private function sembrarLeccion(array $entrada): bool
    {
        $descriptor = $this->descriptorDe($entrada['descriptor'], $entrada['lengua']);
        $bloque = DestinosDeBloque::area($entrada['descriptor']).'.'.$entrada['lengua'];

        // La indirección del clip DENTRO de los bloques de audio, resuelta
        // antes de validar: `Bloques` exige la ruta final del almacén. Un
        // bloque `pendiente` cuyo fichero YA está deja de ser pendiente aquí
        // —sin tocar el banco—; uno cuyo fichero falta conserva clave y
        // pendiente, y se apunta para decirlo al final.
        $bloques = array_map(function (array $b) use ($entrada) {
            if (($b['tipo'] ?? null) === 'audio' && isset($b['clip'])) {
                if ($this->rutaDeClip($b['clip']) !== null) {
                    $b['src'] = $this->publicarClip($b['clip']);
                    unset($b['clip'], $b['pendiente']);
                } else {
                    $this->pendientes[] = "{$b['clip']} ({$entrada['slug']})";
                }
            }
            if (($b['tipo'] ?? null) === 'video' && isset($b['clip'])) {
                $this->pendientes[] = "{$b['clip']} ({$entrada['slug']}, vídeo)";
            }

            return $b;
        }, $entrada['bloques']);

        try {
            $validados = (new Bloques)->validar($bloques, "{$entrada['descriptor']} · {$entrada['slug']}");
        } catch (InvalidArgumentException $e) {
            // No debería llegar (el pre-pase revisó los clips), pero si un
            // bloque está mal formado la lección NO entra a medias.
            $this->error($e->getMessage());

            throw $e;
        }

        // Mismo discriminante que lecciones:sembrar más la LENGUA: hash, no
        // prefijo de uuid (los uuid van ordenados por tiempo y colisionan).
        $slug = "leccion-{$entrada['slug']}-".substr(
            hash('sha256', "{$descriptor->id}:{$entrada['lengua']}"), 0, 12,
        );

        $recurso = Resource::firstOrNew(['slug' => $slug]);
        $esNueva = ! $recurso->exists;

        $recurso->fill([
            'kind' => Resource::LECTURA,
            'origen' => Resource::GENERADO,   // sin firma no la ve nadie
            'lengua' => $entrada['lengua'],
            'title' => is_array($entrada['titulo']) ? $entrada['titulo'] : ['es' => $entrada['titulo']],
            'summary' => is_array($entrada['resumen']) ? $entrada['resumen'] : ['es' => $entrada['resumen']],
            'status' => 'published',
            'a11y' => ['wcag' => '2.2AA', 'keyboard' => true, 'screenreader' => true],
        ])->save();

        $version = ResourceVersion::firstOrNew([
            'resource_id' => $recurso->id, 'semver' => '1.0.0',
        ]);
        $version->fill([
            'config' => ['bloque' => $bloque, 'bloques' => $validados],
            'bundle_url' => null,
            'published_at' => $version->published_at ?? now(),
        ])->save();

        $recurso->update(['current_version_id' => $version->id]);
        $recurso->objectives()->syncWithoutDetaching([$descriptor->id => ['role' => 'primary']]);

        return $esNueva;
    }

    // ================= clips =================

    /** Huecos declarados (`pendiente => true`) cuyo fichero todavía no está. */
    private array $pendientes = [];

    /** Los huecos se DICEN al terminar, no se esconden: la lección ya los pide. */
    private function informarPendientes(): void
    {
        $faltan = array_values(array_unique($this->pendientes));
        if ($faltan === []) {
            return;
        }
        $this->warn(count($faltan).' hueco(s) declarados sin fichero todavía (la lección se lee entera):');
        foreach ($faltan as $clip) {
            $this->line("  - {$clip}");
        }
    }

    private function dirAudio(): string
    {
        return $this->option('audio') ?: database_path('data/audio-lenguas');
    }

    /** La ruta local de una clave de clip, o null si no hay fichero. */
    private function rutaDeClip(string $clave): ?string
    {
        foreach (array_keys(AlmacenDeAudio::TIPOS) as $ext) {
            $ruta = $this->dirAudio().'/'.$clave.'.'.$ext;
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /** Publica la clave en el almacén y devuelve la ruta /audio/<hash>.<ext>. */
    private function publicarClip(string $clave): string
    {
        // El pre-pase ya garantizó que existe; publicar es idempotente por
        // contenido (mismo clip, misma ruta, un solo fichero).
        return (new AlmacenDeAudio)->publicar(new ClipCurricular($this->rutaDeClip($clave)));
    }

    /** Sustituye la clave `clip` de un ítem por su `audio_src` publicado. */
    private function resolverClip(array $entrada): array
    {
        if (isset($entrada['clip'])) {
            $entrada['audio_src'] = $this->publicarClip($entrada['clip']);
            unset($entrada['clip']);
        }

        return $entrada;
    }

    // ================= anclaje e informe =================

    private function marcoDe(string $lengua): string
    {
        return (string) ($this->option('marco') ?: app(CursoDeLenguas::class)->marco($lengua));
    }

    private function versionesPara(string $lengua): ?Collection
    {
        $marco = $this->marcoDe($lengua);
        if (! array_key_exists($marco, $this->versionesPorMarco)) {
            $this->versionesPorMarco[$marco] = DestinosDeBloque::versionesDe($marco);
        }

        return $this->versionesPorMarco[$marco];
    }

    /** La caché va por (marco, código): el mismo código en dos marcos son dos destrezas. */
    private function descriptorDe(string $code, string $lengua): ?LearningObjective
    {
        $versiones = $this->versionesPara($lengua);
        if ($versiones === null) {
            return null;
        }

        return $this->descriptores[$this->marcoDe($lengua).'|'.$code] ??= LearningObjective::query()
            ->whereIn('version_id', $versiones)
            ->where('native_code', $code)
            ->first();
    }

    private function informar(array $creados, array $actualizados, array $huecos): void
    {
        $this->info(sprintf(
            '%s%d lección(es) y %d ítem(s) creados · %d y %d actualizados.',
            $this->option('dry-run') ? '[dry-run] ' : '',
            $creados['lecciones'], $creados['items'],
            $actualizados['lecciones'], $actualizados['items'],
        ));

        if ($huecos !== []) {
            $this->newLine();
            $this->warn('Descriptores del banco que el grafo aún no trae ('.count($huecos).'):');
            foreach (array_unique($huecos) as $hueco) {
                $this->line("  {$hueco}");
            }
        }

        if ($this->option('dry-run')) {
            return;
        }

        $pendientes = PracticeItem::whereNull('reviewed_at')->whereNotNull('lengua')->count()
            + ResourceVersion::whereNull('reviewed_at')
                ->whereHas('resource', fn ($q) => $q->whereNotNull('lengua'))->count();
        if ($pendientes > 0) {
            $this->newLine();
            $this->warn("{$pendientes} pieza(s) de lenguas pendiente(s) de firma: NO llegan a ningún alumno.");
            $this->line('  La revisión es POR LENGUA — quien sabe italiano firma el italiano:');
            // Área del descriptor + lengua: A1.IO.it, EN7.R.en…
            $this->line('    php artisan practica:firmar --bloque=<área>.<lengua>   (p. ej. A1.IO.it, EN7.R.en)');
            $this->line('    php artisan lecciones:firmar --bloque=<área>.<lengua>  (o desde /docente/revisar)');
        }
    }
}
