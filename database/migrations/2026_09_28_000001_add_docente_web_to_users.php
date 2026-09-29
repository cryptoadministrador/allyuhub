<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 20 · DOCENTE POR WEB, sin Moodle.
 *
 * Hasta aquí «docente» solo existía POR CONTEXTO LTI (membership `instructor`),
 * y sin un Moodle conectado nadie podía revisar ni firmar contenido. Esta marca
 * es la segunda puerta: una cuenta creada A MANO por consola
 * (`php artisan docente:web`) que entra con correo y contraseña.
 *
 * Aditiva y con default false: ningún usuario existente —todos LTI— la gana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('docente_web')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('docente_web');
        });
    }
};
