<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `alignments.reviewed_by` nació como `foreignUuid` mientras `users.id` es un
 * `bigint` autoincremental (Laravel por defecto). En SQLite no hay tipado y
 * cualquier id colaba, así que los 154 tests pasaban; en PostgreSQL —producción
 * y el job pgsql del CI— firmar una alineación revienta con
 * «SQLSTATE[22P02] invalid input syntax for type uuid: "1"».
 *
 * Nadie lo había notado porque NADIE ESCRIBÍA esa columna: la rama del catálogo
 * es la primera que la usa (los tests que siembran una equivalencia revisada
 * para comprobar que la sección no está muerta). O sea: el mecanismo central de
 * la regla 5 de CLAUDE.md —«la IA propone, el docente dispone»— nunca se había
 * ejercido, y al ejercerlo se descubre que en la única base de datos que
 * importa no funciona.
 *
 * Peor aún: la vía anónima (`reviewed_at` con `reviewed_by = NULL`) SÍ funciona
 * en pgsql, así que sin este arreglo se puede «revisar» sin dejar constancia de
 * quién revisó, que es exactamente la trazabilidad que la regla pretende.
 *
 * Todas las filas existentes tienen `reviewed_by IS NULL` (no hay ninguna
 * alineación revisada todavía), así que recrear la columna no pierde datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alignments', function (Blueprint $table) {
            $table->dropColumn('reviewed_by');
        });

        Schema::table('alignments', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('method')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
        });

        Schema::table('alignments', function (Blueprint $table) {
            $table->foreignUuid('reviewed_by')->nullable()->after('method');
        });
    }
};
