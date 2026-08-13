<?php

namespace App\Console\Commands;

use App\Services\Lti\ToolKeys;
use Illuminate\Console\Command;

class LtiKeys extends Command
{
    protected $signature = 'lti:keys
        {--force : Regenera el par aunque exista (ROTA el kid: Moodle debe refrescar el JWKS)}';

    protected $description = 'Genera el par de claves RSA de la Tool LTI 1.3 (storage/app/lti)';

    public function handle(ToolKeys $keys): int
    {
        if ($keys->generate($this->option('force'))) {
            $this->info('Par de claves RSA generado en storage/app/'.ToolKeys::STORAGE_PATH);
        } else {
            $this->line('Ya existe una clave; usa --force para rotarla (ojo: invalida las firmas en vuelo).');
        }

        $this->line('kid: '.$keys->kid());
        $this->line('JWKS público: GET /lti/jwks');

        return self::SUCCESS;
    }
}
