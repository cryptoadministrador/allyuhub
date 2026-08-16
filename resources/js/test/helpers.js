/**
 * Utilidades compartidas de los tests JS.
 * Regla de la casa: cero violaciones axe `serious` o `critical`.
 * (Las `moderate` de landmarks en fragmentos sueltos no bloquean.)
 */
export function violacionesGraves(resultadosAxe) {
    return resultadosAxe.violations.filter((v) => ['serious', 'critical'].includes(v.impact));
}

/** Respuesta estilo fetch para los mocks. */
export function respuestaJson(status, body) {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: () => Promise.resolve(body),
    };
}
