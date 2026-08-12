<?php

use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\TrackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — solo lectura (el grafo se importa; los recursos se publican
| por el pipeline de autoría, no por esta API)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::get('frameworks', [CurriculumController::class, 'frameworks']);
    Route::get('frameworks/{code}/tree', [CurriculumController::class, 'tree']);
    Route::get('nodes/{node}', [CurriculumController::class, 'node']);
    Route::get('nodes/{node}/objectives', [CurriculumController::class, 'nodeObjectives']);
    Route::get('objectives/search', [CurriculumController::class, 'search']);
    Route::get('objectives/{objective}', [CurriculumController::class, 'objective']);

    Route::get('tracks', [TrackController::class, 'index']);
    Route::get('tracks/{code}', [TrackController::class, 'show']);
    Route::get('phases/{phase}/objectives', [TrackController::class, 'phaseObjectives']);

    Route::get('resources', [ResourceController::class, 'index']);
    Route::get('resources/{resource:slug}', [ResourceController::class, 'show']);
});
