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
 * El backfill pone `curado` porque eso es exactamente lo que hay hoy en
 * producción: los simuladores que alguien registró uno a uno. El DEFAULT, en
 * cambio, es `generado` — lo contrario, y a propósito: ver abajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            // El default CIERRA. `practice_items.origen` puede permitirse nacer
            // en `curado` porque allí nadie lo lee para decidir qué se ve; aquí
            // gobierna `scopePublished()`. Heredar el default permisivo de una
            // columna descriptiva a otra que es una PUERTA es la consistencia
            // equivocada: una puerta falla cerrada. Quien siembre sin declarar
            // procedencia se queda sin publicar —molesto— en vez de con
            // contenido sin firmar delante de un alumno.
            $table->string('origen')->default('generado')->after('kind');
        });

        // Explícito aunque parezca redundante: las filas que ya existen son las
        // que registró un operador a mano, y esas son `curado`. Una migración
        // que dependa del default para las filas viejas funciona por casualidad
        // del motor — y aquí, además, diría lo contrario de lo que queremos.
        DB::table('resources')->update(['origen' => 'curado']);
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
