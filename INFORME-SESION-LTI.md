# Informe de sesión — LTI 1.3 (2026-08-13)

Rama: `lti13` (desde `main` = `1acb5b3`). **Sin push, sin PR** (flujo humano).
Librería: `packbackbooks/lti-1p3-tool` **v6.4.3** (composer.lock fijado; usa la
API nueva de fábricas `MessageFactory`/`Messages\*` — la vieja `LtiMessageLaunch`
está `#[\Deprecated]` y en PHP 8.4 dispararía avisos).

## Decisiones de arquitectura (tomadas leyendo vendor/, no de memoria)

- **Connector HTTP propio** (`LtiHttpConnector`): el `LtiServiceConnector` de la
  librería usa Guzzle crudo y `Http::fake()` no lo intercepta. El nuestro
  replica su contrato (incluido el `client_assertion` JWT del grant) sobre el
  cliente Http de Laravel; el reintento tras 401 va por código de estado
  porque Laravel no lanza excepción en 4xx como Guzzle.
- **`findDeployment` devuelve el `LtiDeployment` CONCRETO** de la librería:
  `MessageFactory::validateDeployment` lo tipa así (no basta la interfaz).
- **Nonces en la cache de Laravel** (no tabla): `pull()` los hace de un solo
  uso. En producción exige driver compartido (database/redis) — documentado.
- **Rutas `/lti/*` en grupo de middleware propio** (`lti`): sesión + cookies
  SIN `EncryptCookies` (el nombre de la cookie de state lleva el propio state,
  dinámico: imposible excluirlo del cifrado por nombre) y sin CSRF de Laravel
  (los POST llegan cross-site desde Moodle; la protección es state+nonce).
- **users.lti_iss/lti_sub ya existían** (migración inicial, con unique
  compuesto): cero migraciones sobre users.

## Fase A — Cimientos (commit de esta fase)

`lti_platforms` (unique issuer+client_id, deployment_ids jsonb, is_active),
`ToolKeys` (RSA 2048 en storage/app/lti, kid estable = sha256 de la pública),
`lti:keys` / `lti:platform:add`, `GET /lti/jwks`, y las 4 implementaciones de
interfaces de la librería (`LtiDatabase`, `LtiCache`, `LtiCookie`,
`LtiHttpConnector`).

### Oráculo adversarial de seguridad — fase A (pregunta → respuesta)

- **¿El endpoint /lti/jwks filtra la clave privada?** No: `JwksEndpoint` de la
  librería exporta solo n/e, y hay un TEST que afirma que d/p/q/dp/dq/qi/oth
  no aparecen en la respuesta. La privada vive en storage/app (fuera del
  webroot) y ningún endpoint la lee para output.
- **¿Puedo dar de alta o alterar una Platform por HTTP?** No: el alta es solo
  por comando artisan (`lti:platform:add`); no existe ruta de escritura.
- **¿Puedo reusar un nonce?** No: `LtiCache::checkNonceIsValid` hace `pull()`
  (borra al leer) — el segundo uso devuelve false aunque el state coincida.
  Cubierto por test. CAVEAT producción: exige cache compartida entre workers
  (database/redis); con driver `array`/por-proceso la protección se degrada.
  Anotado en docs para el despliegue.
- **¿El kid revela algo?** No: es sha256 (truncado) de la clave PÚBLICA.
- **¿`lti:keys --force` puede romper launches en silencio?** Avisa en la
  salida del comando (rota el kid e invalida firmas en vuelo) y sin --force
  es no-op. Test cubre idempotencia y rotación.
- **¿Una platform desactivada resuelve?** No: scope `active()` en todas las
  búsquedas de `LtiDatabase`. Cubierto por test.
- **¿`sub` de otra platform colisiona?** Se responde en fase B (identidad
  compuesta iss+sub); el esquema de users ya trae el unique compuesto.

## Fase B — OIDC login + Resource Link Launch

`GET|POST /lti/login` (redirect con state+nonce), `POST /lti/launch` validado
por `MessageFactory` (firma contra el JWKS real de la Platform servido por
Http::fake en tests, state↔cookie, nonce de un solo uso, registration,
deployment, claims requeridos), provisión por `(lti_iss, lti_sub)`, sesión
Laravel iniciada con `regenerate()`, y vista Blade mínima que enlaza la
práctica con el usuario DE LA SESIÓN. El contexto AGS del launch queda en
sesión para la fase D.

### Oráculo adversarial de seguridad — fase B (pregunta → respuesta)

- **¿Puedo lanzar con un id_token firmado por MI clave?** No. Test: token
  firmado con la clave del atacante PERO con el kid legítimo de la Platform →
  403. La clave se resuelve por kid dentro del JWKS real de la Platform y la
  verificación es criptográfica (firebase/php-jwt), no un mock.
- **¿Puedo reusar un nonce?** No. Test de replay exacto del mismo id_token →
  el primer launch pasa, el segundo muere con 403 (nonce consumido por pull()).
- **¿Un state forjado (CSRF)?** No. El POST sin la cookie `lti1p3_<state>` del
  navegador muere en validateState (test). La cookie va HttpOnly + Secure +
  SameSite=None (el POST del launch llega cross-site desde Moodle/iframe).
- **¿El `sub` de otra platform colisiona con el mismo `sub` de otro issuer?**
  No. La identidad es la TUPLA (lti_iss, lti_sub) — regla de CLAUDE.md — con
  unique compuesto en users. Test: mismo sub en dos issuers → dos usuarios.
- **¿El email identifica o fusiona cuentas?** Jamás. Si el email del claim ya
  pertenece a una cuenta local, el usuario LTI nuevo recibe un placeholder
  único `lti+<hash>@lti.allyuhub.invalid` (dominio .invalid, RFC 2606) y la
  cuenta local queda intacta (test).
- **¿Fijación de sesión?** `session()->regenerate()` inmediatamente tras
  `Auth::login()`.
- **¿Deployment no registrado? ¿token caducado? ¿issuer desconocido?** 403 los
  tres, cada uno con su test.
- **¿El 403 filtra información sensible?** Devuelve el mensaje de error de la
  librería («Invalid signature on id_token», etc.). Ayuda a depurar la
  integración y no revela secretos ni existencia de usuarios. Aceptado.
- **Riesgos aceptados y documentados**: (1) `/lti/*` va sin el CSRF de Laravel
  — el POST es cross-site por diseño del protocolo y state+nonce cumplen ese
  rol; (2) el grupo `lti` no cifra cookies (nombre de la cookie de state
  dinámico): el id de sesión ya es opaco, y la cookie de state es un valor
  aleatorio de un solo apretón; (3) dos launches simultáneos de un alumno
  NUEVO: el unique (lti_iss, lti_sub) hace ganar a uno y el otro relee
  (catch de UniqueConstraintViolation).

(Las fases C-D añaden sus secciones más abajo.)
