# Unidad 1 · italiano — los cinco clips que faltan

El banco de la U1 ya siembra sin audio. Esto es lo que hay que grabar para
completarla, y exactamente dónde se pega cada cosa después.

**Dónde van los ficheros:** `database/data/audio-lenguas/it/u1/<clave>.mp3`
(también valen `.ogg` y `.m4a`). El sembrador calcula el hash, lo publica en el
almacén y sustituye la ruta. **Quien escribe el banco nunca escribe un hash.**

**Quién los graba:** un hablante nativo o un profesor de italiano. Despacio y
con claridad — es lo que pide literalmente el descriptor A1.CO.2. Sin música de
fondo: un A1 necesita oír las consonantes.

---

## Los cinco clips

| Clave | Duración | Guion exacto |
|---|---|---|
| `it/u1/saluti` | ~12 s | Ciao. Buongiorno. Buonasera. Salve. Arrivederci. |
| `it/u1/c-e-g` | ~18 s | ciao — piacere — arrivederci — come — scusa — amico — chi — mi chiamo — buongiorno — grazie |
| `it/u1/escena` | ~34 s | El diálogo completo de *Il primo giorno* (abajo) |
| `it/u1/giulia` | ~6 s | Ciao, mi chiamo Giulia. Sono italiana, di Roma. |
| `it/u1/dettato-1` | ~4 s | Sono ecuadoriana, di Quito. |

**`it/u1/escena`, guion literal** — dos voces, un chico y una chica:

> **Marco** — Buongiorno. Io sono Marco. E tu, come ti chiami?
> **Sofía** — Ciao, Marco. Mi chiamo Sofía.
> **Marco** — Piacere, Sofía. Di dove sei?
> **Sofía** — Sono ecuadoriana, di Quito. E tu?
> **Marco** — Io sono italiano, di Torino. Sei studentessa qui?
> **Sofía** — Sì. Scusa… e lei chi è?
> **Marco** — È la professoressa Rossi.
> **Sofía** — Grazie, Marco. Arrivederci!
> **Marco** — Ciao!

---

## Lo que se añade al banco cuando los clips existan

### 1 · Bloques de audio en las dos lecciones

En la lección `saluti` (A1.CO.2), después del primer párrafo:

```php
['tipo' => 'audio', 'clip' => 'it/u1/saluti', 'duracion_s' => 12,
 'texto' => ['it' => 'Ciao. Buongiorno. Buonasera. Salve. Arrivederci.',
             'es' => 'Hola. Buenos días. Buenas tardes. Hola (neutro). Hasta la vista.']],
```

En la lección `presentarsi` (A1.IO.3), después de la lista de la c y la g:

```php
['tipo' => 'audio', 'clip' => 'it/u1/c-e-g', 'duracion_s' => 18,
 'texto' => ['it' => 'ciao — piacere — arrivederci — come — scusa — amico — chi — mi chiamo — buongiorno — grazie',
             'es' => 'Los diez ejemplos de la regla, en orden.']],
```

Y al final de la misma lección, justo antes del último aviso, la escena:

```php
['tipo' => 'audio', 'clip' => 'it/u1/escena', 'duracion_s' => 34,
 'texto' => ['it' => 'Buongiorno. Io sono Marco. E tu, come ti chiami? — Ciao, Marco. Mi chiamo Sofía. — Piacere, Sofía. Di dove sei? — Sono ecuadoriana, di Quito. E tu? — Io sono italiano, di Torino. Sei studentessa qui? — Sì. Scusa… e lei chi è? — È la professoressa Rossi. — Grazie, Marco. Arrivederci! — Ciao!',
             'es' => 'El primer día de clase. Sofía, de Quito, conoce a Marco en un colegio de Torino.']],
```

La transcripción **no es opcional** y no es burocracia: `Bloques::audio()` la
exige porque un audio sin transcribir no existe para quien no puede oírlo, y
porque un alumno de A1 necesita poder leer lo que oye.

### 2 · Los dos ítems de A1.CO.2

Van en `'items'`. Con estos dos, A1.CO.2 pasa de tener solo lección a poder
alcanzar dominio — la regla de los dos ítems por descriptor.

```php
[
    'tipo' => 'escucha', 'descriptor' => 'A1.CO.2', 'lengua' => 'it', 'seq' => 1,
    'clip' => 'it/u1/giulia',
    'transcripcion' => ['it' => 'Ciao, mi chiamo Giulia. Sono italiana, di Roma.'],
    'consigna' => ['es' => 'Escucha y responde: ¿de dónde es Giulia?'],
    'opciones' => [
        ['clave' => 'a', 'texto' => ['es' => 'De Roma']],
        ['clave' => 'b', 'texto' => ['es' => 'De Torino']],
        ['clave' => 'c', 'texto' => ['es' => 'De Quito']],
        ['clave' => 'd', 'texto' => ['es' => 'No lo dice']],
    ],
    'correcta' => 'a',
],
[
    'tipo' => 'dictado', 'descriptor' => 'A1.CO.2', 'lengua' => 'it', 'seq' => 2,
    'clip' => 'it/u1/dettato-1',
    'transcripcion' => ['it' => 'Sono ecuadoriana, di Quito.'],
    'consigna' => ['es' => 'Escucha y escribe exactamente lo que oyes.'],
    // Dos formas aceptadas porque LA COMA NO SE OYE. Exigir un signo que el
    // audio no contiene es evaluar adivinación. Las mayúsculas y los espacios
    // sobrantes los perdona el normalizador: no hace falta listarlos.
    'aceptadas' => ['Sono ecuadoriana, di Quito', 'Sono ecuadoriana di Quito'],
],
```

---

## Comprobar antes de dar por buena la unidad

```
php artisan lenguas:sembrar --dry-run
```

Si falta un clip, el pre-pase revienta nombrando **la clave y la entrada que la
pide**, y no escribe nada. Es deliberado: un audio roto delante de un alumno
parece su teléfono, no nuestro fallo.

Y después, ya en serio:

```
php artisan lenguas:sembrar
php artisan lecciones:firmar --bloque=A1.CO.it
php artisan lecciones:firmar --bloque=A1.IO.it
php artisan practica:firmar --bloque=A1.IO.it
php artisan practica:firmar --bloque=A1.CE.it
php artisan practica:firmar --bloque=A1.EE.it
```

**Las firmas las da quien sabe italiano.** No es una formalidad del sistema: es
la única barrera entre un error de contenido y un alumno que se lo cree.
