<?php

use App\Http\Controllers\Api\DialogoController;
use App\Http\Controllers\Api\PracticeController;
use App\Http\Controllers\Api\ProduccionController;
use App\Http\Controllers\Api\RevisionPracticaController;
use App\Http\Controllers\App\BienvenidaController;
use App\Http\Controllers\App\DocenteController;
use App\Http\Controllers\App\InicioController;
use App\Http\Controllers\App\PageController;
use App\Http\Controllers\App\ProduccionDocenteController;
use App\Http\Controllers\App\RevisionController;
use Illuminate\Support\Facades\Route;

// La raíz lleva a la casa del alumno cuando hay sesión; el visitante sin
// sesión ve la PORTADA pública (antes se le rebotaba al catálogo, que a su vez
// lo mandaba a /entrar: dos redirecciones para acabar en una pared).
// Ojo con el welcome de fábrica: referenciaba resources/js/app.js — borrado al
// pasar a Inertia (app.jsx) — y con el manifest de Vite presente (producción)
// la raíz daba 500. Los tests no lo veían: withoutVite() y nadie visitaba '/'.
Route::get('/', BienvenidaController::class)->name('bienvenida');

// Sesión caducada o acceso sin launch: la única puerta de entrada es Moodle.
Route::view('/entrar', 'entrar')->name('entrar');

/*
|--------------------------------------------------------------------------
| CONTENIDO ABIERTO (modelo Khan) — se NAVEGA y se PRACTICA sin sesión.
|
| El currículo es público: un rector, un docente o un papá tienen que poder
| verlo y probar los ejercicios sin credenciales. Entrar desde Moodle deja de
| ser una pared y pasa a ser lo que activa GUARDAR el avance y que la nota
| viaje al aula (AGS) — ver el grupo `auth` de abajo.
|
| Estas páginas NO leen `Auth::id()`: con invitado, `auth.user` es null en las
| props compartidas y la vista se adapta (CTA + aviso de «no se guarda»).
|--------------------------------------------------------------------------
*/
Route::get('/catalogo', [PageController::class, 'catalogo'])->name('catalogo');
Route::get('/catalogo/{node}', [PageController::class, 'catalogoNodo'])->name('catalogo.nodo');
Route::get('/destreza/{objective}', [PageController::class, 'destreza'])->name('destreza');

// El cascarón del curso de idiomas: portada y unidad. La {n} sale por where a
// dígitos — un /corso/it/uX que no sea un número no llega ni al controlador.
Route::get('/corso/{lengua}', [\App\Http\Controllers\App\CursoController::class, 'portada'])->name('corso');
Route::get('/corso/{lengua}/u{n}', [\App\Http\Controllers\App\CursoController::class, 'unidad'])
    ->where('n', '[0-9]+')->name('corso.unidad');
// La tarea de producción de la unidad: se VE sin sesión (enviarla, no).
Route::get('/corso/{lengua}/u{n}/producir', [\App\Http\Controllers\App\CursoController::class, 'producir'])
    ->where('n', '[0-9]+')->name('corso.producir');
// El interlocutor guionizado de la unidad: abierto, se juega sin sesión.
Route::get('/corso/{lengua}/u{n}/hablar', [\App\Http\Controllers\App\CursoController::class, 'hablar'])
    ->where('n', '[0-9]+')->name('corso.hablar');
Route::get('/buscar', [PageController::class, 'buscar'])->name('buscar');
Route::get('/practicar/{objective}', [PageController::class, 'practicar'])->name('practicar');
Route::get('/recurso/{resource}', [PageController::class, 'recurso'])->name('recurso');

// Los clips de audio de las lecciones e ítems de escucha. Abiertos como el
// resto del contenido. El nombre deriva del contenido (AlmacenDeAudio), así
// que la caché puede ser INMUTABLE: si el clip cambia, cambia la URL. La forma
// del nombre es cerrada — lo que no la cumple es 404 sin mirar el disco, y un
// path traversal no llega ni a componer una ruta.
Route::get('/audio/{fichero}', function (string $fichero) {
    $ruta = \App\Services\Audio\AlmacenDeAudio::resolver($fichero);
    abort_if($ruta === null, 404);

    $ext = pathinfo($fichero, PATHINFO_EXTENSION);

    return response()->file($ruta, [
        'Content-Type' => \App\Services\Audio\AlmacenDeAudio::TIPOS[$ext],
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('fichero', '[a-f0-9]{16}\.(mp3|ogg|m4a)')->name('audio');

// Lo del alumno y lo del docente SIGUE cerrado: su casa, su progreso guardado
// y el panel del curso son suyos, y la identidad es SIEMPRE la sesión.
Route::middleware('auth')->group(function () {
    // La casa del alumno: dónde iba, qué toca y cómo va.
    Route::get('/inicio', InicioController::class)->name('inicio');

    Route::get('/progreso', [PageController::class, 'progreso'])->name('progreso');

    // El panel del docente (misión vista-docente): autorización dura por
    // membership de instructor EN ESE contexto, dentro del controlador.
    // whereUuid/whereNumber: un id malformado es 404 en el router, ANTES de
    // tocar la BD — en PostgreSQL el binding con un id no-uuid revienta con
    // «invalid input syntax» (500). En SQLite no se ve porque no tipa.
    Route::get('/docente/{context}', [DocenteController::class, 'panel'])
        ->whereUuid('context')->name('docente');
    Route::post('/docente/{context}/track', [DocenteController::class, 'asignarTrack'])
        ->whereUuid('context')->name('docente.track');
    Route::get('/docente/{context}/alumno/{user}', [DocenteController::class, 'alumno'])
        ->whereUuid('context')->whereNumber('user')->name('docente.alumno');

    // La cola de producción del docente y la corrección. La ruta LITERAL va
    // antes que /docente/{context} (que solo casa uuids): 'producciones' no es
    // un uuid, pero se deja explícito para no depender del orden de matcheo.
    Route::get('/docente/producciones', [ProduccionDocenteController::class, 'cola'])
        ->name('docente.producciones');
    Route::post('/docente/producciones/{produccion}', [ProduccionDocenteController::class, 'corregir'])
        ->whereUuid('produccion')->name('docente.producciones.corregir');
});

/*
|--------------------------------------------------------------------------
| LA REVISIÓN DOCENTE. FUERA del grupo `auth` a propósito.
|
| El grupo `auth` manda al invitado a /entrar (302). Aquí la regla es otra: un
| alumno y un invitado reciben **403**, no una redirección — esto no es una
| puerta a la que les falte la llave, es una sala que no es suya. La
| autorización (docente = instructor en un contexto LTI activo) la hace el
| controlador, que es también quien conoce el 403.
|
| Las rutas literales van ANTES de /docente/{context} (que solo casa uuids).
|--------------------------------------------------------------------------
*/
Route::get('/docente/revisar', [RevisionController::class, 'index'])->name('docente.revisar');
Route::get('/docente/revisar/{tipo}/{id}', [RevisionController::class, 'pieza'])
    ->whereIn('tipo', ['item', 'leccion'])->whereUuid('id')->name('docente.revisar.pieza');
Route::post('/docente/revisar/unidad', [RevisionController::class, 'firmarUnidad'])
    ->name('docente.revisar.unidad');
Route::post('/docente/revisar/{tipo}/{id}/firmar', [RevisionController::class, 'firmar'])
    ->whereIn('tipo', ['item', 'leccion'])->whereUuid('id')->name('docente.revisar.firmar');
Route::post('/docente/revisar/{tipo}/{id}/devolver', [RevisionController::class, 'devolver'])
    ->whereIn('tipo', ['item', 'leccion'])->whereUuid('id')->name('docente.revisar.devolver');
Route::post('/docente/revisar/{tipo}/{id}/desfirmar', [RevisionController::class, 'desfirmar'])
    ->whereIn('tipo', ['item', 'leccion'])->whereUuid('id')->name('docente.revisar.desfirmar');

// Ver un ítem SIN FIRMAR tal como lo verá el alumno: `Practicar.jsx` pide su
// ejercicio a la API, y la de práctica solo sirve lo firmado. Mismo payload,
// mismas piezas, sin persistir nada. 403 para quien no sea docente.
Route::prefix('api/v1')->group(function () {
    Route::get('revision/items/{item}/next', [RevisionPracticaController::class, 'next'])
        ->whereUuid('item')->name('revision.next');
    Route::post('revision/items/{item}/attempts', [RevisionPracticaController::class, 'attempt'])
        ->whereUuid('item')->name('revision.attempt');
});

// Producción de un menor: crear, borrar y SERVIR LA VOZ. En su propio grupo
// api/v1 CERRADO (el de práctica de abajo es abierto) — así un invitado recibe
// un 401 limpio (api/* en shouldRenderJsonWhen), no el 302 a /entrar de las
// páginas, y jamás el fichero. La voz sale SOLO por aquí (auth + policy), nunca
// por /audio/*.
Route::prefix('api/v1')->middleware('auth')->group(function () {
    Route::post('producciones', [ProduccionController::class, 'store'])->name('producciones.store');
    Route::delete('producciones/{produccion}', [ProduccionController::class, 'destroy'])
        ->whereUuid('produccion')->name('producciones.destroy');
    Route::get('producciones/{produccion}/audio', [ProduccionController::class, 'audio'])
        ->whereUuid('produccion')->name('produccion.audio');
});

/*
|--------------------------------------------------------------------------
| API de práctica — MISMAS URLs /api/v1/practice/* de siempre, ahora ABIERTAS.
|
| Sin sesión: se sirve el ítem y se CORRIGE en el servidor (mismo motor), pero
| no se persiste NADA atribuido a un usuario ni se dispara AGS. Con sesión:
| exactamente como siempre — intento, dominio y nota al aula.
| El alumno sigue siendo SIEMPRE el de la sesión: un `user_id` en el request es
| un 422 explícito (regla prohibited), con sesión y sin ella.
|
| `throttle:practica` limita al INVITADO (60/min por IP) para que nadie
| martille el generador; con sesión no impone límite (como hasta hoy).
| Los errores salen en JSON por el shouldRenderJsonWhen de bootstrap.
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1')->middleware('throttle:practica')->group(function () {
    Route::get('objectives/{objective}/practice/next', [PracticeController::class, 'next']);
    Route::post('practice/items/{item}/attempts', [PracticeController::class, 'submitAttempt']);
    Route::get('practice/mastery', [PracticeController::class, 'mastery']);
    Route::get('practice/progress', [PracticeController::class, 'progress']);
    Route::get('practice/repasos', [PracticeController::class, 'repasos']);
    // Completar el interlocutor: abierto (el invitado lo hace y no escribe nada).
    Route::post('dialogos/{dialogo}/completado', [DialogoController::class, 'completado'])
        ->whereUuid('dialogo');
});

// Una URL que no casa con NINGUNA ruta la rechaza el router antes del grupo
// `web`: sin sesión, sin props compartidas y sin CSP, así que la página de
// error salía con la salida del visitante aunque el alumno tuviera sesión. Este
// catch-all la devuelve al pipeline normal para que se pinte con marca+auth+CSP.
// Cubre todos los verbos MENOS OPTIONS: un `any()` se tragaba el preflight CORS
// (OPTIONS -> 404 en vez del 200 autogenerado con Allow) y el 405 de las rutas
// reales; excluir OPTIONS devuelve el preflight (auditoría PR #20). Las de
// /api/v1 quedan fuera: su 404 es JSON.
Route::match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], '{cualquiera}', fn () => abort(404))
    ->where('cualquiera', '(?!api/).*')
    ->fallback();
