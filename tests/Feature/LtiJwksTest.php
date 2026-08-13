<?php

namespace Tests\Feature;

use App\Services\Lti\ToolKeys;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Claves RSA de la Tool (fase A LTI): `lti:keys` genera el par y
 * GET /lti/jwks publica SOLO la parte pública (JWKS).
 */
class LtiJwksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_lti_keys_genera_el_par_y_el_jwks_lo_publica(): void
    {
        $this->artisan('lti:keys')->assertSuccessful();

        $key = $this->getJson('/lti/jwks')
            ->assertOk()
            ->assertJsonCount(1, 'keys')
            ->json('keys.0');

        $this->assertSame('RSA', $key['kty']);
        $this->assertSame('RS256', $key['alg']);
        $this->assertSame('sig', $key['use']);
        $this->assertNotEmpty($key['kid']);
        $this->assertNotEmpty($key['n']);    // módulo público
        $this->assertNotEmpty($key['e']);    // exponente público

        // SEGURIDAD: ningún componente privado sale por el endpoint.
        foreach (['d', 'p', 'q', 'dp', 'dq', 'qi', 'oth'] as $private) {
            $this->assertArrayNotHasKey($private, $key, "El JWKS filtra el componente privado '{$private}'");
        }
    }

    public function test_sin_claves_generadas_el_jwks_devuelve_404(): void
    {
        $this->getJson('/lti/jwks')->assertNotFound();
    }

    public function test_lti_keys_es_idempotente_y_solo_force_rota(): void
    {
        $this->artisan('lti:keys')->assertSuccessful();
        $kid = app(ToolKeys::class)->kid();

        // Sin --force no toca la clave existente (rotar rompería launches en vuelo).
        $this->artisan('lti:keys')->assertSuccessful();
        $this->assertSame($kid, app(ToolKeys::class)->kid());

        $this->artisan('lti:keys', ['--force' => true])->assertSuccessful();
        $this->assertNotSame($kid, app(ToolKeys::class)->kid());
    }
}
