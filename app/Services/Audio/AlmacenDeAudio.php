<?php

namespace App\Services\Audio;

use InvalidArgumentException;

/**
 * Dónde viven los ficheros de audio, y por qué así.
 *
 * El audio NO viaja dentro del JSON. Nada de base64 en `config` ni en el
 * payload de `next`: son cientos de clips por curso y reventaría la carga de
 * cualquier página. Un bloque o un ítem llevan solo la RUTA (`/audio/…`).
 *
 * El nombre es un HASH DEL CONTENIDO, y de ahí salen tres propiedades a la vez:
 *
 *  - Re-sembrar no rompe enlaces: mismo clip, misma URL, siempre.
 *  - No hay duplicados: veinte lecciones que usan el mismo saludo comparten
 *    un único fichero sin que nadie coordine nada.
 *  - La caché puede ser INMUTABLE sin riesgo: si el contenido cambia, cambia
 *    el nombre, así que `max-age` de un año jamás sirve un clip viejo.
 *
 * El vocabulario de extensiones es cerrado — mp3, ogg, m4a — porque la ruta
 * que sirve estos ficheros deriva el Content-Type de la extensión y no debe
 * adivinar nada: lo que no está en la lista no se publica ni se sirve.
 *
 * Y este almacén solo admite AUDIO CURRICULAR PUBLICADO — lo dice el TIPO de
 * `publicar()`, no este comentario: la ruta `/audio/*` es pública, sin auth y
 * cacheada inmutable un año, y eso solo es aceptable para contenido que ya es
 * público por naturaleza. El día que un alumno se grabe (frente D, PR 3+), su
 * voz NO entra por aquí: necesita su propio camino con autenticación, y el
 * sistema lo obliga porque `publicar()` no acepta un string — misma lección
 * que el default de `origen`.
 */
class AlmacenDeAudio
{
    /** Extensión → Content-Type. La lista cerrada que gobierna almacén Y ruta. */
    public const TIPOS = [
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
    ];

    /** La forma exacta de un nombre publicado: hash de 16 + extensión de la lista. */
    public const FORMA = '/^[a-f0-9]{16}\.(mp3|ogg|m4a)$/';

    /**
     * ¿Es `src` una ruta del propio almacén? LA regla, escrita UNA vez.
     *
     * La llaman el bloque `audio` de las lecciones (`Bloques::audio`) y el
     * guardián de `practice_items.audio_src` (al guardar el ítem): antes
     * estaba escrita en el bloque y solo documentada en el ítem, y una ruta
     * externa en un ítem no es XSS pero sí es filtrar la IP de cada alumno a
     * un tercero en cada reproducción — además de romper en silencio el
     * degradado sin red y la caché inmutable, que asumen que el clip es
     * nuestro.
     */
    public static function esRutaPublicada(string $src): bool
    {
        return str_starts_with($src, '/audio/')
            && preg_match(self::FORMA, substr($src, 7)) === 1;
    }

    public static function directorio(): string
    {
        return storage_path('app/audio');
    }

    /**
     * Publica un fichero local y devuelve su ruta pública `/audio/<hash>.<ext>`.
     * Idempotente por contenido: publicar dos veces el mismo clip devuelve la
     * misma ruta y no escribe dos ficheros.
     */
    public function publicar(ClipCurricular $clip): string
    {
        $rutaLocal = $clip->ruta;

        if (! is_file($rutaLocal)) {
            throw new InvalidArgumentException("No existe el fichero {$rutaLocal}.");
        }

        $ext = strtolower(pathinfo($rutaLocal, PATHINFO_EXTENSION));
        if (! isset(self::TIPOS[$ext])) {
            throw new InvalidArgumentException(
                "Extensión «{$ext}» fuera de la lista (".implode(', ', array_keys(self::TIPOS)).').',
            );
        }

        // 16 hex del sha256: espacio de sobra para un catálogo escolar y un
        // nombre que cabe en un vistazo. NO es un prefijo de uuid ordenado por
        // tiempo (la trampa de #25): el hash del contenido no tiene estructura
        // temporal, dos clips creados en la misma milésima no colisionan.
        $nombre = substr(hash_file('sha256', $rutaLocal), 0, 16).'.'.$ext;
        $destino = self::directorio().'/'.$nombre;

        if (! is_dir(self::directorio())) {
            mkdir(self::directorio(), 0755, recursive: true);
        }
        if (! is_file($destino)) {
            copy($rutaLocal, $destino);
        }

        return "/audio/{$nombre}";
    }

    /** La ruta absoluta de un nombre ya validado contra FORMA, o null si no está. */
    public static function resolver(string $fichero): ?string
    {
        if (! preg_match(self::FORMA, $fichero)) {
            return null;
        }

        $ruta = self::directorio().'/'.$fichero;

        return is_file($ruta) ? $ruta : null;
    }
}
