<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * LA LENGUA ES DEL CONTENIDO, NO DEL MARCO.
 *
 * `A1.IO.1` («puedo presentarme…») es el MISMO descriptor MCER en italiano y
 * en alemán — es lo que hace que los cuatro cursos sean el mismo curso. Lo que
 * distingue un ítem italiano de uno alemán colgados del mismo descriptor es
 * esta columna, y se pide al servir (`?lengua=`, lista cerrada en
 * `Practice\Lenguas::LISTA`).
 *
 * La regla de servicio es CERRADA: sin `lengua` en la petición solo se sirve
 * contenido SIN lengua (todo MINEDEC, p. ej.). El contenido de un curso de
 * idioma se pide siempre declarando el idioma — así un alumno de italiano no
 * puede recibir un ítem alemán ni por descuido del cliente.
 *
 * NULL no es un default permisivo: significa «contenido sin lengua», que es
 * exactamente lo que son las 4.717 destrezas de MINEDEC, y con la regla de
 * arriba lo nulo y lo lingüístico no se mezclan en ninguna dirección.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->string('lengua', 8)->nullable()->after('kind');
        });
        Schema::table('resources', function (Blueprint $table) {
            $table->string('lengua', 8)->nullable()->after('origen');
        });
    }

    public function down(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->dropColumn('lengua');
        });
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('lengua');
        });
    }
};
