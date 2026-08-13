<?php

namespace Tests\Support;

use App\Models\LtiPlatform;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Packback\Lti1p3\Claims\Claim;
use phpseclib3\Crypt\RSA;

/**
 * Una "Platform" (Moodle) de mentira para los tests LTI: par RSA REAL,
 * id_tokens firmados de verdad y JWKS servido con Http::fake. Nada de
 * mockear la validación: se valida contra firmas reales.
 *
 * Las claves se memoizan por proceso (generar RSA 2048 es caro) — son
 * material de TEST, jamás se persisten.
 */
class FakeLtiPlatform
{
    public const ISSUER = 'https://moodle.colegio.test';

    public const CLIENT_ID = 'client-abc';

    public const DEPLOYMENT_ID = 'dep-1';

    public const KID = 'moodle-key-1';

    public const JWKS_URL = self::ISSUER.'/mod/lti/certs.php';

    private static ?string $privatePem = null;

    private static ?string $attackerPem = null;

    public LtiPlatform $platform;

    public function __construct(array $overrides = [])
    {
        $this->platform = LtiPlatform::create(array_merge([
            'issuer' => self::ISSUER,
            'client_id' => self::CLIENT_ID,
            'auth_login_url' => self::ISSUER.'/mod/lti/auth.php',
            'auth_token_url' => self::ISSUER.'/mod/lti/token.php',
            'jwks_url' => self::JWKS_URL,
            'deployment_ids' => [self::DEPLOYMENT_ID],
        ], $overrides));
    }

    /** Sirve el JWKS de la Platform con Http::fake (se puede llamar varias veces). */
    public function fakeJwks(): void
    {
        Http::fake([$this->platform->jwks_url => Http::response(self::jwks())]);
    }

    public static function privateKeyPem(): string
    {
        return self::$privatePem ??= RSA::createKey(2048)->toString('PKCS8');
    }

    /** Clave de un ATACANTE: distinta de la de la Platform, mismo formato. */
    public static function attackerKeyPem(): string
    {
        return self::$attackerPem ??= RSA::createKey(2048)->toString('PKCS8');
    }

    public static function jwks(): array
    {
        $public = RSA::load(self::privateKeyPem())->getPublicKey();
        $jwk = json_decode($public->toString('JWK'), true)['keys'][0];

        return ['keys' => [array_merge($jwk, ['alg' => 'RS256', 'use' => 'sig', 'kid' => self::KID])]];
    }

    /**
     * id_token firmado por la Platform (o por $signWith, p. ej. la clave del
     * atacante). Un override a null ELIMINA el claim.
     */
    public function idToken(array $overrides = [], ?string $signWith = null, ?string $kid = null): string
    {
        $claims = array_merge([
            'iss' => $this->platform->issuer,
            'aud' => $this->platform->client_id,
            'sub' => 'moodle-user-7',
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => 'nonce-sin-handshake',
            'name' => 'Ana Estudiante',
            'email' => 'ana@colegio.test',
            Claim::MESSAGE_TYPE => 'LtiResourceLinkRequest',
            Claim::VERSION => '1.3.0',
            Claim::DEPLOYMENT_ID => self::DEPLOYMENT_ID,
            Claim::TARGET_LINK_URI => 'http://localhost/lti/launch',
            Claim::RESOURCE_LINK => ['id' => 'rl-1'],
            Claim::ROLES => ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'],
        ], $overrides);

        $claims = array_filter($claims, fn ($v) => $v !== null);

        return JWT::encode($claims, $signWith ?? self::privateKeyPem(), 'RS256', $kid ?? self::KID);
    }
}
