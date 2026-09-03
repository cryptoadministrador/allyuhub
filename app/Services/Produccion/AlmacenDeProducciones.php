<?php

namespace App\Services\Produccion;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Dónde vive la VOZ de un alumno, y por qué NO donde el resto del audio.
 *
 * El almacén público (`AlmacenDeAudio`) nombra por hash del contenido y sirve
 * por `/audio/*` sin sesión, cacheado inmutable un año — perfecto para un clip
 * curricular, inaceptable para la grabación de un menor:
 *
 *  - El nombre NO es el hash del contenido: es un uuid aleatorio. Con el hash,
 *    dos alumnos que dijeran lo mismo compartirían fichero (dedupe), y el
 *    nombre sería adivinable desde el contenido. Aquí cada grabación es única y
 *    su nombre no dice nada de lo que contiene.
 *  - Vive bajo storage/app/producciones/<año>/, que NO es público. Se sirve
 *    solo por una ruta con `auth` + policy (ProduccionController::audio). La
 *    ruta `/audio/*` jamás lo alcanza.
 *  - Se agrupa POR AÑO LECTIVO para que la purga borre un año de un barrido.
 *
 * El vocabulario de extensiones es cerrado, como en el almacén público: lo que
 * MediaRecorder produce (webm/ogg) más los formatos de audio comunes. Lo que no
 * está en la lista no se guarda ni se sirve — el Content-Type de la ruta sale
 * de aquí, no de adivinar.
 */
final class AlmacenDeProducciones
{
    /** Extensión → Content-Type. Cerrada, gobierna almacén Y ruta. */
    public const TIPOS = [
        'webm' => 'audio/webm',
        'ogg' => 'audio/ogg',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'wav' => 'audio/wav',
    ];

    /** La forma exacta de una ruta guardada: producciones/<año>/<uuid>.<ext>. */
    public const FORMA = '#^producciones/\d{4}-\d{4}/[0-9a-f-]{36}\.(webm|ogg|mp3|m4a|wav)$#';

    /** El directorio raíz — bajo storage/app, nunca público. */
    public static function directorio(string $anio): string
    {
        return storage_path("app/producciones/{$anio}");
    }

    /**
     * Guarda la grabación subida y devuelve su RUTA RELATIVA a storage/app
     * (`producciones/<año>/<uuid>.<ext>`), que es lo que se guarda en la fila.
     */
    public function guardar(UploadedFile $fichero, string $anio): string
    {
        $ext = strtolower($fichero->getClientOriginalExtension() ?: $fichero->extension());
        if (! isset(self::TIPOS[$ext])) {
            throw new InvalidArgumentException(
                "Formato de audio «{$ext}» fuera de la lista (".implode(', ', array_keys(self::TIPOS)).').',
            );
        }

        $dir = self::directorio($anio);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        // uuid aleatorio: sin estructura temporal ni de contenido. Nadie puede
        // adivinar el nombre de la grabación de otro.
        $nombre = Str::uuid()->toString().'.'.$ext;
        $fichero->move($dir, $nombre);

        return "producciones/{$anio}/{$nombre}";
    }

    /** La ruta absoluta de una relativa ya validada, o null si no existe/mal formada. */
    public static function resolver(string $rel): ?string
    {
        if (preg_match(self::FORMA, $rel) !== 1) {
            return null;
        }

        $ruta = storage_path('app/'.$rel);

        return is_file($ruta) ? $ruta : null;
    }

    /** El Content-Type de una ruta guardada, por su extensión. */
    public static function contentType(string $rel): ?string
    {
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));

        return self::TIPOS[$ext] ?? null;
    }

    /** Borra el fichero de una producción (la purga y el borrado del alumno). */
    public function borrar(string $rel): void
    {
        $ruta = self::resolver($rel);
        if ($ruta !== null) {
            @unlink($ruta);
        }
    }
}
