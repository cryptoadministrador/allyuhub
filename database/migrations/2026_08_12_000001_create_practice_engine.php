<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de práctica parametrizada (plan v2 §Frente 1, roadmap ítem 4).
 *
 *  - practice_items: plantilla de ítem anclada a un learning_objective.
 *    El enunciado lleva variables ({m}, {theta}…), `params` define sus rangos
 *    (min/max/step) o constantes ({"const": 9.8}), y `solution_expr` es la
 *    expresión que evalúa App\Services\Practice\MathExpression (lista blanca,
 *    nunca eval()).
 *  - practice_attempts: cada intento con su semilla sha256(item:user:intento),
 *    los parámetros instanciados (auditoría/reproducibilidad) y el veredicto
 *    calculado SIEMPRE en el servidor.
 *
 * Sin nada específico de PostgreSQL: compatible pgsql/sqlite tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('objective_id')->constrained('learning_objectives')->cascadeOnDelete();
            $table->jsonb('statement');                       // {"es": "Un bloque de {m} kg…"}
            $table->jsonb('params');                          // {"m":{"min":1,"max":20,"step":0.5},"g":{"const":9.8}}
            $table->string('solution_expr');                  // 'm * g * sin(deg2rad(theta))'
            $table->double('tolerance')->default(0.02);
            $table->string('tolerance_kind')->default('rel'); // rel | abs
            $table->string('answer_unit')->nullable();        // 'N' | '°' | 'm/s²' — informativo
            $table->integer('seq')->default(0);               // orden estable dentro del objetivo
            $table->jsonb('attrs')->default('{}');
            $table->timestamps();
            $table->index(['objective_id', 'seq']);
        });

        Schema::create('practice_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')->constrained('practice_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');            // n.º de intento del alumno en este ítem
            $table->char('seed', 64);                         // sha256(item:user:intento)
            $table->jsonb('params');                          // valores instanciados que vio el alumno
            $table->double('answer');
            $table->double('expected');                       // calculado en servidor, jamás por el cliente
            $table->boolean('is_correct');
            $table->unsignedInteger('time_ms')->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'user_id', 'attempt_no']);
            $table->index(['user_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_attempts');
        Schema::dropIfExists('practice_items');
    }
};
