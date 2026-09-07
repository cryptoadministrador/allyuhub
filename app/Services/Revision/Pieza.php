<?php

namespace App\Services\Revision;

use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\ResourceVersion;
use InvalidArgumentException;

/**
 * UNA PIEZA DE CONTENIDO REVISABLE: un ítem de práctica o una lección.
 *
 * Existe porque la firma NO vive en el mismo sitio para las dos: la del ítem
 * está en `practice_items.reviewed_at` y la de la lección en la VERSIÓN vigente
 * (`resource_versions.reviewed_at`, que es lo que mira `Resource::published()`).
 * Sin esta capa, la pantalla de revisión tendría que saberlo —y sería el tercer
 * sitio donde está escrito, después de los dos comandos.
 *
 * El vocabulario de tipos es CERRADO: un tipo desconocido no se localiza.
 */
final class Pieza
{
    public const ITEM = 'item';

    public const LECCION = 'leccion';

    public const TIPOS = [self::ITEM, self::LECCION];

    private function __construct(
        public readonly string $tipo,
        public readonly PracticeItem|ResourceVersion $modelo,
    ) {}

    public static function deItem(PracticeItem $item): self
    {
        return new self(self::ITEM, $item);
    }

    public static function deLeccion(ResourceVersion $version): self
    {
        return new self(self::LECCION, $version);
    }

    /** Localiza una pieza por (tipo, id), o null si no existe. */
    public static function localizar(string $tipo, string $id): ?self
    {
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException("Tipo de pieza desconocido: «{$tipo}».");
        }

        if ($tipo === self::ITEM) {
            $item = PracticeItem::find($id);

            return $item === null ? null : self::deItem($item);
        }

        $version = ResourceVersion::with('resource')->find($id);

        return $version === null ? null : self::deLeccion($version);
    }

    public function id(): string
    {
        return (string) $this->modelo->id;
    }

    /** ¿Está firmada? La MISMA noción para las dos, cada una en su columna. */
    public function estaFirmada(): bool
    {
        return $this->modelo->reviewed_at !== null;
    }

    /** La lengua del contenido (null = contenido sin lengua, todo MINEDEC). */
    public function lengua(): ?string
    {
        return $this->tipo === self::ITEM
            ? $this->modelo->lengua
            : $this->modelo->resource?->lengua;
    }

    /** El descriptor al que está anclada (para agrupar por unidad). */
    public function descriptor(): ?LearningObjective
    {
        return $this->tipo === self::ITEM
            ? $this->modelo->objective
            : $this->modelo->resource?->objectives()->first();
    }

    /** La lección (Resource) de la que esta versión es una versión. */
    public function recurso(): ?Resource
    {
        return $this->tipo === self::LECCION ? $this->modelo->resource : null;
    }

    /** Un título corto para la lista de pendientes. */
    public function titulo(): string
    {
        if ($this->tipo === self::LECCION) {
            $t = $this->modelo->resource?->title;

            return is_array($t) ? ($t['es'] ?? 'Lección') : (string) ($t ?? 'Lección');
        }

        $enunciado = $this->modelo->statement;

        return is_array($enunciado) ? ($enunciado['es'] ?? 'Ejercicio') : (string) ($enunciado ?? 'Ejercicio');
    }
}
