/**
 * LOS JUEGOS SOBRE EL VOCABULARIO (misión 4, PR 15), la parte pura: barajar
 * con semilla (nada de Math.random: el mismo tablero todo el día, y se prueba
 * sin adivinar), y cómo se arma cada juego con lo que ya está sembrado.
 *
 * Los juegos NO dan dominio ni AGS: son apoyo, como el mazo. Lo único que
 * escriben es «la sé» en la tarjeta al acertar, y eso lo hace la página por el
 * endpoint del mazo (el invitado, nada).
 */

/** FNV-1a de 32 bits sobre una cadena: la semilla numérica del generador. */
export function hash32(texto) {
    let h = 0x811c9dc5;
    for (let i = 0; i < texto.length; i++) {
        h ^= texto.charCodeAt(i);
        h = Math.imul(h, 0x01000193) >>> 0;
    }

    return h >>> 0;
}

/** mulberry32: un generador pequeño y determinista. Devuelve () => [0, 1). */
export function generador(semilla) {
    let a = hash32(String(semilla)) || 1;

    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/** Fisher-Yates con semilla: la misma lista y la misma semilla, el mismo orden. */
export function barajar(lista, semilla) {
    const azar = generador(semilla);
    const copia = [...lista];
    for (let i = copia.length - 1; i > 0; i--) {
        const j = Math.floor(azar() * (i + 1));
        [copia[i], copia[j]] = [copia[j], copia[i]];
    }

    return copia;
}

/**
 * MEMORIA. Hasta `grupos` tarjetas; cada una da un grupo de cartas: en it/fr/de
 * palabra ↔ significado (2 cartas); en chino carácter · pinyin · significado
 * (3 cartas, y se gana el grupo cuando están las tres). Las cartas se barajan
 * con la semilla y llevan la tarjeta de la que salen.
 *
 * @return {{ cartas: Array<{id: string, tarjeta: string, cara: string, texto: string, lang: ?string}>, tamano: number }}
 */
export function tableroDeMemoria(tarjetas, lengua, semilla, grupos = 6) {
    const zh = lengua === 'zh';
    const elegidas = barajar(tarjetas, `${semilla}:memoria:grupos`).slice(0, grupos);
    const cartas = elegidas.flatMap((t) => {
        const caras = [
            { id: `${t.id}:palabra`, tarjeta: t.id, cara: 'palabra', texto: t.palabra, lang: lengua },
            ...(zh ? [{ id: `${t.id}:lectura`, tarjeta: t.id, cara: 'lectura', texto: t.lectura ?? '', lang: 'zh-Latn' }] : []),
            { id: `${t.id}:significado`, tarjeta: t.id, cara: 'significado', texto: t.significado, lang: null },
        ];

        return caras;
    });

    return { cartas: barajar(cartas, `${semilla}:memoria:cartas`), tamano: zh ? 3 : 2 };
}

/** ¿Están estas cartas completas y de la misma tarjeta? */
export function grupoCompleto(cartas, tamano) {
    return cartas.length === tamano && new Set(cartas.map((c) => c.tarjeta)).size === 1;
}

/**
 * EMPAREJAR CONTRA EL RELOJ: dos columnas barajadas por separado — palabras a
 * la izquierda (en chino, con su pinyin debajo), significados a la derecha.
 */
export function columnasDeEmparejar(tarjetas, semilla) {
    return {
        palabras: barajar(tarjetas, `${semilla}:emparejar:palabras`),
        significados: barajar(tarjetas, `${semilla}:emparejar:significados`),
    };
}

/**
 * ¿CUÁL SOBRA?: rondas de cuatro palabras —tres de la unidad y una INTRUSA de
 * otra unidad de la misma lengua—. Los grupos salen del dato: el «campo» es la
 * unidad. Cada ronda baraja las cuatro con la semilla y el número de ronda.
 *
 * @return Array<{ opciones: Array<{id: string, palabra: string, lectura: ?string, significado: string, unidad: ?number}>, intrusa: string }>
 */
export function rondasDeCualSobra(tarjetas, intrusas, semilla, rondas = 5) {
    if (tarjetas.length < 3 || intrusas.length === 0) return [];
    const propias = barajar(tarjetas, `${semilla}:sobra:propias`);
    const ajenas = barajar(intrusas, `${semilla}:sobra:ajenas`);
    const salida = [];
    for (let r = 0; r < rondas; r++) {
        const tres = [0, 1, 2].map((k) => propias[(r * 3 + k) % propias.length]);
        if (new Set(tres.map((t) => t.id)).size < 3) break;   // no hay tres distintas: se acaba
        const intrusa = ajenas[r % ajenas.length];
        salida.push({
            opciones: barajar([...tres.map((t) => ({ ...t, unidad: null })), intrusa], `${semilla}:sobra:${r}`),
            intrusa: intrusa.id,
        });
    }

    return salida;
}

// ---- la marca personal del reloj: del alumno consigo mismo, y de nadie más ----

export function claveMarca(userId, lengua, unidad) {
    return `marca:${userId ?? 'invitado'}:${lengua}:u${unidad}`;
}

export function leerMarca(userId, lengua, unidad) {
    try {
        const v = localStorage.getItem(claveMarca(userId, lengua, unidad));

        return v === null ? null : Number(v);
    } catch {
        return null;
    }
}

/** Guarda solo si mejora. Devuelve la marca vigente. */
export function guardarMarca(userId, lengua, unidad, parejas) {
    const anterior = leerMarca(userId, lengua, unidad);
    if (anterior !== null && anterior >= parejas) return anterior;
    try {
        localStorage.setItem(claveMarca(userId, lengua, unidad), String(parejas));
    } catch { /* sin almacenamiento no hay marca: se juega igual */ }

    return parejas;
}
