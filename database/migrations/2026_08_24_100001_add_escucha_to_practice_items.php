<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * El tipo de ítem `escucha`: se oye un clip y se elige por clave, como choice.
 *
 * Dos columnas, y la segunda con la misma disciplina que `answer_key`:
 *
 *  - `audio_src`: la ruta del clip en el almacén (`/audio/<hash>.<ext>`). SÍ
 *    viaja al cliente en `next` — sin ella no hay nada que oír.
 *  - `transcripcion`: lo que DICE el clip, en su idioma. Columna propia y
 *    NUNCA serializada antes de responder, exactamente como la clave correcta:
 *    si el alumno la lee antes, el ejercicio de escucha no existe. Se revela
 *    en el veredicto, porque después de responder es pedagogía y no un secreto.
 *
 * Texto plano y no jsonb multilingüe a propósito: una transcripción está por
 * definición en el idioma del clip. Un mapa de idiomas aquí invitaría a meter
 * la traducción, y la traducción es la RESPUESTA de casi cualquier ítem de
 * escucha de A1.
 *
 * Aditiva: nullable, sin defaults con significado. Un ítem numeric o choice
 * lleva NULL en las dos, y la invariante «cada kind puebla lo suyo» la vigilan
 * los tests del motor, no un default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->string('audio_src')->nullable()->after('shuffle');
            $table->text('transcripcion')->nullable()->after('audio_src');
        });
    }

    public function down(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->dropColumn(['audio_src', 'transcripcion']);
        });
    }
};
