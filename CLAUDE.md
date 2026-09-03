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
  catálogo. Los endpoints de práctica viven en `routes/web.php` bajo el mismo prefijo.
  **Ya no exigen sesión** (contenido abierto, modelo Khan): un invitado pide ítems y
  recibe corrección real, pero no escribe ni una fila ni dispara AGS. Con sesión, el
  alumno es SIEMPRE `Auth::id()` — un `user_id` en el request es 422 (`prohibited`).
  La deuda del user_id de payload está CERRADA. Ver «La frontera» abajo.
- Frontend: Inertia + React 19 (Vite 8 + Tailwind 4), páginas en **`resources/js/pages`**
  y layouts en `resources/js/layouts` — TODO EN MINÚSCULA: es el default de Inertia 3
  (`resource_path('js/pages')`). En Windows/macOS da igual porque el sistema de archivos
  no distingue mayúsculas, pero en Linux (CI y producción) `Pages` NO se encuentra y
  `assertInertia` falla. Rutas: `/catalogo`, `/catalogo/{node}`, `/destreza/{objective}`,
  `/buscar`, `/practicar/{objective}`, `/recurso/{resource}`, `/progreso`, en español y
  aptas para el iframe de Moodle (CSP frame-ancestors construida con los issuers de las
  Platforms activas, normalizados con `parse_url`).
- **Dos suites, y ninguna sustituye a la otra.** `php artisan test` NO compila el
  frontend (`withoutVite()` + `assertInertia`): solo verifica que llegan las props
  correctas. El COMPORTAMIENTO de React lo prueba `npm run test:js` (Vitest + Testing
  Library + axe, con `vitest-axe` como oráculo de accesibilidad). Un bug que vive en el
  JSX —como la cabecera que rotulaba el ejercicio del prerrequisito con la destreza
  pedida— es invisible para la suite PHP. Y el bundle no puede contener los `__tests__`:
  el glob los arrastró una vez y `vite build` salió con código 0 (guardián en el CI).
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
   (ltree, GIN, tsvector) va detrás de `DriverName === 'pgsql'`. Los tests corren en SQLite
   **y también contra PostgreSQL 16 en el CI** — esto último desde que se descubrió que
   `alignments.reviewed_by` era `uuid` contra un `users.id` `bigint`: SQLite no tipa, así
   que 154 tests pasaban mientras firmar una alineación era imposible en producción.
   `migrate --seed` valida el esquema; solo la suite valida el comportamiento.
   **Un tipo de columna que referencia a otra tabla se comprueba comparándolos entre sí**
   (`Schema::getColumnType`), nunca exigiendo un tipo concreto: así el test es dual.

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

0. ~~Higiene de producción~~ **HECHO (PR 0 de la misión curso)**:
   `PracticeItemSeeder` ancla por (VERSIÓN VIGENTE, código) y el replicado por
   grado es cobertura — el «código ambiguo» que abortaba `migrate --seed` en
   producción ya no aborta. `DatabaseSeeder` corre lo ESTRUCTURAL antes que el
   contenido (`curriculo:fases-ord` va antes del seeder de ítems: su silencio
   en producción lo tapaba el aborto). `install.sh` recachea SIEMPRE vía trap
   y la siembra salió del camino crítico (paso 8/8, no fatal). Las 3 preguntas
   CS.EC esperan su import en `database/data/banco-pendiente.php` (decisión
   2026-08-26: no se importa el área por ahora). Y `app.jsx` carga las páginas
   LAZY (`resolvePageComponent`): el entry queda en ~316 KB de marco y cada
   página es su chunk — el guardián del CI sigue midiendo `app-*.js`.
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
6. ~~Puente con el pipeline de cursos Moodle (e-learnium)~~ **HECHO (blueprint)**:
   `App\Services\Blueprint\*` + `php artisan curso:blueprint <nodo> --grado= --track= --out=`
   y `GET /api/v1/nodes/{node}/blueprint`. Exporta el contrato
   `allyuhub/curso-blueprint@1` (`curso.yaml` + esqueleto de `COBERTURA.md`) para
   que el repo `cursos-moodle` NO copie las DCD a mano: unidades desde los
   bloques, orden curricular, prerrequisitos con el mismo criterio que el motor
   (manual o revisada), `practica_url` por destreza practicable, `peso`/`fase`
   si se pasa `--track`, `idnumber` estable `AH-<codigo>-<grado>` (la clave de
   enlace con EduPlat) y `fingerprint` que solo se mueve si cambia el currículo.
   Contrato y reglas en `docs/cursos-moodle.md`. El YAML se emite a mano
   (todo escalar por `json_encode`, que es YAML 1.2 válido) y el test lo
   reparsea con symfony/yaml (dev) como oráculo.
   **Falta**: del lado de e-learnium, que el compilador consuma `curso.yaml`.
7. ~~La plataforma se VE~~ **HECHO**: portada pública `/` (`bienvenida`, cifras
   agregadas cacheadas 1 h — cero contenido del grafo), catálogo con tarjetas
   (grado con etiqueta corta + edad + conteos de subárbol; asignatura con icono
   y color), ficha y aula con el acento heredado de la asignatura, anillo de
   dominio en SVG propio y página de error 404/403 con marca.
   **Tras cada importación oficial, ejecuta `php artisan curriculo:estilos`**:
   escribe `icon`/`color` en `attrs` de los nodos asignatura desde
   `database/data/curriculo-semilla.json` (el importador oficial no los trae).
   Es idempotente y QUIRÚRGICO — no crea nodos, no toca `learning_objectives`
   y solo mira `node_type = 'asignatura'` (los códigos `CN`/`CS`/`M` también
   los llevan los nodos de área). El seeder ya los persiste en instalación nueva.
   **Falta**: colores para los marcos internacionales (CAIE/IB) y PCEI.
8. ~~La destreza ENSEÑA~~ **HECHO (fase 1: lecciones)**: una lección es un
   `resource` de `kind = 'reading'` cuyo texto vive INLINE en
   `resource_versions.config` (no hay `bundle_url`: no es un bundle de CDN, y
   no hay tabla nueva para lo que `resources`/`resource_versions` ya modelan).
   El contenido son BLOQUES TIPADOS (`parrafo | ejemplo | formula | lista |
   aviso | imagen`), nunca HTML: `App\Services\Lesson\Bloques` los valida al
   sembrar y `Recurso.jsx` pinta cada tipo con su componente, así que un
   `<script>` en el texto se LEE como texto. La matemática se convierte a un
   ÁRBOL MathML en el servidor (`App\Services\Lesson\MathML`, subconjunto de
   LaTeX en lista blanca) y `Formula.jsx` lo monta con `createElement`: cero
   KaTeX, cero `dangerouslySetInnerHTML`, +5.5 KB de bundle en total.
   `/destreza` pasa a ser un hub con el orden **1. Aprende → 2. Practica**, y
   cuando no hay lección lo DICE en vez de esconder la sección.
   Operación:
   ```bash
   php artisan lecciones:sembrar [--dry-run]   # nacen SIN firmar
   php artisan lecciones:firmar --bloque=M.4.1 --docente=7
   ```
   Banco en `database/data/lecciones.php`, idempotente por clave natural
   (destreza, slug). Cubre **Básica Superior entera** (LL.4, M.4, CN.4, CS.4:
   16 bloques × 3 grados) y el informe lista destreza a destreza lo que falta
   en `storage/app/lecciones-sin-cobertura.txt`.
   **Falta**: el resto de subniveles, y ofrecer lecciones por Deep Linking
   (hoy Moodle solo puede incrustar simuladores y destrezas con práctica).

   Dos reglas que cuestan caro si se rompen:
   - **La firma se le exige a lo GENERADO, no a un `kind`.** `published()` pide
     `reviewed_at` cuando `resources.origen = 'generado'` (espejo de
     `practice_items.origen`). **El DEFAULT de esa columna es `generado`**, al
     revés que en `practice_items`: allí nadie la lee para decidir qué se ve y
     aquí gobierna la puerta, así que falla CERRADA. Quien dé de alta un
     recurso sin declarar procedencia se queda sin publicar —molesto— en vez de
     con contenido sin firmar delante de un alumno. El backfill, en cambio,
     pone `curado`: las filas que ya existían las registró un operador. Estuvo atada a `kind = 'reading'` y era cierta
     por una circunstancia, no por naturaleza: la Fase 2 son simuladores
     DECLARATIVOS generados por un pipeline de IA, así que habrían entrado por
     `kind = 'simulation'` y salido al alumno sin que nadie tocara esa línea —
     el agujero se abría solo. Lo curado (un simulador que un operador da de
     alta uno a uno) sale sin firma, y por eso la migración hace backfill a
     `curado`: sin él, esa migración habría vaciado el catálogo.
   - **Toda ruta que sirva o CUENTE un recurso pasa por `published()`**, nunca
     por un `status === 'published'` escrito al lado. Ya divergió tres veces:
     `GET /api/v1/resources/{slug}` llegó a servir el texto íntegro de una
     lección sin revisar, y la portada contaba a mano. El oráculo que lo fija
     recorre TODAS las rutas de una vez, la portada incluida
     (`LeccionTest::test_ninguna_ruta_sirve_una_leccion_sin_firmar`).
   - **`kind` tiene un vocabulario cerrado** —`simulation|lab|video|reading|
     practice_set|project`, declarado en la migración de `resources`— y se usa
     por constante (`Resource::SIMULACION`, `Resource::INTERACTIVOS`). La
     portada contaba `'simulator'`, que no existe; el fixture de su test traía
     la MISMA errata, así que pasaba en verde diciendo «0 simuladores» para
     siempre. `BienvenidaTest` compara ahora contra el vocabulario de la
     migración, no contra una lista escrita a mano en el test.

   Y una trampa de los tests: Inertia serializa las props como JSON con escapes
   unicode, así que buscar «ecuación» en el cuerpo NUNCA la encuentra (viaja
   como `ecuaci\u00f3n`). Los centinelas de no-filtración van sin acentos, y
   siempre con control positivo.

## Dos tipos de ítem: `numeric` y `choice`

`practice_items.kind` decide cómo se corrige. El numérico es el de siempre
(rangos en `params`, expresión en `solution_expr`). El de **opción múltiple**
existe porque Lengua y Sociales no son numéricas y sin él la mitad del
currículo importado no podía tener práctica.

Tres reglas que no se negocian:

1. **La clave correcta vive en su columna** `practice_items.answer_key`, nunca
   en `params` ni en `attrs` — los dos se serializan al cliente en `next()`.
   El payload se arma por LISTA BLANCA, campo a campo; el test de no-filtración
   busca el TEXTO de la opción buena en el cuerpo entero, no un nombre de campo.
2. **Se responde por CLAVE inmutable, no por posición.** La semilla baraja el
   orden de PINTADO y nada más. Por eso un barajado distinto entre servir y
   corregir no puede calificar mal: el orden no entra en la comparación. Es
   imposible por construcción, no algo que vigile un test.
3. **Por intento, exactamente una vía poblada según `kind`**: un choice deja
   `answer`/`expected` en NULL y llena `answer_key`; un numérico, al revés.
   Rellenar con `0.0` o `''` escondería un bug de bifurcación.

**Un ítem no llega a un alumno hasta que alguien lo FIRMA.**
`LearningObjective::practiceItems()` solo devuelve lo que tiene `reviewed_at`
— el filtro vive en la relación, no en cada consulta, porque de ahí cuelgan
también `has_items` del catálogo, el Deep Linking de Moodle y los conteos del
blueprint: con la puerta solo en el selector, una destreza anunciaría «con
ejercicios» y daría 404 al entrar. Para contar TODO (informes, administración)
está `todosLosPracticeItems()`. Se publica con
**`php artisan practica:firmar --bloque=LL.4.1`**.

**El dominio exige aciertos en DOS ítems distintos** (`MasteryTracker::apply`),
y la nota al aula también. Un `choice` no re-aleatoriza nada entre intentos y
al fallar revela cuál era la buena, así que con una sola pregunta bastaban tres
clics para sellar `mastered_at` —que no se borra nunca— y empujar la nota al
cuaderno del profesor. La regla se aplica también al numérico: el dominio de una
destreza con un único ítem no significa nada, haya trampa o no.

Para llenar el banco: **`php artisan practica:sembrar`** (idempotente por
(objetivo, seq); `--incluir-no-verificadas` para el grafo de demostración).
Los ítems se anclan a un BLOQUE por el prefijo del código y aterrizan en la
primera destreza de ese bloque EN CADA NODO — un bloque replicado en 8.º, 9.º
y 10.º recibe los tres, que es lo que convierte el viejo «código ambiguo» en
cobertura. El informe se mide en DESTREZAS (% con ≥1 ítem por asignatura ×
subnivel) y escribe las que faltan en `storage/app/practica-sin-cobertura.txt`.
Ojo: los ítems nacen con `attrs.revision.alineado_a = 'bloque'` — alineados al
bloque, no cotejados uno a uno con el enunciado oficial de cada destreza.

## Audio (curso de idiomas, fase 1)

La plataforma dicta cuatro idiomas de cero (FR/IT/DE/ZH, 1.º BGU, A1) y para eso
el motor aprendió a OÍR:

- **Bloque `audio` en las lecciones** (`Bloques`): `{src, texto, duracion_s?}`.
  La transcripción es OBLIGATORIA (accesibilidad + pedagogía A1) y el `src` solo
  puede ser del almacén propio. Sin red, el bloque degrada a su transcripción
  con aviso — la lección de texto sigue entera.
- **Ítem `escucha`**: la mecánica de `choice` con un clip delante. El motor
  pregunta `respondePorClave()` y no el kind: el siguiente tipo por clave hereda
  el circuito entero (billete, barajado de pintado, 422 por clave inventada).
  La `transcripcion` es columna propia y oculta COMO la clave correcta: se
  revela en el veredicto, jamás en `next` — si se lee antes, el ejercicio de
  escucha no existe. En un ítem SIN red no se cae a la transcripción (sería
  regalar la respuesta): se avisa y se puede pasar al siguiente.
- **Los ficheros** (`AlmacenDeAudio`): nunca base64 en JSON. Nombre = hash del
  contenido (16 hex) ⇒ re-sembrar no rompe enlaces, no hay duplicados, y la
  ruta `/audio/{fichero}` puede servir `Cache-Control: immutable` un año sin
  riesgo. La FORMA del nombre es cerrada y se comprueba en `resolver()` ADEMÁS
  del `where` de la ruta: dos puertas, cada una con su test (una mutación
  enseñó que la segunda estaba sin vigilar). Reproductor `<audio>` nativo:
  cero librerías, el presupuesto de bundle (450 KB) no se toca por audio.

## Los tipos de ítem son PIEZAS, no ramas (desde el PR de lenguas)

Siete kinds: `numeric | choice | escucha | hueco | orden | pares | dictado`.
Cada uno declara su comportamiento completo en `App\Services\Practice\Tipos\*`
(payload de next, reglas del POST, corrección, columnas del intento y guardián
`saving`), elegido por `Tipos\Registro` — vocabulario CERRADO: un kind
desconocido no se guarda. El controlador no sabe cuántos tipos hay: un tipo
nuevo es una clase y una entrada en el registro, sin tocar `next` ni
`submitAttempt`. La exclusión mutua de campos de respuesta (`answer` |
`answer_key` | `respuesta`) se DERIVA de `camposDeRespuesta()`, no se escribe
por tipo.

Reglas que no se negocian, heredadas de #22/#25/#26:

- **La solución vive en `practice_items.solucion`** (jsonb, en `$hidden` con
  `answer_key`/`solution_expr`/`transcripcion`) y JAMÁS se serializa. Payload
  por lista blanca; oráculo sobre el cuerpo completo con centinelas sin acentos.
- **Se responde por ID INMUTABLE o por texto, nunca por posición pintada**:
  en `orden` viajan secuencias de ids, en `pares` tuplas de ids (una clave por
  columna, columnas en orden alfabético). El barajado no participa en la
  corrección por construcción.
- **La normalización de `hueco`/`dictado` es POR LENGUA** (`Tipos\Normalizador`,
  sin intl a propósito: el CI no la carga): mayúsculas/espacios/apóstrofo
  tipográfico se perdonan; los acentos NO, pero `detalle` distingue 'acento' de
  'palabra'; la ß→ss solo en `de`.
- **`pares`: crédito TODO O NADA** (`is_correct` alimenta dominio y AGS), el
  veredicto enseña el parcial. Ojo: con n=2 y claves sin repetir un parcial es
  IMPOSIBLE — el oráculo del parcial usa 3 filas (mutación mediante).
- **Un id fuera de los servidos es 422**, no un falso «incorrecto».
- La interacción de orden/pares es PULSAR, no arrastrar: teléfono + teclado +
  lector de pantalla piden lo mismo que el presupuesto (0 KB de librerías).

## El cascarón del curso (`/corso/{lengua}`)

Un alumno de idiomas entra por `/corso/it` (portada: 9 unidades, dominio por
unidad, racha, y LA única cosa que hacer ahora) y `/corso/it/u{n}` (la unidad:
sus «Puedo…» del MCER pintados como objetivos del alumno). Todo el estado se
calcula en el SERVIDOR (`App\Services\Curso\CursoDeLenguas`) y llega como
props — React solo pinta, y así el cascarón cuesta ~6 KB de JS, no ~40.

- **El molde de 9 unidades** vive en `database/data/cursos-lenguas.php`: mismo
  para las cuatro lenguas (el MCER así lo escribe), con los descriptores de
  cada unidad. Una unidad sin ítems/lecciones FIRMADOS de esa lengua se pinta
  «próximamente», nunca vacía.
- **La racha** (`RachaDeAlumno`) se rompe con TRES días naturales sin
  actividad, no con uno: un fin de semana no castiga. Ojo con `diffInDays` de
  Carbon nuevo — devuelve con signo y float, hay que `abs`+`int`.
- **La lengua es cerrada**: `/corso/klingon` es 404. Y el cabo suelto de #28
  quedó cerrado — `/destreza?lengua=` filtra RECURSOS por lengua en las dos
  direcciones (pedir italiano sirve solo lecciones italianas; sin lengua, solo
  contenido sin lengua), igual que ya hacían los ítems.

## El camino del contenido de lenguas (MCER)

El marco de los cursos de idiomas es el **CEFR** (`CefrSeeder`, en la cadena de
`migrate --seed`): nivel → área (`A1.CO/CE/IO/PO/EE`) → descriptores «Puedo…».
Entra **verificado y citado** — los descriptores del MCER son públicos, al
revés que CAIE/IB — y un descriptor sin `source_url` no entra.

- **La lengua es del CONTENIDO, no del marco**: `A1.IO.3` es el mismo
  descriptor en italiano y en alemán. La separa la columna `lengua` (ítems y
  recursos), de lista CERRADA (`Practice\Lenguas::LISTA`). La regla de
  servicio es cerrada en las dos direcciones: `?lengua=it` sirve SOLO
  italiano, y sin `lengua` se sirve SOLO contenido sin lengua (todo MINEDEC).
- **El banco es `database/data/banco-lenguas.php`**, separado del de MINEDEC:
  entradas POR CLAVE (nunca posicionales) y cada tipo declara cómo se lee la
  suya en `Tipo::desdeBanco()` — el octavo tipo no toca `lenguas:sembrar`.
  Anclaje por descriptor EXACTO, no por prefijo de bloque.
- **El audio se nombra por CLAVE** (`'clip' => 'it/u1/saludo'`, fichero en
  `database/data/audio-lenguas/`): quien escribe el banco no puede calcular el
  hash de un clip que no existe. El sembrador publica en el almacén y
  sustituye; un clip que falta REVIENTA nombrando clave y entrada, y la
  siembra va en UNA transacción — el banco entra entero o no entra.
- **La firma es POR LENGUA**: `practica:firmar --bloque=A1.IO.it`,
  `lecciones:firmar --bloque=A1.CO.it`. Quien sabe italiano firma el italiano.

**La errata que motivó todo** (`practica:sembrar` y `lenguas:sembrar`): un
área que NO existe en el grafo **revienta** la siembra
(`DestinosDeBloque::areaExiste`, sin filtrar por verificación); un bloque o
descriptor hueco dentro de un área real **avisa**. La disyuntiva «o no está
importada, o no está verificada» dejó pasar `CS.FL` por `CS.F` y Filosofía
entera se quedó sin ejercicios. Ojo: la semilla demo llamaba `CS.FL` al nodo
de Filosofía y el import oficial escribe `CS.F` — la semilla ya está alineada,
pero una base sembrada ANTES conserva el nodo viejo.

## La frontera del contenido abierto (modelo Khan)

Se **navega** y se **practica** sin sesión; se **guarda** y se **califica** solo con
sesión LTI. Abiertas: `/catalogo`, `/catalogo/{node}`, `/destreza/{objective}`,
`/buscar`, `/practicar/{objective}`, `/recurso/{resource}` y los cuatro endpoints de
`/api/v1/practice/*`. Cerradas: `/inicio`, `/progreso`, `/docente/*`.

**Regla de oro**: un invitado no escribe NI UNA FILA atribuida a un usuario
—`practice_attempts`, `objective_masteries`, `users`— ni encola `PushLtiScore`.
Quién practica lo decide `App\Services\Practice\Practitioner`, y la corrección es
la MISMA para los dos: un único `PracticeEngine::verify()` por encima de la
bifurcación. Lo que cambia debajo es si se persiste y si califica (200 para el
invitado, 201 para el alumno).

Dos cosas que hay que saber antes de tocar esto:

1. **`se_guarda` en la respuesta no es decorativo.** Desde que los endpoints no
   exigen sesión, NUNCA devuelven 401: a un alumno con la sesión caducada se le
   atiende como invitado. La prop `auth` del cliente se renderizó cuando la sesión
   aún vivía, así que lo único que delata la caducidad es ese campo. Si lo quitas,
   el alumno practica en el vacío sin enterarse.
2. **El invitado rota de ítem por número de intento**, no por el selector adaptativo
   (que decide mirando historial, y él no tiene). Sin eso veía un solo ítem de cada
   destreza y el resto del banco era inalcanzable sin sesión.

## El billete del intento (no lo deduzcas otra vez)

`next` firma un **billete** —HMAC de la `APP_KEY` sobre `{item, quién, intento,
semilla}`— y `submitAttempt` lo EXIGE. El cliente no lo lee ni lo construye: lo
guarda con el ítem y lo devuelve tal cual. `intento` en el POST es `prohibited`.

Antes, `submitAttempt` re-derivaba `attempt_no` contando filas. Coincidía con lo
que sirvió `next` mientras nada se moviera entre las dos peticiones — pero se
mueve: otra pestaña, un reintento tras el 409 (cuya propia respuesta invita a
reintentar), una petición repetida. Cambiaba la cuenta, con ella la semilla y con
ella los números: el alumno resolvía «12 kg» y el servidor lo corregía contra
7 kg, poniéndole mal una respuesta buena. Desde el aula eso parece una
plataforma que miente.

Tres cosas que no se tocan:

- **Va FIRMADO**, no en claro. Sin firma el cliente elegiría su `attempt_no`: no
  le daría respuestas correctas —la verificación sigue en servidor— pero sí
  podría reservar números y tumbar el intento legítimo de otra pestaña.
- **Va atado al ÍTEM y a QUIÉN** (`Practitioner::seedKey`, la misma identidad con
  la que se sella la semilla). Sin lo primero, el billete de un ejercicio vale
  para responder otro; sin lo segundo, el de un invitado vale para escribirle un
  intento a un alumno. Lo del ítem lo encontró una mutación, no una lectura.
- **No caduca, a propósito.** Guardárselo no sirve de nada: hay que acertar
  igual, y el índice único (ítem, usuario, intento) impide gastarlo dos veces.
  Una caducidad añadiría un reloj que sincronizar a cambio de nada.

En los tests, `TestCase::billete()` emite uno y `billeteComoNext()` reproduce el
que `next` daría en ese instante. Los tests del CIRCUITO COMPLETO no usan el
atajo: piden `next` y devuelven el billete de la respuesta.

## Regla de color (no la violes)

El color de asignatura **jamás** es el único portador de significado: siempre va
con icono Y con texto. Y ninguno de los 15 colores de la semilla llega a 4.5:1
sobre blanco (el mejor, ECA `#b45fc4`, da 3.91), así que el color se usa como
ACENTO —borde, fondo suave, texto ya oscurecido— y nunca como fondo de texto
blanco. Los cálculos viven en `resources/js/lib/color.js` y
`__tests__/color.test.js` los MIDE contra el JSON real: si alguien añade un
color que no cumple, el test cae.

## Qué NO hacer

- No añadir un chat-tutor IA antes de que el motor de práctica exista e instrumente datos.
- No editar el grafo por API ni por Tinker en producción: todo entra por importadores.
- No usar el email como identidad de usuario LTI (usa `lti_iss` + `lti_sub` de `users`).
- No copiar destrezas a mano en el repo de cursos Moodle: se generan con
  `curso:blueprint` (el grafo es la verdad). Y AllyuHub no escribe en Moodle:
  eso es del compilador, con sandbox y human gate.
- No usar matter.js en los simuladores (abandonado): integrador ODE propio o Rapier
  `-deterministic`. Nunca `Math.random()` en simuladores.
- No distinguir nada SOLO por color (ni estado, ni asignatura, ni acierto/error):
  siempre texto, y el icono como refuerzo. Ver «Regla de color» arriba.
- No meter una librería de gráficas por un anillo o una barra: `resources/js/components/Anillo.jsx`
  son 60 líneas de SVG. El guardián del CI corta el bundle en 450 KB.
- No renderizar contenido con `dangerouslySetInnerHTML`, y no meter KaTeX ni
  MathJax: la matemática se convierte a MathML en el SERVIDOR y el navegador la
  pinta nativa. KaTeX solo son ~280 KB sobre un presupuesto de 450.
- No duplicar la regla de dónde aterriza un bloque del currículo: vive en
  `App\Services\Lesson\DestinosDeBloque` y la usan los DOS sembradores
  (práctica y lecciones). Si divergen, un alumno lee el bloque en una destreza
  y practica el mismo bloque en otra, y nadie se entera.
- No ordenar códigos curriculares como cadenas: `M.4.1.10` va DESPUÉS de
  `M.4.1.2`. Y ojo con `sortBy([closure, closure])`, que NO ordena por los
  closures — compara null con null y deja el orden de llegada.
- No dar de alta un `Resource` sin declarar `origen`. Es contenido que no se
  publica hasta que alguien lo firme, y eso no es un error visible: es un
  simulador que «no aparece» y nadie sabe por qué. Los dos labs de
  `CurriculumSeeder` van `CURADO`, y `PuertaPorDefectoTest` comprueba que tras
  `migrate --seed` se ven — el seeder es el único sitio donde equivocarse no
  ponía nada rojo.
- No construir claves a partir de un prefijo de un UUID de `HasUuids`: son
  UUID ordenados por tiempo, así que ese prefijo es una marca de tiempo y dos
  filas creadas en la misma milésima colisionan. Hash del id, no prefijo.
- No hacer que un test escriba sobre un fichero VERSIONADO y lo restaure en un
  `finally`. `practica:sembrar` acepta `--banco=<ruta>` justo para eso: el
  `finally` aguanta mientras el proceso termine, pero una interrupción o dos
  suites a la vez dejan un fichero del repo destruido, en silencio y con pinta
  de cambio legítimo. Pasó.

## Memoria larga: la bóveda

La memoria de largo plazo de este proyecto **no vive aquí**. Vive en:

`C:\Users\Carlos\Desktop\Obsidian\Talo\01-Proyectos\AllyuHub.md`

Y el conocimiento reutilizable que salió de aquí, en
`C:\Users\Carlos\Desktop\Obsidian\Talo\03-Recursos\AllyuHub — *.md`.

### Al empezar

Antes de cualquier trabajo de peso —una decisión de arquitectura, un módulo
nuevo, un trámite, un cambio que afecte a otros— **lee la nota del proyecto**.
Contiene las decisiones ya tomadas con su motivo, los callejones sin salida (lo
que se intentó y falló) y las tareas pendientes reales.

No repropongas nada que ahí figure como descartado. Si crees que esta vez sería
distinto, dilo explícitamente y explica qué cambió.

### Al terminar

**Nunca escribas dentro de `Talo/`.** Esa carpeta la gobierna el humano.

Si la sesión produjo algo que merezca quedar registrado —una decisión, un
aprendizaje caro, un callejón sin salida, tareas cerradas— escribe una bitácora
en:

`C:\Users\Carlos\Desktop\Obsidian\_entrada\bitacora\AllyuHub <YYYY-MM-DD>.md`

con este frontmatter y solo las secciones que tengan contenido:

```yaml
---
titulo: AllyuHub — <fecha>
tipo: captura
creado: <YYYY-MM-DD>
proyecto: AllyuHub
tags:
  - bitacora
---
```

Secciones: `## Qué cambió`, `## Decisiones nuevas`, `## Lo que costó`,
`## Callejones sin salida`, `## Tareas` (cerradas y nuevas), y
`## Contradice a la bóveda` —esta última es la más importante: si lo de hoy
invalida algo que la nota da por bueno, dilo ahí.

Si la sesión fue trabajo rutinario, **no escribas nada**. Una bitácora vacía es
peor que ninguna.

### Frontera

El repo manda en el código y los `TODO` de implementación. La bóveda manda en el
porqué, lo descartado y los pendientes de nivel proyecto. **No espejes tareas
entre los dos.**
