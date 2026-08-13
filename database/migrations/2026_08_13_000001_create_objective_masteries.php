<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mastery learning (motor de práctica v2, plan v2 §Frente 1).
 *
 * Una fila por (alumno, destreza) con el estado de dominio:
 *  - `mastery` 0..1 por media móvil exponencial (α/β en MasteryTracker).
 *  - `streak` FIRMADA: >0 aciertos seguidos, <0 fallos seguidos — así el selector
 *    adaptativo detecta "2 fallos seguidos" sin una columna extra.
 *  - `mastered_at` es un HITO: se sella la primera vez que racha ≥ 3 con
 *    mastery ≥ 0.8 y no se borra aunque el mastery baje después.
 *
 * Sin nada específico de PostgreSQL: compatible pgsql/sqlite tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objective_masteries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('objective_id')->constrained('learning_objectives')->cascadeOnDelete();
            $table->decimal('mastery', 6, 5)->default(0);     // 0..1, redondeado a 5 decimales
            $table->unsignedInteger('attempts_count')->default(0);
            $table->integer('streak')->default(0);            // firmada (ver cabecera)
            $table->timestamp('mastered_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'objective_id']);
            $table->index('objective_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objective_masteries');
    }
};
