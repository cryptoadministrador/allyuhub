<?php

namespace App\Services\Practice\Tipos;

use App\Models\PracticeItem;
use App\Services\Practice\PracticeEngine;

/**
 * CÓMO SE COMPORTA UN TIPO DE ÍTEM — la generalización de `respondePorClave()`.
 *
 * En #26 la decisión correcta fue preguntar por la capacidad y no por el kind:
 * `escucha` heredó billete, barajado y 422 sin tocar el controlador. Pero con
 * hueco/orden/pares/dictado aparecen TRES formas de respuesta (clave, texto,
 * estructura), y seguir añadiendo booleanos —`respondePorTexto()`,
 * `respondePorEstructura()`— habría llenado el controlador de ramas otra vez.
 *
 * Ahora cada kind declara su comportamiento completo en una pieza pequeña, y
 * el motor la elige por el `Registro`. El controlador no sabe cuántos tipos
 * hay ni qué forma tienen: los PR siguientes añaden una clase y una entrada en
 * el registro, y no tocan ni `next` ni `submitAttempt`.
 *
 * Las cinco preguntas que responde un tipo:
 *
 *  - `camposDeRespuesta()`: qué campos del POST usa. El motor PROHÍBE todos
 *    los demás — la exclusión mutua no se escribe por tipo, se deriva.
 *  - `reglas()`: cómo se validan esos campos (un id fuera de los servidos es
 *    422, no un falso «incorrecto»).
 *  - `payload()`: qué viaja en `next`. LISTA BLANCA campo a campo — la
 *    solución no está porque ningún tipo la pone, no porque alguien la quite.
 *  - `corregir()`: el veredicto que ve el cliente, con lo que se revela
 *    DESPUÉS de responder (esperado, transcripción…).
 *  - `columnas()`: qué persiste el intento. Invariante: una vía por kind, las
 *    demás NULL.
 *
 * Y `alGuardar()`: la forma de `solucion`/`options` se valida en el guardián
 * `saving` (#26) — un ítem mal formado revienta donde lo ve quien lo escribe.
 */
abstract class Tipo
{
    /** @return list<'answer'|'answer_key'|'respuesta'> */
    abstract public function camposDeRespuesta(): array;

    /** @return array<string, mixed> reglas de validación de SUS campos */
    abstract public function reglas(PracticeItem $item): array;

    /** @return array<string, mixed> los campos propios del payload de next */
    abstract public function payload(PracticeItem $item, PracticeEngine $engine, string $seed): array;

    /** @return array<string, mixed> el veredicto que ve el cliente */
    abstract public function corregir(PracticeItem $item, array $data, PracticeEngine $engine, string $seed): array;

    /** @return array<string, mixed> columnas del intento (una vía poblada) */
    abstract public function columnas(PracticeItem $item, array $veredicto, array $data, PracticeEngine $engine, string $seed): array;

    /** El guardián `saving`: la forma del ítem, validada al escribirse. */
    public function alGuardar(PracticeItem $item): void {}

    /**
     * CÓMO SE LEE LA ENTRADA DE BANCO de este tipo — la sexta pregunta.
     *
     * El sembrador de MINEDEC leía tuplas posicionales con un `if` por kind:
     * el motor sabía siete tipos y el sembrador dos, la misma familia de
     * defecto de toda la semana (la regla en un sitio y no en su hermano).
     * Ahora la entrada va POR CLAVES y cada tipo declara aquí qué claves lee
     * y a qué columnas van: añadir el octavo tipo no toca el sembrador.
     *
     * Devuelve SOLO columnas de contenido (statement, options, solucion…);
     * el anclaje (objective_id, lengua, seq, attrs de revisión) es del
     * sembrador. Si la entrada trae un `clip`, el sembrador ya lo publicó y
     * lo entrega como `audio_src` — la indirección clave→hash es suya.
     *
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    abstract public function desdeBanco(array $entrada): array;
}
