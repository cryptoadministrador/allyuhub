<?php

namespace Tests;

use App\Models\LtiPlatform;
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
}
