<?php

namespace App\Policies;

use App\Models\Produccion;
use App\Models\User;

/**
 * Quién puede tocar la producción de un MENOR. Auto-descubierta por Laravel
 * (App\Models\Produccion → App\Policies\ProduccionPolicy).
 *
 * Todo lo que no está permitido AQUÍ es 403. No hay `before` que dé barra
 * libre a nadie (ni admin): el contenido de un menor no tiene un rol que lo
 * abra por encima de la regla del curso.
 */
class ProduccionPolicy
{
    /**
     * VER (incluida la grabación): el alumno que la hizo, o un docente de su
     * curso. Nadie más — un docente de OTRO curso es 403.
     */
    public function ver(User $u, Produccion $p): bool
    {
        return $u->id === $p->user_id || $p->docenteComparteCurso((int) $u->id);
    }

    /** CORREGIR: solo un docente del curso del alumno, nunca el propio alumno. */
    public function corregir(User $u, Produccion $p): bool
    {
        return $u->id !== $p->user_id && $p->docenteComparteCurso((int) $u->id);
    }

    /**
     * BORRAR: solo el alumno que la hizo y solo mientras NO esté corregida.
     * Una vez corregida es un registro de evaluación, no un borrador.
     */
    public function borrar(User $u, Produccion $p): bool
    {
        return $u->id === $p->user_id && ! $p->estaCorregida();
    }
}
