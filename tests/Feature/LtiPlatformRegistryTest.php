<?php

namespace Tests\Feature;

use App\Models\LtiPlatform;
use App\Services\Lti\LtiCache;
use App\Services\Lti\LtiDatabase;
use App\Services\Lti\ToolKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\LtiDeployment;
use Tests\TestCase;

/**
 * Registro de plataformas LTI (fase A): el comando lti:platform:add da de alta
 * un Moodle sin tocar código, y LtiDatabase/LtiCache implementan las interfaces
 * de la librería sobre Eloquent y la cache de Laravel.
 */
class LtiPlatformRegistryTest extends TestCase
{
    use RefreshDatabase;

    private const ISSUER = 'https://moodle.colegio.test';

    private const CLIENT = 'client-abc';

    private function addPlatform(array $overrides = []): LtiPlatform
    {
        return LtiPlatform::create(array_merge([
            'issuer' => self::ISSUER,
            'client_id' => self::CLIENT,
            'auth_login_url' => self::ISSUER.'/mod/lti/auth.php',
            'auth_token_url' => self::ISSUER.'/mod/lti/token.php',
            'jwks_url' => self::ISSUER.'/mod/lti/certs.php',
            'deployment_ids' => ['dep-1'],
        ], $overrides));
    }

    public function test_el_comando_registra_y_reejecutar_actualiza(): void
    {
        $this->artisan('lti:platform:add', [
            'issuer' => self::ISSUER,
            'client_id' => self::CLIENT,
            '--auth-login-url' => self::ISSUER.'/mod/lti/auth.php',
            '--auth-token-url' => self::ISSUER.'/mod/lti/token.php',
            '--jwks-url' => self::ISSUER.'/mod/lti/certs.php',
            '--deployment' => ['dep-1', 'dep-2'],
        ])->assertSuccessful();

        $this->assertDatabaseCount('lti_platforms', 1);
        $this->assertDatabaseHas('lti_platforms', [
            'issuer' => self::ISSUER, 'client_id' => self::CLIENT, 'is_active' => true,
        ]);

        // Re-ejecutar con otra URL actualiza el registro, no lo duplica.
        $this->artisan('lti:platform:add', [
            'issuer' => self::ISSUER,
            'client_id' => self::CLIENT,
            '--auth-login-url' => self::ISSUER.'/otro/auth.php',
        ])->assertSuccessful();

        $this->assertDatabaseCount('lti_platforms', 1);
        $this->assertDatabaseHas('lti_platforms', ['auth_login_url' => self::ISSUER.'/otro/auth.php']);
        // Los deployments previos no se pierden al actualizar sin --deployment.
        $this->assertContains('dep-1', LtiPlatform::first()->deployment_ids);
    }

    public function test_find_registration_by_issuer(): void
    {
        Storage::fake('local');
        $this->artisan('lti:keys')->assertSuccessful();
        $this->addPlatform();

        $db = app(LtiDatabase::class);

        $reg = $db->findRegistrationByIssuer(self::ISSUER, self::CLIENT);
        $this->assertInstanceOf(ILtiRegistration::class, $reg);
        $this->assertSame(self::CLIENT, $reg->getClientId());
        $this->assertSame(self::ISSUER.'/mod/lti/auth.php', $reg->getAuthLoginUrl());
        $this->assertSame(self::ISSUER.'/mod/lti/token.php', $reg->getAuthTokenUrl());
        $this->assertSame(self::ISSUER.'/mod/lti/certs.php', $reg->getKeySetUrl());
        // La registration lleva la clave privada de la Tool para firmar (kid estable).
        $this->assertNotEmpty($reg->getToolPrivateKey());
        $this->assertSame(app(ToolKeys::class)->kid(), $reg->getKid());

        $this->assertNull($db->findRegistrationByIssuer('https://otro.test', self::CLIENT));
        $this->assertNull($db->findRegistrationByIssuer(self::ISSUER, 'client-desconocido'));
    }

    public function test_plataforma_inactiva_no_resuelve(): void
    {
        Storage::fake('local');
        $this->artisan('lti:keys')->assertSuccessful();
        $this->addPlatform(['is_active' => false]);

        $this->assertNull(app(LtiDatabase::class)->findRegistrationByIssuer(self::ISSUER, self::CLIENT));
    }

    public function test_find_deployment_respeta_los_registrados(): void
    {
        Storage::fake('local');
        $this->artisan('lti:keys')->assertSuccessful();
        $this->addPlatform();

        $db = app(LtiDatabase::class);

        $dep = $db->findDeployment(self::ISSUER, 'dep-1', self::CLIENT);
        $this->assertInstanceOf(LtiDeployment::class, $dep);
        $this->assertSame('dep-1', $dep->getDeploymentId());

        $this->assertNull($db->findDeployment(self::ISSUER, 'dep-desconocido', self::CLIENT));
        $this->assertNull($db->findDeployment('https://otro.test', 'dep-1', self::CLIENT));
    }

    public function test_nonce_es_de_un_solo_uso_y_atado_al_state(): void
    {
        $cache = app(LtiCache::class);

        $cache->cacheNonce('nonce-1', 'state-1');

        // Nonce con state equivocado no vale (y ya no se consume otra vez).
        $cache->cacheNonce('nonce-2', 'state-2');
        $this->assertFalse($cache->checkNonceIsValid('nonce-2', 'state-EQUIVOCADO'));

        // Primer uso válido, segundo uso (replay) rechazado.
        $this->assertTrue($cache->checkNonceIsValid('nonce-1', 'state-1'));
        $this->assertFalse($cache->checkNonceIsValid('nonce-1', 'state-1'));

        // Nonce jamás cacheado.
        $this->assertFalse($cache->checkNonceIsValid('nonce-fantasma', 'state-1'));
    }

    public function test_launch_data_y_access_token_en_cache(): void
    {
        $cache = app(LtiCache::class);

        $this->assertNull($cache->getLaunchData('launch-x'));
        $cache->cacheLaunchData('launch-x', ['sub' => 'alumno-1']);
        $this->assertSame(['sub' => 'alumno-1'], $cache->getLaunchData('launch-x'));

        $this->assertNull($cache->getAccessToken('tok'));
        $cache->cacheAccessToken('tok', 'abc123');
        $this->assertSame('abc123', $cache->getAccessToken('tok'));
        $cache->clearAccessToken('tok');
        $this->assertNull($cache->getAccessToken('tok'));
    }
}
