<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Misión vista-docente (frente 1): persistir lo que el launch ya trae y
 * antes se tiraba — el curso de Moodle (claim context) y el rol (claim roles).
 *
 *  - lti_contexts: un curso de Moodle por Platform. El mapeo curso→track vive
 *    AQUÍ (track_id NULABLE: nace sin asignar y el docente lo corrige cuando
 *    quiera). Única por (platform_id, context_id).
 *  - lti_context_memberships: el rol es POR CONTEXTO, jamás en users — la
 *    misma persona es instructor de un curso y learner de otro.
 *
 * ADITIVA (producción viva) y con la lección del PR #11 aplicada: la FK a
 * users es foreignId (bigint), comparada contra su destino por el test de
 * tipos duales. Sin nada específico de PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lti_contexts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_id')->constrained('lti_platforms')->cascadeOnDelete();
            $table->string('context_id');            // el id del claim context de Moodle
            $table->string('title')->nullable();
            $table->string('label')->nullable();
            $table->foreignUuid('track_id')->nullable()->constrained('tracks')->nullOnDelete();
            $table->timestamps();
            $table->unique(['platform_id', 'context_id']);
        });

        Schema::create('lti_context_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lti_context_id')->constrained('lti_contexts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // bigint, como users.id
            $table->string('role');                  // instructor | learner — POR contexto
            $table->timestamp('last_launched_at')->nullable();
            $table->timestamps();
            $table->unique(['lti_context_id', 'user_id']);
            $table->index(['lti_context_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lti_context_memberships');
        Schema::dropIfExists('lti_contexts');
    }
};
