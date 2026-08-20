<?php

use App\Http\Controllers\Api\BlueprintController;
use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\TrackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 pública — SOLO LECTURA del grafo y el catálogo (se importan y
| publican por pipeline, no por esta API). Los endpoints de práctica viven
| en routes/web.php bajo el mismo prefijo /api/v1: desde el contenido abierto
| TAMPOCO exigen sesión —un invitado practica y se le corrige— pero solo con
| sesión se GUARDA el avance y viaja la nota, y el alumno sale siempre de
| Auth::id(), jamás del payload (un `user_id` en el request es 422).
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::get('frameworks', [CurriculumController::class, 'frameworks']);
    Route::get('frameworks/{code}/tree', [CurriculumController::class, 'tree']);
    Route::get('nodes/{node}', [CurriculumController::class, 'node']);
    Route::get('nodes/{node}/objectives', [CurriculumController::class, 'nodeObjectives']);
    // Blueprint del curso para el compilador de cursos-moodle (e-learnium).
    Route::get('nodes/{node}/blueprint', [BlueprintController::class, 'show']);
    Route::get('objectives/search', [CurriculumController::class, 'search']);
    Route::get('objectives/{objective}', [CurriculumController::class, 'objective']);

    Route::get('tracks', [TrackController::class, 'index']);
    Route::get('tracks/{code}', [TrackController::class, 'show']);
    Route::get('phases/{phase}/objectives', [TrackController::class, 'phaseObjectives']);

    Route::get('resources', [ResourceController::class, 'index']);
    Route::get('resources/{resource:slug}', [ResourceController::class, 'show']);
});
