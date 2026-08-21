<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * La PROCEDENCIA de un recurso, espejo de `practice_items.origen`.
 *
 * La puerta de revisión estaba atada a `kind = 'reading'`, y esa regla era
 * cierta por una circunstancia, no por naturaleza: hoy lo único que se produce
 * a escala son lecciones. La Fase 2 son simuladores DECLARATIVOS generados por
 * un pipeline de IA —el propio esquema lo dice: «config jsonb, config
 * declarativa validada»—, así que el día que aterricen entrarían por `kind =
 * 'simulation'` y saldrían al alumno sin que nadie hubiera tocado la línea de
 * la puerta. Un agujero que se abre solo no es un agujero: es una trampa.
 *
 * Atado a la procedencia, la regla sobrevive al cambio de circunstancia: lo
 * GENERADO se firma, lo que un operador da de alta a mano no.
 *
 * `curado` por defecto y en el backfill porque eso es exactamente lo que hay
 * hoy en producción: los simuladores que alguien registró uno a uno. Las
 * lecciones nacen `generado` desde su sembrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            // Mismo vocabulario y mismo default que practice_items.origen: dos
            // columnas que significan lo mismo tienen que leerse igual.
            $table->string('origen')->default('curado')->after('kind');
        });

        // Explícito aunque el default ya lo cubra: una migración que depende de
        // que el default se aplique a las filas viejas es una migración que
        // funciona por casualidad del motor.
        DB::table('resources')->update(['origen' => 'curado']);
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
