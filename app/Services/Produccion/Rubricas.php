<?php

namespace App\Services\Produccion;

use InvalidArgumentException;

/**
 * Lee las rúbricas del CONTENIDO (`database/data/rubricas-lenguas.php`). El
 * panel del docente las pinta desde aquí y la corrección se valida contra
 * aquí: los criterios y el número de niveles no se escriben dos veces.
 */
final class Rubricas
{
    /** Los tipos de producción con rúbrica — la lista cerrada. */
    public const TIPOS = ['escritura', 'voz'];

    /** Cuántos niveles tiene cada criterio (0..NIVELES-1). */
    public const NIVELES = 3;

    /** Cuántos criterios tiene una rúbrica. */
    public const CRITERIOS = 4;

    /** @var array<string, array>|null */
    private static ?array $cache = null;

    /**
     * La rúbrica de un tipo de producción. `$unidad` se acepta y se ignora hoy
     * (rúbrica única de A1); está en la firma para que una rúbrica por unidad
     * no cambie a quien la llama.
     *
     * @return array{titulo: string, criterios: list<array{clave: string, titulo: string, niveles: list<string>}>}
     */
    public static function para(string $tipo, int $unidad): array
    {
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException("Tipo de producción desconocido: «{$tipo}».");
        }

        self::$cache ??= require database_path('data/rubricas-lenguas.php');

        return self::$cache[$tipo];
    }

    /** Las claves de criterio de un tipo, en orden. @return list<string> */
    public static function claves(string $tipo, int $unidad): array
    {
        return array_map(fn (array $c) => $c['clave'], self::para($tipo, $unidad)['criterios']);
    }
}
