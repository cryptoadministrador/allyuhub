<?php

use App\Http\Controllers\Api\PracticeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Sesión caducada o acceso sin launch: la única puerta de entrada es Moodle.
Route::view('/entrar', 'entrar')->name('entrar');

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
