<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CurNode;
use App\Models\LearningObjective;
use App\Models\Resource;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

/**
 * GET / para el visitante SIN sesión: la única página pública de AllyuHub.
 *
 * No expone ni una destreza ni un enunciado — solo cifras agregadas — y las
 * cachea una hora: la portada es la que más visitas recibe y la que menos
 * cambia (el currículo se mueve cuando corre un importador, no cada minuto).
 */
class BienvenidaController extends Controller
{
    public const CACHE_KEY = 'bienvenida.cifras';

    public function __invoke()
    {
        if (auth()->check()) {
            return redirect('/inicio');
        }

        return Inertia::render('bienvenida', [
            'cifras' => Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => [
                'destrezas' => LearningObjective::count(),
                'verificadas' => LearningObjective::where('is_verified', true)->count(),
                'grados' => CurNode::where('node_type', 'grado')->count(),
                'simuladores' => Resource::where('status', 'published')
                    ->where('kind', 'simulator')->count(),
            ]),
            'entrar' => route('entrar'),
        ]);
    }
}
