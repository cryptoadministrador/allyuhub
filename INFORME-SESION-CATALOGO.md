# Informe de sesión — Misión ANTY: catálogo del currículo y fin del frontend a ciegas

Rama: `catalogo-curriculo` (desde `main` = `eb6bffc`). PR contra `main`.

## 1. La tabla de mutaciones (qué rompí, qué se puso rojo)

| # | Mutación (a propósito) | Resultado |
|---|------------------------|-----------|
| F0-1 | Cabecera de `Practicar.jsx` con `objective.native_code` en vez de `item.objective_code` | 🔴 JS: «REGRESIÓN PR #10…» (1 test) |
| F0-2 | Quitar `aria-labelledby` del progressbar | 🔴 JS: 4 tests axe + «progressbar con nombre accesible» (5 tests) |
| F0-3 | Devolver `expected` en la respuesta de `next` (PHP) | 🔴 PHP: `test_siguiente_item_…_no_filtra_la_solucion` |
| F1-B1 | Invertir la condición de `DistintivoVerificacion` | 🔴 JS: destreza + buscar. **⚠ el test de catalogo-nodo AGUANTÓ VERDE** — comprobaba presencia de ambos textos, no su ubicación. Se endureció con `within()` (ancla cada distintivo a SU destreza); re-mutado: 3 archivos rojos. Es la información más útil de esta tabla. |
| F1-B2 | Quitar `published()` de `objective()` | 🔴 PHP: `test_borradores_invisibles_en_la_ficha` |
| F1-B3 | Comentar `production()` del crosswalk | 🔴 PHP: `test_ficha_de_destreza_completa` + `test_equivalencia_sin_revisar_no_aparece` |

Ninguna mutación sobrevivió tras el endurecimiento de B1.

## 2. Qué se construyó, por frente

**Frente 0 (bloqueante).** Vitest + Testing Library + vitest-axe + jsdom;
`npm run test:js`; job de CI «Tests JS (Vitest)» con Node 22, `npm ci`,
vitest `--run` y `npm run build` (un JSX roto ahora tumba el CI — antes ni se
compilaba). 13 tests de `Practicar.jsx`: bucle completo, la regresión del
PR #10 explícita, DOM sin datos sensibles con payload envenenado, degradados
(401/409/5xx/sin ítems), teclado puro y axe en 4 estados.

**Frente 1.** `/catalogo` (marcos + árbol MINEDEC hasta grado),
`/catalogo/{node}` (migas por `CurNode::ancestors()`, hijos, destrezas
paginadas de 50 con «Cargar más» contra la API), `/destreza/{objective}`
(distintivo en texto, Practicar deshabilitado con explicación si no hay
ítems, recursos publicados, equivalencias SOLO revisadas con vacío honesto,
prerrequisitos) y `/buscar` (debounce+abort, mínimo 3 letras como mensaje,
`aria-live`, URL compartible). El primer pintado reutiliza LOS MISMOS
métodos del `CurriculumController` (fuente de verdad); la API ganó
`has_items` (withExists) y orden curricular estable — aditivo.
Los 8 oráculos viven como tests (PHP: 1, 3, 5, 6, 7, 8; JS: 2, 4).

**Bucle C (auditor adversarial, subagente con la única instrucción de
refutar).** Encontró **9 defectos con ambas suites en verde**, 3 demostrados
ejecutando: `replaceState(null)` que rompía el botón Atrás de Inertia (ALTO),
`?q[]=` con 500, `q>120` que expulsaba de la app, un `prerequisite` firmado
disfrazado de «equivalencia» en inglés, orden lexicográfico (la MISMA
regresión que el PR #10 corrigió en el selector), resultados obsoletos por
abort tardío, duplicados/bucle muerto en «Cargar más», truncado UTF-16
asimétrico y código muerto en un test. **Los 9 corregidos** (commit
`98bc93c`), 3 con test nuevo de regresión. Y al medir el bundle para este
informe apareció el décimo, propio: el `import.meta.glob` metía los
`__tests__` en el bundle de producción (320→861 KB); excluidos (333 KB).

## 3. Qué NO funciona todavía / decisiones donde había duda

- **La sección de equivalencias sale vacía con los datos actuales y es
  correcto**: hay 0 alineaciones revisadas; el vacío lo dice con todas las
  letras y el test de la revisada-a-mano demuestra que la sección no está
  muerta.
- **El orden «natural» es longitud+alfabético**: perfecto dentro de una
  familia de códigos (CN.F.5.1.2 < CN.F.5.1.10), imperfecto entre familias
  cruzadas en un mismo listado (documentado en el comentario del código). El
  orden natural puro no existe portable en SQL sqlite/pgsql.
- **Los marcos Cambridge/IB se listan pero su árbol no es navegable desde
  /catalogo** (solo se llega a sus fichas vía equivalencias o búsqueda). La
  misión pedía el árbol de EC-MINEDEC; el resto quedó fuera a propósito.
- **Bucle D casi intacto**: no hay teclas rápidas, ni memoria de posición
  (localStorage está prohibido y la sesión no lo amerita aún), ni resaltado
  del término en resultados. La sesión se invirtió en los bucles B y C, que
  es donde estaba el valor.
- El test del oráculo 3 valida DIRECTORIOS con mayúscula; el case de archivos
  e imports queda cubierto indirectamente por el `npm run build` del CI Linux
  (hallazgo del auditor, mitigación aceptada).

## 4. Los números

- **PHP: 154/154** (1394 aserciones) — 137 previos intactos + 17 nuevos.
- **JS: 30/30** en 4 archivos (13 Practicar + 5 catalogo-nodo + 7 destreza + 5 buscar).
- **Bundle**: 333.83 KB (103.80 KB gzip) tras expulsar los tests del glob.
- **Pint** limpio, `npm run build` limpio, HTML inicial de /catalogo/{node}
  con 200 destrezas < 150 KB (test).
- Cobertura JS: no se midió formalmente en esta sesión (el reporter v8 está
  configurado en vitest.config.js; `npm run test:js -- --run --coverage`).
