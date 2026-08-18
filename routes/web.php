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

// Una URL que no casa con NINGUNA ruta la rechaza el router antes de aplicar
// el grupo `web`: sin sesión, sin props compartidas y sin CSP, así que la
// página de error salía con la salida del visitante aunque el alumno tuviera
// sesión (auditoría del frente visual). Con un fallback DENTRO del grupo, el
// 404 vuelve al pipeline normal y se pinta como cualquier otro.
// Las rutas de la API quedan fuera: allí el 404 tiene que seguir siendo JSON.
//
// `any()->fallback()` y no `Route::fallback()`: el helper de Laravel registra
// SOLO GET, así que un POST a una URL inexistente pasaba a casar la ruta por
// path pero no por método y devolvía 405 en vez de 404 — además de revelar que
// «ahí hay algo, pero con otro verbo». `->fallback()` conserva lo importante:
// esta ruta se prueba la ÚLTIMA, después de las de /lti que se registran luego.
// El `(?!api/)` es lo que cumple esa promesa: sin él, un POST a un endpoint de
// solo lectura de /api/v1 pasaba de 405 a 404 porque esta ruta lo casaba.
Route::any('{cualquiera}', fn () => abort(404))
    ->where('cualquiera', '(?!api/).*')
    ->fallback();
