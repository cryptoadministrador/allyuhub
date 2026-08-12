<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trayectos (ordinaria + PCEI/EPJA) y catálogo de recursos.
 *
 * Un TRACK es una forma de recorrer el mismo grafo de objetivos:
 *  - ORD:        educación ordinaria, 13 grados (1.º EGB → 3.º BGU)
 *  - PCEI-*:     fases intensivas EPJA (Acuerdos 2024-00046-A, reforma 2025-00010-A,
 *                currículo dosificado 2017-00040-A)
 * Un RECURSO se crea una vez y se alinea con N objetivos → sirve a ambos trayectos
 * y a los tres marcos (MINEDEC/Cambridge/IB) sin duplicarse.
 *
 * OJO: las reglas duras (edades 15/18, rezago ≥3 años, módulos 100/200 días) vienen
 * de fuentes oficiales secundarias; validar contra el texto íntegro de los acuerdos
 * antes de usarlas para bloquear matrículas. Por eso son columnas, no constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();        // ORD | PCEI-ALFA | PCEI-POST | PCEI-BSI | PCEI-BI | PCEI-VIRTUAL
            $table->jsonb('label');
            $table->string('modality')->nullable();  // presencial|semipresencial|distancia-virtual|distancia-asistida
            $table->integer('min_age')->nullable();          // 15 | 18 — referencial, no bloqueante
            $table->integer('min_gap_years')->nullable();    // rezago ≥3 — referencial
            $table->integer('module_days')->nullable();      // 100 intensivo | 200 no intensivo
            $table->jsonb('attrs')->default('{}');
            $table->timestamps();
        });

        Schema::create('track_phases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('track_id')->constrained()->cascadeOnDelete();
            $table->integer('seq');
            $table->jsonb('label');                  // 'Módulo 2 · Básica Superior Intensiva'
            $table->decimal('duration_months', 4, 1)->nullable();
            $table->boolean('is_propedeutic')->default(false);  // fase obligatoria (reforma 2025-00010-A)
            // En ORD cada "fase" ES un grado: se enlaza al nodo del grafo.
            $table->foreignUuid('grade_node_id')->nullable()->constrained('cur_nodes')->nullOnDelete();
            $table->jsonb('attrs')->default('{}');
            $table->timestamps();
            $table->unique(['track_id', 'seq']);
        });

        // Qué objetivos cubre cada fase, con la dosificación PCEI.
        Schema::create('track_phase_objectives', function (Blueprint $table) {
            $table->foreignUuid('phase_id')->constrained('track_phases')->cascadeOnDelete();
            $table->foreignUuid('objective_id')->constrained('learning_objectives')->cascadeOnDelete();
            $table->decimal('weight', 4, 2)->default(1);
            $table->string('source')->default('mapeo-interno');  // 'ACUERDO-2017-00040-A' | …
            $table->primary(['phase_id', 'objective_id']);
        });

        // ---------- Catálogo de recursos ----------

        Schema::create('resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('kind');                  // simulation|lab|video|reading|practice_set|project
            $table->jsonb('title');
            $table->jsonb('summary')->nullable();
            $table->string('band')->nullable();
            $table->integer('duration_min')->nullable();
            $table->jsonb('a11y')->default('{}');    // {wcag:'2.2AA', keyboard:true, screenreader:true}
            $table->string('status')->default('draft');   // draft|in_review|published|deprecated
            $table->string('license')->nullable();
            $table->foreignUuid('current_version_id')->nullable();
            $table->timestamps();
            $table->index(['kind', 'status']);
        });

        Schema::create('resource_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('resource_id')->constrained()->cascadeOnDelete();
            $table->string('semver');
            $table->string('bundle_url')->nullable();    // CDN inmutable /sims/{id}/{semver}/
            $table->jsonb('config')->default('{}');      // config declarativa validada (pipeline IA)
            $table->string('integrity')->nullable();     // SRI
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['resource_id', 'semver']);
        });

        // El enganche recurso ↔ grafo. La tabla que evita triplicar la biblioteca.
        Schema::create('resource_objectives', function (Blueprint $table) {
            $table->foreignUuid('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('objective_id')->constrained('learning_objectives')->cascadeOnDelete();
            $table->string('role')->default('primary');  // primary|supporting|assessed
            $table->primary(['resource_id', 'objective_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_objectives');
        Schema::dropIfExists('resource_versions');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('track_phase_objectives');
        Schema::dropIfExists('track_phases');
        Schema::dropIfExists('tracks');
    }
};
