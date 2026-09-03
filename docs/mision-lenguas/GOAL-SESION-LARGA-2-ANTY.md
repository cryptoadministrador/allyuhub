# MISIÓN CURSO · SESIÓN LARGA 2 · sin paradas

Repo `allyuhub` · base `main` con **#34 dentro** (`224f1a9`) · producción desplegada
y con TRES cursos publicados: `/corso/it`, `/corso/fr`, `/corso/de`.

Misma regla que la sesión anterior: **una sesión entera sin preguntar nada**.
Todas las decisiones están tomadas abajo. Lo que no esté decidido, lo decides
tú, lo escribes bajo «decidí yo», y sigues.

---

## §0 · LO QUE CAMBIÓ DESDE TU ÚLTIMA SESIÓN

1. **Mergear a `main` despliega producción** en menos de 5 minutos (timer en el
   droplet). Tú no mergeas; abres PRs.
2. **PRs apilados, y esta vez SÍ se borra la rama al mergear.** La sesión pasada
   no se borraron y los PRs 2-4 acabaron en sus ramas base en vez de en `main`;
   hizo falta un #34 para arreglarlo. Dilo en cada informe: «al mergear, borrar
   la rama».
3. `database/data/banco-lenguas.php` en el árbol de trabajo trae ahora **tres
   lenguas** (it, fr, de: 45 lecciones, 186 ítems) y pronto **cuatro** (zh).
   Es de Carlos. Producción se sembró desde una copia en `storage/app/`; el
   fichero del repo va **por detrás** de producción hasta que lo commitees.
   **Primera tarea del PR 5: commitear ese fichero tal cual** y que la suite
   siembre las cuatro lenguas (o las que haya) sin reventar.
4. Presupuesto: total **396 KB / 450**, página más gorda 16 KB / 40. Quedan
   **54 KB** para esta sesión. Reparto: PR 5 → 14 · PR 6 → 10 · reserva 30.

---

## §1 · PR 5 · LA REVISIÓN DOCENTE EN PANTALLA (rama `pr5-revision`)

Hoy firmar contenido es `php artisan practica:firmar --bloque=A1.IO.it` por SSH.
**Ningún profesor de italiano va a hacer eso.** Y la regla de la casa es que
antes del primer alumno lo lee un profesor. Sin esta pantalla, esa regla es
mentira.

### Lo que tiene que existir

**`/docente/revisar?lengua=it`** — lo pendiente de firma de esa lengua: lecciones
e ítems, agrupados por unidad y descriptor, con **contador de cuántos faltan**.
Cada pieza se abre **tal como la ve el alumno** (reutiliza `Recurso.jsx` y
`Practicar.jsx`; no inventes un visor de revisión) y tiene tres acciones:

- **Firmar** → `reviewed_at` + `reviewed_by` con el docente real. Por fin con
  autoría; hoy todo dice «sin autoría registrada».
- **Devolver** → una nota corta obligatoria («el ejemplo 3 está mal»). La pieza
  sigue sin firmar y la nota queda visible para quien la corrija.
- **Firmar la unidad entera** → solo si está TODO leído: cada pieza abierta al
  menos una vez en esa sesión. No es un `--todo`: es un atajo que exige haber
  mirado.

Y **des-firmar**: lo que un docente firmó puede retirarlo él u otro docente,
con nota. Un error publicado tiene que poder salir de pantalla en un clic.

### Quién puede

Un docente (instructor en algún contexto LTI) ve y firma **todas las lenguas**.
No hay «profesor de italiano» en el modelo y no lo inventes ahora: la
responsabilidad es del que firma, y queda su nombre. **Un alumno recibe 403**,
no una redirección — y un invitado, lo mismo.

### El motor no cambia

`SignPracticeItems` y `SignLessons` siguen existiendo. La pantalla llama a **la
misma pieza** que los comandos; no dupliques la regla de firma. Si al hacerlo
descubres que la regla vive en dos sitios, unifícala y dilo.

---

## §2 · PR 6 · CAMBRIDGE INGLÉS ENTRA AL GRAFO (rama `pr6-cambridge-en`)

Carlos lo dijo el primer día: «necesitamos enfocarnos en Cambridge en todos los
niveles» — y Cambridge es **inglés**, un frente aparte de las cuatro lenguas.

### Lo que hay y lo que falta

Existen en el grafo CAIE-LSEC, CAIE-IGCSE y CAIE-ASA, **todos de STEM**. Falta la
**lengua inglesa** de Cambridge, en sus niveles:

- Cambridge Primary English (Stages 1-6)
- Cambridge Lower Secondary English (Stages 7-9)
- IGCSE English as a Second Language (0510 / 0511) y First Language (0500)
- AS & A Level English Language (9093)

**Cotejados contra el syllabus oficial vigente**, con `source_url` por nodo,
`is_verified=false` (son paráfrasis de documentos con copyright, como el resto
de CAIE) y **sin inventar códigos**: si un objetivo no está en la fuente, no va.

### La decisión de diseño: inglés es la quinta lengua del cascarón

`Lenguas::LISTA` += `'en'`. **`/corso/en`** existe con el mismo cascarón que los
otros cuatro. Pero el anclaje NO es el MCER: **es CAIE**. Un alumno de inglés
avanza por Stages y por syllabus, que es lo que el colegio certifica.

Eso obliga a una generalización que hoy no existe: **el cascarón asume que un
curso son 9 unidades sobre 13 descriptores A1**. Para inglés, un curso es un
Stage sobre sus strands. Generaliza `CursoController` para que un curso declare
su marco y su estructura de unidades, en vez de tenerlos escritos dentro. Es
la parte del PR con más riesgo de romper `it/fr/de`: **los tres cursos
existentes tienen que seguir idénticos**, con un test que recorra los tres.

Y el **mapeo Cambridge ↔ MCER** (Primary ≈ pre-A1/A1, Lower Secondary ≈ A2/B1,
IGCSE ESL ≈ B1/B2, AS/A ≈ C1) entra como **crosswalk**, con la misma disciplina
que los que ya existen: `reviewed_at` nulo hasta que alguien lo coteje.

### Contenido: NO

Tú importas el marco y construyes el cascarón. **No escribes lecciones ni
ítems de inglés**: eso es de Carlos, como las otras cuatro lenguas. `/corso/en`
nace con «próximamente» en todas las unidades.

---

## §3 · PR 7 · LOS GUIONES DE LAS OTRAS LENGUAS (rama `pr7-guiones`)

El interlocutor existe pero solo tiene un guion: U1 de italiano. Hacen falta
**los guiones de la U1 de francés y alemán** (el chino, cuando Carlos entregue
su banco), escritos **sobre el vocabulario exacto de la U1 de cada lengua** que
está en `banco-lenguas.php` — 6-8 nodos cada uno, la escena del primer día.

Los escribes tú, nacen sin firmar, y **el informe los cita enteros** para que
Carlos los lea. Y una cosa que la U1 de italiano no tenía: cada nodo del
interlocutor lleva su **clip por clave** (`it/u1/dialogo/n1`), aunque el fichero
no exista aún — que el guion esté listo para el audio el día que llegue.

Este PR es pequeño y es el que menos KB gasta. Si el 5 o el 6 se alargan, este
se hace igual: es contenido que un profesor tiene que revisar y cuanto antes
exista, antes lo lee.

---

## ORÁCULOS GLOBALES — se heredan

1. No se filtra la solución en ninguna vía nueva; el oráculo recorre
   `Registro::kinds()`.
2. Regla de oro: el invitado no escribe ni una fila. En cada PR.
3. Nada se publica sin firma. **Y ahora al revés: nada se des-firma sin nota.**
4. Toda columna que gobierne visibilidad falla cerrada.
5. Lengua cerrada en las dos direcciones, **con las cinco lenguas** en el test
   de separación.
6. Presupuesto por el guardián del manifest: ≤ 450 total, ≤ 40 por página.
7. axe + teclado en cada pantalla nueva.
8. PostgreSQL en verde; `orderBy` explícito sobre nullables.
9. Cero tests risky.
10. Migraciones aditivas y nullables. Se despliegan solas.
11. **Los tres cursos existentes no cambian**: un test recorre `/corso/it`,
    `/corso/fr`, `/corso/de` y sus nueve unidades antes y después del PR 6.

## BUCLES — A, B, C en cada PR, como siempre. Foco nuevo del C: **una regla que
el cascarón tenga escrita dentro y que el inglés rompa** (nueve unidades, trece
descriptores, código `A1.*`). Si existe, el PR 6 la saca; si no la sacas, el PR 6
está mal aunque el CI esté en verde.

## PROHIBIDO

Mergear. Librerías. Escribir contenido de inglés. Inventar códigos de Cambridge.
Un rol «profesor de italiano». Que des-firmar no deje rastro. Textos en inglés
en la interfaz (la interfaz sigue en español aunque el curso sea de inglés).
Parar a preguntar.

## ENTREGA

Tres PRs apilados, cada uno con su informe: mutaciones, «decidí yo», regla de
oro, bundle, qué es seguro por circunstancia, comandos de siembra post-merge. Y
en el 7, los guiones enteros. **Y en cada uno: «al mergear, borrar la rama».**

CHECKS de GitHub, no tu resumen.
