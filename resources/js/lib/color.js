/**
 * Color de asignatura: medido, no supuesto.
 *
 * REGLA DE LA CASA — el color JAMÁS es el único portador de significado (siempre
 * va con icono y con texto) y el texto que se pinte encima cumple WCAG 2.2 AA
 * (4.5:1). Ninguno de los 15 colores de `curriculo-semilla.json` llega a 4.5:1
 * contra blanco (el mejor da 3.91), así que el color se usa como ACENTO: borde,
 * fondo suave, y texto oscurecido hasta cumplir. Las funciones de abajo lo
 * calculan; `__tests__/color.test.js` mide el resultado contra el JSON real.
 */

const NEGRO_TINTA = '#0f172a'; // slate-900, la tinta de la app
const BLANCO = '#ffffff';

export { NEGRO_TINTA };

function canales(hex) {
    const h = hex.replace('#', '');
    return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
}

function aHex([r, g, b]) {
    return (
        '#' +
        [r, g, b]
            .map((v) =>
                Math.max(0, Math.min(255, Math.round(v)))
                    .toString(16)
                    .padStart(2, '0'),
            )
            .join('')
    );
}

/** Luminancia relativa WCAG 2.x. */
export function luminancia(hex) {
    const [r, g, b] = canales(hex).map((v) => {
        const s = v / 255;
        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** Razón de contraste entre dos colores (1 a 21). */
export function contraste(a, b) {
    const la = luminancia(a);
    const lb = luminancia(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

/** La tinta legible sobre un fondo dado: la que más contraste da. */
export function tintaSobre(fondo) {
    return contraste(fondo, NEGRO_TINTA) >= contraste(fondo, BLANCO) ? NEGRO_TINTA : BLANCO;
}

/** Mezcla el color con blanco (0 = blanco puro, 1 = color puro). */
export function mezclaConBlanco(hex, proporcion) {
    return aHex(canales(hex).map((v) => 255 - (255 - v) * proporcion));
}

/**
 * El mismo color, oscurecido lo justo para que sirva de TEXTO sobre blanco.
 * Converge siempre (el bucle tiende a negro, que da 21:1).
 */
export function tintaDeAcento(hex, minimo = 4.5) {
    let rgb = canales(hex);
    for (let i = 0; i < 60 && contraste(aHex(rgb), BLANCO) < minimo; i++) {
        rgb = rgb.map((v) => v * 0.92);
    }
    return aHex(rgb);
}

/**
 * Variables CSS de una asignatura, para pasarlas por `style` a una tarjeta.
 * Si no hay color (currículo importado sin estilos), devuelve un acento neutro
 * y la UI sigue funcionando: el color nunca es obligatorio.
 */
export function estiloDeAsignatura(color) {
    const base = /^#[0-9a-f]{6}$/i.test(color || '') ? color : '#64748b';
    return {
        '--acento': base,
        '--acento-suave': mezclaConBlanco(base, 0.14),
        '--acento-tinta': tintaDeAcento(base),
        '--acento-sobre': tintaSobre(base),
    };
}
