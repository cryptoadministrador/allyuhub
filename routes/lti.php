<?php

use App\Http\Controllers\Lti\LtiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LTI 1.3 — la Tool vista desde Moodle (roadmap §5)
|--------------------------------------------------------------------------
| Estas rutas van por el grupo de middleware `lti` (bootstrap/app.php):
| sesión y cookies en claro (el nombre de la cookie de state es dinámico y
| no se puede excluir de EncryptCookies), sin CSRF de Laravel porque los
| POST llegan cross-site desde la Platform: la protección es state+nonce.
*/
Route::get('jwks', [LtiController::class, 'jwks']);
Route::match(['get', 'post'], 'login', [LtiController::class, 'login'])->name('lti.login');
Route::post('launch', [LtiController::class, 'launch'])->name('lti.launch');
