# Informe de sesión — Identidad, /inicio y el currículo real

Rama `plataforma-inicio`. Tabla medida contra **HEAD `90abed4`**, con
`git diff` vacío tras revertir cada mutación.

## 1. La tabla de mutaciones

| # | Mutación (a propósito) | Resultado |
|---|---|---|
| M1 | `es_docente` sin el filtro `role='instructor'` | 🔴 2 tests (`LayoutSharedPropsTest`) |
| M2 | Quitar `hidden={!menuAbierto}` del nav móvil | 🔴 4 tests (`AppLayout.test.jsx`) |
| M3 | `/inicio` calcula su propio «siguiente» en vez de pedírselo al selector | 🔴 2 tests (`InicioPageTest`, los de divergencia) |
| M4 | El launch vuelve a aterrizar en `/progreso` | 🔴 4 tests (`LtiLandingTest`) |
| M5 | Quitar `-raw` de `pdfToText` | 🔴 1 test (`ImportMineducTest`, contra el PDF real) |
| M6 | Invertir el desempate (gana la más corta) | 🔴 1 test (`ImportMineducTest`) |
| M7 | Criterio (b) del oráculo vuelve a `\p{Ll}\p{Lu}` | 🔴 1 test (`ImportMineducParserTest`) |
| M8 | `fases-ord` borra sin la guarda de `--force` | 🔴 1 test (`FasesOrdTest`) |

**Dos sobrevivieron en la primera medición y esa es la información útil:**

- **M5 sobrevivió al principio**: el test del PDF real solo comprobaba el
  *porcentaje* de calidad, y sin `-raw` la calidad seguía en 99,3 % — por
  encima del umbral — mientras el enunciado era falso. Ahora compara el
  **contenido** de `CN.F.5.1.30` contra el texto oficial.
- **M6 sobrevivió al principio** porque el test **copiaba el comparador dentro
  del propio test**: mutar producción no lo tocaba. Es exactamente el vicio que
  el auditor señaló en otros tres tests. La garantía se fijó por la ruta real
  (el comando sobre un fixture con las dos ocurrencias del mismo código).
- Nota de medición: `M6` sale «verde» si se filtra por `ImportMineducParserTest`
  — su defensa vive en `ImportMineducTest`. Ambas cifras están arriba.

## 2. Qué se construyó, por frente

**F0 — identidad y esqueleto** (`e8b64a3`). Wordmark tipográfico, token
`--color-marca-*` en el `@theme` de Tailwind 4 (contraste medido sobre blanco:
600 = 6,29:1 · 700 = 8,03:1; la interfaz usa el 700), favicon SVG, theme-color
y meta description. Navegación Inicio · Catálogo · Buscar · Mi progreso con
`aria-current` (y test de que solo hay uno). «Panel del curso» aparece solo con
membership instructor, vía `auth.es_docente` + `auth.contextos` — un learner no
recibe ni los ids de sus cursos. Menú móvil con `aria-expanded`/`aria-controls`
operable **solo con teclado**. Modo iframe decidido por una función **pura**
(`estaEmbebido`), con el caso real de Moodle cubierto: en un iframe de otro
origen leer `window.top` *lanza*, y asumir «no embebido» pintaría navegación
duplicada dentro del LMS. Migración aditiva con índice `(user_id, role)`: la
unique existente guía por contexto, y en PostgreSQL una FK **no** crea índice
sobre la columna que referencia.

**F1 — `/inicio`** (`f1a1f26`). Continúa donde ibas · Tu siguiente paso ·
Cómo vas · accesos. El «siguiente paso» se le **pide** a `AdaptiveSelector`; el
oráculo de divergencia arma el grafo, pone al alumno en refuerzo y en avance, y
exige que la prop coincida con lo que devuelve el servicio. Matriz de
aterrizaje completa del launch (sin deep link → `/inicio`; con deep link → su
destino, también para el docente; deep link roto → `/inicio`, no un 404;
instructor sin curso → `/inicio`). Los textos de razón viven en un módulo
compartido con el bucle de práctica.

**F2 — el currículo real** (`3ae35f4`, `711662f`, `90abed4`). Calidad de
extracción del PDF oficial de CCNN: **58,6 % → 100 %**. Tres causas, todas con
evidencia del documento:

1. `pdftotext` en modo **por defecto** entrelaza las columnas de las tablas.
   De ahí salían `invertetros` (`inverte-` + `tros` de `noso-tros`) y
   `gimproblemas` (`gim-` + `problemas`), y enunciados como «…mediante la
   relaambiente físico». Ahora `-raw`, y si llega texto `-layout` se
   reconstruyen las columnas por posición X (en caracteres, no bytes).
2. `I.CN.2.2.1` —un **indicador de evaluación**— matcheaba el regex de
   destrezas: 86 falsas destrezas de 489. Lo excluye un lookbehind.
3. El desempate entre ocurrencias duplicadas premiaba la basura. Al arreglarlo
   descubrí que `sortBy([closure, closure])` **no ordena por los closures**.

El **oráculo de calidad** vive en el comando: (a) sin códigos ajenos, (b) sin
palabras partidas, (c) ≥30 caracteres, (d) termina en puntuación. Reporta el %
con desglose y `--official` **aborta** bajo el 95 % sin escribir una fila;
además omite las que no pasan, porque marcar un enunciado corrupto como
verificado es mentirle al colegio.

**ORD deja de estar vacío**: `php artisan curriculo:fases-ord` crea las cinco
fases por subnivel y les ata las destrezas de sus grados. Verificado con
`migrate --seed`: 5 fases, **1010 destrezas** (14/213/211/210/362).

## 3. Lo que encontró el auditor adversarial (7 hallazgos, `711662f`)

El más grave: `--official` sellaba **132 filas con enunciado falso** e
`is_verified=true`, por una cadena de tres eslabones inocuos por separado —
extracción en modo por defecto, un recorte goloso que dejaba el amasijo
*terminado en punto* (o sea, con aspecto limpio) y un desempate que premiaba al
más corto. El segundo: el criterio (b) tenía **100 % de falsos positivos** en
el documento real —marcaba `pH` como palabra partida— y con `--official` las
dos destrezas de ácidos y bases de Química BGU se omitían en silencio. También:
`fases-ord --track=PCEI-BI` borraba la fase 0 propedéutica (obligatoria por la
reforma 2025-00010-A); el seeder llamaba al comando en cada despliegue
descartando su salida; `reconstruirColumnas` podía destrozar listas indentadas;
`auth.contextos` se calculaba en cada respuesta JSON de la API de práctica; y
dos menores (orden de NULLs distinto en SQLite/pgsql, `aria-controls` apuntando
a un id inexistente con el menú cerrado). **Los siete, corregidos y con test.**

## 4. Qué NO funciona todavía

- **No ejecuté `--official` contra el PDF real ni toqué el servidor**, como
  pedía la misión. El comando queda listo: `php artisan mineduc:import
  storage/curriculo/CCNN_COMPLETO.pdf --official` (necesita `pdftotext` en el
  PATH; en esta máquina se instaló poppler aparte).
- **Solo se probó CCNN.** M, LL y CCSS no se han pasado por el parser: sus PDF
  no están descargados. El oráculo dirá si el troceado se comporta igual —
  esa es precisamente su función.
- **El desempate por longitud ya no cambia nada en este documento**: tras la
  guarda del recorte, las ocurrencias mutiladas salen *inválidas*, así que gana
  la buena por validez. Los únicos códigos con dos candidatos válidos de
  distinta longitud son variantes editoriales triviales («mezcla»/«mezclas»).
  Es cinturón y tirantes, y está dicho.
- **`reconstruirColumnas` no se ejecuta en la ruta real** (con `-raw` no hay
  columnas que reconstruir). Es una red de seguridad para `.txt` ya extraídos
  en `-layout`, y sus tests lo prueban en ambas direcciones.
- **`curriculo:fases-ord` retira las 13 fases-grado de ORD**. En producción
  esas fases están vacías, pero sus UUID dejarán de resolver en la API pública
  `/api/v1/phases/{phase}/objectives`. Es deliberado y ahora visible en el log
  del despliegue; se niega a borrar si alguna tiene destrezas atadas.
- **Bucle D no abordado** (transiciones, breadcrumbs en /inicio, skeletons, 404
  con marca): la sesión se fue en los frentes y en cerrar la auditoría.

## 5. Los números

- **PHP 257/257** (1974 aserciones) — baseline al empezar: 203.
- **Vitest 86/86** en 9 archivos — baseline: 56.
- **Build** limpio. **Pint** limpio.
- Calidad del PDF oficial de CCNN: **100 %** (403 destrezas, 0 rechazadas).
- ORD: 5 fases, 1010 destrezas atadas. Verificadas en el grafo: 9 de 1186.

## 6. Cómo se ve `/inicio` en un móvil de 360 px

No hay captura: esta máquina no tiene navegador headless y montar uno para una
foto no compensaba. Lo que sigue sale de leer el marcado y las clases que
aplican a esa anchura.

Todo cae en la rama móvil (`sm` = 640 px). **Cabecera**: wordmark «AllyuHub» en
índigo a la izquierda y un botón «Abrir menú» a la derecha; la navegación de
escritorio y el nombre del usuario están ocultos. Al desplegar, el botón pasa a
«Cerrar menú» y aparece la lista vertical de enlaces con el actual subrayado y
con fondo `marca-50`. **Cuerpo**: una sola columna; el h1 «Tu aprendizaje» y
tres tarjetas apiladas con borde y fondo blanco: la de continuar (código,
enunciado, barra de dominio a ancho completo y botón «Seguir practicando»), la
del siguiente paso (aviso de la razón sobre fondo `marca-50`, luego destreza y
botón «Empezar») y la de progreso, que se lee en una frase con las cifras en
negrita. **Accesos**: dos enlaces que envuelven a dos líneas. **Pie**: una
línea.

Lo que no está bien del todo, dicho sin adornos: los enlaces del menú móvil son
`px-2 py-1` sobre texto de 14 px (~28 px de alto). Pasan el mínimo de WCAG 2.2
(24×24) pero van justos para un dedo; deberían subir a 44 px. Y el alumno nuevo
ve dos tarjetas casi vacías seguidas —la invitación al catálogo y «aún no hay
progreso»— que en una pantalla pequeña se sienten redundantes.
