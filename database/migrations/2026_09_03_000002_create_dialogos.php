<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 4 · EL INTERLOCUTOR — un diálogo GUIONIZADO por unidad (no un LLM).
 *
 * Un `dialogo` es contenido: un grafo de NODOS (lo que dice el interlocutor + 2-3
 * respuestas del alumno, cada una con el nodo al que lleva) escrito en el banco
 * y firmado por un docente. Sin modelo de lenguaje: sin API key en producción,
 * sin datos de un menor en un tercero, y sin respuestas fuera de nivel — el
 * guion no puede decir una palabra que no esté escrita.
 *
 * Dos tablas, las dos ADITIVAS y sin defaults con significado:
 *
 *  - `dialogos`: el guion. Nace SIN firmar (`reviewed_at` nulo) — como toda vía
 *    nueva, no se sirve hasta que un docente que sabe la lengua lo firma.
 *  - `dialogo_completions`: que un alumno LO COMPLETÓ, una vez (único por
 *    diálogo+alumno). El interlocutor NO evalúa: solo registra que se hizo, y de
 *    ahí el dominio del descriptor de interacción (A1.IO.1) sube UNA vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dialogos', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // El descriptor de interacción que ejercita (A1.IO.1) y la unidad
            // del curso a la que pertenece.
            $table->foreignUuid('objective_id')->constrained('learning_objectives')->cascadeOnDelete();
            $table->unsignedTinyInteger('unidad');

            $table->string('lengua', 8);
            $table->string('slug');
            $table->string('titulo');

            // El grafo: [{id, dice, audio?, respuestas:[{texto, va, pista?}], fin?}].
            $table->json('nodos');

            // La firma. NULL = no se sirve. Es la puerta, como en lecciones e ítems.
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['objective_id', 'lengua', 'slug']);
            $table->index(['lengua', 'unidad']);
        });

        Schema::create('dialogo_completions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dialogo_id')->constrained('dialogos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            // Una sola vez por alumno: completar dos veces no infla el dominio.
            $table->unique(['dialogo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dialogo_completions');
        Schema::dropIfExists('dialogos');
    }
};
