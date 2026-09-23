import { Fragment, useEffect, useRef, useState } from 'react';

/**
 * EL ÁREA COMPARTIDA (misión 4, PR 12): lo que el alumno TOCA.
 *
 * Hasta aquí `orden` y `pares` eran listas de botones y `hueco` una consigna
 * con «___» al final. Ahora `orden` es una frase que se construye arriba con
 * fichas de un banco, `pares` es un tablero donde la unión SE VE (color
 * compartido + número), y el hueco vive dentro de la frase.
 *
 * LA REGLA QUE NO SE NEGOCIA: se juega entero con teclado. La interacción a
 * clic de siempre es el camino accesible (tocar una ficha la coloca o la
 * devuelve; las flechas la mueven dentro de la frase); arrastrar se AÑADE
 * ENCIMA con eventos de puntero, sin librería, y nunca sustituye al clic.
 */

/** El primer texto de la opción con esa clave (las de lengua no llevan `es`). */
export function primerTexto(opciones, clave) {
    return Object.values(opciones.find((o) => o.key === clave)?.text ?? {})[0];
}

/**
 * Dónde cae una ficha soltada en la línea: el índice de inserción entre las
 * fichas ya colocadas (sus rectángulos, en orden, SIN la que se arrastra).
 * Pura, para poder probarla sin layout: jsdom no mide nada.
 */
export function indiceDeCaida(rects, x, y) {
    if (rects.length === 0) return 0;

    // La fila: las fichas cuyo rango vertical contiene al puntero.
    const enFila = rects.map((r, i) => ({ r, i })).filter(({ r }) => y >= r.top && y <= r.bottom);
    if (enFila.length === 0) {
        return y < rects[0].top ? 0 : rects.length;
    }
    const derecha = enFila.find(({ r }) => x < r.left + r.width / 2);

    return derecha ? derecha.i : enFila[enFila.length - 1].i + 1;
}

/** ¿Está el punto dentro del rectángulo (con un margen para no ser quisquilloso)? */
export function dentroDe(rect, x, y, margen = 12) {
    return x >= rect.left - margen && x <= rect.right + margen && y >= rect.top - margen && y <= rect.bottom + margen;
}

/** Los dos trozos de una consigna alrededor de su hueco («___»), o null si no lo tiene. */
export function partirHueco(texto) {
    const m = /_{2,}/.exec(texto ?? '');

    return m ? [texto.slice(0, m.index), texto.slice(m.index + m[0].length)] : null;
}

const FICHA = 'touch-none select-none rounded border px-3 py-1.5 text-lg leading-snug focus:outline-2 focus:outline-offset-2 focus:outline-marca-600';
const UMBRAL_ARRASTRE = 6;

function Marcador() {
    return <span aria-hidden="true" className="h-8 w-0.5 rounded bg-marca-600" />;
}

/**
 * ORDEN: la frase construyéndose arriba, el banco abajo. Se toca o se arrastra.
 * Controlado: la secuencia vive en quien lo usa.
 */
export function TableroDeOrden({ opciones, secuencia, onSecuencia, nombre, inputRef, fijas = [] }) {
    const lineaRef = useRef(null);
    const fichasRef = useRef({});
    const gesto = useRef(null);          // el puntero que empezó sobre una ficha
    const ignorarClick = useRef(false);  // tras un arrastre, el clic que lo sigue no cuenta
    const enfocar = useRef(null);        // la ficha que hay que re-enfocar tras moverla
    const [arrastre, setArrastre] = useState(null);

    useEffect(() => {
        if (enfocar.current) {
            fichasRef.current[enfocar.current]?.focus();
            enfocar.current = null;
        }
    });

    const enBanco = opciones.filter((o) => !secuencia.includes(o.key));
    // Las fichas FIJAS (el andamiaje del segundo fallo: «esta va primero») son
    // el prefijo de la frase: no se tocan, no se arrastran, nada pasa por delante.
    const esFija = (clave) => fijas.includes(clave);
    const primeraLibre = fijas.length;

    function mover(clave, aIndice) {
        if (esFija(clave)) return;
        const sin = secuencia.filter((k) => k !== clave);
        const i = Math.max(primeraLibre, Math.min(aIndice, sin.length));
        onSecuencia([...sin.slice(0, i), clave, ...sin.slice(i)]);
    }

    function alPulsarTecla(e, clave) {
        if (esFija(clave)) return;
        const i = secuencia.indexOf(clave);
        if (e.key === 'ArrowLeft' && i > primeraLibre) {
            e.preventDefault();
            enfocar.current = clave;
            mover(clave, i - 1);
        } else if (e.key === 'ArrowRight' && i < secuencia.length - 1) {
            e.preventDefault();
            enfocar.current = clave;
            mover(clave, i + 1);
        }
    }

    // ---- arrastrar, ENCIMA del clic ----

    function indiceEnLinea(x, y, clave) {
        const linea = lineaRef.current?.getBoundingClientRect();
        if (!linea || !dentroDe(linea, x, y)) return null;
        const rects = secuencia
            .filter((k) => k !== clave)
            .map((k) => fichasRef.current[k]?.getBoundingClientRect())
            .filter(Boolean);

        return indiceDeCaida(rects, x, y);
    }

    function alBajar(e, clave, origen) {
        if (esFija(clave)) return;
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        gesto.current = { clave, origen, x0: e.clientX, y0: e.clientY, arrastrando: false };
        e.currentTarget.setPointerCapture?.(e.pointerId);
    }

    function alMover(e) {
        const g = gesto.current;
        if (!g) return;
        const dx = e.clientX - g.x0;
        const dy = e.clientY - g.y0;
        if (!g.arrastrando && Math.hypot(dx, dy) < UMBRAL_ARRASTRE) return;
        g.arrastrando = true;
        setArrastre({ clave: g.clave, dx, dy, indice: indiceEnLinea(e.clientX, e.clientY, g.clave) });
    }

    function alSoltar(e) {
        const g = gesto.current;
        gesto.current = null;
        if (!g?.arrastrando) return;   // fue un clic: lo atiende onClick
        ignorarClick.current = true;
        setTimeout(() => { ignorarClick.current = false; }, 0);
        const indice = indiceEnLinea(e.clientX, e.clientY, g.clave);
        if (indice !== null) mover(g.clave, indice);
        else if (g.origen === 'linea') onSecuencia(secuencia.filter((k) => k !== g.clave));
        setArrastre(null);
    }

    function alCancelar() {
        gesto.current = null;
        setArrastre(null);
    }

    const alClic = (fn) => () => {
        if (ignorarClick.current) return;
        fn();
    };

    const puntero = (clave, origen) => ({
        onPointerDown: (e) => alBajar(e, clave, origen),
        onPointerMove: alMover,
        onPointerUp: alSoltar,
        onPointerCancel: alCancelar,
    });

    const estiloArrastre = (clave) => (arrastre?.clave === clave
        ? { transform: `translate(${arrastre.dx}px, ${arrastre.dy}px)`, position: 'relative', zIndex: 10, opacity: 0.85 }
        : undefined);

    // El marcador de caída va entre las fichas que NO se arrastran: se cuenta
    // sobre esa lista, que es sobre la que se calculó el índice.
    let j = 0;
    const idAyuda = `${nombre}-ayuda-frase`;

    return (
        <div className="mb-4">
            <p id={idAyuda} className="sr-only">
                Pulsa una palabra de la frase para devolverla al banco; con las flechas izquierda y derecha la mueves de sitio.
            </p>
            <div
                ref={lineaRef}
                role="group"
                aria-label="Tu frase"
                className="mb-4 flex min-h-16 flex-wrap items-center gap-x-1.5 gap-y-2 rounded-lg border-2 border-dashed border-slate-300 bg-white px-3 py-2"
            >
                {secuencia.length === 0 && (
                    <span className="text-sm text-slate-600">Toca o arrastra las palabras aquí, en orden.</span>
                )}
                {secuencia.map((clave) => {
                    const arrastrada = arrastre?.clave === clave;
                    const marcador = !arrastrada && arrastre?.indice === j && j >= primeraLibre;
                    if (!arrastrada) j++;

                    if (esFija(clave)) {
                        return (
                            <span
                                key={clave}
                                ref={(el) => { fichasRef.current[clave] = el; }}
                                aria-label={`${primerTexto(opciones, clave)}, va primero`}
                                className={`${FICHA} border-emerald-600 bg-emerald-50 text-emerald-900`}
                            >
                                {primerTexto(opciones, clave)}
                            </span>
                        );
                    }

                    return (
                        <Fragment key={clave}>
                            {marcador && <Marcador />}
                            <button
                                ref={(el) => { fichasRef.current[clave] = el; }}
                                type="button"
                                aria-describedby={idAyuda}
                                onClick={alClic(() => onSecuencia(secuencia.filter((k) => k !== clave)))}
                                onKeyDown={(e) => alPulsarTecla(e, clave)}
                                {...puntero(clave, 'linea')}
                                style={estiloArrastre(clave)}
                                className={`${FICHA} cursor-grab border-marca-300 bg-marca-50 text-marca-900 hover:bg-marca-100`}
                            >
                                {primerTexto(opciones, clave)}
                            </button>
                        </Fragment>
                    );
                })}
                {arrastre !== null && arrastre.indice === j && secuencia.length > 0 && <Marcador />}
            </div>

            <div role="group" aria-label="Palabras disponibles" className="flex flex-wrap gap-2">
                {enBanco.map((o, i) => (
                    <button
                        key={o.key}
                        ref={i === 0 ? inputRef : undefined}
                        type="button"
                        onClick={alClic(() => onSecuencia([...secuencia, o.key]))}
                        {...puntero(o.key, 'banco')}
                        style={estiloArrastre(o.key)}
                        className={`${FICHA} cursor-grab border-slate-300 bg-white text-slate-900 hover:bg-slate-50`}
                    >
                        {primerTexto(opciones, o.key)}
                    </button>
                ))}
            </div>
        </div>
    );
}

// Seis parejas distintas a la vista; el NÚMERO es el que porta el significado,
// el color solo refuerza (regla de color: nunca solo el color).
const COLORES_PAREJA = [
    'border-marca-600 bg-marca-50 text-marca-900',
    'border-emerald-600 bg-emerald-50 text-emerald-900',
    'border-amber-600 bg-amber-50 text-amber-900',
    'border-sky-600 bg-sky-50 text-sky-900',
    'border-rose-600 bg-rose-50 text-rose-900',
    'border-violet-600 bg-violet-50 text-violet-900',
];

/**
 * PARES: dos o tres columnas. Se une tocando un elemento y luego su pareja; la
 * unión se ve en el tablero (mismo color, mismo número). Deshacer es tocarla.
 */
export function TableroDePares({ opciones, columnas, parejas, pendiente, onCambio, inputRef, fijas = [] }) {
    const parejaDe = {};
    parejas.forEach((tupla, i) => tupla.forEach((k) => { parejaDe[k] = i; }));
    // Las parejas FIJAS (el andamiaje: las que el alumno ya clavó) se quedan
    // formadas y no se deshacen: en el tablero solo se juega lo que está mal.
    const esFija = (i) => fijas.some((f) => f.length === parejas[i]?.length && f.every((k, j) => k === parejas[i][j]));

    function tocar(col, clave) {
        const sel = { ...pendiente, [col]: pendiente[col] === clave ? undefined : clave };
        // Una clave por columna: al completarse, la pareja se forma sola (en
        // el orden de las columnas, que es el de la tupla).
        if (columnas.every((c) => sel[c])) {
            onCambio({ parejas: [...parejas, columnas.map((c) => sel[c])], pendiente: {} });
        } else {
            onCambio({ pendiente: sel });
        }
    }

    return (
        <div className="mb-4 grid gap-3" style={{ gridTemplateColumns: `repeat(${columnas.length}, minmax(0, 1fr))` }}>
            {columnas.map((col, ci) => (
                <div key={col} role="group" aria-label={`Columna ${ci + 1}`} className="space-y-2">
                    {opciones.filter((o) => o.col === col).map((o, oi) => {
                        const texto = primerTexto(opciones, o.key);
                        const i = parejaDe[o.key];

                        if (i !== undefined) {
                            const textos = parejas[i].map((k) => primerTexto(opciones, k)).join(' — ');
                            const fija = esFija(i);

                            return (
                                <button
                                    key={o.key}
                                    type="button"
                                    disabled={fija}
                                    aria-label={fija ? `Pareja ${i + 1}: ${textos}. Correcta` : `Pareja ${i + 1}: ${textos}. Deshacer`}
                                    onClick={() => onCambio({ parejas: parejas.filter((_, j) => j !== i), pendiente: {} })}
                                    className={`flex w-full items-center gap-2 rounded border-2 px-3 py-2 text-left text-base focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 ${fija ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : COLORES_PAREJA[i % COLORES_PAREJA.length]}`}
                                >
                                    <span aria-hidden="true" className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/80 text-xs font-bold">
                                        {i + 1}
                                    </span>
                                    <span>{texto}</span>
                                </button>
                            );
                        }

                        return (
                            <button
                                key={o.key}
                                ref={ci === 0 && oi === 0 ? inputRef : undefined}
                                type="button"
                                aria-pressed={pendiente[col] === o.key}
                                onClick={() => tocar(col, o.key)}
                                className={`block w-full rounded border px-3 py-2 text-left text-base focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 ${
                                    pendiente[col] === o.key
                                        ? 'border-marca-600 bg-marca-50 text-marca-900'
                                        : 'border-slate-300 bg-white text-slate-900 hover:bg-slate-50'
                                }`}
                            >
                                {texto}
                            </button>
                        );
                    })}
                </div>
            ))}
        </div>
    );
}
