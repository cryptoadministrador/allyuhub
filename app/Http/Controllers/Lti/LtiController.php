<?php

namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Models\LearningObjective;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\User;
use App\Services\Lti\LtiCache;
use App\Services\Lti\LtiCookie;
use App\Services\Lti\LtiDatabase;
use App\Services\Lti\LtiHttpConnector;
use App\Services\Lti\ToolKeys;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Packback\Lti1p3\Claims\Claim;
use Packback\Lti1p3\Factories\MessageFactory;
use Packback\Lti1p3\LtiException;
use Packback\Lti1p3\LtiOidcLogin;
use Packback\Lti1p3\Messages\DeepLinkingRequest;
use Packback\Lti1p3\OidcException;
use UnexpectedValueException;

/**
 * Endpoints LTI 1.3 de la Tool. Van por el grupo de middleware `lti`
 * (sesión + cookies SIN cifrar — la cookie de state tiene nombre dinámico —
 * y sin CSRF de Laravel: el POST viene cross-site desde Moodle y la
 * protección real es state+nonce del propio protocolo).
 */
class LtiController extends Controller
{
    public function __construct(
        private readonly LtiDatabase $db,
        private readonly LtiCache $cache,
        private readonly LtiCookie $cookie,
        private readonly LtiHttpConnector $connector,
    ) {}

    /** GET /lti/jwks — clavero PÚBLICO de la Tool (solo n/e, jamás la privada). */
    public function jwks(ToolKeys $keys)
    {
        abort_unless($keys->exists(), 404, 'La Tool no tiene claves LTI: ejecuta `php artisan lti:keys`.');

        return response()->json($keys->publicJwks());
    }

    /**
     * GET|POST /lti/login — OIDC third-party initiated login.
     * Valida issuer/login_hint, siembra state (cookie) + nonce (cache) y
     * redirige al auth endpoint de la Platform.
     */
    public function login(Request $request)
    {
        try {
            $redirect = LtiOidcLogin::new($this->db, $this->cache, $this->cookie)
                ->getRedirectUrl(route('lti.launch'), $request->all());
        } catch (OidcException $e) {
            abort(400, $e->getMessage());
        }

        return redirect()->away($redirect);
    }

    /**
     * POST /lti/launch — valida el id_token DE VERDAD (firma contra el JWKS
     * de la Platform, state↔cookie, nonce de un solo uso, registration,
     * deployment, claims) vía MessageFactory de la librería, provisiona al
     * usuario por (lti_iss, lti_sub) e inicia sesión.
     */
    public function launch(Request $request)
    {
        abort_if(! $request->filled('state') || ! $request->filled('id_token'), 400, 'Faltan state o id_token.');

        $factory = new MessageFactory($this->db, $this->connector, $this->cache, $this->cookie);

        try {
            $message = $factory->create($request->only(['state', 'id_token']));
        } catch (LtiException|OidcException|UnexpectedValueException|DomainException|InvalidArgumentException $e) {
            // Cualquier fallo de validación es un 403 seco: no se filtra en qué
            // paso murió más allá del mensaje de la librería.
            abort(403, $e->getMessage());
        }

        $body = $message->getBody();

        $user = $this->provisionUser($body);
        Auth::login($user);
        $request->session()->regenerate();   // anti-fijación de sesión

        // Contexto del launch para AGS (fase D): lineitem/scopes viven en sesión.
        session(['lti' => [
            'launch_id' => $message->getLaunchId(),
            'iss' => $body['iss'],
            'client_id' => $message->getAud(),
            'deployment_id' => $body[Claim::DEPLOYMENT_ID],
            'sub' => $body['sub'],
            'ags' => $body[Claim::AGS_ENDPOINT] ?? null,
        ]]);

        if ($message instanceof DeepLinkingRequest) {
            abort(501, 'Deep Linking llega en la fase C.');
        }

        return $this->resourceView($body, $user);
    }

    /**
     * Provisión por (lti_iss, lti_sub) — regla dura de CLAUDE.md: el email
     * JAMÁS identifica. Si el claim trae email y está libre se guarda como
     * dato; si falta o ya está en uso, placeholder único e inválido a
     * propósito (dominio .invalid, RFC 2606).
     */
    private function provisionUser(array $body): User
    {
        $existing = User::query()
            ->where('lti_iss', $body['iss'])
            ->where('lti_sub', $body['sub'])
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $name = trim($body['name'] ?? trim(($body['given_name'] ?? '').' '.($body['family_name'] ?? '')));
        $email = $body['email'] ?? null;
        if ($email === null || User::where('email', $email)->exists()) {
            $email = 'lti+'.substr(hash('sha256', $body['iss'].'|'.$body['sub']), 0, 20).'@lti.allyuhub.invalid';
        }

        try {
            return User::forceCreate([
                'name' => $name !== '' ? $name : 'Estudiante LTI',
                'email' => $email,
                'password' => Str::password(40),   // jamás se usa: la vía de entrada es LTI
                'lti_iss' => $body['iss'],
                'lti_sub' => $body['sub'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dos launches simultáneos del mismo alumno nuevo: gana uno, el
            // otro relee (unique compuesto lti_iss+lti_sub).
            return User::query()
                ->where('lti_iss', $body['iss'])
                ->where('lti_sub', $body['sub'])
                ->firstOrFail();
        }
    }

    /** Vista mínima del resource link: destreza (con enlace a práctica) o simulador. */
    private function resourceView(array $body, User $user)
    {
        $custom = $body[Claim::CUSTOM] ?? [];
        $objective = null;
        $resource = null;
        $bundleUrl = null;
        $practiceUrl = null;

        if (($custom['allyu_type'] ?? null) === 'objective' && isset($custom['allyu_id'])) {
            $objective = LearningObjective::find($custom['allyu_id']);
            if ($objective !== null && $objective->practiceItems()->exists()) {
                // El usuario va DE LA SESIÓN, no del payload (provisional hasta
                // que la API de práctica se fusione con esta autenticación).
                $practiceUrl = url("/api/v1/objectives/{$objective->id}/practice/next").'?user_id='.$user->id;
            }
        } elseif (($custom['allyu_type'] ?? null) === 'resource' && isset($custom['allyu_id'])) {
            $resource = Resource::query()->published()->find($custom['allyu_id']);
            $bundleUrl = $resource?->current_version_id
                ? ResourceVersion::find($resource->current_version_id)?->bundle_url
                : null;
        }

        return view('lti.launch', [
            'user' => $user,
            'objective' => $objective,
            'resource' => $resource,
            'bundleUrl' => $bundleUrl,
            'practiceUrl' => $practiceUrl,
        ]);
    }
}
