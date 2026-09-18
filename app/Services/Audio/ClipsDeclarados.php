<?php

namespace App\Services\Audio;

/**
 * UN CLIP DECLARADO POR CLAVE, con o sin fichero todavía.
 *
 * Es el trato del audio de los DIÁLOGOS (PR 4), y desde el vocabulario (PR 10)
 * lo comparten dos sembradores, así que vive en un sitio: la CLAVE se conserva
 * siempre (el contenido queda listo para el audio del día que llegue), la RUTA
 * pública se rellena solo si el fichero está, y las claves sin fichero se
 * APUNTAN para decirlas al final — nunca se esconden, nunca abortan.
 *
 * Es la única excepción a la regla del banco de lenguas, donde un clip que
 * falta sí revienta: en un ítem de ESCUCHA sin audio no hay ejercicio; en un
 * diálogo o una tarjeta el audio es un añadido sobre algo que se usa entero
 * leyendo, y reventar obligaría a grabar antes de poder escribir.
 */
final class ClipsDeclarados
{
    /** @var list<string> */
    private array $pendientes = [];

    public function __construct(
        private readonly AlmacenDeAudio $almacen,
        private readonly string $directorio,
    ) {}

    /**
     * La ruta pública del clip, o null si su fichero no existe todavía (y
     * entonces queda apuntado como pendiente).
     */
    public function publicar(string $clave): ?string
    {
        foreach (array_keys(AlmacenDeAudio::TIPOS) as $ext) {
            $candidato = "{$this->directorio}/{$clave}.{$ext}";
            if (is_file($candidato)) {
                return $this->almacen->publicar(new ClipCurricular($candidato));
            }
        }

        $this->pendientes[] = $clave;

        return null;
    }

    /** Las claves declaradas cuyo fichero falta, sin repetir, en orden de aparición. */
    public function pendientes(): array
    {
        return array_values(array_unique($this->pendientes));
    }
}
