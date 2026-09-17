import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '../layouts/AppLayout';

/**
 * EL VOCABULARIO DE LA UNIDAD: tarjetas, como Khan/Anki. Anverso = la palabra
 * (en chino, el carácter grande y el pinyin debajo; en alemán, con su
 * artículo, que ya viene dentro de la palabra); reverso = significado y una
 * frase de la lección. Dos botones: «La sé» la saca del mazo hasta mañana;
 * «Todavía no» la manda al final del mazo de hoy.
 *
 * NO es un ejercicio: no se corrige nada y no da dominio. Con sesión, cada
 * respuesta va a `POST api/v1/vocabulario/{id}/estado`; el invitado juega en
 * memoria y la API no le escribe nada aunque la llame.
 */

function tokenXsrf() {
    const par = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return par ? decodeURIComponent(par[1]) : '';
}

function decir(id, conocida) {
    return fetch(`/api/v1/vocabulario/${id}/estado`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': tokenXsrf() },
        body: JSON.stringify({ conocida }),
    }).catch(() => null);
}

const BOTON = 'rounded px-4 py-2 font-medium focus:outline-2 focus:outline-offset-2 focus:outline-marca-600';

/** Una tarjeta con sus dos caras. Es un botón: Enter/Espacio la dan la vuelta. */
export function Tarjeta({ tarjeta, volteada, onVoltear, lengua }) {
    const zh = lengua === 'zh';

    return (
        <button
            type="button"
            onClick={onVoltear}
            aria-pressed={volteada}
            aria-label={volteada ? 'Reverso. Pulsa para ver la palabra' : 'Anverso. Pulsa para ver el significado'}
            className="block w-full rounded-xl border-2 border-marca-200 bg-white p-8 text-center shadow-sm transition-shadow hover:shadow-md focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
        >
            {!volteada ? (
                <>
                    <p lang={lengua} className={`font-semibold text-slate-900 ${zh ? 'text-6xl' : 'text-3xl'}`}>{tarjeta.palabra}</p>
                    {zh && tarjeta.lectura && <p className="mt-3 text-xl text-slate-700">{tarjeta.lectura}</p>}
                </>
            ) : (
                <>
                    <p className="text-2xl font-semibold text-slate-900">{tarjeta.significado}</p>
                    <p lang={lengua} className="mt-4 text-lg text-slate-800">{tarjeta.ejemplo.texto}</p>
                    <p className="mt-1 text-sm text-slate-600">{tarjeta.ejemplo.es}</p>
                </>
            )}
        </button>
    );
}

export default function Vocabulario({ lengua, nombre, unidad, tarjetas, se_guarda: seGuarda, revision = false }) {
    const { props: compartidas } = usePage();
    const invitado = !compartidas.auth?.user;

    // El mazo de hoy, en el orden del banco; «todavía no» rota al final.
    const [mazo, setMazo] = useState(() => tarjetas.filter((t) => t.hoy).map((t) => t.id));
    const [conocidas, setConocidas] = useState(() => new Set(tarjetas.filter((t) => t.conocida).map((t) => t.id)));
    const [volteada, setVolteada] = useState(false);
    const [hechas, setHechas] = useState(0);
    const cartaRef = useRef(null);

    const porId = Object.fromEntries(tarjetas.map((t) => [t.id, t]));
    const actual = porId[mazo[0]];

    useEffect(() => { cartaRef.current?.focus?.(); }, [mazo.length, hechas]);

    function responder(laSe) {
        const id = mazo[0];
        if (!revision) decir(id, laSe);
        setConocidas((c) => { const n = new Set(c); laSe ? n.add(id) : n.delete(id); return n; });
        // «La sé» sale del mazo hasta mañana; «todavía no» va al final.
        setMazo((m) => (laSe ? m.slice(1) : [...m.slice(1), id]));
        setVolteada(false);
        setHechas((h) => h + 1);
    }

    return (
        <AppLayout>
            <Head title={`Vocabulario · Unidad ${unidad.n} · ${nombre}`} />

            <div className="mx-auto max-w-xl">
                {!revision && (
                    <Link href={`/corso/${lengua}/u${unidad.n}`} className="text-sm font-medium text-marca-700 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                        ← Unidad {unidad.n} · {unidad.titulo}
                    </Link>
                )}
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Vocabulario</h1>
                <p className="mt-1 text-sm text-slate-700" role="status">
                    Sabes {conocidas.size} de {tarjetas.length} palabras
                    {mazo.length > 0 ? ` · Hoy: ${mazo.length} ${mazo.length === 1 ? 'tarjeta' : 'tarjetas'}` : ''}
                </p>
                {invitado && !revision && (
                    <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Estás repasando como visitante: lo que marques <strong>no se guarda</strong>.
                    </p>
                )}

                {actual ? (
                    <div className="mt-6" ref={cartaRef} tabIndex={-1}>
                        <Tarjeta tarjeta={actual} volteada={volteada} onVoltear={() => setVolteada((v) => !v)} lengua={lengua} />
                        {actual.audio && (
                            <audio controls preload="none" src={actual.audio} className="mt-3 w-full" aria-label={`Escucha: ${actual.palabra}`} />
                        )}
                        {volteada ? (
                            <div className="mt-4 flex flex-wrap justify-center gap-3">
                                <button type="button" onClick={() => responder(false)} className={`${BOTON} border border-slate-300 bg-white text-slate-800 hover:bg-slate-50`}>
                                    Todavía no
                                </button>
                                <button type="button" onClick={() => responder(true)} className={`${BOTON} bg-marca-600 text-white hover:bg-marca-700`}>
                                    La sé
                                </button>
                            </div>
                        ) : (
                            <p className="mt-4 text-center text-sm text-slate-600">Piensa qué significa y dale la vuelta.</p>
                        )}
                    </div>
                ) : (
                    <div className="mt-6 rounded-lg border border-l-4 border-emerald-200 border-l-emerald-600 bg-emerald-50 p-4">
                        <p className="text-xl font-semibold text-slate-900">Por hoy, listo.</p>
                        <p className="mt-1 text-sm text-slate-800">
                            Sabes {conocidas.size} de {tarjetas.length} palabras. Mañana vuelven a salir para que no se te olviden.
                        </p>
                        {invitado && !revision && <p className="mt-1 text-sm text-slate-800">Esto no se ha guardado.</p>}
                        {!revision && (
                            <Link href={`/corso/${lengua}/u${unidad.n}`} className={`${BOTON} mt-3 inline-block bg-marca-600 text-white hover:bg-marca-700`}>
                                Volver a la unidad
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
