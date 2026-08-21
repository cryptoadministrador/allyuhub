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
                // Por `published()`, como todas las demás rutas que sirven
                // recursos, y no por un `status` escrito al lado: es la tercera
                // vez que esas dos formas divergen, y esta es la superficie más
                // pública que hay. Hoy la cifra saldría igual; con la puerta
                // atada a la procedencia, ya no.
                //
                // Y `simulation`, no `simulator`: el vocabulario lo declara la
                // migración de `resources` y lo usa el Deep Linking. Nadie
                // escribe nunca 'simulator' — salvo el fixture del test, con la
                // MISMA errata, que es por lo que esto llevaba verde desde el
                // primer día contando cero para siempre.
                'simuladores' => Resource::published()
                    ->whereIn('kind', Resource::INTERACTIVOS)->count(),
            ]),
            'entrar' => route('entrar'),
        ]);
    }
}
