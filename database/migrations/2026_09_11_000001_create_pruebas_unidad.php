<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 8 · LA PRUEBA DE UNIDAD — el «unit test» de Khan.
 *
 * Una fila por prueba ENTREGADA por un alumno: diez ítems de una unidad y una
 * lengua, contestados de corrido y corregidos al final. Lo que la prueba
 * añade sobre la práctica es SOLO esta fila: cada una de sus diez respuestas
 * es un `practice_attempt` normal, con su dominio y su AGS, porque es el mismo
 * ítem y el mismo motor (`RegistroDeIntento`). Aquí queda la nota, el desglose
 * por descriptor y si aprobó (≥ 8 / 10), que es lo que marca la unidad como
 * «completada» en el mapa del curso.
 *
 * `user_id` es NOT NULL: el invitado hace la prueba entera y ve su nota, pero
 * NO deja fila — la regla de oro. `intento` numera las pruebas del mismo
 * alumno en la misma unidad: suspendida se repite sin límite, y la siguiente
 * lleva otra semilla.
 *
 * ADITIVA (tabla nueva) y sin defaults con significado: se despliega sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pruebas_unidad', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('lengua', 8);
            $table->unsignedTinyInteger('unidad');
            $table->unsignedSmallInteger('intento');

            $table->unsignedTinyInteger('nota');       // aciertos
            $table->unsignedTinyInteger('total');      // ítems servidos (10, o menos si el banco no da)
            $table->json('desglose');                  // {code: {aciertos, total}}
            $table->boolean('aprobada');

            $table->timestamp('completed_at');
            $table->timestamps();

            // Una prueba por (alumno, lengua, unidad, intento): la semilla de la
            // prueba sale de ahí, y dos entregas con el mismo intento son la
            // misma prueba mandada dos veces.
            $table->unique(['user_id', 'lengua', 'unidad', 'intento']);
            $table->index(['user_id', 'lengua', 'unidad', 'aprobada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pruebas_unidad');
    }
};
