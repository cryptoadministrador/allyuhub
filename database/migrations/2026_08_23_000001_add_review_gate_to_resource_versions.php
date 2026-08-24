<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La lección pasa por la MISMA puerta que los ítems de práctica.
 *
 * Una lección mal escrita hace exactamente el mismo daño que una pregunta mal
 * escrita —más, si cabe: la pregunta se falla y se corrige; el texto se cree—.
 * Así que el mismo vocabulario que en `practice_items` y en el crosswalk:
 * `reviewed_at` NULL significa «esto todavía no lo ha leído nadie».
 *
 * Va en `resource_versions` y no en `resources` porque lo que se revisa es el
 * CONTENIDO, y el contenido vive en la versión. Un recurso publicado cuya
 * versión vigente no esté firmada no se sirve: `Resource::scopePublished()`
 * exige las dos cosas.
 *
 * RETROCOMPATIBILIDAD EXPLÍCITA, la misma lección que costó una mutación
 * superviviente en el PR de la firma de ítems: todo lo que existe al migrar
 * queda firmado. Si no, el simulador sembrado desaparecería en silencio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_versions', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('published_at');
            // foreignId y no foreignUuid: `users.id` es un bigint. Esa lección
            // ya costó una migración de arreglo en `alignments`.
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('resource_versions')->update(['reviewed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('resource_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
    }
};
