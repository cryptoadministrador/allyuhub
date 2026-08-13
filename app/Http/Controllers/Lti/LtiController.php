<?php

namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Services\Lti\ToolKeys;

/**
 * Endpoints LTI 1.3 de la Tool. Van por el grupo de middleware `lti`
 * (sesión + cookies SIN cifrar — la cookie de state tiene nombre dinámico —
 * y sin CSRF de Laravel: el POST viene cross-site desde Moodle y la
 * protección real es state+nonce del propio protocolo).
 */
class LtiController extends Controller
{
    /** GET /lti/jwks — clavero PÚBLICO de la Tool (solo n/e, jamás la privada). */
    public function jwks(ToolKeys $keys)
    {
        abort_unless($keys->exists(), 404, 'La Tool no tiene claves LTI: ejecuta `php artisan lti:keys`.');

        return response()->json($keys->publicJwks());
    }
}
