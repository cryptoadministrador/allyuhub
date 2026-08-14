<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * ORÁCULO 3 (misión ANTY): Windows no distingue mayúsculas y el CI de Linux
 * sí — un directorio `Pages/` compila aquí y revienta allá. Este test lo caza
 * ANTES del CI. (Ya costó cinco tests una vez: el default de Inertia 3 es
 * resource_path('js/pages') en minúscula.)
 */
class JsCaseSensitivityTest extends TestCase
{
    public function test_ningun_directorio_bajo_pages_o_layouts_empieza_por_mayuscula(): void
    {
        $raices = [
            dirname(__DIR__, 2).'/resources/js/pages',
            dirname(__DIR__, 2).'/resources/js/layouts',
        ];

        $infractores = [];

        foreach ($raices as $raiz) {
            $this->assertDirectoryExists($raiz, "Falta {$raiz}: ¿alguien lo renombró a mayúscula?");

            $iterador = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($raiz, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterador as $entrada) {
                if ($entrada->isDir() && preg_match('/^\p{Lu}/u', $entrada->getFilename())) {
                    $infractores[] = $entrada->getPathname();
                }
            }
        }

        // Y las raíces mismas: resources/js no debe contener Pages/ ni Layouts/.
        foreach (scandir(dirname(__DIR__, 2).'/resources/js') as $nombre) {
            if (in_array($nombre, ['Pages', 'Layouts'], true)
                && is_dir(dirname(__DIR__, 2).'/resources/js/'.$nombre)
                // En Windows is_dir('Pages') matchea 'pages': confirmar el case REAL.
                && in_array($nombre, scandir(dirname(__DIR__, 2).'/resources/js'), true)) {
                $infractores[] = 'resources/js/'.$nombre;
            }
        }

        $this->assertSame([], $infractores,
            'Directorios con mayúscula inicial bajo resources/js/pages|layouts (el CI de Linux los rechazará): '
            .implode(', ', $infractores));
    }
}
