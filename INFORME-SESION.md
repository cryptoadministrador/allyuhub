# Informe de sesión — Motor de práctica v2 (2026-08-13)

Rama: `motor-practica-v2` (desde `main` = `ce07595`). **Sin push**, según las
reglas de la misión: el PR lo abre Carlos a mano.

## Qué se hizo (las 4 fases, con oráculos en verde)

| Fase | Commit | Contenido | Suite al cerrar |
|------|--------|-----------|-----------------|
| A — Mastery learning | `486e5ee` | `objective_masteries` + `MasteryTracker` (EMA determinista) + hook transaccional en attempts + `GET practice/mastery` | 63/63 |
| B — Selección adaptativa | `871e074` | `AdaptiveSelector` (retroceso/avance por prerrequisitos, preferencia de marco, `reason`) | 72/72 |
| C — Banco de ítems | `1cf2ec5` | 17 ítems (12 nuevos) sobre las 8 destrezas `is_verified`; seeder idempotente | 74/74 |
| D — Pulido | `6eba5e6` | `GET practice/progress?track=`, 5 factories, tests de carga anti-N+1 | 80/80 |

Verificación final: `php artisan test` 80/80 (742 aserciones), `pint --test`
limpio, contrato v1 de `next`/`attempts` intacto (los 54 tests previos pasan
sin haberlos tocado; `next` solo AÑADE el campo `reason`).

## Decisiones tomadas (y por qué)

- **Racha firmada** en `objective_masteries.streak` (>0 aciertos, <0 fallos
  seguidos) en vez de una columna extra `fail_streak`: el esquema queda como
  pedía la misión y el selector detecta "≥2 fallos seguidos" leyendo un campo.
- **`mastered_at` como hito** (columna añadida al esquema pedido): "racha ≥3 con
  mastery ≥0.8 ⇒ mastered" necesita representación consultable. Se sella una
  vez y NO se borra al fallar después; el retroceso usa el mastery vivo.
- **EMA redondeada a 5 decimales** y guardada en `decimal(6,5)`: mismo valor
  reproducible en pgsql y sqlite (sin ruido de flotantes por driver).
- **Aristas admitidas: `method=manual` O `reviewed_at NOT NULL`**, tal como pide
  la misión. A propósito NO se usa `Alignment::production()`: exige
  `confidence ≥ 0.8` (irrelevante para progresión estructural) y las aristas
  del CrosswalkSeeder están sin revisar por diseño (la firma es humana).
- **La preferencia de marco gana al menor mastery**: un candidato de otro marco
  solo entra si el marco de partida no tiene ninguno ("filtra por framework
  salvo que no haya candidatos"). Cubierto por test con doble aserción.
- **`pickItem` del objetivo pedido se ejecuta siempre** (2 consultas extra
  cuando hay retroceso/avance): conserva el 404 del contrato v1 — "objetivo sin
  ítems" — con el código más simple.
- **Idempotencia del seeder por `(objective_id, seq)`** con `updateOrCreate`:
  resembrar actualiza contenido sin duplicar ni cambiar ids. Regla operativa:
  los ítems nuevos de una destreza se AÑADEN al final (el `seq` es la clave).
- **Faker solo en factories de test**, jamás en el motor: el determinismo
  (semillas sha256) es del dominio; los datos de prueba pueden variar.
- Heredadas de v1 y sin cambios: `user_id` en el payload (hasta LTI 1.3) y
  `expected` revelado tras cada intento (issue #1, retroalimentación gradual).

## Qué quedó fuera (y dónde retomarlo)

- **Aristas `prerequisite` dentro de EC-MINEDEC**: el CrosswalkSeeder solo trae
  progresión entre marcos internacionales (LSEC→IGCSE→ASA/DP). Hoy el retroceso
  no se dispara en la práctica MINEDEC en producción por falta de aristas, no
  de código: cuando se siembre progresión intra-MINEDEC funcionará tal cual
  (los tests lo prueban con aristas sintéticas).
- **Selección por fase de track**: la misión menciona "objetivo/fase pedidos";
  el endpoint sigue anclado a objetivo (contrato v1) y la vista por fase la da
  `practice/progress`. Un `next` por fase sería una ruta nueva, no una ruptura.
- **Concurrencia**: `firstOrNew` del mastery y el `attempt_no` por conteo pueden
  chocar si el MISMO alumno envía dos intentos simultáneos; la transacción y
  las uniques hacen que uno aborte limpio (sin corrupción). Elegante sería un
  upsert con bloqueo; innecesario antes del piloto.
- **`GET practice/next` global** (sin objetivo): fuera de alcance de la misión.

## Hallazgos de los oráculos adversariales (por fase)

- **A**: `now()` solo en `last_attempt_at` (metadato, jamás en semillas); orden
  del endpoint mastery con desempate por `objective_id`; carrera teórica del
  `firstOrNew` documentada arriba. Sin SQL específico de driver.
- **B**: todos los desempates son un orden total (mastery → native_code → id);
  `edgeIds` alimenta un `whereIn` (el orden de la query no influye); ~9
  consultas acotadas en `next()`. Verificada contra CrosswalkSeeder la
  semántica source→target («para intentar SOURCE domina antes TARGET»).
- **C**: física revisada fórmula por fórmula (está en el commit `1cf2ec5`);
  hallazgo corregido: el enunciado del aumento lateral decía "imagen a d_i cm
  de un objeto" cuando es "de la lente". Denominadores acotados por rango
  (d_o−f ≥ 5, d_o+d_i ≥ 40, m1+m2 ≥ 4): división por cero imposible, y el test
  evalúa los 17 ítems con 4 semillas exigiendo resultado finito.
- **D**: los tests anti-N+1 comparan conteos de consultas entre tamaños (2 vs
  12 ítems; 1 vs 6 fases; intento 1 vs 200) en lugar de números mágicos.

## Bloqueos y notas de entorno

- Sin bloqueos de datos externos: ningún PDF ni credencial hacía falta.
- Esta máquina no tiene PHP en el PATH: se usa el PHP 8.4.24 portátil de
  `C:\Users\Carlos\.claude\tools\php84\` (+ `composer.phar`); `vendor/` local.
- En la raíz hay 3 documentos sin trackear del usuario (`allyuhub-plan-maestro.md`,
  `e-skool-arquitectura.md`, `goal-anty-motor-practica-v2.md`). NO se
  commitearon a propósito, aunque la misión sugería `git add -A`: son material
  de contexto personal, no código del repo. Si deben versionarse, decisión de
  Carlos.
