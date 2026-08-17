/**
 * La razón del selector adaptativo, en lenguaje de alumno.
 *
 * Vive aquí y no en cada página porque la usan dos superficies (el bucle de
 * práctica y /inicio): si divergen, el alumno lee dos explicaciones distintas
 * para la misma decisión del motor.
 */

/** Las razones que implican un DESVÍO a otra destreza (las que hay que avisar). */
export const RAZONES_DESVIO = {
    'refuerzo de prerrequisito': {
        icono: '↩',
        texto: 'Repasemos algo anterior: dominar esta destreza te destrabará la que estabas practicando.',
    },
    avance: {
        icono: '↗',
        texto: '¡A por lo siguiente! Ya dominas esta destreza, así que subimos un escalón.',
    },
};

/** Texto para CUALQUIER razón, incluida la práctica normal (la usa /inicio). */
export function textoDeRazon(reason) {
    if (RAZONES_DESVIO[reason]) {
        return RAZONES_DESVIO[reason];
    }

    return {
        icono: '→',
        texto: 'Sigue practicando esta destreza: aún no la dominas del todo.',
    };
}
