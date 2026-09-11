# MISIÓN CURSO · SESIÓN LARGA 3 · lo dinámico, sin audio

Repo `allyuhub` · base `main` con **#39 dentro** (`76c6097`, el chino) y, cuando
Carlos lo mergee, el PR de contenido de esta sesión (guiones ×36, banco ×484,
vocabulario). Producción desplegada con **cuatro cursos** MCER publicados y
firmados para evaluación: `/corso/it`, `/fr`, `/de`, `/zh`, más `/corso/en`
(Cambridge, cascarón sin contenido).

Misma regla: **una sesión entera sin preguntar nada.** Lo no decidido lo
decides tú, lo escribes bajo «decidí yo», y sigues.

**Decisión de Carlos para esta sesión, literal:** «avancemos con el tema de
ejercicios y tests prácticos y demás cosas dinámicas y didácticas como en Khan;
olvidémonos de vídeos, audios e imágenes hasta el último; pueden quedar las
secciones donde van audios y vídeos pero esos los generamos al último».
Traducido: **nada de audio, vídeo ni imágenes en esta sesión**, pero el molde
tiene que dejar el hueco declarado para que entren al final sin tocar código.

---

## §0 · LO QUE CAMBIÓ DESDE TU ÚLTIMA SESIÓN

1. **El contenido ya no es el cuello de botella.** `banco-lenguas.php`: 60
   lecciones y **484 ítems** (121 por lengua, ~13 por unidad, misma cobertura en
   las cuatro: 10 de 13 descriptores, ≥2 ítems cada uno). `dialogos-lenguas.php`:
   **36 guiones**, uno por unidad y lengua. Todo sembrado y firmado en producción
   por Carlos para evaluación. Lo que falta son **modos de práctica**, no
   ejercicios.
2. **Los tres descriptores de comprensión oral (A1.CO.1/2/3) siguen sin ítems**
   en las cuatro lenguas, a propósito: sin audio no hay escucha. Los ítems están
   escritos (`docs/mision-lenguas/*-audio-pendiente.md`) y entran al final.
3. **Chino**: los `pares` a tres columnas ya se sirven y corrigen en producción;
   los `hueco` aceptan pinyin con tonos, con números y carácter. Un tono que
   falta es `detalle: 'acento'` para el motor. En la interfaz eso se lee mal.
4. Presupuesto del manifest: mide al empezar y repártelo. Regla: ≤ 450 total,
   ≤ 40 por página. Cuatro pantallas nuevas en esta sesión: no más de 8 KB cada
   una, y la prueba de unidad **reutiliza `Practicar.jsx`**, no lo copia.
5. Siguen vigentes: PRs apilados, **al mergear borrar la rama**, informe por PR
   con CHECKS de GitHub.

---

## §1 · PR 8 · LA PRUEBA DE UNIDAD (rama `pr8-prueba-unidad`)

Khan tiene «practice» y tiene «unit test». Nosotros solo tenemos el primero.

### Lo que tiene que existir

**`/corso/{lengua}/u{n}/prueba`** — una tanda de **10 ítems** de esa unidad y
esa lengua, con estas reglas, decididas:

- Se eligen entre los ítems **firmados** de los descriptores de la unidad
  (`cursos-lenguas.php`), **repartidos entre descriptores** (ningún descriptor
  con más de la mitad de la prueba mientras haya otros con ítems) y **sin repetir
  ítem** dentro de la prueba. Semilla por (usuario, unidad, número de intento),
  como el `seed` de práctica: dos alumnos no ven la misma prueba en el mismo
  orden, y el mismo alumno que repite ve otra.
- **Sin pista y sin veredicto hasta el final.** En práctica se corrige ítem a
  ítem; en la prueba se contesta todo, y al enviar se ve la nota: aciertos /
  10, y **el desglose por descriptor** («Puedo decir la hora: 2 de 3»).
- La corrección es **la misma pieza** que en práctica: `Tipo::corregir`, con el
  mismo billete por ítem. No dupliques ni un normalizador.
- **Aprobada con ≥ 8 / 10.** Una prueba aprobada marca la unidad como
  «completada» en el mapa del curso (estado nuevo, además de disponible /
  en-curso / próximamente). Suspendida: se puede repetir sin límite; la
  siguiente lleva otra semilla.
- Cada respuesta de la prueba **cuenta como intento de práctica** para el
  dominio y el AGS (misma transacción que hoy), porque es el mismo ítem y el
  mismo motor. Lo que la prueba añade es la fila `pruebas_unidad`
  (usuario, lengua, unidad, intento, nota, desglose, `completed_at`).
- **Invitado**: hace la prueba entera, ve la nota, **no escribe ni una fila**.
  `se_guarda: false`, como en práctica.

### Lo que NO es

No es un examen de curso, no tiene tiempo límite, no tiene nota en letras, no
bloquea la unidad siguiente. Khan no bloquea; nosotros tampoco.

---

## §2 · PR 9 · REPASO DIARIO Y RACHA (rama `pr9-repaso`)

Existe `GET api/v1/practice/repasos` y existe la racha en el cascarón. Lo que
no existe es **un sitio donde el alumno vaya cada día**.

### Lo que tiene que existir

**`/corso/{lengua}/repaso`** — «Tu repaso de hoy»: hasta **10 ítems** elegidos
así, en este orden de prioridad:

1. Descriptores **dominados hace más tiempo** sin intento reciente (el repaso
   espaciado que `repasos` ya calcula; si calcula otra cosa, dilo y ajusta).
2. Descriptores **con fallos recientes** (último intento incorrecto).
3. Relleno con ítems no vistos de unidades disponibles.

Se juega **como la práctica** (corrección ítem a ítem, pista, veredicto), no
como la prueba. Al terminar: «Repaso hecho: N/10» y la **racha** sube si es el
primer repaso del día (zona horaria de Ecuador, decidido). La racha se rompe
si pasa un día natural sin repaso ni práctica; se muestra en la portada del
curso con el número y nada más — sin fuego, sin confeti.

**Invitado**: puede jugar el repaso «genérico» (relleno de la prioridad 3);
no tiene racha porque no tiene historia. No escribe nada.

---

## §3 · PR 10 · VOCABULARIO POR UNIDAD (rama `pr10-vocabulario`)

Carlos entrega en este mismo PR de contenido **`database/data/vocabulario-lenguas.php`**:
por lengua y unidad, la lista de palabras de esa unidad con esta forma:

```php
['lengua' => 'zh', 'unidad' => 1, 'clave' => 'nihao',
 'palabra' => '你好', 'lectura' => 'nǐ hǎo',          // `lectura` solo en zh (pinyin); en de lleva el artículo en `palabra`
 'significado' => 'hola', 'ejemplo' => ['zh' => '你好，李明！', 'es' => '¡Hola, Li Ming!'],
 'clip' => 'zh/u1/vocab/nihao']                        // declarado, sin fichero: como en los diálogos
```

### Lo que tiene que existir

- **Sembrador `vocabulario:sembrar`** idempotente por (lengua, unidad, clave),
  transaccional, con el mismo trato del clip que `dialogos:sembrar`: se
  declara, se engancha si el fichero está, y se avisa de los que faltan. Nace
  **sin firmar** y se firma con `vocabulario:firmar --lengua=` y desde
  `/docente/revisar` (misma pieza de firma; no la dupliques).
- **`/corso/{lengua}/u{n}/vocabulario`** — tarjetas: anverso la palabra (en zh,
  el carácter grande y el pinyin debajo; en de, con artículo), reverso el
  significado y el ejemplo. Dos botones: **«La sé» / «Todavía no»**. Lo que el
  alumno marca se guarda por (usuario, tarjeta): `vocab_estado` con
  `conocida_at` nullable. La tarjeta marcada «Todavía no» vuelve al final del
  mazo en la misma sesión; una «La sé» sale del mazo hasta el siguiente día.
- En la portada de la unidad: «Vocabulario: 12 / 17 palabras» y el enlace.
- **Invitado**: ve las tarjetas, las voltea, los botones funcionan en memoria
  y no escriben nada.
- **Un ítem nuevo NO**: el vocabulario no es un tipo de práctica y no da
  dominio. Es material de apoyo; el dominio lo dan los ítems.

---

## §4 · PR 11 · LOS HUECOS DECLARADOS Y «TONO» (rama `pr11-huecos`)

Pequeño, y es el que hace verdad la frase de Carlos «pueden quedar las
secciones donde van audios y vídeos».

1. **Bloques de lección `audio` y `video` con `pendiente => true`.** Hoy un
   bloque `audio` con clip sin fichero aborta la siembra (bien: sin audio no
   hay escucha). Añade la forma `['tipo' => 'audio', 'clip' => 'zh/u1/fayin',
   'pendiente' => true, 'texto' => [...]]` que **siembra sin fichero** y se pinta
   como un hueco honesto: el texto del bloque (la transcripción) visible y una
   marca «audio pendiente», sin reproductor roto. Lo mismo para `video`
   (`src` o `pendiente`). Al re-sembrar con el fichero presente, el bloque deja
   de ser pendiente sin tocar el banco. Los ítems `escucha` y `dictado` NO
   cambian: siguen exigiendo el clip.
2. **«tono» en chino.** Cuando `lengua === 'zh'` y el veredicto trae
   `detalle: 'acento'`, la interfaz dice «te falta el tono» y no «acento». El
   motor no cambia (el detalle sigue siendo `acento`): es copy, y vive en un
   solo sitio.
3. **Pinyin con números en la pista.** En zh, el texto de ayuda del `hueco`
   recuerda que se acepta `ni3 hao3` y el carácter. Ya está en las consignas;
   que esté también en la interfaz del hueco, una vez, discreto.

---

## ORÁCULOS GLOBALES — se heredan y se amplían

1. No se filtra la solución en ninguna vía nueva: **la prueba de unidad
   entrega 10 ítems sin `solucion`**, y el oráculo lo recorre por
   `Registro::kinds()` en las cuatro lenguas.
2. Regla de oro: el invitado no escribe ni una fila — en la prueba, en el
   repaso, en el vocabulario. En cada PR, con su test.
3. Nada se publica sin firma; nada se des-firma sin nota. **El vocabulario
   entra en la misma regla.**
4. Toda columna que gobierne visibilidad falla cerrada.
5. Lengua cerrada en las dos direcciones, con las cinco lenguas: una prueba
   de `/corso/it/u3/prueba` no contiene ni un ítem de `fr`.
6. Presupuesto por el guardián del manifest: ≤ 450 total, ≤ 40 por página.
7. axe + teclado en cada pantalla nueva. Las tarjetas se voltean con teclado.
8. PostgreSQL en verde; `orderBy` explícito sobre nullables.
9. Cero tests risky.
10. Migraciones aditivas y nullables. Se despliegan solas.
11. **Los cuatro cursos existentes no cambian**: el test recorre `/corso/it`,
    `/fr`, `/de`, `/zh`, sus nueve unidades y sus 36 diálogos antes y después.
12. **Nuevo — la prueba es justa:** con la misma semilla, la misma prueba; con
    otra, otra. Nunca repite ítem dentro de una prueba. Nunca un descriptor
    acapara más de la mitad si hay otros con ítems firmados.
13. **Nuevo — la nota es la misma corrección:** para cada tipo, la respuesta
    correcta en práctica es correcta en la prueba y la incorrecta, incorrecta.
    Un test recorre `Registro::kinds()` con un ítem de cada tipo en las dos vías.
14. **Nuevo — el banco de Carlos siembra entero**: 60 lecciones, 484 ítems,
    36 diálogos y todo el vocabulario, en SQLite y en pgsql, y la cuenta exacta
    aterriza.

## BUCLES — A, B, C en cada PR. Foco nuevo del C: **una regla de práctica que la
prueba de unidad rompa por copiarla mal** (el billete, la semilla, el
`se_guarda`, el 200-no-201 del invitado). Si la prueba tiene su propio camino
de corrección, está mal aunque el CI esté en verde.

## PROHIBIDO

Mergear. Librerías. Audio, vídeo o imágenes generados o incrustados. Un
reproductor que apunte a un fichero que no existe. Bloquear la unidad
siguiente. Tiempo límite en la prueba. Un tipo de ítem nuevo para el
vocabulario. Copiar `Practicar.jsx`. Textos en inglés en la interfaz. Parar a
preguntar.

## ENTREGA

Cuatro PRs apilados (8 → 9 → 10 → 11), cada uno con su informe: mutaciones,
«decidí yo», regla de oro, bundle, qué es seguro por circunstancia, comandos
de siembra post-merge. **Y en cada uno: «al mergear, borrar la rama».**

CHECKS de GitHub, no tu resumen.
