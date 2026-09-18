# MISIÓN CURSO · SESIÓN LARGA 4 · que se pueda tocar

Repo `allyuhub` · base `main` en `dd46323` (misión 3 dentro: prueba de unidad,
repaso diario con racha, 624 tarjetas de vocabulario, huecos declarados).
Producción desplegada y sembrada: cuatro cursos MCER (`/corso/it|fr|de|zh`), 60
lecciones, 484 ítems, 36 diálogos, 624 tarjetas — todo firmado para evaluación.

Misma regla de siempre: **una sesión entera sin preguntar nada.** Lo no decidido
lo decides tú, lo escribes bajo «decidí yo», y sigues.

---

## §0 · POR QUÉ ESTA MISIÓN, Y DE DÓNDE SALE

Carlos preguntó lo que había que preguntar: *«pero aún no está con simuladores ni
actividades dinámicas didácticas y muy entretenidas, ¿o sí?»*. La respuesta,
mirando el código y no el informe, es **no**:

- Los siete tipos de ejercicio son **todos de texto**.
- `orden` y `pares` se juegan **a clic sobre una lista**, no sobre un tablero.
- **Cero imágenes** en las 60 lecciones.
- **Cero animación**: en toda la interfaz hay once `transition-shadow` de hover
  y nada más. Ni un sonido, ni una celebración.
- El interlocutor es lo más vivo que existe, y se juega como texto plano.

Es riguroso, está bien probado y es **austero**. A un alumno de 1.º BGU le
parece un cuaderno digital bien hecho, no algo que quiera abrir por gusto.

**La referencia que pidió Carlos: Synthesis Tutor** (synthesis.com, el tutor de
matemáticas nacido en SpaceX, 5-11 años). Lo que hace, en concreto:

- Una lección de **~10 minutos** con dos piezas: un chat donde el tutor habla y
  un **«ÁREA COMPARTIDA»** — un objeto visual que el niño MANIPULA mientras el
  tutor le guía.
- El niño responde con **botones acotados** o entrada numérica, no con texto
  libre a pelo.
- Si falla o tarda, el tutor **interviene con ayuda** en vez de pasar de largo.
- El ejercicio de cierre **se repite hasta que responde bien**.

Y la crítica de Andy Matuschak, que vale más que el elogio y que es la que
gobierna esta misión: su ramificación es **simple, comparable a sistemas de los
años 70**; se puede completar una lección **sin haber entendido el concepto**
porque falta diagnóstico fino; y la narración tiene **bajo impacto emocional**,
con una voz sintética que suena condescendiente.

### Las tres cosas que sacamos de ahí

1. **El área compartida es la diferencia estructural.** Nuestros ejercicios son
   *consigna + campo*. Los suyos son *consigna + una cosa que tocas*. Y nosotros
   YA TENEMOS EL MATERIAL: `orden` es una frase que se construye, `pares` es un
   tablero de dos o tres columnas. Falta que se PINTEN como un tablero.
2. **El bucle de «otra vez» en vez de «incorrecto, siguiente».** Es barato y
   cambia la sensación entera.
3. **En diagnóstico fino ya les ganamos, y lo desaprovechamos.** Nuestro
   veredicto distingue «te falta el acento» de «esa palabra no es», y en chino
   «te falta el tono». Hoy eso se enseña una vez y se pasa de largo. Es
   exactamente lo que Matuschak echa en falta en Synthesis: úsalo.

**Decisión de Carlos para esta sesión, literal:** «juego y ritmo, sin medios».
Es decir: **NADA de audio, vídeo ni imágenes generadas**, otra vez. Todo lo de
esta misión se hace con el dato que ya está sembrado y con CSS.

---

## §0 bis · EL PRESUPUESTO SUBE, Y SIGUE HABIENDO GUARDIÁN

Hoy: **425 / 450 KB** totales, y el guardián del manifest hace fallar el CI si
se pasa. Con 25 KB de margen no cabe nada de esto.

**Techo nuevo, decidido: 550 KB totales y 60 KB por página.** El guardián SIGUE
EXISTIENDO con los números nuevos — subir el techo no es quitarlo. Mide al
empezar, reparte entre los cuatro PRs y di en cada informe cuánto gastaste.

**Y no se instalan librerías.** Arrastrar se hace con eventos de puntero; la
animación, con CSS. Si crees que algo obliga a una librería, es que ese algo
está mal planteado: escríbelo bajo «decidí yo» y haz la versión que no la
necesita.

---

## §1 · PR 12 · EL TABLERO (rama `pr12-tablero`)

Que `orden` y `pares` dejen de ser listas de botones y sean el área compartida.

### Lo que tiene que existir

- **`orden`**: las fichas en un banco abajo y **la frase construyéndose arriba**,
  visible como frase. Se arrastra una ficha al sitio, o se reordena dentro de la
  línea. La frase a medio construir se lee entera en todo momento — eso es el
  ejercicio: ver cómo queda.
- **`pares`**: un tablero de dos o tres columnas (tres en chino: carácter ·
  pinyin · significado). Se une tocando un elemento y luego su pareja, y **la
  unión se ve** — línea, color compartido o fichas que se juntan, tú decides.
  Deshacer una unión es tocarla.
- **`hueco`**: la frase con el hueco **en su sitio dentro de la frase**, no una
  consigna con `___` al final. El campo vive dentro del texto.

### LA REGLA QUE NO SE NEGOCIA: se juega entero con teclado

Arrastrar y soltar **sin camino de teclado es inaccesible**, y aquí hay alumnos
con tableta, con teclado y con lector de pantalla. Lo bueno es que la salida es
fácil y ya está escrita: **la interacción a clic que existe HOY es el camino
accesible**. Arrastrar se AÑADE ENCIMA, no sustituye. Un tablero donde solo se
pueda arrastrar es un PR mal hecho aunque el CI esté verde.

axe limpio y recorrido completo con teclado en los tres tableros, con test.

### Microanimación, con freno

Acierto: un pulso corto de color. Fallo: una sacudida breve. Nada más, y todo en
CSS. **Respeta `prefers-reduced-motion`**: quien lo tenga puesto ve los mismos
estados sin movimiento. Sin sonido — eso es otra misión.

---

## §2 · PR 13 · EL BUCLE DE «OTRA VEZ» (rama `pr13-otra-vez`)

Hoy: fallas, te decimos que fallaste, siguiente. Es lo que hace que esto se
sienta como un examen y no como practicar.

### El bucle, decidido

1. **Primer fallo** → la pista que sale del veredicto que YA calculamos:
   «te falta el acento», «te falta el tono» (zh), «esa palabra no es», «tienes
   3 de 5 parejas». Y se reintenta el mismo ítem.
2. **Segundo fallo** → **andamiaje**, que es lo de los «botones acotados» de
   Synthesis: el `hueco` de texto libre se convierte en tres opciones (la
   correcta y dos distractores sacados del propio ítem o de su unidad); el
   `orden` marca cuál va primero; el `pares` deja en el tablero solo las que
   están mal.
3. **Tercer fallo** → se muestra la respuesta con su explicación y el ítem queda
   marcado para el repaso de mañana.

### LA REGLA DE CRÉDITO, y es la parte delicada

**Solo el PRIMER intento alimenta el dominio y la nota AGS.** Un acierto al
tercer intento es un acierto para el alumno y una mentira para el dominio. Si no
se separa, `MasteryTracker` sella destrezas que nadie tiene.

Cómo: **el billete ya lleva `repaso` firmado; añade `reintento` igual, firmado**.
Cada reintento SE GUARDA como su propia fila (es verdad histórica: el alumno
respondió tres veces, y eso vale para el docente), pero con `reintento: true`
NO toca `MasteryTracker` ni encola AGS. Firmado en el billete, así que no se
puede forjar desde el cliente para inflar.

### Dónde NO va este bucle

- **En la prueba de unidad NO hay reintento.** Es una prueba: se contesta y se
  corrige al final. Si copias el bucle ahí, el PR está mal.
- En el repaso diario SÍ: es práctica.

---

## §3 · PR 14 · EL RITMO DE LA SESIÓN (rama `pr14-ritmo`)

Diez ejercicios seguidos sin saber por dónde vas es una lista de deberes.

### Lo que tiene que existir

- **Progreso de la tanda**: «4 de 10», visible siempre, en práctica, repaso y
  prueba.
- **Racha DENTRO de la sesión**: «3 seguidos». Se rompe al fallar. No se guarda
  en base: vive en la tanda y muere con ella. Es ritmo, no historial.
- **Puntos de la sesión**: al terminar, «8 de 10 · 2 seguidos al final». Un
  cierre que diga cómo fue.
- **Contrarreloj OPCIONAL**, con un interruptor que empieza APAGADO y se
  recuerda por alumno. Un A1 con un reloj encima no aprende, se bloquea; pero a
  quien le motive, que lo tenga.

### PROHIBIDO, y es una decisión de colegio, no de producto

**Ningún ranking entre alumnos. Ninguna tabla de clasificación. Ningún «vas por
detrás de».** Esto es un colegio con menores: comparar en público hace daño y
además expondría el rendimiento de unos a otros. Los puntos son del alumno
consigo mismo y con nadie más.

Y la racha de días sigue como está: **un número, sin fuego y sin confeti.**

---

## §4 · PR 15 · JUEGOS SOBRE LO QUE YA ESTÁ SEMBRADO (rama `pr15-juegos`)

624 tarjetas de vocabulario firmadas, en cuatro lenguas, con significado y
ejemplo. Y en chino, con carácter Y pinyin Y significado — tres columnas ya
sembradas. Eso da para jugar sin escribir ni un dato nuevo.

### `/corso/{lengua}/u{n}/jugar` — tres juegos, decididos

1. **Memoria**: parejas boca abajo. En it/fr/de, palabra ↔ significado. **En
   chino, TRES cartas por grupo**: carácter, pinyin y significado — y se gana el
   grupo cuando están las tres. Eso es justo lo que el tipo `pares` de tres
   columnas enseñó y aquí se juega.
2. **Emparejar contra el reloj** (opcional, ver §3): cuántas parejas en 60
   segundos, contra tu propia marca anterior y la de nadie más.
3. **¿Cuál sobra?**: cuatro palabras de la unidad, tres de un campo y una
   intrusa. Los grupos salen de la unidad, no de una lista escrita a mano.

### Las reglas

- **Los juegos NO dan dominio ni AGS.** Como el vocabulario: son apoyo. El
  dominio lo dan los ítems. Si un juego mueve `MasteryTracker`, está mal.
- Marcan «la sé» en la tarjeta cuando se acierta, eso sí: es la misma señal que
  el mazo.
- **El invitado juega entero y no escribe ni una fila.**
- Se juegan con teclado. Una carta que solo se voltea con ratón no vale.

---

## ORÁCULOS — se heredan y se amplían

1. No se filtra la solución en ninguna vía nueva: **ni un tablero entrega la
   solución en su payload**, ni el andamiaje la manda antes de tiempo (los tres
   distractores del segundo fallo se calculan EN EL SERVIDOR y viajan solo
   entonces). Recorre `Registro::kinds()` en las cuatro lenguas.
2. Regla de oro: el invitado no escribe ni una fila — en el tablero, en el
   reintento, en los juegos. Con su test en cada PR.
3. Nada se publica sin firma; nada se des-firma sin nota.
4. Toda columna que gobierne visibilidad falla cerrada.
5. Lengua cerrada en las dos direcciones, con las cinco lenguas.
6. **Presupuesto NUEVO por el guardián del manifest: ≤ 550 total, ≤ 60 por
   página.** El guardián sigue, con los números nuevos.
7. **axe + TECLADO en cada tablero y cada juego**, y `prefers-reduced-motion`
   respetado. Este oráculo es el que más me importa de la misión: un tablero
   bonito que excluye a un alumno es peor que la lista de botones de hoy.
8. PostgreSQL en verde; `orderBy` explícito sobre nullables.
9. Cero tests risky.
10. Migraciones aditivas y nullables. Se despliegan solas.
11. **Los cuatro cursos no cambian**: el test recorre `/corso/it|fr|de|zh`, sus
    nueve unidades, sus 36 diálogos y sus 624 tarjetas antes y después.
12. **Nuevo — el reintento no infla el dominio**: un ítem acertado al tercer
    intento deja TRES filas y mueve `MasteryTracker` UNA vez, la del primer
    intento (fallido). Un test lo fija por cada kind.
13. **Nuevo — `reintento` viaja firmado**: un cliente que mande `reintento:
    false` en un reintento no consigue que cuente. 422 o veredicto sin crédito,
    nunca crédito.
14. **Nuevo — la prueba de unidad NO reintenta**: se contesta de corrido y se
    corrige al final, exactamente como hoy. Test que lo fija.
15. **Nuevo — ningún juego mueve dominio ni AGS**, y ninguna pantalla nueva
    muestra el resultado de OTRO alumno.

## BUCLES — A, B, C en cada PR

Foco nuevo del **C**: **una regla del motor que el tablero o el reintento rompan
por reimplementarla**. El billete, la semilla, el `se_guarda`, el 200-no-201 del
invitado, el `attempt_no` que ya se cazó una vez en el repaso. Si un tablero
tiene su propio camino de corrección o su propio número de intento, está mal
aunque el CI esté verde.

## PROHIBIDO

Mergear. Librerías (ni de drag-and-drop, ni de animación, ni de confeti). Audio,
vídeo o imágenes. Arrastrar sin camino de teclado. Movimiento que ignore
`prefers-reduced-motion`. Ranking entre alumnos. Contrarreloj por defecto.
Reintento en la prueba de unidad. Que un juego dé dominio. Que el andamiaje
mande la solución antes del segundo fallo. Copiar `Ejercicio.jsx` en vez de
extenderlo. Textos en inglés en la interfaz. Parar a preguntar.

## ENTREGA

Cuatro PRs apilados (12 → 13 → 14 → 15), cada uno con su informe: tabla de
mutación primero, «decidí yo», regla de oro, bundle gastado sobre el techo
nuevo, qué es seguro por circunstancia, y siembra post-merge si la hay.

**Y en cada uno: «al mergear, borrar la rama».** Con el orden aprendido a
golpes: se mergea de abajo arriba y, tras cada merge, el siguiente se reapunta
a `main` con `gh pr edit <N> --base main` ANTES de tocarlo. Si no, GitHub cierra
solo los PRs hijos cuando desaparece su base — ya nos pasó con el #42 y el #44.

CHECKS de GitHub, no tu resumen.
