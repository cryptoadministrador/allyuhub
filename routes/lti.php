<?php

use App\Http\Controllers\Lti\LtiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LTI 1.3 — la Tool vista desde Moodle (roadmap §5)
|--------------------------------------------------------------------------
| Estas rutas van por el grupo `web` COMPLETO (bootstrap/app.php): la sesión
| del launch es la misma que la de la app. Única excepción: CSRF de Laravel
| desactivado para lti/* — los POST llegan cross-site desde la Platform y la
| protección del protocolo es state+nonce.
*/
Route::get('jwks', [LtiController::class, 'jwks']);
Route::match(['get', 'post'], 'login', [LtiController::class, 'login'])->name('lti.login');
Route::post('launch', [LtiController::class, 'launch'])->name('lti.launch');
Route::post('deep-link', [LtiController::class, 'deepLinkRespond'])->name('lti.deeplink');
