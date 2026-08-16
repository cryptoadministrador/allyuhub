<?php

namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Models\LearningObjective;
use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\LtiResourceLink;
use App\Models\Resource;
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
use Packback\Lti1p3\DeepLinkResources\Resource as DeepLinkResource;
use Packback\Lti1p3\Factories\MessageFactory;
use Packback\Lti1p3\LtiConstants;
use Packback\Lti1p3\LtiDeepLink;
use Packback\Lti1p3\LtiException;
use Packback\Lti1p3\LtiLineitem;
use Packback\Lti1p3\LtiOidcLogin;
use Packback\Lti1p3\Messages\DeepLinkingRequest;
use Packback\Lti1p3\OidcException;
use UnexpectedValueException;

/**
 * Endpoints LTI 1.3 de la Tool. Van por el grupo `web` completo (sesión y
 * cookies cifradas, compartidas con la app Inertia) con una única excepción:
 * sin CSRF de Laravel en lti/* — el POST viene cross-site desde Moodle y la
 * protección real es state+nonce del propio protocolo. El launch validado
 * REDIRIGE a la app; aquí solo queda Blade para el deep linking (un
 * formulario de un solo uso de vuelta a Moodle).
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

        // Los claims context y roles se persisten SIEMPRE (también en deep
        // linking): el rol es por contexto y aquí nace la membership.
        [$context, $role] = $this->rememberContextMembership($body, $user);

        if ($message instanceof DeepLinkingRequest) {
            return $this->deepLinkingSelection();
        }

        $this->rememberAgsContext($body, $user);

        // Un instructor aterriza en SU panel; un learner sigue yendo donde iba.
        // El panel es el destino por DEFECTO del instructor, no una cárcel:
        // si el launch trae un destino elegido por Deep Linking (allyu_type),
        // ese manda. Sin esto, el docente que hace clic en la actividad
        // «Práctica: destreza X» que él mismo configuró caía SIEMPRE en el
        // panel y jamás podía previsualizar lo que asigna (auditoría PR #17).
        $custom = $body[Claim::CUSTOM] ?? [];
        if ($role === 'instructor' && $context !== null && ! isset($custom['allyu_type'])) {
            return redirect()->route('docente', $context);
        }

        // El launch NO renderiza nada: redirige (302) a la app Inertia, que
        // vive en el grupo web completo (con CSRF) y usa esta misma sesión.
        return $this->redirectToContent($body);
    }

    /**
     * Upsert del curso (claim context) y de la membership con su rol.
     * Solo membership#Instructor o membership#ContentDeveloper convierten en
     * instructor DE ESE CURSO: los roles de sistema/institución no cuentan,
     * y roles vacíos o basura degradan a learner (jamás se infla).
     *
     * @return array{0: ?LtiContext, 1: string}
     */
    private function rememberContextMembership(array $body, User $user): array
    {
        $roles = $body[Claim::ROLES] ?? [];
        $role = $this->roleForContext(is_array($roles) ? $roles : []);

        $claim = $body[Claim::CONTEXT] ?? null;
        $contextId = is_array($claim) ? ($claim['id'] ?? null) : null;
        if (! is_string($contextId) || $contextId === '') {
            return [null, $role];   // launch sin curso: nada que persistir
        }

        $platformId = LtiPlatform::query()->active()
            ->where('issuer', $body['iss'])
            ->where('client_id', is_array($body['aud']) ? $body['aud'][0] : $body['aud'])
            ->value('id');
        if ($platformId === null) {
            return [null, $role];
        }

        // updateOrCreate no es atómico (SELECT y luego INSERT): dos launches
        // simultáneos del mismo curso/alumno chocarían en el unique y darían un
        // 500. Se blinda con el mismo patrón de provisionUser — un reintento
        // basta: tras la violación la fila existe y el segundo pasa por UPDATE.
        $context = $this->upsert(
            fn () => LtiContext::updateOrCreate(
                ['platform_id' => $platformId, 'context_id' => $contextId],
                [
                    'title' => is_string($claim['title'] ?? null) ? $claim['title'] : null,
                    'label' => is_string($claim['label'] ?? null) ? $claim['label'] : null,
                    // OJO: track_id no se toca aquí — lo asigna el docente.
                ],
            ),
        );

        $this->upsert(
            fn () => LtiContextMembership::updateOrCreate(
                ['lti_context_id' => $context->id, 'user_id' => $user->id],
                ['role' => $role, 'last_launched_at' => now()],
            ),
        );

        return [$context, $role];
    }

    /** updateOrCreate resistente a la carrera SELECT→INSERT (un reintento). */
    private function upsert(callable $upsert)
    {
        try {
            return $upsert();
        } catch (UniqueConstraintViolationException) {
            return $upsert();
        }
    }

    /**
     * El rol EN ESTE curso a partir del claim roles del id_token validado.
     * LTI admite la URI completa del vocabulario de contexto y su forma corta;
     * se comparan por igualdad EXACTA (str_contains inflaba con URIs que solo
     * imitaban el sufijo, p. ej. «…/membership#Instructor-falso», y descartaba
     * la forma corta legítima «Instructor»).
     */
    private function roleForContext(array $roles): string
    {
        $instructor = [
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor',
            'http://purl.imsglobal.org/vocab/lis/v2/membership#ContentDeveloper',
            'Instructor',
            'ContentDeveloper',
        ];

        foreach ($roles as $rol) {
            if (is_string($rol) && in_array($rol, $instructor, true)) {
                return 'instructor';
            }
        }

        return 'learner';
    }

    /** A dónde aterriza el resource link: destreza, simulador o el progreso. */
    private function redirectToContent(array $body)
    {
        $custom = $body[Claim::CUSTOM] ?? [];
        $type = $custom['allyu_type'] ?? null;
        $id = $custom['allyu_id'] ?? null;

        // El claim custom lo configura el admin de Moodle (semi-confiable): si no
        // es un UUID, PostgreSQL revienta el whereKey con «invalid input syntax
        // for type uuid» y el launch responde 500. Se filtra antes de consultar.
        if (! is_string($id) || ! Str::isUuid($id)) {
            return redirect()->route('inicio');
        }

        if ($type === 'objective' && LearningObjective::whereKey($id)->exists()) {
            return redirect()->route('practicar', $id);
        }
        if ($type === 'resource' && Resource::query()->published()->whereKey($id)->exists()) {
            return redirect()->route('recurso', $id);
        }

        return redirect()->route('inicio');
    }

    /**
     * Si el launch trae el claim AGS, persiste el contexto de calificación:
     * el job PushLtiScore lo necesita fuera de la sesión (corre en cola).
     */
    private function rememberAgsContext(array $body, User $user): void
    {
        if (! isset($body[Claim::AGS_ENDPOINT], $body[Claim::RESOURCE_LINK]['id'])) {
            return;
        }

        $platformId = LtiPlatform::query()->active()
            ->where('issuer', $body['iss'])
            ->where('client_id', is_array($body['aud']) ? $body['aud'][0] : $body['aud'])
            ->value('id');
        if ($platformId === null) {
            return;   // no debería pasar: el launch ya validó la registration
        }

        $custom = $body[Claim::CUSTOM] ?? [];
        $objectiveId = ($custom['allyu_type'] ?? null) === 'objective'
            ? LearningObjective::query()->whereKey($custom['allyu_id'] ?? null)->value('id')
            : null;

        LtiResourceLink::updateOrCreate(
            [
                'platform_id' => $platformId,
                'resource_link_id' => $body[Claim::RESOURCE_LINK]['id'],
                'user_id' => $user->id,
            ],
            [
                'objective_id' => $objectiveId,
                'ags' => $body[Claim::AGS_ENDPOINT],
                'last_launched_at' => now(),
            ],
        );
    }

    /**
     * POST /lti/deep-link — el docente eligió un contenido: la Tool responde
     * con el DeepLinkingResponse JWT firmado con SU clave privada, en un
     * formulario auto-enviado al deep_link_return_url de la Platform.
     * Solo vale dentro de una sesión iniciada por un launch de Deep Linking.
     */
    public function deepLinkRespond(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:objective,resource',
            'id' => 'required|uuid',
        ]);

        $launchId = session('lti.launch_id');
        abort_if($launchId === null, 403, 'No hay una sesión LTI activa.');

        $body = $this->cache->getLaunchData($launchId);
        abort_if($body === null, 403, 'El launch expiró: vuelve a entrar desde Moodle.');
        abort_if(
            ($body[Claim::MESSAGE_TYPE] ?? null) !== LtiConstants::MESSAGE_TYPE_DEEPLINK,
            403,
            'Este launch no es de Deep Linking.',
        );

        $clientId = is_array($body['aud']) ? $body['aud'][0] : $body['aud'];
        $registration = $this->db->findRegistrationByIssuer($body['iss'], $clientId);
        abort_if($registration === null, 403, 'Platform no registrada.');

        $deepLink = new LtiDeepLink(
            $registration,
            $body[Claim::DEPLOYMENT_ID],
            $body[Claim::DL_DEEP_LINK_SETTINGS],
        );

        return view('lti.deep-link-response', [
            'returnUrl' => $deepLink->returnUrl(),
            'jwt' => $deepLink->getResponseJwt([
                $this->contentItemFor($data['type'], $data['id'], $deepLink),
            ]),
        ]);
    }

    /** Vista de selección: simuladores publicados + destrezas con ítems. */
    private function deepLinkingSelection()
    {
        return view('lti.deep-link', [
            'sims' => Resource::query()->published()
                ->whereIn('kind', ['simulation', 'lab'])
                ->orderBy('slug')->get(),
            'objectives' => LearningObjective::query()
                ->whereHas('practiceItems')
                ->orderBy('native_code')->orderBy('id')->get(),
        ]);
    }

    /** El content item del elegido; con lineitem AGS si la Platform lo acepta. */
    private function contentItemFor(string $type, string $id, LtiDeepLink $deepLink): DeepLinkResource
    {
        if ($type === 'objective') {
            $objective = LearningObjective::query()->whereHas('practiceItems')->find($id);
            abort_if($objective === null, 422, 'Destreza inexistente o sin ítems de práctica.');

            $item = DeepLinkResource::new()
                ->setUrl(route('lti.launch'))
                ->setTitle($objective->native_code.' — '.Str::limit($objective->statement['es'] ?? '', 80))
                ->setCustomParams(['allyu_type' => 'objective', 'allyu_id' => $objective->id]);

            if ($deepLink->canAcceptLineitem()) {
                // Un lineitem por destreza: la nota que AGS empujará en fase D.
                $item->setLineItem(LtiLineitem::new()
                    ->setScoreMaximum(100)
                    ->setLabel($objective->native_code)
                    ->setResourceId('objective:'.$objective->id)
                    ->setTag('allyuhub-mastery'));
            }

            return $item;
        }

        $resource = Resource::query()->published()->find($id);
        abort_if($resource === null, 422, 'Recurso inexistente o sin publicar.');

        return DeepLinkResource::new()
            ->setUrl(route('lti.launch'))
            ->setTitle($resource->title['es'] ?? $resource->slug)
            ->setCustomParams(['allyu_type' => 'resource', 'allyu_id' => $resource->id]);
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
}
