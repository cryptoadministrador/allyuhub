<?php

use App\Http\Controllers\Api\PracticeController;
use App\Http\Controllers\App\BienvenidaController;
use App\Http\Controllers\App\DocenteController;
use App\Http\Controllers\App\InicioController;
use App\Http\Controllers\App\PageController;
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
Route::get('/buscar', [PageController::class, 'buscar'])->name('buscar');
Route::get('/practicar/{objective}', [PageController::class, 'practicar'])->name('practicar');
Route::get('/recurso/{resource}', [PageController::class, 'recurso'])->name('recurso');

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
