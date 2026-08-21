<?php

namespace App\Services\Practice;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * EL BILLETE DEL INTENTO: lo que `next` entrega y `submitAttempt` exige.
 *
 * El problema que resuelve. `next` elegía el ítem y calculaba
 * `attempt_no = intentos(item, alumno) + 1`, instanciaba los números con
 * `seed(item:quien:intento)` y se los pintaba al alumno. Al responder,
 * `submitAttempt` VOLVÍA A CONTAR FILAS para deducir el mismo número. Mientras
 * nada se moviera entre las dos peticiones, coincidían.
 *
 * Pero se mueve. Otra pestaña abierta, un reintento tras el 409 —cuya propia
 * respuesta invita a reintentar—, una petición que llega dos veces: cualquiera
 * de esas cosas cambia la cuenta, y con ella la semilla, y con ella los
 * números. El alumno resolvía «un móvil de 12 kg» y el servidor lo corregía
 * contra 7 kg. No es un fallo de concurrencia exótico: es un alumno al que se
 * le pone mal una respuesta buena, y desde el aula parece que la plataforma
 * miente.
 *
 * El arreglo es dejar de deducir lo que ya se sabía. `next` firma lo que sirvió
 * y `submitAttempt` corrige contra eso, no contra el estado de la tabla en otro
 * instante.
 *
 * Por qué FIRMADO y no la semilla a secas. Sin firma, el cliente elegiría su
 * propio `attempt_no`: no le daría respuestas correctas —la verificación sigue
 * siendo en servidor— pero sí podría reservar el número que quisiera y hacer
 * fallar el intento legítimo de otra pestaña. Con HMAC, el billete solo lo
 * emite el servidor.
 *
 * Y va atado a QUIÉN. Sin eso, el billete de un invitado serviría para escribir
 * el intento de un alumno, o el de un alumno para el de otro. El vínculo es la
 * misma clave con la que se sella la semilla (`Practitioner::seedKey`), así que
 * no hay dos nociones de identidad que puedan separarse.
 *
 * NO CADUCA, a propósito. Guardarse un billete no sirve de nada: el alumno
 * seguiría teniendo que acertar, y el índice único (ítem, usuario, intento)
 * impide gastarlo dos veces. Una caducidad añadiría un reloj que sincronizar y
 * un modo de fallo nuevo —el alumno que se va a comer y vuelve— a cambio de
 * ninguna garantía que la base de datos no dé ya.
 */
final class AttemptTicket
{
    /** Todo lo que `submitAttempt` necesita saber y no debe volver a deducir. */
    public static function emitir(string $itemId, int|string $quien, int $attemptNo, string $seed): string
    {
        $cuerpo = self::codificar(compact('itemId', 'quien', 'attemptNo', 'seed'));

        return $cuerpo.'.'.self::firma($cuerpo);
    }

    /**
     * Abre el billete y comprueba que es de ESTE ítem y de ESTE practicante.
     *
     * Un billete válido pero de otro ítem es tan inaceptable como uno falso, y
     * por eso las dos comprobaciones viven aquí y no en el controlador: quien
     * llame no puede olvidarse de una.
     *
     * @return array{attempt_no: int, seed: string}
     *
     * @throws InvalidArgumentException
     */
    public static function abrir(string $billete, string $itemId, int|string $quien): array
    {
        $partes = explode('.', $billete);

        if (count($partes) !== 2) {
            throw new InvalidArgumentException('El billete del intento está mal formado.');
        }

        [$cuerpo, $firma] = $partes;

        // `hash_equals` y no `===`: la comparación de una firma se hace en
        // tiempo constante o no se hace.
        if (! hash_equals(self::firma($cuerpo), $firma)) {
            throw new InvalidArgumentException('El billete del intento no es válido.');
        }

        $datos = json_decode(base64_decode(strtr($cuerpo, '-_', '+/'), true) ?: '', true);

        if (! is_array($datos)
            || ! isset($datos['itemId'], $datos['quien'], $datos['attemptNo'], $datos['seed'])) {
            throw new InvalidArgumentException('El billete del intento está incompleto.');
        }

        if (! hash_equals($datos['itemId'], $itemId)) {
            throw new InvalidArgumentException('El billete del intento es de otro ejercicio.');
        }

        // Comparación en cadena: el invitado usa 'invitado' y el alumno su id
        // numérico, y `'0' == 'invitado'` sería cierto con la comparación laxa
        // de PHP — justo el par que hay que separar.
        if (! hash_equals((string) $datos['quien'], (string) $quien)) {
            throw new InvalidArgumentException('El billete del intento es de otra sesión.');
        }

        return ['attempt_no' => (int) $datos['attemptNo'], 'seed' => (string) $datos['seed']];
    }

    private static function firma(string $cuerpo): string
    {
        return hash_hmac('sha256', $cuerpo, (string) Config::get('app.key'));
    }

    private static function codificar(array $datos): string
    {
        return rtrim(strtr(base64_encode(json_encode($datos)), '+/', '-_'), '=');
    }
}
