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

(Las fases B-D añaden sus secciones más abajo.)
