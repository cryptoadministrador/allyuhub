<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CAPA 1 — El grafo curricular (inmutable, versionado, importado).
 *
 * Decisiones (ver claude/allyuhub-plan-maestro.md en el proyecto):
 *  - Un solo nodo recursivo `cur_nodes` con `node_type` abierto y `path` materializado,
 *    porque MINEDEC, Cambridge (framework Y syllabus: dos jerarquías distintas) e IB
 *    son incompatibles entre sí. Los atributos propios de cada marco van en JSONB.
 *  - La clave de un código curricular SIEMPRE es (framework, version, código):
 *    Cambridge recicla códigos entre programas (Primary 0067 vs IGCSE 0400) y en el tiempo.
 *  - `path` usa ltree en PostgreSQL (índice GiST) y texto plano en SQLite (tests rápidos).
 */
return new class extends Migration
{
    public function up(): void
    {
        $pg = DB::connection()->getDriverName() === 'pgsql';

        Schema::create('frameworks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();        // EC-MINEDEC | CAIE-PRIMARY | CAIE-IGCSE | IB-MYP …
            $table->string('authority');             // MINEDEC | Cambridge International | IBO
            $table->string('kind');                  // national | international | internal
            $table->char('country', 2)->nullable();
            $table->jsonb('label');                  // {"es": "...", "en": "..."}
            $table->timestamps();
        });

        Schema::create('framework_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('framework_id')->constrained()->cascadeOnDelete();
            $table->string('label');                 // '2016+2023', '2020', '2025-2027'
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_sha256')->nullable();  // trazabilidad al PDF oficial
            $table->timestamps();
            $table->unique(['framework_id', 'label']);
        });

        Schema::create('cur_nodes', function (Blueprint $table) use ($pg) {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('framework_versions')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();   // FK autorreferente: se añade tras crear la tabla
            $table->string('node_type');             // nivel|subnivel|grado|area|asignatura|bloque|stage|strand|…
            $table->string('native_code')->nullable();   // '0096', 'Nc', 'C1.1'
            $table->jsonb('title');
            $table->integer('seq')->default(0);
            $table->string('path', 1024);            // ej. 'egb.superior.g8.cn.bloque3' — ltree en pgsql
            $table->decimal('age_min', 3, 1)->nullable();
            $table->decimal('age_max', 3, 1)->nullable();
            $table->jsonb('attrs')->default('{}');   // carga horaria, checkpoint, tier C/E, criterio MYP…
            $table->timestamps();
            $table->unique(['version_id', 'path']);
            $table->index(['version_id', 'node_type']);
            $table->index(['parent_id', 'seq']);
        });

        Schema::table('cur_nodes', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('cur_nodes')->nullOnDelete();
        });

        Schema::create('learning_objectives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')->constrained('cur_nodes')->cascadeOnDelete();
            $table->foreignUuid('version_id')->constrained('framework_versions')->cascadeOnDelete();
            $table->string('native_code')->nullable();   // 'CN.F.5.1.12' | '1Nc.01' | 'E4.2'
            $table->jsonb('statement');              // {"es": "...", "en": "..."}
            $table->string('cognitive_level')->nullable();   // Bloom normalizado
            $table->boolean('is_essential')->nullable();     // MINEDEC: imprescindible vs deseable
            $table->boolean('spans_stages')->default(false); // Cambridge: el asterisco
            $table->boolean('is_verified')->default(false);  // redacción cotejada contra la fuente oficial
            $table->jsonb('attrs')->default('{}');
            $table->timestamps();
            $table->unique(['version_id', 'node_id', 'native_code']);
            $table->index('native_code');
        });

        // ---------- CAPA 2 — Crosswalk (la propiedad intelectual real) ----------

        Schema::create('concepts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();        // 'conservacion-energia-mecanica'
            $table->jsonb('label');
            $table->string('domain')->nullable();    // fisica | matematica | lengua …
            $table->string('band')->nullable();      // elemental | media | superior | bachillerato
            $table->timestamps();
        });

        Schema::create('alignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('learning_objectives')->cascadeOnDelete();
            $table->foreignUuid('target_id')->constrained('learning_objectives')->cascadeOnDelete();
            $table->foreignUuid('concept_id')->nullable()->constrained('concepts')->nullOnDelete();
            $table->string('relation');              // exact|narrower|broader|related|prerequisite
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('method')->default('manual');   // manual | llm-assisted | imported
            $table->foreignUuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'target_id', 'relation']);
            $table->index(['relation']);
        });

        // Optimizaciones solo-PostgreSQL: ltree + GIN + trigram + tsvector.
        if ($pg) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS ltree');
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('ALTER TABLE cur_nodes ALTER COLUMN path TYPE ltree USING path::ltree');
            DB::statement('CREATE INDEX cur_nodes_path_gist ON cur_nodes USING gist (path)');
            DB::statement('CREATE INDEX cur_nodes_attrs_gin ON cur_nodes USING gin (attrs jsonb_path_ops)');
            DB::statement("ALTER TABLE learning_objectives ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (to_tsvector('spanish', coalesce(statement->>'es','') || ' ' || coalesce(native_code,''))) STORED");
            DB::statement('CREATE INDEX lo_search_gin ON learning_objectives USING gin (search_vector)');
            DB::statement('CREATE INDEX lo_code_trgm ON learning_objectives USING gin (native_code gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alignments');
        Schema::dropIfExists('concepts');
        Schema::dropIfExists('learning_objectives');
        Schema::dropIfExists('cur_nodes');
        Schema::dropIfExists('framework_versions');
        Schema::dropIfExists('frameworks');
    }
};
