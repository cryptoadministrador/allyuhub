<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La navegación pregunta «¿este usuario es instructor de algún curso?» en
 * CADA página. La unique de lti_context_memberships es (lti_context_id,
 * user_id): su columna guía es el contexto, así que una consulta por
 * `user_id` no puede usarla. En PostgreSQL una FK tampoco crea índice sobre
 * la columna que referencia (a diferencia de MySQL) — sin esto, cada carga
 * de página hace un recorrido completo de la tabla en producción.
 *
 * ADITIVA: solo añade un índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lti_context_memberships', function (Blueprint $table) {
            $table->index(['user_id', 'role'], 'lti_memberships_user_role_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lti_context_memberships', function (Blueprint $table) {
            $table->dropIndex('lti_memberships_user_role_idx');
        });
    }
};
