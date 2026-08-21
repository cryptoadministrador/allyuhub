<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La firma docente deja de ser una etiqueta y pasa a ser una PUERTA.
 *
 * `attrs.revision.alineado_a = 'bloque'` decía «esto lo tiene que revisar
 * alguien» y no lo leía nadie: `practica:sembrar` publicaba las 80 preguntas
 * del banco a alumnos reales en el instante en que se ejecutaba. El precedente
 * correcto ya estaba en el repo —el crosswalk no se navega sin `reviewed_at`
 * (regla 5 de CLAUDE.md)— y aquí se aplica con el mismo vocabulario.
 *
 * RETROCOMPATIBILIDAD EXPLÍCITA. Los 17 ítems de física que ya existen no
 * llevan marca de revisión: fueron escritos a mano contra las 8 destrezas
 * verificadas, y cerrar la puerta sin más los haría desaparecer EN SILENCIO —
 * peor que no poner puerta. Por eso todo lo que existe al migrar queda firmado.
 *
 * `reviewed_by` es `foreignId` y no `foreignUuid`: `users.id` es un bigint, y
 * esa lección ya costó una migración de arreglo en `alignments` (donde 154
 * tests pasaban en SQLite mientras firmar era imposible en PostgreSQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('origen');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();
        });

        // Lo preexistente nace firmado. `reviewed_by` se queda en NULL: nadie
        // en concreto lo revisó, fue la migración — y falsear una autoría sería
        // peor que dejarla vacía.
        DB::table('practice_items')->update(['reviewed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
    }
};
