<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 5 · EL RASTRO DE LA REVISIÓN DOCENTE.
 *
 * Hasta ahora firmar era `practica:firmar` por SSH: sin autoría (todo decía
 * «sin autoría registrada»), sin nota y sin vuelta atrás. Esta tabla es el
 * cuaderno de lo que un docente hace con una pieza de contenido:
 *
 *   firmar    → la pieza sale al alumno. Nota opcional.
 *   devolver  → la pieza NO sale; queda una nota para quien la corrija. OBLIGATORIA.
 *   desfirmar → la pieza se retira de pantalla. Nota OBLIGATORIA.
 *
 * **Des-firmar sin rastro está prohibido**: retirar contenido que ya vieron
 * alumnos es justo lo que hay que poder auditar después. Por eso el rastro es
 * una tabla y no una columna que se sobrescribe.
 *
 * UNA VÍA POBLADA, como en `practice_attempts` y `producciones`: una revisión
 * es de un ÍTEM o de una VERSIÓN DE LECCIÓN, nunca de las dos ni de ninguna. Se
 * apunta a `resource_versions` y no a `resources` porque ahí es donde vive la
 * firma de una lección (`Resource::published()` mira la versión vigente).
 *
 * ADITIVA (tabla nueva) y sin defaults con significado: se despliega sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisiones', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Exactamente una de las dos (guardián `saving` en el modelo).
            $table->foreignUuid('practice_item_id')->nullable()
                ->constrained('practice_items')->cascadeOnDelete();
            $table->foreignUuid('resource_version_id')->nullable()
                ->constrained('resource_versions')->cascadeOnDelete();

            // QUIÉN. Ya no «sin autoría registrada»: la pantalla exige sesión.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('accion');            // firmar | devolver | desfirmar
            $table->text('nota')->nullable();    // obligatoria en devolver y desfirmar

            $table->timestamps();

            // La consulta caliente: la última nota de una pieza devuelta.
            $table->index(['practice_item_id', 'created_at']);
            $table->index(['resource_version_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisiones');
    }
};
