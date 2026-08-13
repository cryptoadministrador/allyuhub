<?php

namespace App\Services\Lti;

use Exception;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Packback\Lti1p3\Interfaces\ICache;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\Interfaces\ILtiServiceConnector;
use Packback\Lti1p3\Interfaces\IServiceRequest;
use Packback\Lti1p3\LtiServiceConnector;
use Packback\Lti1p3\ServiceRequest;
use Psr\Http\Message\ResponseInterface;

/**
 * ILtiServiceConnector sobre el cliente Http de LARAVEL (no el Guzzle crudo
 * de la librería): así Http::fake() intercepta JWKS, token endpoint y AGS en
 * los tests sin tocar la validación real.
 *
 * El grant client-credentials replica el de LtiServiceConnector de la
 * librería (JWT client_assertion RS256 firmado con la clave de la Tool);
 * el reintento tras 401 va por código de estado porque el cliente de
 * Laravel no lanza excepción en 4xx como Guzzle.
 */
class LtiHttpConnector implements ILtiServiceConnector
{
    private bool $debuggingMode = false;

    public function __construct(private readonly ICache $cache) {}

    public function getAccessToken(ILtiRegistration $registration, array $scopes): string
    {
        $key = $this->accessTokenCacheKey($registration, $scopes);
        $token = $this->cache->getAccessToken($key);
        if (isset($token)) {
            return $token;
        }

        // client_assertion idéntico al de la librería (LtiServiceConnector).
        $clientId = $registration->getClientId();
        $assertion = JWT::encode([
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => [$registration->getAuthTokenUrl()],
            'iat' => time() - 5,
            'exp' => time() + 60,
            'jti' => 'lti-service-token'.hash('sha256', random_bytes(64)),
        ], $registration->getToolPrivateKey(), 'RS256', $registration->getKid());

        $request = new ServiceRequest(
            ServiceRequest::METHOD_POST,
            $registration->getAuthTokenUrl(),
            ServiceRequest::TYPE_AUTH,
        );
        $request->setPayload(['form_params' => [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $assertion,
            'scope' => implode(' ', $scopes),
        ]])->setMaskResponseLogs(true);

        $body = $this->getResponseBody($this->makeRequest($request));

        $this->cache->cacheAccessToken($key, $body['access_token']);

        return $body['access_token'];
    }

    public function makeRequest(IServiceRequest $request): ResponseInterface
    {
        // El payload trae opciones estilo Guzzle (headers, body, form_params):
        // Http::send() las pasa tal cual, pero por la pila fakeable de Laravel.
        $response = Http::send(
            $request->getMethod(),
            $request->getUrl(),
            $request->getPayload(),
        )->toPsrResponse();

        if ($this->debuggingMode) {
            Log::debug(LtiServiceConnector::getLogMessage(
                $request,
                $this->getResponseHeaders($response) ?? [],
                $this->getResponseBody($response),
            ));
        }

        return $response;
    }

    public function getResponseHeaders(ResponseInterface $response): ?array
    {
        $headers = $response->getHeaders();
        array_walk($headers, function (&$value) {
            $value = $value[0];
        });

        return $headers;
    }

    public function getResponseBody(ResponseInterface $response): ?array
    {
        return json_decode((string) $response->getBody(), true);
    }

    public function makeServiceRequest(
        ILtiRegistration $registration,
        array $scopes,
        IServiceRequest $request,
        bool $shouldRetry = true,
    ): array {
        $request->setAccessToken($this->getAccessToken($registration, $scopes));
        $response = $this->makeRequest($request);

        // Token caducado/revocado: se limpia la cache y se reintenta UNA vez.
        if ($response->getStatusCode() === 401 && $shouldRetry) {
            $this->cache->clearAccessToken($this->accessTokenCacheKey($registration, $scopes));

            return $this->makeServiceRequest($registration, $scopes, $request, false);
        }

        return [
            'headers' => $this->getResponseHeaders($response),
            'body' => $this->getResponseBody($response),
            'status' => $response->getStatusCode(),
        ];
    }

    public function getAll(
        ILtiRegistration $registration,
        array $scopes,
        IServiceRequest $request,
        ?string $key = null,
    ): array {
        if ($request->getMethod() !== ServiceRequest::METHOD_GET) {
            throw new Exception('An invalid method was specified by an LTI service requesting all items.');
        }

        $results = [];
        $nextUrl = $request->getUrl();

        while ($nextUrl) {
            $request->setUrl($nextUrl);
            $response = $this->makeServiceRequest($registration, $scopes, $request);
            $body = isset($key) ? ($response['body'][$key] ?? []) : ($response['body'] ?? []);
            $results = array_merge($results, $body);
            $nextUrl = $this->nextUrlFrom($response['headers']);
        }

        return $results;
    }

    public function setDebuggingMode(bool $enable): ILtiServiceConnector
    {
        $this->debuggingMode = $enable;

        return $this;
    }

    /** Misma derivación de clave de cache que la librería. */
    private function accessTokenCacheKey(ILtiRegistration $registration, array $scopes): string
    {
        sort($scopes);

        return $registration->getIssuer().$registration->getClientId().md5(implode('|', $scopes));
    }

    private function nextUrlFrom(array $headers): ?string
    {
        $subject = $headers['Link'] ?? $headers['link'] ?? '';
        preg_match(LtiServiceConnector::NEXT_PAGE_REGEX, $subject, $matches);

        return $matches[1] ?? null;
    }
}
