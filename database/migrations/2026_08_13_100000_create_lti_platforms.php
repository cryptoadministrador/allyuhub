<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LTI 1.3 (roadmap §5): registro de Platforms (Moodle) autorizadas a lanzar
 * la Tool. Una fila por (issuer, client_id); los deployment_ids permitidos
 * van en jsonb porque Moodle crea uno por curso/actividad y no son clave.
 *
 * La protección anti-replay de nonces NO lleva tabla: vive en la cache de
 * Laravel (App\Services\Lti\LtiCache) — en producción exige un driver
 * compartido (database/redis), documentado en docs/lti-moodle.md.
 *
 * Las columnas de identidad del alumno (users.lti_iss + users.lti_sub, con
 * unique compuesto) existen desde la migración inicial de users: aquí no se
 * tocan (regla: jamás editar migraciones ejecutadas).
 *
 * Sin nada específico de PostgreSQL: compatible pgsql/sqlite tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lti_platforms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('issuer');                       // https://moodle.colegio.edu.ec
            $table->string('client_id');
            $table->string('auth_login_url');               // …/mod/lti/auth.php
            $table->string('auth_token_url');               // …/mod/lti/token.php
            $table->string('jwks_url');                     // …/mod/lti/certs.php
            $table->jsonb('deployment_ids')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['issuer', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lti_platforms');
    }
};
