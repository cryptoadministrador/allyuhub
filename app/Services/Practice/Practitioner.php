<?php

namespace App\Services\Practice;

use Illuminate\Http\Request;

/**
 * Quién está practicando: un alumno con sesión o un invitado.
 *
 * Existe para que la bifurcación «¿persisto y califico?» se decida UNA vez y en
 * un sitio, en vez de repartir `if (auth()->check())` por el controlador. La
 * corrección en sí NO se bifurca: los dos caminos pasan por el mismo
 * PracticeEngine::verify(), que es el punto donde la nota es de verdad.
 *
 * Contenido abierto (modelo Khan): el invitado navega y practica; solo la
 * sesión LTI hace que el avance se guarde y que la nota viaje al aula.
 */
final class Practitioner
{
    /**
     * El id con el que se consultan las tablas de avance. Para un invitado es
     * CERO: las secuencias de `users` empiezan en 1, así que no existe ni puede
     * existir un usuario con ese id, y todas las consultas del selector
     * (masteries, intentos previos) devuelven vacío sin ningún caso especial.
     * Es un int a propósito: en PostgreSQL comparar un bigint con una cadena no
     * numérica revienta con «invalid input syntax» — en SQLite no se vería.
     */
    public const ID_INVITADO = 0;

    /**
     * La clave del invitado dentro de la semilla sha256(item:quien:intento).
     *
     * Es CONSTANTE, no el id de sesión, por dos razones. Una: así la corrección
     * es de verdad sin estado — el servidor no necesita recordar nada entre la
     * petición que sirve el ítem y la que lo corrige, y una sesión que rote a
     * media práctica no puede hacer que se califique contra otros números. Dos:
     * la variedad ya la da el número de intento, que lo lleva el cliente.
     *
     * Que dos invitados vean los mismos números es aceptable —no hay nota en
     * juego— y ningún invitado puede tocar la semilla de un alumno real: la de
     * un alumno lleva su id numérico y esta no.
     */
    public const CLAVE_INVITADO = 'invitado';

    private function __construct(private readonly ?int $userId) {}

    public static function fromRequest(Request $request): self
    {
        $user = $request->user();

        return new self($user === null ? null : (int) $user->id);
    }

    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    /** El id real del alumno. Null para un invitado: nada se le atribuye. */
    public function userId(): ?int
    {
        return $this->userId;
    }

    /** El id con el que consultar avance (0 para invitado: no casa con nadie). */
    public function queryId(): int
    {
        return $this->userId ?? self::ID_INVITADO;
    }

    /** Lo que entra en la semilla del ítem. */
    public function seedKey(): int|string
    {
        return $this->userId ?? self::CLAVE_INVITADO;
    }
}
