<?php

namespace App\Console\Commands;

use App\Models\LtiPlatform;
use Illuminate\Console\Command;

/**
 * Alta/actualización de una Platform (Moodle) sin tocar código:
 *
 *  php artisan lti:platform:add https://moodle.colegio.edu.ec CLIENTID \
 *    --auth-login-url=.../mod/lti/auth.php --auth-token-url=.../mod/lti/token.php \
 *    --jwks-url=.../mod/lti/certs.php --deployment=1
 *
 * Re-ejecutar con el mismo (issuer, client_id) actualiza solo lo que se pase;
 * los --deployment se ACUMULAN (Moodle crea deployments nuevos por actividad).
 */
class LtiPlatformAdd extends Command
{
    protected $signature = 'lti:platform:add
        {issuer : Issuer de la Platform (URL base de Moodle)}
        {client_id : Client ID que Moodle asignó a la Tool}
        {--auth-login-url= : Endpoint OIDC auth (…/mod/lti/auth.php)}
        {--auth-token-url= : Endpoint de token OAuth2 (…/mod/lti/token.php)}
        {--jwks-url= : JWKS de la Platform (…/mod/lti/certs.php)}
        {--deployment=* : deployment_id permitido (repetible; se acumulan)}
        {--inactive : Deja la Platform desactivada}';

    protected $description = 'Registra o actualiza una Platform LTI 1.3 (Moodle)';

    public function handle(): int
    {
        $existing = LtiPlatform::query()
            ->where('issuer', $this->argument('issuer'))
            ->where('client_id', $this->argument('client_id'))
            ->first();

        $urls = array_filter([
            'auth_login_url' => $this->option('auth-login-url'),
            'auth_token_url' => $this->option('auth-token-url'),
            'jwks_url' => $this->option('jwks-url'),
        ]);

        if ($existing === null && count($urls) < 3) {
            $this->error('Para el alta inicial hacen falta --auth-login-url, --auth-token-url y --jwks-url.');

            return self::FAILURE;
        }

        $platform = LtiPlatform::updateOrCreate(
            [
                'issuer' => $this->argument('issuer'),
                'client_id' => $this->argument('client_id'),
            ],
            $urls + [
                'is_active' => ! $this->option('inactive'),
                'deployment_ids' => array_values(array_unique(array_merge(
                    $existing?->deployment_ids ?? [],
                    $this->option('deployment'),
                ))),
            ],
        );

        $this->info(($existing ? 'Actualizada' : 'Registrada').' la Platform '.$platform->issuer);
        $this->line('client_id: '.$platform->client_id);
        $this->line('deployments: '.(implode(', ', $platform->deployment_ids) ?: '(ninguno aún)'));

        return self::SUCCESS;
    }
}
