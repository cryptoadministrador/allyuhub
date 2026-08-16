<?php

namespace App\Services\Blueprint;

/**
 * Emisor YAML mínimo y DETERMINISTA para el blueprint.
 *
 * Por qué a mano y no symfony/yaml: el contrato es nuestro y muy acotado
 * (mapas, listas y escalares), y no queremos una dependencia nueva en
 * producción solo para escribir un archivo. El riesgo clásico de un emisor
 * casero es el escapado; aquí se elimina de raíz: TODO escalar de texto se
 * emite con `json_encode`, y una cadena JSON es un escalar YAML 1.2 válido
 * (YAML 1.2 es un superconjunto de JSON). Los enteros, floats, booleanos y
 * null van tal cual.
 */
class YamlWriter
{
    public function dump(array $data): string
    {
        return "# Generado por AllyuHub (php artisan curso:blueprint) — NO editar a mano.\n"
            ."# El grafo es la verdad: corrige el currículo en AllyuHub y vuelve a generar.\n"
            .'---'."\n"
            .$this->map($data, 0);
    }

    private function map(array $data, int $indent): string
    {
        $pad = str_repeat('  ', $indent);
        $out = '';

        foreach ($data as $key => $value) {
            $k = $this->key($key);

            if (is_array($value) && $value !== []) {
                $out .= $pad.$k.":\n".(array_is_list($value)
                    ? $this->list($value, $indent + 1)
                    : $this->map($value, $indent + 1));

                continue;
            }

            if ($value === []) {
                $out .= $pad.$k.": []\n";

                continue;
            }

            $out .= $pad.$k.': '.$this->scalar($value)."\n";
        }

        return $out;
    }

    private function list(array $items, int $indent): string
    {
        $pad = str_repeat('  ', $indent);
        $out = '';

        foreach ($items as $item) {
            if (is_array($item) && $item !== []) {
                $block = array_is_list($item)
                    ? $this->list($item, $indent + 1)
                    : $this->map($item, $indent + 1);
                $lines = explode("\n", rtrim($block, "\n"));
                // El "- " ocupa el sitio del sangrado extra en la primera línea.
                $lines[0] = $pad.'- '.substr($lines[0], strlen($pad) + 2);
                $out .= implode("\n", $lines)."\n";

                continue;
            }

            $out .= $pad.'- '.($item === [] ? '[]' : $this->scalar($item))."\n";
        }

        return $out;
    }

    private function key(string|int $key): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key)
            ? (string) $key
            : $this->json((string) $key);
    }

    private function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            // Los floats también por json_encode: `%.4F` truncaba a cuatro
            // decimales y convertía 100.0 en el entero 100 (1.0e-5 salía como 0).
            default => $this->json($value),
        };
    }

    /**
     * `JSON_THROW_ON_ERROR` a propósito: sin él, un enunciado con UTF-8 roto
     * (los hay saliendo de `pdftotext`) devolvía `false`, se concatenaba como
     * cadena vacía y el YAML quedaba VÁLIDO con la destreza en null. Un
     * currículo corrupto tiene que hacer ruido, no colarse en silencio.
     */
    private function json(mixed $value): string
    {
        return json_encode(
            is_float($value) ? $value : (string) $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION
        );
    }
}
