# MISIÓN CURSO · SESIÓN LARGA · sin paradas

Repo `allyuhub` · base `main` con **#29 dentro** (`570a114`) · producción desplegada.

Este encargo está escrito para que trabajes **una sesión entera sin preguntar
nada**. Todas las decisiones que en la misión anterior quedaban «esperando a
Carlos» están tomadas aquí abajo. Si algo no está decidido, decídelo tú, déjalo
escrito en el informe bajo «decidí yo», y sigue. Parar a preguntar es el único
fallo que no quiero.

---

## §0 · TRES COSAS QUE CAMBIAN CÓMO TRABAJAS

### 1 · Mergear a `main` DESPLIEGA producción

El droplet mira `main` cada cinco minutos y, si avanzó, hace `git pull` y corre
`deploy/install.sh` solo. **Cada merge llega a `allyu.cuysoft.io` en menos de
cinco minutos y sin que nadie lo vea.** Consecuencias:

- **Tú no mergeas.** Abres PRs; mergea Carlos. Tu trabajo termina en «PR abierto
  y CI en verde».
- Una migración que rompa producción se despliega sola. **Migraciones aditivas y
  nullables, sin defaults con significado** — ya era la regla, ahora es la
  diferencia entre un PR y un incidente.
- `install.sh` corre `migrate --force` en el camino crítico y la siembra al
  final, no fatal. Lo que necesite sembrarse en producción tras un PR, lo dices
  en el informe con el comando exacto; lo corre Claude por SSH.

### 2 · PRs APILADOS: no esperes a que mergeen el anterior

Cada PR nace de la rama del anterior, no de `main`:

```
main ─┬─ pr1-cascaron ─┬─ pr2-memoria ─┬─ pr3-produccion ─┬─ pr4-interlocutor
```

Abres el PR 1 contra `main`, el PR 2 contra `pr1-cascaron`, y así. Cuando Carlos
mergea el 1, GitHub re-apunta el 2 a `main` solo. Si te piden cambios en el 1,
los haces en su rama y **rebasas la pila** — di en el informe que lo hiciste.

Así una sesión tuya produce cuatro PRs sin esperar a nadie.

### 3 · Presupuesto: el guardián del CI está ciego y lo arreglas ANTES de nada

Desde #29 el troceo deja `app-*.js` como solo el marco (315,63 KB). El guardián
del CI mide `app-*.js`. **Está mirando el archivo equivocado justo cuando
empiezan las cuatro entregas más cargadas de frontend.** Lo primero que haces
en el PR 1, antes de escribir una línea del cascarón:

- El guardián mide **el total de assets JS** del manifest. Techo **450 KB**.
- Y de propina, **un techo por página**: ninguna página sola supera **40 KB**
  (la más gorda hoy es `Practicar` con 16,36). Con esto el «seguro por
  circunstancia» de tu propio informe de #29 deja de serlo.

Reparto, corregido por el overhead del troceo (~4 KB): quedan **68**, no 72.
Asignados 46; **reserva 22**. Techos por PR: 1 → 14 · 2 → 4 · 3 → 16 · 4 → 12.
Lo que sobre de un PR vuelve a la reserva; lo que falte se toma de ella
**diciéndolo**.

---

## §1 · PR 1 · EL CASCARÓN DEL CURSO (rama `pr1-cascaron`)

### Contenido que entra con este PR

**Reemplaza `database/data/banco-lenguas.php` por el que te da Carlos** (U1 y U2
de italiano: 4 lecciones, 17 ítems, todos los descriptores con ≥2 ítems). Es el
que sustituye a tu andamio de #28: corrige el `orden` que se resolvía por la
puntuación, quita el *Sono dell'Ecuador*, y mete la regla de la c y la g. Si al
sembrarlo en tu suite algo revienta, arréglalo en el fichero y dilo — pero el
contenido es de Carlos, no lo reescribas.

### Las rutas

**`/corso/{lengua}`** — la portada del curso. Nueve unidades. El estado —cuál está
abierta, cuál terminada, dominio agregado por unidad, racha, y **la única cosa
que hacer ahora**— se calcula en **PHP** y llega como props. React pinta.

**`/corso/{lengua}/u{n}`** — la unidad: sus «Puedo…» (los descriptores MCER,
pintados como objetivos del alumno), su lección, sus ejercicios, su tarea.

El mapa unidad → descriptores está en `ESQUELETO-9-UNIDADES.md` de Carlos. Para
U1 y U2 los descriptores son los que ya usa el banco (`A1.CO.2`, `A1.IO.3`,
`A1.CE.1`, `A1.EE.2`, `A1.PO.1`, `A1.CE.2`). Para U3–U9 **usa los mismos 13
descriptores de `cefr-a1.php` repartidos según el esqueleto**; una unidad sin
contenido sembrado se pinta como «próximamente», nunca como vacía.

### El cabo suelto de #28, cerrado aquí

`/destreza/{id}` no filtra recursos por lengua. Hoy es inofensivo porque solo hay
italiano; deja de serlo el día que entre francés. **Ciérralo igual que los ítems:
cerrado en las dos direcciones.** Con dos lenguas sembradas en el test, no una —
una sola lengua hace pasar cualquier test de separación.

### Lo que va y lo que no (decidido, no se re-discute)

VA: el dominio como única barra de progreso (ya existe, ya es honesto); la racha
— **regla fijada: se rompe con 3 días naturales sin actividad, no con 1**, un
fin de semana no castiga; el siguiente paso siempre uno y siempre visible; el
error como información.

NO VA: XP paralelo al dominio, vidas o corazones, temporizadores de castigo,
rankings entre alumnos. Si crees que algo de eso debe ir, no lo pongas: escribe
el argumento en el informe.

**El invitado ve todo esto sin sesión y sin escribir una fila.** Regla de oro.

---

## §2 · PR 2 · LA MEMORIA (rama `pr2-memoria`)

Repaso espaciado. Casi todo backend. Las tres decisiones, tomadas:

1. **Se repasa el DESCRIPTOR, no el ítem.** Otro ítem del mismo descriptor. Por
   eso el banco exige ≥2 por descriptor; si un descriptor tiene uno solo, no
   entra en la cola de repaso y el informe lo lista.
2. **El repaso NO cuenta para la nota AGS.** Sí cuenta para el dominio (es
   práctica real). Una nota que sube repasando lo sabido es una nota inflada.
3. **Techo por sesión: 12 repasos.** Lo que venza de más espera. Una cola de 200
   el lunes es cómo se abandona un curso.

Algoritmo: **el mínimo que funciona**. Intervalo que crece ×2 con el acierto
(1, 2, 4, 8, 16, 32 días) y vuelve a 1 con el fallo. Sobre `ObjectiveMastery` y
`practice_attempts`, que ya tienen todo lo que hace falta. Nada de importar SM-2
con sus factores de facilidad: primero mide, luego sofistica.

En el cascarón: una tarjeta «te tocan N repasos» que lleva a `Practicar.jsx`,
que ya sabe los siete tipos. Es lo único de cliente.

---

## §3 · PR 3 · QUE EL ALUMNO PRODUZCA (rama `pr3-produccion`)

### La decisión de retención — TOMADA, construye contra esto

Voz y texto de menores:

- Se guardan **un año lectivo** y se borran al cierre. Sobrevive la nota del
  docente y su comentario; la grabación no. Un comando `producciones:purgar`
  que lo haga, con `--dry-run`, y que **liste** lo que borraría antes de borrar.
- Las ven **el alumno que las hizo y los docentes de su curso**. Nadie más.
  `Gate` explícito, con test de que un docente de OTRO curso recibe 403.
- **Nunca** en el almacén público de audio. Almacén propio bajo `storage/app/
  producciones/`, servido por una ruta con `auth` y el Gate, **nunca** por una
  ruta estática. Test: la URL de una grabación sin sesión es 401/403, no 200.
- El alumno **puede borrar la suya** mientras no esté corregida.
- No salen del sistema: ni a terceros, ni a entrenar nada.

### Las dos vías

**Escritura** — tres o cuatro frases, `textarea`, se encola. **Voz** —
`MediaRecorder` nativo, 20-30 s, estados permiso/grabando/revisar/enviado, se
encola. Cero librerías.

**La cola del docente** — lo pendiente de su curso, la rúbrica de la unidad al
lado (4 criterios × 3 niveles, viene del contenido, no se hardcodea), dos frases
de vuelta. Sin esto la producción es un buzón sin fondo.

**El motor NO corrige producción.** Solo la guarda y la encola.

---

## §4 · PR 4 · EL INTERLOCUTOR (rama `pr4-interlocutor`)

### La decisión que evita tres problemas de golpe: GUIONIZADO, no LLM

La primera versión del interlocutor **no usa un modelo de lenguaje**. Es un
diálogo ramificado por unidad, escrito en el banco de contenido, con las
respuestas del interlocutor **acotadas al vocabulario de la unidad** por
construcción, no por prompt.

Por qué esto es mejor y no un recorte:

1. **Sin API key.** Un LLM necesita una clave de un tercero en `.env` de
   producción, y hoy nadie la puede poner sin pasar por una consola.
2. **Sin datos de menores en un tercero.** Una conversación de un chico de 15
   años no sale del servidor. Se cierra de raíz la pregunta de retención en
   terceros.
3. **Sin alucinaciones fuera de nivel.** El interlocutor que contesta con
   palabras de la U7 en la U2 es un muro; el guion no puede hacerlo.

Formato en el banco: un `dialogo` por unidad con **nodos**, cada nodo con lo que
dice el interlocutor (texto + clip por clave, como el resto del audio) y 2-3
**respuestas posibles del alumno**, cada una con el nodo al que lleva. Los
callejones vuelven al nodo anterior con una pista, no con un error. La U1 de
italiano trae un guion de ejemplo de 6-8 nodos sobre la escena *Il primo
giorno* — escríbelo tú siguiendo el vocabulario de la U1 de Carlos y dilo en el
informe para que él lo revise.

**El interlocutor no evalúa.** Registra que se completó (para el dominio de
`A1.IO.1`), nada más.

Un motor LLM detrás del mismo formato es un PR futuro, si algún día tiene
sentido. La interfaz de este PR no debe hacerlo imposible ni obligatorio.

---

## ORÁCULOS GLOBALES — se heredan en los cuatro

1. **No se filtra la solución** en ninguna vía nueva, sobre el cuerpo
   serializado completo, centinela sin acentos, control positivo, recorriendo
   `Registro::kinds()`.
2. **Regla de oro**: el invitado usa TODO lo nuevo y no escribe ni una fila ni
   encola AGS. Delta cero + `Queue::assertNotPushed`. En cada PR.
3. **Nada se publica sin firma**; toda vía nueva nace con `reviewed_at` nulo.
4. **Toda columna que gobierne visibilidad, corrección o retención falla
   cerrada.**
5. **Lengua cerrada en las dos direcciones** en toda vía nueva, **con dos lenguas
   sembradas** en el test.
6. **Presupuesto**: total ≤ 450 KB y ninguna página > 40 KB, medidos por el
   guardián nuevo del CI, no por tu informe.
7. **axe** con y sin sesión: cero serious/critical. **Teclado**: todo lo nuevo
   se completa sin ratón.
8. **PostgreSQL en verde.** Recuerda #29: `NULL` ordena distinto; todo `orderBy`
   sobre columnas nullables va explícito.
9. **Cero tests risky.**
10. **Migraciones aditivas y nullables, sin defaults con significado.** Ahora
    se despliegan solas.

## BUCLES — en cada PR

- **A** · oráculo rojo antes que código.
- **B** · mutación por frente, tabla contra el HEAD final. Si sale todo muerto a
  la primera dos PR seguidos, sospecha de tus mutaciones y dilo.
- **C** · auditor adversarial. Focos: una vía por la que se escape algo (recorre
  TODAS de una vez); una regla en un sitio y no en su hermano; **algo seguro por
  circunstancia** — di cuál es la circunstancia y cuándo deja de valer; un
  default permisivo; un fixture demasiado cómodo.

## PROHIBIDO

Mergear. Librerías nuevas. Meter la solución en algo que se serializa. Publicar
sin firma. Migraciones destructivas o con defaults. Guardar voz o texto de
alumnos en el almacén público. Un LLM en el interlocutor de esta tanda. Que el
interlocutor ponga nota. Reescribir el contenido de Carlos. Textos en inglés.
`Pages` con mayúscula. **Parar a preguntar.**

## ENTREGA

Cuatro PRs apilados, cada uno con su informe: tabla de mutaciones primero; lo
que decidiste tú bajo un epígrafe «decidí yo»; la prueba de la regla de oro
citada; bundle total y por página contra tu techo; qué quedó fuera; **qué es
seguro hoy por circunstancia**; y los comandos de siembra que producción
necesite tras el merge.

Y en cada uno, el estado de los **CHECKS de GitHub**, no tu resumen.
