# Informe de sesión — Frontend Inertia+React y cierre del user_id (2026-08-13)

Rama: `frontend-auth` (desde `main` = `d8280b0`). **Sin push, sin PR.**

## Fase A — La deuda de seguridad, CERRADA

Los 4 endpoints de práctica (`next`, `attempts`, `mastery`, `progress`)
exigen sesión (`auth`) y el alumno es SIEMPRE `Auth::id()`. Decisiones:

- **Mismas URLs `/api/v1/practice/*`, ahora en `routes/web.php`** bajo
  `middleware('auth')`: grupo web = sesión + cookies cifradas; los errores
  siguen saliendo en JSON por el `shouldRenderJsonWhen('api/*')` que ya
  existía. La API pública restante (`routes/api.php`) queda SOLO con lecturas
  del grafo/catálogo, sin datos de alumnos.
- **`user_id` en el request → 422 explícito** (regla `prohibited` en los 4
  endpoints). Elegido sobre "ignorarlo": un cliente desactualizado que grita
  se arregla; uno que suplanta en silencio, no se ve.
- **Guard: sesión web, sin Sanctum ni tokens** — la única puerta de entrada
  del piloto es el launch LTI, que ya hace `Auth::login()` + `regenerate()`.
  Añadir tokens sería superficie extra sin caso de uso.
- **El grupo `lti` especial se disolvió**: las rutas `/lti/*` van ahora por el
  grupo `web` COMPLETO con una única excepción (`validateCsrfTokens(except:
  ['lti/*'])` — los POST llegan cross-site desde Moodle; state+nonce cumplen
  ese rol). Motivo de fondo: la sesión sembrada en el launch (cookie sin
  cifrar en el grupo viejo) era ILEGIBLE para las páginas cifradas del grupo
  web — la sesión no habría fluido del launch a la app en un navegador real.
  Ahora la cookie de state y la de sesión se cifran/descifran en el mismo
  pipeline en todas partes.
- **Invitados**: bajo `/api/*` siempre 401 JSON (nunca un redirect a mitad de
  un fetch); en páginas, `redirectGuestsTo('/entrar')` — una página que
  orienta a re-entrar desde Moodle.
- Se eliminó el cinturón defensivo de la auditoría LTI en `queueLtiScore`
  (`auth()->check() && auth()->id() === $userId`): con `auth` en la ruta,
  `$userId` ES el autenticado; dos cinturones ocultan cuál sostiene.
- Tests: los ~40 afectados pasaron a `actingAs()` SIN tocar sus aserciones de
  comportamiento (mismos valores esperados, mismas rachas, mismos conteos de
  consultas). El test de la auditoría se reescribió a su versión fuerte:
  «un anónimo ya no puede NI practicar» (401, cero intentos, cero scores).

### Oráculo adversarial de AUTORIZACIÓN — fase A (pregunta → respuesta)

- **¿Puedo leer el mastery/progreso de otro cambiando un id o query param?**
  No existe NINGÚN parámetro de usuario: `mastery` y `progress` filtran por
  `Auth::id()` en la consulta. Test IDOR: Luis no ve nada de Ana; Ana ve lo
  suyo.
- **¿Puedo enviar un intento que se contabilice a otro?** No: el intento se
  persiste con `Auth::id()`; `user_id` en el body es 422. Test: el intento de
  Luis jamás aparece bajo Ana.
- **¿Queda algún endpoint de práctica sin `auth`?** No: los 4 viven en el
  grupo `auth`. Lo que queda público en `routes/api.php` es solo lectura del
  grafo curricular y catálogo (sin datos personales). Verificado ruta a ruta.
- **¿`progress` filtra por sesión o por lo que le pasen?** Por sesión
  (`$request->user()->id`); `track` es el único input y es un código de
  catálogo, no un dato personal.
- **¿Un `user_id` en el body tiene efecto en ALGÚN sitio?** Contraejemplo
  ejecutado: grep de lecturas de `user_id` del request en `app/` → cero; las
  ocurrencias restantes son columnas de BD, reglas `prohibited` y la escritura
  del resource link LTI (que sale del id_token validado, no del cliente).
- **Riesgo residual conocido**: CSRF está exceptuado SOLO en `lti/*`
  (protegido por state+nonce del protocolo); los POST de práctica están en el
  grupo web con CSRF activo (axios/Inertia envían X-XSRF-TOKEN).

## Fase B — Inertia + React

- `inertiajs/inertia-laravel ^3.3` + `@inertiajs/react` 2 + React 19 +
  `@vitejs/plugin-react` sobre el Vite 8/Tailwind 4 existentes.
- `HandleInertiaRequests` comparte lo MÍNIMO: `auth.user` = {id, nombre} —
  jamás email/lti_sub/modelo entero — y flash. Con test que afirma la
  ausencia de esos campos en las props.
- **El launch LTI ya no renderiza Blade: redirige (302)** a
  `/practicar/{objective}`, `/recurso/{resource}` o `/progreso` — rutas del
  grupo web COMPLETO (con CSRF). `launch.blade.php` eliminada. Las Blade de
  deep linking SE QUEDAN como Blade a propósito: son formularios de un solo
  uso hacia Moodle (POST cross-site), sin estado de app — Inertia ahí solo
  añadiría fricción.
- **Iframe**: middleware `AllowLtiFrameEmbedding` emite
  `Content-Security-Policy: frame-ancestors 'self' <issuers activos>` (jamás
  `*`; los desactivados quedan fuera — con test). Sin `X-Frame-Options` que
  lo contradiga. Si la tabla no existe (despliegue a medio migrar), la
  política degrada a `'self'` a secas — nunca más permisiva, nunca un 500.
- **Los tests jamás compilan el frontend**: `withoutVite()` en el TestCase
  base; toda pantalla se afirma con `assertInertia` (componente + props).

## Fase C — El bucle de práctica (`/practicar/{objective}`)

Enunciado instanciado por la API (misma sesión), respuesta con unidad,
verificación en servidor, retroalimentación inmediata (`expected` solo llega
DESPUÉS de responder) y el `reason` adaptativo en lenguaje de alumno
(«Repasemos algo anterior…» / «¡A por lo siguiente!»). Barra de dominio como
`progressbar` real, actualizada tras cada intento. Estados: cargando, sin
ítems (prop `has_items`, con test), sesión caducada (401 → `/entrar`), fallo
de red con reintento, y 409 (intento duplicado) que re-pide el siguiente.
Detalle técnico: Inertia v2 ya no trae axios — `fetch` propio con
`X-XSRF-TOKEN` de la cookie (los POST van por el grupo web CON CSRF).

### Oráculo de frontend — props revisadas una a una

- `Practicar`: objective{id, native_code, statement es, has_items} + mastery.
  SIN solution_expr, SIN seed, SIN item precargado — con aserciones `missing`.
- `Recurso`: id/slug/título/bundle_url (todo público). `Progreso`: tracks
  (catálogo). Compartido: auth.user{id, name} — test afirma que email,
  password y lti_sub NO viajan.
- El `expected` solo aparece en la respuesta del POST attempts (tras
  responder), como siempre; la página nunca lo recibe antes.

## Fase D — `/progreso`

Por fase del track: dominadas / en progreso / sin empezar, con números y
palabras además de la barra. Insignia de fase propedéutica. Selector de
trayecto accesible (con un solo track no hay selector). **Pendiente
documentado**: no existe mapeo curso-de-Moodle → track (necesita datos del
Moodle real); por ahora el alumno elige el trayecto.

## Fase E — Accesibilidad y cierre

Pasada sobre las pantallas nuevas (construida en el código, no a posteriori):

- **Teclado**: todo son controles nativos (button/input/select/a); skip-link
  «Saltar al contenido» en el layout; foco gestionado en el bucle de práctica
  (ítem nuevo → campo; resultado → panel de feedback con tabIndex=-1).
- **Foco visible**: `focus:outline-2` explícito en todos los controles.
- **Labels**: input de respuesta y selector de trayecto con `<label>`
  asociado; barras con `role=progressbar` + aria-valuenow/valuetext.
- **Lectores de pantalla**: resultado del intento en `aria-live=polite`;
  estados de carga/vacío con `role=status`, errores con `role=alert`.
- **Nada solo-color**: correcto/incorrecto llevan icono ✓/✗ y palabra;
  el progreso lleva cifras; la insignia propedéutica es texto.
- **Sin dangerouslySetInnerHTML** en todo el árbol JS (los enunciados vienen
  de PDFs: React escapa por defecto y así se queda).

CLAUDE.md al día: deuda del user_id marcada CERRADA en Stack y roadmap §4/§5,
sección de frontend real.

## Qué quedó fuera (pendientes honestos)

- **Mapeo curso Moodle → track** para preseleccionar el trayecto en /progreso
  (necesita el Moodle real del colegio).
- **Validación manual en un Moodle real** del flujo completo dentro del
  iframe (cookies SameSite=None en Safari/iOS es el sospechoso de siempre) —
  checklist ya en `docs/lti-moodle.md`.
- **Página del docente** (elegir contenido fuera del flujo deep linking) y
  retroalimentación gradual (issue #1): fuera del alcance de esta misión.
- La CSP `frame-ancestors` consulta `lti_platforms` por request (query
  ligera); si algún día pesa, cachear 60 s.

## Estado final

- Suite: **125/125 en verde** (1055 aserciones) sin compilar el frontend;
  `npm run build` verificado aparte (compila limpio). Pint limpio.
- `composer.lock` y `package-lock.json` commiteados.
- Commits: A `1122a45` (la deuda, cerrada), B `aafa391`, C `b8780a9`,
  D `52d173f`, E (este). **Sin push, sin PR** — flujo humano posterior.
