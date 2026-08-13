<?php

namespace App\Services\Lti;

use Illuminate\Support\Facades\Storage;
use Packback\Lti1p3\JwksEndpoint;
use phpseclib3\Crypt\RSA;
use RuntimeException;

/**
 * Par de claves RSA de la Tool (LTI 1.3). La clave privada firma los
 * DeepLinkingResponse y los client_assertion del grant AGS; la pública se
 * publica en GET /lti/jwks para que la Platform valide esas firmas.
 *
 * La privada vive en storage local (fuera del webroot) y JAMÁS sale por
 * ningún endpoint. Rotarla (lti:keys --force) cambia el kid: hay que
 * esperar a que Moodle refresque el JWKS antes de retirar la anterior.
 */
class ToolKeys
{
    public const STORAGE_PATH = 'lti/tool_private.pem';

    public function exists(): bool
    {
        return Storage::disk('local')->exists(self::STORAGE_PATH);
    }

    /** Genera el par (2048 bits). Sin $force respeta la clave existente. */
    public function generate(bool $force = false): bool
    {
        if ($this->exists() && ! $force) {
            return false;
        }

        $key = RSA::createKey(2048);
        // visibility 'private' → 0600: la clave privada no debe ser legible por
        // otros usuarios del servidor (relevante en hosting compartido).
        Storage::disk('local')->put(self::STORAGE_PATH, $key->toString('PKCS8'), 'private');

        return true;
    }

    public function privateKeyPem(): string
    {
        if (! $this->exists()) {
            throw new RuntimeException('La Tool no tiene claves LTI: ejecuta `php artisan lti:keys`.');
        }

        return Storage::disk('local')->get(self::STORAGE_PATH);
    }

    /** kid estable derivado de la clave PÚBLICA: rota solo si rota la clave. */
    public function kid(): string
    {
        $publicPem = RSA::load($this->privateKeyPem())->getPublicKey()->toString('PKCS8');

        return substr(hash('sha256', $publicPem), 0, 32);
    }

    /** JWKS público (la librería extrae solo n/e: nada privado sale de aquí). */
    public function publicJwks(): array
    {
        return JwksEndpoint::new([$this->kid() => $this->privateKeyPem()])->getPublicJwks();
    }
}
