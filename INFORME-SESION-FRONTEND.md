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

(Las fases B-E añaden sus secciones más abajo.)
