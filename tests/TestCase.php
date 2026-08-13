<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI no compila el frontend: los tests jamás dependen del manifest
        // de Vite (las páginas se afirman con assertInertia, no con el bundle).
        $this->withoutVite();
    }
}
