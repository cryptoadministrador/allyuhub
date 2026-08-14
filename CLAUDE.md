# AllyuHub — guía para trabajar en este repo con IA

Plataforma educativa tipo Khan Academy para Ecuador: educación ordinaria (1.º EGB → 3.º BGU)
y PCEI/EPJA (escolaridad inconclusa), con simuladores propios y integración Moodle vía LTI 1.3.

**El plan maestro completo vive en el proyecto Claude "e-skool"**:
`claude/allyuhub-plan-maestro.md` (estrategia, PCEI, capa de IA) y
`claude/arquitectura-plataforma-recursos-interactivos.md` (detalle técnico v1).
Léelos antes de tomar decisiones de arquitectura.

## Stack

- Laravel 13 · PHP 8.4 · PostgreSQL 16 (extensiones: ltree, pg_trgm) · SQLite en tests
- API pública en `routes/api.php` (prefijo `/api/v1`): SOLO lectura del grafo y el
  catálogo. Los endpoints de práctica viven en `routes/web.php` bajo el mismo prefijo,
  con `auth` de sesión: el alumno es SIEMPRE `Auth::id()` — un `user_id` en el request
  es 422 (`prohibited`). La deuda del user_id de payload está CERRADA.
- Frontend: Inertia + React 19 (Vite 8 + Tailwind 4), páginas en **`resources/js/pages`**
  y layouts en `resources/js/layouts` — TODO EN MINÚSCULA: es el default de Inertia 3
  (`resource_path('js/pages')`). En Windows/macOS da igual porque el sistema de archivos
  no distingue mayúsculas, pero en Linux (CI y producción) `Pages` NO se encuentra y
  `assertInertia` falla. Rutas: `/practicar/{objective}`, `/recurso/{resource}`,
  `/progreso`, en español y aptas para el iframe de Moodle (CSP frame-ancestors
  construida con los issuers de las Platforms activas, normalizados con `parse_url`).
  Los tests NUNCA compilan el frontend: `withoutVite()` + `assertInertia`.
- **La práctica es ABIERTA por diseño**: un alumno puede practicar cualquier destreza
  con ítems, esté o no en su track (modelo Khan). No es un agujero — la nota siempre es
  suya y la verificación es en servidor — pero si el colegio quiere restringirla, el
  punto es `submitAttempt` contra `track_phase_objectives`.
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

## Reglas PCEI — VALIDADAS contra los Registros Oficiales (2026-08-12)

Cotejadas contra el RO Nº 9 (31-mar-2025, texto del Acuerdo 2025-00010-A que reforma al
2024-00046-A) y el RO 2.º Supl. Nº 121 (10-sep-2025, Acuerdo 2025-00034-A), vía
esacc.corteconstitucional.gob.ec:

- Edades mínimas: 15 años (alfabetización, post-alfabetización, básica superior
  intensiva) y 18 (bachillerato intensivo). ✔
- Rezago escolar: ≥3 años dentro o fuera del sistema (base Art. 231 RGLOEI). ✔
- Ciclos: intensivo = 100 días pedagógicos consecutivos por módulo/grado (hasta 2 por
  año); no intensivo = 200 días (1 por año). Distancia-virtual: 5 meses por grado. ✔
- Fase propedéutica OBLIGATORIA para quien ingresa por primera vez, ADICIONAL al ciclo:
  5 días hábiles en semipresencial, 10 en distancia (virtual o asistida). ✔
- Duraciones operativas (portal Juntos): ALFA 10 meses, POST 20, BSI 11, BI 15. ✔

Siguen siendo columnas informativas, no bloqueos de matrícula, pero ya se pueden usar
para validaciones suaves. OJO: la numeración de artículos citada arriba salió de lectura
automatizada de los RO; verificar contra el PDF antes de citarla en un documento formal.

**Cambio normativo clave (2025-08/09):** el Acuerdo **MINEDUC-2025-00034-A** deroga
íntegramente al 2017-00040-A (y a su reforma 2017-00067-A) y expide el currículo EPJA
actualizado por competencias, incluido el Currículo Integrado de Alfabetización y
Post-alfabetización **Priorizado**: 6 módulos (ALFA = módulos 1-2, POST = 3-6), 20
períodos semanales, 100 días por módulo. Además: 2025-00032-A reexpide la normativa de
modalidades y 2025-00031-A regula el Bachillerato Técnico EPJA (100 días/ciclo, 18 meses,
8 semanas de nivelación técnica).

## Roadmap inmediato (en orden)

1. ~~Importador MINEDEC~~ **HECHO**: `php artisan mineduc:import <pdf> --official`
   (parser de códigos + replicación por subnivel + trazabilidad sha256 + tests).
   **Falta**: descargar los PDF oficiales (lista en `storage/curriculo/README.md`)
   y ejecutarlo por área — este entorno no puede descargarlos solo.
2. ~~Importador PCEI (ALFA/POST)~~ **HECHO**: `php artisan epja:import <pdf> --official`
   importa el currículo priorizado del Acuerdo 2025-00034-A (263 destrezas con códigos
   PROPIOS: `A.RS.n`/`P.CC.n`/`CAI.JA.b.n` — ¡NO son los del 2016!) como marco `EC-EPJA`,
   y reancla las fases de PCEI-ALFA/POST (source `acuerdo-2025-00034-A` sustituye al
   `mapeo-interno`). Datos duros del documento (verificados contra el PDF completo):
   NO trae dosificación por módulos (destrezas por nivel, agrupadas en criterios CE),
   ni carga horaria, y su numeración tiene huecos a propósito (es priorizado).
   La tabla CAI es de 4 columnas y se parsea con `pdftotext -layout`.
   **Falta PCEI-BSI/BI**: las adaptaciones de Básica Superior y Bachillerato (esas SÍ
   usan códigos 2016 agrupados, `LL.4.1. (1, 2)` = LL.4.1.1 + LL.4.1.2) viven en el
   anexo de adaptaciones del 2025-00034-A / EPJA_Completo 2017 — pendiente de PDF.
3. ~~Marcos Cambridge e IB~~ **HECHO (semilla)**: `InternationalFrameworksSeeder`
   (CAIE-LSEC, CAIE-IGCSE, CAIE-ASA, IB-MYP, IB-DP desde
   `database/data/marcos-internacionales.json`) + `CrosswalkSeeder` (13 conceptos,
   65 aristas STEM ancladas en las 8 destrezas MINEDEC verificadas, todas fuera de
   producción hasta que un docente las revise). Los **códigos nativos** están cotejados
   contra los documentos oficiales vigentes en 2026-08 (`attrs.source_url` de cada nodo);
   los **enunciados** son paráfrasis (`is_verified=false`).
   **Falta**: enunciados cotejados por un docente con acceso al syllabus, Lengua/Sociales,
   y el flujo de revisión que ponga `reviewed_by`/`reviewed_at`.
   **Ojo al resembrar**: los ciclos de Cambridge vencen (0580 en 2027, 0625/0620/0610 en
   2028, 9709 en 2027, 9702 en 2027 con el 2028-2030 ya publicado) y el IB estrena guía
   de Matemática AA en 2027. Cada ciclo nuevo entra como `framework_version` aparte.
4. ~~Motor de práctica~~ **HECHO (núcleo + v2)**: `practice_items`/`practice_attempts`,
   semilla determinista sha256(item:user:intento), verificación en servidor con
   tolerancia (`App\Services\Practice\*`), endpoints next/attempts. La v2 añade
   mastery learning (`objective_masteries`: EMA α=0.35/β=0.3, racha FIRMADA,
   hito `mastered_at`), selección adaptativa sobre el grafo de prerrequisitos
   (retroceso/avance con aristas manual/revisadas, campo `reason` en next),
   banco de 17 ítems sobre las 8 destrezas verificadas (seeder IDEMPOTENTE por
   objetivo+seq) y `GET practice/mastery` + `GET practice/progress?track=`.
   La progresión de prerrequisitos DENTRO de EC-MINEDEC ya está sembrada
   (CrosswalkSeeder, 6 aristas sobre las 8 destrezas con ítems: fuerzas → F = ma
   → sistemas → plano inclinado → rozamiento, y lentes → aumento lateral), así
   que el retroceso y el avance disparan de verdad en producción y no solo en
   los tests de política (`MineducProgressionTest`). Regla que impone el motor:
   **una arista prerequisite entre destrezas SIN ítems es decorativa** — el
   selector la descarta y el motor vuelve a «práctica normal» en silencio.
   **Falta**: más ítems al verificar áreas nuevas (cada área nueva del importador
   oficial trae destrezas que hay que encadenar igual) y retroalimentación
   gradual (issue #1).
   El `user_id` provisional del payload ya NO existe: identidad por sesión.
5. ~~LTI 1.3~~ **HECHO (Tool completa; pendiente de Moodle real)**: OIDC login +
   launch validado con `packbackbooks/lti-1p3-tool` v6.4 (API MessageFactory —
   la vieja LtiMessageLaunch está deprecated), provisión por (lti_iss, lti_sub),
   Deep Linking (simuladores publicados + destrezas con ítems, lineitem AGS) y
   AGS: `PushLtiScore` publica el mastery×100 en cola con backoff. Operación:
   `lti:keys`, `lti:platform:add`, rutas `/lti/*` (grupo `web` completo con
   CSRF exceptuado solo en lti/*: la protección del protocolo es state+nonce),
   guía en `docs/lti-moodle.md` (checklist para el Moodle del colegio).
   El launch redirige a la app Inertia con la MISMA sesión, y la API de
   práctica ya identifica por esa sesión (deuda del user_id CERRADA).
   **Falta**: validarlo contra un Moodle real y mapear curso Moodle → track.

## Qué NO hacer

- No añadir un chat-tutor IA antes de que el motor de práctica exista e instrumente datos.
- No editar el grafo por API ni por Tinker en producción: todo entra por importadores.
- No usar el email como identidad de usuario LTI (usa `lti_iss` + `lti_sub` de `users`).
- No usar matter.js en los simuladores (abandonado): integrador ODE propio o Rapier
  `-deterministic`. Nunca `Math.random()` en simuladores.
