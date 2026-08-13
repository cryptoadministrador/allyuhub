<?php

namespace App\Services\Lti;

use App\Models\LtiPlatform;
use Packback\Lti1p3\Interfaces\IDatabase;
use Packback\Lti1p3\Interfaces\ILtiDeployment;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\LtiDeployment;
use Packback\Lti1p3\LtiRegistration;

/**
 * IDatabase de la librería sobre Eloquent: resuelve la Platform registrada
 * (lti_platforms) y le adjunta la clave privada de la Tool para firmar.
 *
 * OJO: findDeployment devuelve el LtiDeployment CONCRETO de la librería
 * (no solo la interfaz): MessageFactory::validateDeployment lo exige así.
 */
class LtiDatabase implements IDatabase
{
    public function __construct(private readonly ToolKeys $keys) {}

    public function findRegistrationByIssuer(string $iss, ?string $clientId = null): ?ILtiRegistration
    {
        $platform = $this->findPlatform($iss, $clientId);
        if ($platform === null) {
            return null;
        }

        return LtiRegistration::new()
            ->setIssuer($platform->issuer)
            ->setClientId($platform->client_id)
            ->setAuthLoginUrl($platform->auth_login_url)
            ->setAuthTokenUrl($platform->auth_token_url)
            ->setKeySetUrl($platform->jwks_url)
            ->setToolPrivateKey($this->keys->privateKeyPem())
            ->setKid($this->keys->kid());
    }

    public function findDeployment(string $iss, string $deploymentId, ?string $clientId = null): ?ILtiDeployment
    {
        $platform = $this->findPlatform($iss, $clientId);

        if ($platform === null || ! in_array($deploymentId, $platform->deployment_ids ?? [], true)) {
            return null;
        }

        return LtiDeployment::new($deploymentId);
    }

    private function findPlatform(string $iss, ?string $clientId): ?LtiPlatform
    {
        return LtiPlatform::query()
            ->active()
            ->where('issuer', $iss)
            ->when($clientId !== null, fn ($q) => $q->where('client_id', $clientId))
            ->orderBy('client_id')   // sin client_id: resolución determinista
            ->first();
    }
}
