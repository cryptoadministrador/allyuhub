<?php

namespace Tests;

use App\Models\LtiPlatform;
use App\Models\PracticeAttempt;
use App\Services\Practice\AttemptTicket;
use App\Services\Practice\PracticeEngine;
use App\Services\Practice\Practitioner;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI no compila el frontend: los tests jamás dependen del manifest
        // de Vite (las páginas se afirman con assertInertia, no con el bundle).
        $this->withoutVite();
    }

    /**
     * Los tests que MIDEN consultas comparan dos peticiones para ver si el
     * coste escala con los datos. El middleware de la CSP consulta las
     * Platforms LTI una sola vez y luego tira de caché, así que la primera
     * petición del test lleva una consulta que la segunda no: eso no es un
     * N+1, es el calentamiento. Se calienta aquí, a la vista, en lugar de
     * dejar que la diferencia se cuele en la medición.
     *
     * Los tests que comprueban que la CSP se mantiene al día (AppPagesTest)
     * NO usan esto: allí la invalidación es justo lo que se está probando.
     */
    protected function calentarCacheDeLaCsp(): void
    {
        Cache::forget(LtiPlatform::CACHE_ORIGENES);
        $this->get('/entrar')->assertOk();
    }

    /**
     * El billete que `next` habría emitido para este ítem, practicante e
     * intento. `submitAttempt` lo exige firmado, así que un test no puede
     * postear un intento sin él.
     *
     * Vive AQUÍ y no copiado en once ficheros a propósito: el fallo que este
     * billete vino a cerrar consistía precisamente en que dos sitios dedujeran
     * por su cuenta el mismo número. Repetir esa deducción en cada test sería
     * volver a plantarla, esta vez donde nadie la vigila.
     *
     * Un test que quiera comprobar el CIRCUITO COMPLETO no usa este atajo:
     * pide `next` y devuelve el billete que venga en la respuesta.
     */
    protected function billete(string $itemId, int|string $quien = Practitioner::CLAVE_INVITADO, int $intento = 1, bool $repaso = false): string
    {
        $engine = new PracticeEngine;

        return AttemptTicket::emitir(
            $itemId, $quien, $intento, $engine->seedFor($itemId, $quien, $intento), $repaso,
        );
    }

    /**
     * Un ANCLA sin verificar por cada área que el banco real de práctica
     * menciona y el grafo del test no trae.
     *
     * Existe por el pre-pase de erratas: un área que no existe en el grafo
     * REVIENTA la siembra (así se cazó CS.FL por CS.F). Los tests construyen
     * grafos parciales a propósito —solo Básica Superior, solo las ramas— y
     * sin estas anclas el pre-pase confundiría ese grafo parcial con una
     * errata del banco. En producción el papel de las anclas lo hace la
     * semilla demo, que trae todas las áreas sin verificar.
     */
    protected function anclarAreasDelBancoDePractica(\App\Models\FrameworkVersion $version): void
    {
        $areas = collect(require database_path('data/banco-practica.php'))
            ->map(fn (array $e) => \App\Services\Lesson\DestinosDeBloque::area($e[0]))
            ->unique();

        $nodo = null;
        foreach ($areas as $area) {
            $existe = \App\Models\LearningObjective::where('version_id', $version->id)
                ->where('native_code', 'like', $area.'.%')->exists();
            if ($existe) {
                continue;
            }

            $nodo ??= \App\Models\CurNode::create([
                'version_id' => $version->id, 'node_type' => 'grado',
                'native_code' => 'anclas', 'title' => ['es' => 'Anclas de área'],
                'path' => 'anclas',
            ]);
            \App\Models\LearningObjective::create([
                'node_id' => $nodo->id, 'version_id' => $version->id,
                // El .0.0.0 no colisiona con ningún bloque real: ancla el ÁREA
                // sin recibir jamás un ítem (ningún prefijo de banco lo cubre).
                'native_code' => "{$area}.0.0.0",
                'statement' => ['es' => "Ancla del área {$area}"],
                'is_verified' => false,
            ]);
        }
    }

    /**
     * El billete que `next` emitiría AHORA MISMO para este alumno y este ítem.
     *
     * Cuenta filas, sí — pero para SIMULAR al emisor, no para corregir. Esa es
     * toda la diferencia con el fallo que se arregló: el servidor ya no cuenta
     * al recibir la respuesta; cuenta al servir el ejercicio, una sola vez, y
     * firma el resultado. Un test que responde varias veces sin volver a pedir
     * ítem necesita reproducir esa cuenta, o chocaría con el índice único.
     */
    protected function billeteComoNext(string $itemId, int $userId): string
    {
        $intento = PracticeAttempt::where('item_id', $itemId)
            ->where('user_id', $userId)->count() + 1;

        return $this->billete($itemId, $userId, $intento);
    }
}
