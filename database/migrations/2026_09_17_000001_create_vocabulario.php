<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 10 · EL VOCABULARIO — tarjetas por unidad y lengua.
 *
 * Material de apoyo, NO un tipo de ítem: no da dominio, no entra en la prueba
 * ni en el repaso diario. Una palabra por fila, con clave estable por
 * (lengua, unidad, clave) —así `vocabulario:sembrar` es idempotente— y la
 * firma como puerta, igual que ítems, lecciones y diálogos.
 *
 * Tres cambios, los tres ADITIVOS y sin defaults con significado:
 *
 *  - `vocabulario`: la tarjeta. `lectura` (pinyin) solo en chino; el `clip` es
 *    la CLAVE declarada en el banco y `audio` la ruta pública, que se rellena
 *    solo si el fichero existe (mismo trato que los diálogos).
 *  - `vocab_estado`: lo que el ALUMNO dice de cada tarjeta. `conocida_at` nulo
 *    = «todavía no»; con fecha = «la sé» (la última vez que lo dijo). Una fila
 *    por (alumno, tarjeta): decirlo dos veces no crea dos filas.
 *  - `revisiones.tarjeta_id`: la tercera vía del rastro de revisión, para que
 *    `/docente/revisar` firme tarjetas con la MISMA pieza de firma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocabulario', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('lengua', 8);
            $table->unsignedTinyInteger('unidad');
            $table->string('clave');
            // La posición dentro del mazo de su unidad: el orden del banco.
            $table->unsignedSmallInteger('orden');

            $table->string('palabra');
            $table->string('lectura')->nullable();   // pinyin con tonos: solo zh
            $table->string('significado');
            $table->json('ejemplo');                 // {<lengua>: "...", es: "..."}

            // El audio: la clave DECLARADA siempre; la ruta solo si el fichero está.
            $table->string('clip')->nullable();
            $table->string('audio')->nullable();

            // La firma. NULL = no se sirve.
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['lengua', 'unidad', 'clave']);
            $table->index(['lengua', 'unidad', 'orden']);
        });

        Schema::create('vocab_estado', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tarjeta_id')->constrained('vocabulario')->cascadeOnDelete();
            // Nulo = «todavía no»; con fecha = «la sé», y cuándo lo dijo.
            $table->timestamp('conocida_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tarjeta_id']);
        });

        Schema::table('revisiones', function (Blueprint $table) {
            $table->foreignUuid('tarjeta_id')->nullable()
                ->constrained('vocabulario')->cascadeOnDelete();
            $table->index(['tarjeta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('revisiones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tarjeta_id');
        });
        Schema::dropIfExists('vocab_estado');
        Schema::dropIfExists('vocabulario');
    }
};
