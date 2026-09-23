/**
 * EL RITMO DE LA SESIÓN (misión 4, PR 14): dónde vas, cuántos seguidos llevas
 * y cómo fue al terminar. Vive en la tanda y muere con ella — nada de esto se
 * guarda en base: es ritmo, no historial. Y es del alumno consigo mismo: aquí
 * no hay ranking ni «vas por detrás de», y no lo habrá (colegio, menores).
 *
 * Puro, sin React: lo usan práctica y repaso, y se prueba sin pintar nada.
 */

/** Cuántos ejercicios tiene una tanda de práctica. */
export const TANDA = 10;

/** Segundos por ejercicio con el contrarreloj puesto (opcional, apagado por defecto). */
export const SEGUNDOS_POR_ITEM = 20;

export function ritmoInicial() {
    return { respondidos: 0, aciertos: 0, seguidos: 0, mejor: 0 };
}

/**
 * Un ejercicio CERRADO: `acierto` = lo acertó al PRIMER intento. Un acierto a
 * la segunda o a la tercera (bucle de «otra vez») cierra el ejercicio sin
 * sumar — la racha ya se rompió al fallar.
 */
export function registrar(ritmo, acierto) {
    const seguidos = acierto ? ritmo.seguidos + 1 : 0;

    return {
        respondidos: ritmo.respondidos + 1,
        aciertos: ritmo.aciertos + (acierto ? 1 : 0),
        seguidos,
        mejor: Math.max(ritmo.mejor, seguidos),
    };
}

/** Un fallo rompe la racha en el acto, aunque el ejercicio siga abierto (otra vez). */
export function romper(ritmo) {
    return { ...ritmo, seguidos: 0 };
}

/** «8 de 10 · 2 seguidos al final», y la mejor racha si fue otra. */
export function resumen(ritmo, total = ritmo.respondidos) {
    const partes = [`${ritmo.aciertos} de ${total}`];
    partes.push(ritmo.seguidos === 1 ? '1 seguido al final' : `${ritmo.seguidos} seguidos al final`);
    if (ritmo.mejor > ritmo.seguidos && ritmo.mejor >= 2) partes.push(`mejor racha: ${ritmo.mejor} seguidos`);

    return partes.join(' · ');
}

// ---- el interruptor del contrarreloj: apagado por defecto, se recuerda por alumno ----

export function claveContrarreloj(userId) {
    return `contrarreloj:${userId ?? 'invitado'}`;
}

export function leerContrarreloj(userId) {
    try {
        return localStorage.getItem(claveContrarreloj(userId)) === '1';
    } catch {
        return false;
    }
}

export function guardarContrarreloj(userId, activo) {
    try {
        localStorage.setItem(claveContrarreloj(userId), activo ? '1' : '0');
    } catch { /* sin almacenamiento, sin memoria: sigue apagado */ }
}
