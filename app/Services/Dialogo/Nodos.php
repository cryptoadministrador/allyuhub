<?php

namespace App\Services\Dialogo;

use App\Services\Audio\AlmacenDeAudio;
use InvalidArgumentException;

/**
 * Valida el grafo de un diálogo AL SEMBRAR, como `Bloques` valida una lección:
 * un guion roto (un `va` que apunta a un nodo que no existe, un callejón sin
 * pista, ningún final) revienta la siembra nombrando el fallo, en vez de llegar
 * a un alumno y dejarlo colgado a mitad de la conversación.
 *
 * Reglas:
 *  - Al menos un nodo; el primero es el arranque.
 *  - Cada nodo con `id` único y una línea `dice`.
 *  - Un nodo o es FINAL (`fin: true`, sin respuestas) o tiene 1-3 respuestas.
 *  - Cada respuesta lleva `texto` y, o bien `va` a un nodo EXISTENTE, o bien
 *    `va: null` con una `pista` (el callejón que ayuda y vuelve).
 *  - Se llega a un final: hay al menos un nodo `fin`.
 *  - `clip`, si está, es una clave (no una ruta ya publicada): la resuelve el
 *    sembrador contra el almacén público.
 */
final class Nodos
{
    private const MAX_RESPUESTAS = 3;

    public static function validar(array $nodos, string $donde): void
    {
        if ($nodos === []) {
            throw new InvalidArgumentException("El diálogo «{$donde}» no tiene nodos.");
        }

        $ids = [];
        foreach ($nodos as $i => $nodo) {
            $id = $nodo['id'] ?? null;
            if (! is_string($id) || $id === '') {
                throw new InvalidArgumentException("El diálogo «{$donde}» tiene un nodo #{$i} sin id.");
            }
            if (isset($ids[$id])) {
                throw new InvalidArgumentException("El diálogo «{$donde}» repite el nodo «{$id}».");
            }
            $ids[$id] = true;

            if (! isset($nodo['dice']) || ! is_string($nodo['dice']) || $nodo['dice'] === '') {
                throw new InvalidArgumentException("El nodo «{$id}» de «{$donde}» no dice nada.");
            }

            if (isset($nodo['clip']) && AlmacenDeAudio::esRutaPublicada($nodo['clip'])) {
                throw new InvalidArgumentException(
                    "El nodo «{$id}» de «{$donde}» trae una RUTA de audio; el banco lleva la CLAVE, no la ruta.",
                );
            }
        }

        $hayFinal = false;
        foreach ($nodos as $nodo) {
            $id = $nodo['id'];
            $esFinal = ($nodo['fin'] ?? false) === true;
            $respuestas = $nodo['respuestas'] ?? [];

            if ($esFinal) {
                $hayFinal = true;
                if ($respuestas !== []) {
                    throw new InvalidArgumentException("El nodo final «{$id}» de «{$donde}» no lleva respuestas.");
                }

                continue;
            }

            if ($respuestas === [] || count($respuestas) > self::MAX_RESPUESTAS) {
                throw new InvalidArgumentException(
                    "El nodo «{$id}» de «{$donde}» necesita entre 1 y ".self::MAX_RESPUESTAS.' respuestas.',
                );
            }

            foreach ($respuestas as $j => $r) {
                if (! isset($r['texto']) || ! is_string($r['texto']) || $r['texto'] === '') {
                    throw new InvalidArgumentException("Respuesta #{$j} del nodo «{$id}» de «{$donde}» sin texto.");
                }
                $va = $r['va'] ?? null;
                if ($va === null) {
                    if (! isset($r['pista']) || $r['pista'] === '') {
                        throw new InvalidArgumentException(
                            "El callejón (respuesta #{$j}) del nodo «{$id}» de «{$donde}» necesita una pista.",
                        );
                    }

                    continue;
                }
                if (! isset($ids[$va])) {
                    throw new InvalidArgumentException(
                        "La respuesta #{$j} del nodo «{$id}» de «{$donde}» apunta a un nodo inexistente: «{$va}».",
                    );
                }
            }
        }

        if (! $hayFinal) {
            throw new InvalidArgumentException("El diálogo «{$donde}» no tiene ningún nodo final.");
        }
    }
}
