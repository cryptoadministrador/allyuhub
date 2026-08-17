<?php

use App\Http\Controllers\Api\PracticeController;
use App\Http\Controllers\App\DocenteController;
use App\Http\Controllers\App\InicioController;
use App\Http\Controllers\App\PageController;
use Illuminate\Support\Facades\Route;

// La portada ES el catálogo. El welcome de fábrica referenciaba
// resources/js/app.js — borrado al pasar a Inertia (app.jsx) — así que con
// el manifest de Vite presente (producción) la raíz daba 500. Los tests no
// lo veían: withoutVite() y ninguno visitaba '/'.
// La raíz lleva a la casa del alumno cuando hay sesión; el visitante sin
// sesión va al catálogo (que a su vez lo manda a /entrar con redirectGuestsTo).
Route::get('/', fn () => auth()->check() ? redirect('/inicio') : redirect('/catalogo'));

// Sesión caducada o acceso sin launch: la única puerta de entrada es Moodle.
Route::view('/entrar', 'entrar')->name('entrar');

// Páginas de la app (Inertia + React). La identidad es SIEMPRE la sesión.
Route::middleware('auth')->group(function () {
    // La casa del alumno: dónde iba, qué toca y cómo va.
    Route::get('/inicio', InicioController::class)->name('inicio');

    Route::get('/practicar/{objective}', [PageController::class, 'practicar'])->name('practicar');
    Route::get('/recurso/{resource}', [PageController::class, 'recurso'])->name('recurso');
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

    // El catálogo navegable del currículo (misión ANTY).
    Route::get('/catalogo', [PageController::class, 'catalogo'])->name('catalogo');
    Route::get('/catalogo/{node}', [PageController::class, 'catalogoNodo'])->name('catalogo.nodo');
    Route::get('/destreza/{objective}', [PageController::class, 'destreza'])->name('destreza');
    Route::get('/buscar', [PageController::class, 'buscar'])->name('buscar');
});

/*
|--------------------------------------------------------------------------
| API de práctica — MISMAS URLs /api/v1/practice/* de siempre, pero en el
| grupo web con `auth`: el alumno es SIEMPRE el de la sesión (Auth::id()),
| y un user_id en el request es un 422 explícito (regla prohibited).
| Los errores salen en JSON por el shouldRenderJsonWhen de bootstrap.
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1')->middleware('auth')->group(function () {
    Route::get('objectives/{objective}/practice/next', [PracticeController::class, 'next']);
    Route::post('practice/items/{item}/attempts', [PracticeController::class, 'submitAttempt']);
    Route::get('practice/mastery', [PracticeController::class, 'mastery']);
    Route::get('practice/progress', [PracticeController::class, 'progress']);
});
