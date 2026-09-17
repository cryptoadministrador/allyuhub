<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tarjeta;
use App\Models\VocabEstado;
use App\Services\Practice\Practitioner;
use Illuminate\Http\Request;

/**
 * Lo que el alumno dice de una tarjeta de vocabulario (PR 10).
 *
 * POST /api/v1/vocabulario/{tarjeta}/estado — {conocida: bool}
 *
 * ABIERTO como la práctica: el invitado recibe 200 y no escribe nada (su mazo
 * vive en la memoria de la página); el alumno deja UNA fila por tarjeta en
 * `vocab_estado` (201) —decirlo dos veces la actualiza, no la duplica—. Una
 * tarjeta SIN FIRMAR es 404: no se puede opinar de lo que no se sirve.
 *
 * No hay corrección ni dominio: no es un ejercicio.
 */
class VocabularioController extends Controller
{
    public function estado(Request $request, string $tarjeta)
    {
        $data = $request->validate([
            'user_id' => 'prohibited',
            'conocida' => ['required', 'boolean'],
        ]);
        $quien = Practitioner::fromRequest($request);

        $t = Tarjeta::published()->find($tarjeta);
        abort_if($t === null, 404);

        if ($quien->isGuest()) {
            // Ni una fila: la regla de oro del contenido abierto.
            return response()->json(['conocida' => (bool) $data['conocida'], 'se_guarda' => false]);
        }

        $estado = VocabEstado::updateOrCreate(
            ['user_id' => $quien->userId(), 'tarjeta_id' => $t->id],
            ['conocida_at' => $data['conocida'] ? now() : null],
        );

        return response()->json([
            'conocida' => $estado->conocida_at !== null,
            'se_guarda' => true,
        ], 201);
    }
}
