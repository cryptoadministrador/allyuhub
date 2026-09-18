<?php

namespace App\Services\Lesson;

use InvalidArgumentException;

/**
 * El formato de una lección: una lista de BLOQUES TIPADOS, nunca HTML.
 *
 * Dos razones, y la segunda pesa más que la primera. Una: renderizar HTML de un
 * pipeline de IA con `dangerouslySetInnerHTML` es un agujero de inyección. Dos:
 * hace la revisión docente imposible — un profesor puede leer bloques y decir
 * «este ejemplo está mal»; no puede auditar un `<div>` con un `<script>` dentro.
 *
 * La validación es ESTRICTA y ocurre al sembrar: un tipo desconocido revienta
 * donde lo ve quien escribe la lección, no se pinta vacío delante de un alumno.
 * Las fórmulas se convierten aquí a árbol MathML, así que un LaTeX imposible
 * también revienta al sembrar y no en el navegador.
 */
class Bloques
{
    public const TIPOS = ['parrafo', 'ejemplo', 'formula', 'lista', 'aviso', 'imagen', 'audio', 'video'];

    /** Variantes de `aviso`. La de error típico es la que convierte texto en enseñanza. */
    public const VARIANTES = ['error-tipico', 'ojo', 'truco'];

    public function __construct(private readonly MathML $mathml = new MathML) {}

    /**
     * Valida la lista entera y devuelve los bloques ya normalizados, con las
     * fórmulas convertidas a árbol MathML.
     *
     * @param  list<array<string, mixed>>  $bloques
     * @return list<array<string, mixed>>
     */
    public function validar(array $bloques, string $donde = 'lección'): array
    {
        if ($bloques === []) {
            throw new InvalidArgumentException("{$donde}: una lección sin bloques no enseña nada.");
        }

        return array_values(array_map(
            fn (array $b, int $i) => $this->unBloque($b, "{$donde} · bloque ".($i + 1)),
            $bloques, array_keys($bloques),
        ));
    }

    /** @return array<string, mixed> */
    private function unBloque(array $b, string $donde): array
    {
        $tipo = $b['tipo'] ?? null;

        if (! in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException(
                "{$donde}: tipo «".var_export($tipo, true).'» desconocido. '.
                'Admitidos: '.implode(', ', self::TIPOS).'.',
            );
        }

        return match ($tipo) {
            'parrafo' => ['tipo' => 'parrafo', 'texto' => $this->texto($b, 'texto', $donde)],
            'formula' => $this->formula($b, $donde),
            'lista' => $this->lista($b, $donde),
            'aviso' => $this->aviso($b, $donde),
            'ejemplo' => $this->ejemplo($b, $donde),
            'imagen' => $this->imagen($b, $donde),
            'audio' => $this->audio($b, $donde),
            'video' => $this->video($b, $donde),
        };
    }

    /** @return array<string, string> */
    private function texto(array $b, string $clave, string $donde): array
    {
        $t = $b[$clave] ?? null;

        if (! is_array($t) || ! isset($t['es']) || trim((string) $t['es']) === '') {
            throw new InvalidArgumentException("{$donde}: falta «{$clave}.es» o está vacío.");
        }

        // Multilingüe como el resto del grafo: {"es": …}. Se guarda tal cual —
        // el escapado lo hace React al pintarlo, no una limpieza aquí que
        // acabaría comiéndose un «<» legítimo de una desigualdad.
        return array_map(fn ($v) => (string) $v, $t);
    }

    private function formula(array $b, string $donde): array
    {
        if (! isset($b['latex']) || trim((string) $b['latex']) === '') {
            throw new InvalidArgumentException("{$donde}: una fórmula sin «latex».");
        }

        try {
            $arbol = $this->mathml->render((string) $b['latex']);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException("{$donde}: {$e->getMessage()}", previous: $e);
        }

        return array_filter([
            'tipo' => 'formula',
            // El LaTeX se conserva para poder re-renderizar y para que un
            // docente vea la fuente; lo que pinta el cliente es el árbol.
            'latex' => (string) $b['latex'],
            'mathml' => $arbol,
            'etiqueta' => isset($b['etiqueta']) ? $this->texto($b, 'etiqueta', $donde) : null,
        ], fn ($v) => $v !== null);
    }

    private function lista(array $b, string $donde): array
    {
        $items = $b['items'] ?? [];

        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException("{$donde}: una lista sin elementos.");
        }

        return [
            'tipo' => 'lista',
            'ordenada' => (bool) ($b['ordenada'] ?? false),
            'items' => array_values(array_map(
                fn ($item, $i) => $this->texto(['t' => $item], 't', "{$donde} · elemento ".($i + 1)),
                $items, array_keys($items),
            )),
        ];
    }

    private function aviso(array $b, string $donde): array
    {
        $variante = $b['variante'] ?? null;

        if (! in_array($variante, self::VARIANTES, true)) {
            throw new InvalidArgumentException(
                "{$donde}: variante «".var_export($variante, true).'» desconocida. '.
                'Admitidas: '.implode(', ', self::VARIANTES).'.',
            );
        }

        return [
            'tipo' => 'aviso',
            'variante' => $variante,
            'texto' => $this->texto($b, 'texto', $donde),
        ];
    }

    private function ejemplo(array $b, string $donde): array
    {
        $pasos = $b['pasos'] ?? [];

        if (! is_array($pasos) || $pasos === []) {
            throw new InvalidArgumentException("{$donde}: un ejemplo resuelto sin pasos.");
        }

        return array_filter([
            'tipo' => 'ejemplo',
            'titulo' => isset($b['titulo']) ? $this->texto($b, 'titulo', $donde) : null,
            'pasos' => array_values(array_map(function ($paso, $i) use ($donde) {
                $sitio = "{$donde} · paso ".($i + 1);

                if (! is_array($paso)) {
                    throw new InvalidArgumentException("{$sitio}: paso mal formado.");
                }

                return array_filter([
                    'texto' => $this->texto($paso, 'texto', $sitio),
                    'formula' => isset($paso['latex'])
                        ? $this->formula(['latex' => $paso['latex']], $sitio)['mathml']
                        : null,
                    'latex' => $paso['latex'] ?? null,
                ], fn ($v) => $v !== null);
            }, $pasos, array_keys($pasos))),
        ], fn ($v) => $v !== null);
    }

    /**
     * Un clip de audio con su transcripción. Sin transcripción no hay bloque:
     * es requisito de accesibilidad y además es pedagogía — un alumno de A1
     * necesita poder leer lo que oye (en la lección; en un ítem de escucha la
     * transcripción se revela después de responder, pero ese es otro camino).
     *
     * El `src` solo puede ser una ruta del propio almacén (`/audio/<hash>.<ext>`):
     * ni terceros, ni `javascript:`, ni un fichero suelto de `public/`. La forma
     * la declara `AlmacenDeAudio::FORMA` y aquí se comprueba la misma — dos
     * sitios —este bloque y el guardián de `practice_items.audio_src`— llamando
     * a UNA función, no dos copias de la regla.
     */
    private function audio(array $b, string $donde): array
    {
        $src = (string) ($b['src'] ?? '');
        $pendiente = $this->pendiente($b, $donde);

        // UN HUECO DECLARADO (PR 11): `pendiente => true` es un audio que
        // todavía no está grabado. Entra SIN `src` (y con su `clip`, la clave
        // que lo enganchará el día que exista) y se pinta como lo que es —la
        // transcripción y «audio pendiente»—, nunca como un reproductor a un
        // fichero que no está. Con `src` Y `pendiente` a la vez es una
        // contradicción: o está o no está.
        if ($pendiente && $src !== '') {
            throw new InvalidArgumentException("{$donde}: un audio «pendiente» no puede traer «src»: o está grabado o no.");
        }

        if (! $pendiente && ! \App\Services\Audio\AlmacenDeAudio::esRutaPublicada($src)) {
            throw new InvalidArgumentException(
                "{$donde}: «src» tiene que ser una ruta del almacén de audio ".
                "(/audio/<hash>.<mp3|ogg|m4a>), no «{$src}». Si aún no está grabado, declara pendiente => true.",
            );
        }

        $duracion = $b['duracion_s'] ?? null;
        if ($duracion !== null && (! is_int($duracion) || $duracion <= 0)) {
            throw new InvalidArgumentException("{$donde}: «duracion_s» tiene que ser un entero positivo.");
        }

        return array_filter([
            'tipo' => 'audio',
            'src' => $pendiente ? null : $src,
            'texto' => $this->transcripcion($b, $donde, 'audio'),
            'duracion_s' => $duracion,
            'pendiente' => $pendiente ? true : null,
            'clip' => $pendiente ? $this->clave($b, $donde) : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Un VÍDEO de la lección — hoy, SOLO como hueco declarado (PR 11). No hay
     * almacén de vídeo todavía (el de audio es de audio, con su vocabulario
     * cerrado), así que un bloque `video` exige `pendiente => true` y su
     * transcripción, y se pinta como «vídeo pendiente» con el texto delante.
     * El día que exista el almacén, aquí se admite `src`; el banco no cambia.
     */
    private function video(array $b, string $donde): array
    {
        if (! $this->pendiente($b, $donde)) {
            throw new InvalidArgumentException(
                "{$donde}: un bloque «video» todavía no tiene almacén: entra solo como hueco, con pendiente => true.",
            );
        }
        if (isset($b['src'])) {
            throw new InvalidArgumentException("{$donde}: un vídeo «pendiente» no puede traer «src».");
        }

        return array_filter([
            'tipo' => 'video',
            'texto' => $this->transcripcion($b, $donde, 'vídeo'),
            'pendiente' => true,
            'clip' => $this->clave($b, $donde),
        ], fn ($v) => $v !== null);
    }

    /** `pendiente` solo puede ser `true` o no estar: un «pendiente: 'sí'» es una errata, no un hueco. */
    private function pendiente(array $b, string $donde): bool
    {
        if (! array_key_exists('pendiente', $b)) {
            return false;
        }
        if ($b['pendiente'] !== true) {
            throw new InvalidArgumentException("{$donde}: «pendiente» solo puede ser true (o no estar).");
        }

        return true;
    }

    /** La clave del clip que un hueco declara (opcional): la MISMA forma que en el banco. */
    private function clave(array $b, string $donde): ?string
    {
        $clip = $b['clip'] ?? null;
        if ($clip === null) {
            return null;
        }
        if (! is_string($clip) || ! preg_match('~^[a-z]{2}/u[0-9]+/[a-z0-9_/-]+$~', $clip)) {
            throw new InvalidArgumentException("{$donde}: la clave de clip «".var_export($clip, true).'» tiene una forma rara.');
        }

        return $clip;
    }

    /**
     * La transcripción, obligatoria con o sin fichero: va en el idioma del
     * clip (fr, it, de, zh…), así que no se exige `es` — se exige AL MENOS una
     * entrada con texto de verdad. Es requisito de accesibilidad y además es
     * pedagogía: un alumno de A1 necesita leer lo que oye. Y en un hueco es
     * TODO lo que hay.
     *
     * @return array<string, string>
     */
    private function transcripcion(array $b, string $donde, string $que): array
    {
        $texto = $b['texto'] ?? null;
        $conTexto = is_array($texto)
            ? array_filter($texto, fn ($v) => is_string($v) && trim($v) !== '')
            : [];
        if ($conTexto === []) {
            throw new InvalidArgumentException(
                "{$donde}: un {$que} sin transcripción no existe para quien no puede ".
                'oírlo, y un alumno de A1 necesita leer lo que oye.',
            );
        }

        return array_map(fn ($v) => (string) $v, $conTexto);
    }

    private function imagen(array $b, string $donde): array
    {
        $src = (string) ($b['src'] ?? '');

        // Solo rutas propias. Una URL de terceros en una lección de colegio es
        // un rastreador esperando a que alguien la pegue, y `javascript:` en un
        // src es exactamente el agujero que este formato viene a cerrar.
        if (! str_starts_with($src, '/')) {
            throw new InvalidArgumentException(
                "{$donde}: «src» tiene que ser una ruta propia que empiece por «/», no «{$src}».",
            );
        }

        return [
            'tipo' => 'imagen',
            'src' => $src,
            // El alt no es opcional: una imagen sin describir es una imagen que
            // no existe para quien usa lector de pantalla.
            'alt' => $this->texto($b, 'alt', $donde),
        ];
    }
}
