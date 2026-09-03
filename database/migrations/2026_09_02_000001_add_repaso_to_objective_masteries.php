<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * El calendario de REPASO ESPACIADO, por alumno y por descriptor.
 *
 * Vive en `objective_masteries` —ya hay una fila por (alumno, descriptor)— y no
 * en una tabla nueva: el repaso es un atributo del dominio, no otra entidad.
 * Dos columnas, las dos NULLABLE y sin default con significado (se despliegan
 * solas al mergear):
 *
 *  - `repaso_intervalo`: días hasta el próximo repaso. Crece ×2 con el acierto
 *    (1,2,4,8,16,32) y vuelve a 1 con el fallo. NULL = aún no programado.
 *  - `repaso_en`: cuándo toca. NULL = fuera de la cola. La cola son las filas
 *    con `repaso_en <= now` cuyo descriptor tiene ≥2 ítems firmados de la
 *    lengua (para poder servir OTRO ítem, no el mismo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('objective_masteries', function (Blueprint $table) {
            $table->unsignedSmallInteger('repaso_intervalo')->nullable()->after('streak');
            $table->timestamp('repaso_en')->nullable()->after('repaso_intervalo');
            $table->index('repaso_en');
        });
    }

    public function down(): void
    {
        Schema::table('objective_masteries', function (Blueprint $table) {
            $table->dropIndex(['repaso_en']);
            $table->dropColumn(['repaso_intervalo', 'repaso_en']);
        });
    }
};
