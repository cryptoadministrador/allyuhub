<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segundo tipo de ítem: OPCIÓN MÚLTIPLE.
 *
 * El motor solo sabía corregir números, así que Lengua y Literatura y Estudios
 * Sociales —la mitad del currículo importado— no podían tener práctica.
 *
 * TODO ES ADITIVO. Los 17 ítems del banco viejo no se tocan: `kind` nace con
 * default 'numeric', así que siguen siendo exactamente lo que eran.
 *
 * LA CLAVE CORRECTA VA EN SU PROPIA COLUMNA, no en `params` ni en `attrs`.
 * `params` se serializa entero al cliente en `next()`, así que guardar ahí la
 * respuesta sería publicarla. `answer_key` no se serializa NUNCA: el payload de
 * `next` se arma por lista blanca explícita, campo a campo.
 *
 * Las tres columnas que pasan a nullable NO son una migración destructiva:
 * ensanchar NOT NULL → NULL nunca pierde un dato. Y es preferible a rellenar
 * con valores falsos: un `solution_expr` vacío o un `expected` = 0.0 en un ítem
 * de opción múltiple harían que un bug en la bifurcación por `kind` pasara
 * inadvertido en vez de reventar donde se ve. La invariante que sustituye al
 * NOT NULL: por intento, exactamente una vía de respuesta poblada según `kind`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            // numeric | choice. Con default para que lo ya sembrado no cambie.
            $table->string('kind')->default('numeric')->after('objective_id');
            // [{"key": "a", "text": {"es": "…"}}] — SIN marca de correcta.
            // Texto multilingüe, igual que `statement`.
            $table->jsonb('options')->nullable()->after('params');
            // La clave correcta. Jamás sale de la base hacia el cliente.
            $table->string('answer_key')->nullable()->after('options');
            // El orden de PINTADO se baraja por semilla; a false cuando las
            // opciones tienen orden intrínseco (una cronología, una escala).
            $table->boolean('shuffle')->default(true)->after('answer_key');
            // curado (escrito y revisable a mano) | generado.
            $table->string('origen')->default('curado')->after('shuffle');
            // Un ítem de opción múltiple no tiene expresión que evaluar.
            $table->string('solution_expr')->nullable()->change();
        });

        Schema::table('practice_attempts', function (Blueprint $table) {
            // La clave elegida. Es la MISMA clave inmutable del ítem: por eso
            // un barajado distinto entre servir y corregir no puede calificar
            // mal — el orden no entra en la comparación.
            $table->string('answer_key')->nullable()->after('answer');
            // Un intento de choice no tiene respuesta ni esperado numéricos.
            $table->double('answer')->nullable()->change();
            $table->double('expected')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('practice_attempts', function (Blueprint $table) {
            $table->dropColumn('answer_key');
        });

        Schema::table('practice_items', function (Blueprint $table) {
            $table->dropColumn(['kind', 'options', 'answer_key', 'shuffle', 'origen']);
        });
        // Las columnas ensanchadas se quedan nullable: revertirlas exigiría
        // inventar valores para los intentos de choice que ya existan.
    }
};
