<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 3 · LO QUE EL ALUMNO PRODUCE — escritura y voz de un menor.
 *
 * Esta tabla guarda contenido de MENORES, así que cada columna que gobierna su
 * VISIBILIDAD, su CORRECCIÓN o su RETENCIÓN falla cerrada, y ninguna abre nada
 * por omisión:
 *
 *  - Quién la ve NO vive aquí: es el alumno que la hizo y los docentes de su
 *    curso (membership instructor de un contexto donde el alumno es learner).
 *    La visibilidad es una CONSULTA (ProduccionPolicy), no una columna que
 *    alguien pueda dejar mal puesta.
 *  - `estado` nace 'pendiente' (sin corregir): el estado seguro. Solo el
 *    docente lo mueve a 'corregida', y solo mientras está 'pendiente' puede el
 *    alumno borrar la suya.
 *  - `anio_lectivo` es NOT NULL y se fija al crear: una producción sin año se
 *    escaparía de la purga y viviría para siempre — eso sería fallar ABIERTA.
 *    `producciones:purgar` borra la GRABACIÓN (texto/archivo) de los años ya
 *    cerrados; la nota del docente (`rubrica`, `comentario`) SOBREVIVE, y
 *    `purgada_en` marca cuándo se hizo.
 *  - El fichero de voz NUNCA entra en el almacén público de audio: su ruta
 *    (`archivo`) apunta a storage/app/producciones y solo se sirve por una
 *    ruta con `auth` + policy. Aquí no hay nada content-addressed.
 *
 * ADITIVA y sin defaults con significado sobre filas existentes (tabla nueva):
 * se despliega sola al mergear. FK a users por `foreignId` (bigint), como el
 * resto (lección del PR #11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producciones', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // El ALUMNO que produjo. Siempre la sesión, jamás un id del request.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Contra qué descriptor productivo (EE→escritura, PO→voz) y en qué
            // unidad del curso — la unidad elige la rúbrica.
            $table->foreignUuid('objective_id')->constrained('learning_objectives')->cascadeOnDelete();
            $table->unsignedTinyInteger('unidad');

            // La lengua del curso, de lista cerrada (misma que el resto).
            $table->string('lengua', 8);

            // 'escritura' | 'voz'. Decide qué columna de contenido se puebla.
            $table->string('tipo');

            // Exactamente UNA poblada según `tipo` (guardián en el modelo), y
            // las DOS nulas tras la purga.
            $table->text('texto')->nullable();
            $table->string('archivo')->nullable();   // ruta en storage/app/producciones

            // La cita con la purga. NOT NULL: sin año no hay retención.
            $table->string('anio_lectivo', 9);        // 'YYYY-YYYY'

            // El estado seguro es 'pendiente'. Es un default sobre una tabla
            // NUEVA (no reescribe filas de producción), y es el que falla
            // cerrada: sin corregir, el alumno puede borrarla y sale en la cola.
            $table->string('estado')->default('pendiente');   // pendiente | corregida

            // La corrección del docente. SOBREVIVE a la purga de la grabación.
            $table->json('rubrica')->nullable();      // {criterio: nivel 0..2}
            $table->text('comentario')->nullable();
            $table->foreignId('corregida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corregida_en')->nullable();

            // Cuándo se borró la grabación (la nota siguió viva).
            $table->timestamp('purgada_en')->nullable();

            $table->timestamps();

            // La cola del docente y la purga son las dos consultas calientes.
            $table->index(['estado', 'user_id']);
            $table->index('anio_lectivo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producciones');
    }
};
