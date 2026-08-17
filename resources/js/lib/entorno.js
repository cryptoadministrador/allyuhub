/**
 * ¿La app corre embebida (iframe de Moodle) o en ventana completa?
 *
 * No puede salir de una prop del servidor: la sesión nace en el launch, pero
 * la MISMA sesión sirve después pestañas en ventana completa. Solo el
 * navegador sabe dónde está pintando, así que la decisión es del cliente —
 * extraída aquí como función PURA para poder probarla.
 */
export function estaEmbebido(win) {
    if (!win) {
        return false;   // sin window (SSR, test): asumir ventana completa
    }

    try {
        return win.self !== win.top;
    } catch {
        // Un iframe de OTRO origen (el caso real de Moodle) hace que leer
        // window.top lance: si lanza, estamos embebidos.
        return true;
    }
}
