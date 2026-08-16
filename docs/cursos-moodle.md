# AllyuHub ↔ cursos Moodle "curso-como-código" (e-learnium)

La estrategia de generar cursos Moodle completos con IA (piloto: Matemáticas
1.º BGU en el Moodle de e-learnium) y AllyuHub se unen en un punto: **el
currículo no se copia a mano al repo de cursos, se lee de AllyuHub**.

```
AllyuHub (el grafo es la verdad)
   │  curso:blueprint / GET .../blueprint
   ▼
cursos-moodle (repo fuente: curso.yaml, lecciones .md, .gift, sims)
   │  compilador idempotente → moosh vía SSH
   ▼
Moodle e-learnium (categoría sandbox → promoción con "go" humano)
   │  LTI 1.3 (ya implementado en AllyuHub)
   ▼
práctica y mastery del alumno vuelven a AllyuHub, la nota vuelve al gradebook
```

Sin esto, el repo de cursos tendría su propia copia de las DCD y las dos
verdades divergirían en la primera corrección del PDF oficial.

## Qué produce AllyuHub

**Comando** (para el repo de cursos, en disco):

```bash
php artisan curso:blueprint M --grado=g11 --track=ORD \
    --out=../cursos-moodle/matematica-1bgu
# escribe curso.yaml y COBERTURA.md
```

- `nodo`: uuid, `path` del grafo o `native_code`. Un código repetido entre
  grados o marcos **no se adivina**: el comando lista los candidatos y exige
  `--grado` o `--marco` (regla 2: la clave es marco+versión+código).
- `--track`: añade `peso` y `fase` de `track_phase_objectives`. El mismo nodo
  compila para ORD y para PCEI-BSI cambiando solo esto — un track es un
  recorrido, no una copia.
  Ojo: hoy solo los tracks PCEI tienen `track_phase_objectives` sembrados; en
  ORD la dosificación vive en el propio grafo (grado → asignatura → bloque),
  así que `--track=ORD` sale con `peso`/`fase` en `null`. Es dato real, no un
  fallo del exportador.
- `--idnumber`: por defecto `AH-<codigo>-<grado>` (p. ej. `AH-M-G11`). Es la
  **clave de enlace con EduPlat** (§9.3 de la estrategia): nunca casar por
  nombre.
- Sin `--out` imprime el YAML por pantalla.

**API** (para el compilador cuando corra en el servidor):

```
GET /api/v1/nodes/{node}/blueprint?track=ORD&idnumber=IA-MAT-1BGU
```

Solo lectura, como todo `/api/v1`.

## El contrato: `allyuhub/curso-blueprint@1`

```yaml
contrato: "allyuhub/curso-blueprint@1"
curso:
  idnumber: "AH-M-G11"        # clave estable Moodle ↔ EduPlat
  titulo: "Matemática"
  marco: "EC-MINEDEC"
  version_marco: "2016+2023"
  nodo: {id, tipo, codigo, path}
  grado: {codigo, titulo}
  area:  {codigo, titulo}
  track: "ORD"                # null si no se pidió
cobertura:
  min_lecciones_por_dcd: 1
  min_preguntas_por_dcd: 3
unidades:
  - seq: 1
    slug: "unidad-01"         # = carpeta del repo de cursos
    titulo: "Álgebra y funciones"
    nodo_id: "…"
    codigo: "M.5.1"
    destrezas:
      - id: "…"               # uuid de la destreza en AllyuHub
        codigo: "M.5.1.1"     # DCD
        enunciado: "…"
        esencial: true
        verificada: true      # false = enunciado sin cotejar contra el PDF oficial
        practicable: true     # tiene ítems de práctica en AllyuHub
        items: 3
        prerrequisitos: [{id, codigo, enunciado}]
        practica_url: "https://…/practicar/<uuid>"
        peso: 1.0             # solo con --track
        fase: "1.º BGU"       # solo con --track
resumen: {unidades, destrezas, destrezas_practicables, destrezas_sin_items, destrezas_sin_verificar}
fingerprint: "sha256…"
```

Reglas del contrato:

1. **Determinista.** Mismo grafo → mismos bytes. El generador de contenido y el
   compilador pueden diffear sin ruido.
2. **`fingerprint` es el disparador de recompilación.** Cubre solo lo
   curricular: cambiar el nombre del curso o el idnumber NO lo mueve; una
   destreza nueva o un enunciado corregido SÍ. Guárdalo en el repo de cursos y
   compara antes de recompilar.
3. **`unidad-00` existe.** Una destreza colgada de la asignatura sin bloque
   (el importador MINEDEC deja algunas así) va ahí en vez de desaparecer.
4. **Orden curricular**, no alfabético: `M.5.1.2` antes que `M.5.1.10`.
5. **`prerrequisitos` usa el mismo criterio que el motor de práctica**: arista
   `prerequisite` con `method=manual` o `reviewed_at NOT NULL`. Lo que el
   selector no respeta, el curso tampoco lo insinúa.
6. **`verificada: false` es una alerta**, no un bloqueo: ese enunciado es
   paráfrasis y no debe citarse como texto oficial en el curso.
7. **`practica_url`** es el enlace que la lección Moodle embebe o lanza por LTI
   para que el alumno practique en AllyuHub. Las destrezas sin ítems se
   cubren con quizzes GIFT del propio curso.

## Qué NO hace (y por qué)

- **No genera contenido.** Lecciones, GIFT y simuladores los produce el
  pipeline de cursos; AllyuHub aporta el esqueleto curricular y el enlace de
  práctica.
- **No habla con Moodle.** La escritura en Moodle es del compilador (moosh vía
  SSH, categoría sandbox, human gate). AllyuHub no escribe en Moodle salvo la
  nota AGS que ya publica el LTI.
- **No edita el grafo.** Si una DCD está mal, se corrige en el importador y se
  vuelve a generar el blueprint. Nunca al revés.

## Siguiente paso del lado de e-learnium

El repo `cursos-moodle` consume `curso.yaml` como fuente de verdad curricular:
su README (el contrato del formato fuente) debe apuntar a este documento y su
compilador debe (a) usar `curso.idnumber` como identidad del curso Moodle,
(b) crear una sección por `unidades[].slug`, y (c) fallar si `COBERTURA.md`
tiene filas sin lecciones o con menos de 3 preguntas.
