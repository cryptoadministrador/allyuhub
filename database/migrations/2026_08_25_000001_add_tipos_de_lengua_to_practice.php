<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Los tipos de ejercicio de lengua: hueco, orden, pares y dictado.
 *
 * UNA columna `solucion` jsonb para los cuatro, con la forma validada por
 * `kind` en el guardián `saving` del modelo — no una columna por tipo, que es
 * cómo se pudre un esquema. La forma por tipo:
 *
 *   hueco/dictado: {lengua: 'fr', textos: ['où', …]}     (formas aceptadas)
 *   orden:         {secuencias: [['w3','w2','w1','w4'], …]}  (conjunto válido)
 *   pares:         {parejas: [['c1','p1','s1'], …]}          (tuplas por columna)
 *
 * `solucion` entra en `$hidden` junto a `answer_key`, `solution_expr` y
 * `transcripcion`: la disciplina de #22/#26 no se rediseña, se extiende. La
 * defensa de verdad sigue siendo la lista blanca de `next`.
 *
 * Simétrico en el intento: `respuesta` jsonb, con la invariante de que por
 * intento hay EXACTAMENTE una vía poblada según kind — numeric usa
 * answer/expected, choice/escucha usan answer_key, y los tipos de lengua usan
 * respuesta. Rellenar las otras con '' o 0.0 escondería un bug de bifurcación.
 *
 * Aditiva, nullable, sin defaults con significado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->jsonb('solucion')->nullable()->after('answer_key');
        });

        Schema::table('practice_attempts', function (Blueprint $table) {
            $table->jsonb('respuesta')->nullable()->after('answer_key');
        });
    }

    public function down(): void
    {
        Schema::table('practice_items', function (Blueprint $table) {
            $table->dropColumn('solucion');
        });
        Schema::table('practice_attempts', function (Blueprint $table) {
            $table->dropColumn('respuesta');
        });
    }
};
