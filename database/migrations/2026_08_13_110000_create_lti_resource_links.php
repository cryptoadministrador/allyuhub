<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AGS (fase D LTI): el contexto de calificación de cada launch.
 *
 * Una fila por (platform, resource_link, alumno): guarda el claim AGS
 * completo (lineitem, lineitems, scope[]) tal como llegó en el id_token
 * validado, y la destreza interna si el content item era una destreza.
 * El job PushLtiScore lee de aquí — la sesión no sobrevive a la cola.
 *
 * Sin nada específico de PostgreSQL: compatible pgsql/sqlite tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lti_resource_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_id')->constrained('lti_platforms')->cascadeOnDelete();
            $table->string('resource_link_id');       // id del claim resource_link
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('objective_id')->nullable()
                ->constrained('learning_objectives')->nullOnDelete();
            $table->jsonb('ags')->default('{}');      // claim endpoint: lineitem, lineitems, scope[]
            $table->timestamp('last_launched_at')->nullable();
            $table->timestamps();
            $table->unique(['platform_id', 'resource_link_id', 'user_id']);
            $table->index(['user_id', 'objective_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lti_resource_links');
    }
};
