# AllyuHub como LTI 1.3 Tool — guía de conexión con Moodle

Cómo registrar AllyuHub en un Moodle real (probado con la estructura de
Moodle 4.x) y qué revisar antes del piloto. Los endpoints de la Tool:

| Qué | URL |
|-----|-----|
| Initiate login URL | `https://TU-DOMINIO/lti/login` |
| Redirection URI / Tool URL | `https://TU-DOMINIO/lti/launch` |
| Public keyset (JWKS) | `https://TU-DOMINIO/lti/jwks` |
| Deep Linking (Content Selection) | `https://TU-DOMINIO/lti/launch` (mismo launch) |

## 0. Requisitos del despliegue de la Tool (antes de tocar Moodle)

1. **HTTPS obligatorio** con dominio público: Moodle rechaza tools sin TLS y
   las cookies (state y sesión) van con `Secure`.
2. **Claves de la Tool**: `php artisan lti:keys` (una sola vez). La privada
   queda en `storage/app/lti/tool_private.pem` — respáldala; rotarla
   (`--force`) cambia el kid y Moodle tarda en refrescar el JWKS.
3. **Cache COMPARTIDA** (`CACHE_STORE=database` o `redis`, jamás `file`/`array`
   con varios workers): ahí viven los nonces anti-replay y los access tokens
   AGS. Sin cache compartida, la protección anti-replay se degrada.
4. **Cola corriendo** (`php artisan queue:work` o supervisor): los scores AGS
   se publican con el job `PushLtiScore` (reintentos 10s/60s/300s/900s).
5. **Sesión apta para iframe**: Moodle incrusta la Tool en un iframe
   cross-site. En `.env`:
   `SESSION_SAME_SITE=none` y `SESSION_SECURE_COOKIE=true`.

## 1. Registrar la Tool en Moodle (admin del colegio)

`Administración del sitio → Extensiones → Módulos de actividad → Herramienta
externa → Gestionar herramientas → "configurar una herramienta manualmente"`:

- **Nombre**: AllyuHub
- **URL de la herramienta**: `https://TU-DOMINIO/lti/launch`
- **Versión LTI**: LTI 1.3
- **Tipo de clave pública**: *URL del conjunto de claves* →
  `https://TU-DOMINIO/lti/jwks`
- **URL de inicio de sesión**: `https://TU-DOMINIO/lti/login`
- **URI(s) de redirección**: `https://TU-DOMINIO/lti/launch`
- **Compatible con Deep Linking (mensaje de selección de contenido)**: SÍ.
  URL de selección de contenido: `https://TU-DOMINIO/lti/launch`
- **Servicios**:
  - *IMS LTI Assignment and Grade Services*: **usar para sincronizar notas**
    (imprescindible para AGS).
  - *IMS LTI Names and Role Provisioning*: opcional (aún no se usa).
- **Privacidad**: compartir nombre y email con la herramienta = SÍ
  (el email solo se GUARDA; la identidad siempre es iss+sub).
- **Colocación**: activar "Aparece en el selector de actividades".

Guarda y abre **"Ver detalles de configuración"** de la herramienta creada:
Moodle muestra los datos que necesita la Tool.

## 2. Registrar ese Moodle en la Tool

Con los datos del paso anterior (los nombres de Moodle → nuestros flags):

```bash
php artisan lti:platform:add \
  "https://moodle.colegio.edu.ec"        `# Platform ID (issuer)` \
  "CLIENT_ID_QUE_MUESTRA_MOODLE" \
  --auth-login-url="https://moodle.colegio.edu.ec/mod/lti/auth.php" \
  --auth-token-url="https://moodle.colegio.edu.ec/mod/lti/token.php" \
  --jwks-url="https://moodle.colegio.edu.ec/mod/lti/certs.php" \
  --deployment="DEPLOYMENT_ID_QUE_MUESTRA_MOODLE"
```

Moodle crea **deployment ids nuevos** según dónde se use la herramienta
(p. ej. al activarla como herramienta de curso): si un launch da 403 con
«Unable to find deployment», vuelve a correr el comando solo con
`--deployment=NUEVO_ID` (se acumulan, no se pisan).

## 3. Checklist de prueba manual (cuando Carlos conecte el Moodle real)

En un curso de prueba, con un docente y un alumno de prueba:

- [ ] **Launch básico**: como docente, añadir actividad "Herramienta externa"
      apuntando a AllyuHub y abrirla → debe verse la vista de AllyuHub con el
      nombre del docente (usuario creado con lti_iss/lti_sub en `users`).
- [ ] **Deep Linking**: en la actividad, "Seleccionar contenido" → debe salir
      la lista de simuladores publicados y destrezas con ítems; elegir una
      destreza → Moodle guarda el enlace y crea la columna de calificación
      (lineitem de 100 puntos).
- [ ] **Launch de alumno**: entrar como alumno a esa actividad → vista de la
      destreza con el enlace «Practicar esta destreza».
- [ ] **Práctica y nota**: hacer 2-3 intentos por la API → en
      `Calificaciones` del curso debe aparecer el mastery×100 del alumno
      (sube con aciertos, baja con fallos). Requiere el queue worker vivo.
- [ ] **Anti-replay**: reenviar el formulario del launch (F5 + reenviar) →
      403 (nonce consumido). Es el comportamiento correcto: se re-entra
      desde Moodle.
- [ ] **Relojes**: si hay 403 «Invalid signature on id_token» sistemáticos,
      revisar NTP en ambos servidores (exp/iat de los JWT).

## 4. Problemas conocidos y su causa probable

| Síntoma | Causa probable |
|---------|----------------|
| 400 en /lti/login | Issuer no registrado (`lti:platform:add`) o falta login_hint |
| 403 «…cookies and cross-site tracking…» | El navegador bloqueó la cookie de state (revisar HTTPS y SameSite) |
| 403 «Invalid Nonce» | Replay real, cache no compartida entre workers, o >10 min entre login y launch |
| 403 «Unable to find deployment» | Deployment id nuevo: añadirlo con `--deployment=` |
| Launch entra pero sin nota en Moodle | Falta el servicio AGS en la config de la herramienta, o el queue worker está caído (`failed_jobs`) |
| La sesión se pierde dentro del iframe | Falta `SESSION_SAME_SITE=none` + `SESSION_SECURE_COOKIE=true` |
