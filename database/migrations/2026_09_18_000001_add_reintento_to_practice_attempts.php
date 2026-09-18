<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 13 · EL BUCLE DE «OTRA VEZ»: cada reintento se guarda como su propia fila.
 *
 * `reintento` = qué vuelta del bucle fue este intento: NULL el primero (el que
 * cuenta para el dominio y la nota), 1 el segundo, 2 el tercero. Es verdad
 * histórica —el alumno respondió tres veces, y eso vale para el docente— pero
 * SOLO el primer intento alimenta `MasteryTracker` y AGS: un acierto a la
 * tercera es un acierto para el alumno y una mentira para el dominio.
 *
 * ADITIVA y nullable: las filas de antes son todas primeros intentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_attempts', function (Blueprint $table) {
            $table->unsignedTinyInteger('reintento')->nullable()->after('is_correct');
        });
    }

    public function down(): void
    {
        Schema::table('practice_attempts', function (Blueprint $table) {
            $table->dropColumn('reintento');
        });
    }
};
