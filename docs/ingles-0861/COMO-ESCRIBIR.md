# Inglés 0861 · cómo escribir el contenido

`/corso/en` = Cambridge Lower Secondary English **0861** (primera lengua),
Stages 7, 8 y 9 → unidades `u7`, `u8`, `u9`. Desde el PR 16 el curso tiene
**33 descriptores propios** donde anclar el contenido. Mientras un Stage no
tenga nada firmado, sale «próximamente» con sus «Puedo…» a la vista.

## 1. Los descriptores (dónde se ancla)

Fichero: `database/data/ingles-0861-interno.php`. Código `EN<stage>.<strand>.<n>`:

| Strand | Stage 7 | Stage 8 | Stage 9 |
|---|---|---|---|
| R · Lectura | EN7.R.1–4 | EN8.R.1–4 | EN9.R.1–4 |
| W · Escritura | EN7.W.1–4 | EN8.W.1–4 | EN9.W.1–4 |
| SL · Oral y escucha | EN7.SL.1–3 | EN8.SL.1–3 | EN9.SL.1–3 |

Los enunciados son un **borrador**: corrígelos en el mismo fichero y vuelve a
sembrar (`php artisan db:seed --class=InglesInternoSeeder --force`), que los
actualiza sin romper nada. **No cambies la etiqueta de versión** (`propio`) ni
renombres un código que ya tenga contenido: el seeder se niega, y con razón.

No son códigos de Cambridge ni lo pretenden. Cada uno apunta (`ref`) al
sub-strand público de 0861 que desarrolla; cuando el colegio tenga el
curriculum framework oficial, el contenido se reancla por esa referencia.

## 2. El contenido (los mismos ficheros que el resto de lenguas)

Todo con `'lengua' => 'en'`. Nace **sin firmar**.

- **Lecciones e ítems** → `database/data/banco-lenguas.php`, con
  `'descriptor' => 'EN7.R.2'`. Tipos útiles sin audio: `choice`, `hueco`,
  `orden`, `pares`. `escucha` y `dictado` exigen el clip grabado.
  Para que el Stage entre en el **repaso** y en la **prueba de unidad**, cada
  descriptor necesita **≥ 2 ítems firmados** (y el dominio exige acertar dos
  distintos).
- **Vocabulario** → `database/data/vocabulario-lenguas.php`, con
  `'unidad' => 7|8|9`. Sin `pinyin`.
- **Diálogos** (interlocutor) → `database/data/dialogos-lenguas.php`, con
  `'unidad' => 7|8|9` y `'objective'` sobre un `EN*.SL.*`.
- **Tareas de producción** (escribir / grabar): el curso aún no declara
  destrezas productivas. Si las quieres, se añaden en
  `cursos-lenguas.php` (`'productivas' => ['escritura' => '.W.', 'voz' => '.SL.']`)
  — pídelo y se hace con sus tests.

## 3. Sembrar y firmar

```bash
php artisan lenguas:sembrar          # ancla el inglés en AH-EN0861 solo
php artisan vocabulario:sembrar
php artisan dialogos:sembrar
php artisan practica:firmar --bloque=EN7.R.en     # o desde la pantalla:
# /docente/revisar?lengua=en
```

`BancoEnteroTest` lleva la cuenta EXACTA del banco: al añadir contenido,
actualiza sus números en el mismo PR.
