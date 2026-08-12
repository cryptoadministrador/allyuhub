# AllyuHub — guía para trabajar en este repo con IA

Plataforma educativa tipo Khan Academy para Ecuador: educación ordinaria (1.º EGB → 3.º BGU)
y PCEI/EPJA (escolaridad inconclusa), con simuladores propios y integración Moodle vía LTI 1.3.

**El plan maestro completo vive en el proyecto Claude "e-skool"**:
`claude/allyuhub-plan-maestro.md` (estrategia, PCEI, capa de IA) y
`claude/arquitectura-plataforma-recursos-interactivos.md` (detalle técnico v1).
Léelos antes de tomar decisiones de arquitectura.

## Stack

- Laravel 13 · PHP 8.4 · PostgreSQL 16 (extensiones: ltree, pg_trgm) · SQLite en tests
- API en `routes/api.php` (prefijo `/api/v1`): solo lectura, salvo los intentos del
  motor de práctica (`POST practice/items/{item}/attempts`, verificados en servidor)
- Frontend: pendiente (Inertia + React según el plan)
- Simuladores: viven FUERA de este repo (monorepo Vite/TS aparte, ver plan §4 de v1);
  aquí solo se registran como `resources` con `bundle_url` al CDN

## Arranque

```bash
composer install
cp .env.example .env && php artisan key:generate
# .env: DB_CONNECTION=pgsql, DB_DATABASE=allyuhub (usuario con CREATEDB)
# En la BD: CREATE EXTENSION ltree; CREATE EXTENSION pg_trgm;
php artisan migrate --seed        # siembra 13 grados, 1010 destrezas, 5 tracks, 2 sims
php artisan serve                 # API en /api/v1/...
php artisan test                  # SQLite en memoria, no toca tu BD
```

## Modelo mental (no lo violes)

1. **El grafo es la verdad.** `frameworks → framework_versions → cur_nodes (recursivo, ltree)
   → learning_objectives`. Un "grado" y una "asignatura" son nodos, no tablas.
   La Capa 1 se IMPORTA y no se edita a mano ni por API.
2. **La clave de un código curricular es (framework, version, código)** — nunca el código solo.
   Cambridge recicla códigos entre programas y en el tiempo.
3. **Un track es un recorrido, no una copia.** ORD y PCEI-* comparten los mismos
   `learning_objectives`; `track_phase_objectives` define qué cubre cada fase y con qué peso.
   Toda fase PCEI tiene la fase 0 propedéutica (`is_propedeutic`) — es obligatoria por la
   reforma 2025-00010-A.
4. **Un recurso se alinea, no se duplica.** `resource_objectives` es la tabla que hace que
   un simulador sirva a ordinaria + PCEI + Cambridge/IB a la vez.
5. **El crosswalk (alignments) solo entra a producción revisado**: `reviewed_at NOT NULL`
   y `confidence >= 0.8` (scope `Alignment::production()`). La IA propone, el docente dispone.
6. **Compatibilidad dual pgsql/sqlite en migraciones y scopes.** Lo específico de PostgreSQL
   (ltree, GIN, tsvector) va detrás de `DriverName === 'pgsql'`. Los tests corren en SQLite.

## Convenciones

- Modelos: UUIDs (`HasUuids`), `$guarded = []`, casts jsonb → array. Textos multilingües
  como `{"es": "...", "en": "..."}` — accede con `title['es']`.
- Comentarios y strings de dominio en español; código (clases, métodos) en inglés.
- Cada cambio de esquema = migración nueva; NUNCA editar migraciones ya ejecutadas.
- Tests de API en `tests/Feature/` con datos mínimos por test (no el seeder completo).
- Antes de dar por terminada una tarea: `php artisan test` en verde.

## Reglas duras pendientes de validación (¡no las conviertas en constraints!)

Las edades PCEI (15/18), rezago ≥3 años y módulos de 100/200 días vienen de fuentes
oficiales secundarias. Hasta validar contra el texto íntegro de los Acuerdos
MINEDUC-2024-00046-A y 2025-00010-A, son columnas informativas, no bloqueos de matrícula.

## Roadmap inmediato (en orden)

1. ~~Importador MINEDEC~~ **HECHO**: `php artisan mineduc:import <pdf> --official`
   (parser de códigos + replicación por subnivel + trazabilidad sha256 + tests).
   **Falta**: descargar los PDF oficiales (lista en `storage/curriculo/README.md`)
   y ejecutarlo por área — este entorno no puede descargarlos solo.
2. **Importador PCEI**: dosificación real del Acuerdo 2017-00040-A (PDF EPJA_Completo)
   → reemplazar el mapeo-interno de `track_phase_objectives`.
3. ~~Marcos Cambridge e IB~~ **HECHO (semilla)**: `InternationalFrameworksSeeder`
   (CAIE-LSEC, CAIE-IGCSE, CAIE-ASA, IB-MYP, IB-DP desde
   `database/data/marcos-internacionales.json`) + `CrosswalkSeeder` (11 conceptos,
   ~50 aristas STEM ancladas en las 8 destrezas MINEDEC verificadas, todas fuera de
   producción hasta que un docente las revise).
   **Falta**: enunciados cotejados contra los framework/syllabus oficiales
   (hoy son paráfrasis, `is_verified=false`), Lengua/Sociales, y el flujo de revisión
   docente que ponga `reviewed_by`/`reviewed_at`.
4. ~~Motor de práctica~~ **HECHO (núcleo)**: `practice_items`/`practice_attempts`,
   semilla determinista sha256(item:user:intento), verificación en servidor con
   tolerancia (`App\Services\Practice\*`), endpoints next/attempts y 5 ítems de
   plano inclinado sembrados. **Falta**: mastery learning, selección adaptativa,
   más ítems, y sustituir el `user_id` provisional del payload cuando llegue LTI 1.3.
5. **LTI 1.3** con `packbackbooks/lti-1p3-tool` (plan v1 §5): OIDC + Deep Linking + AGS.

## Qué NO hacer

- No añadir un chat-tutor IA antes de que el motor de práctica exista e instrumente datos.
- No editar el grafo por API ni por Tinker en producción: todo entra por importadores.
- No usar el email como identidad de usuario LTI (usa `lti_iss` + `lti_sub` de `users`).
- No usar matter.js en los simuladores (abandonado): integrador ODE propio o Rapier
  `-deterministic`. Nunca `Math.random()` en simuladores.
