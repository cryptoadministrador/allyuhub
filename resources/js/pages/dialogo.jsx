import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../layouts/AppLayout';

/**
 * EL INTERLOCUTOR GUIONIZADO. Una conversación ramificada escrita a mano: el
 * interlocutor dice algo, el alumno elige entre 2-3 respuestas, y cada una lleva
 * a otro nodo. Un callejón NO es un error: vuelve al mismo nodo con una pista.
 *
 * No hay «solución» oculta — todas las ramas son contenido. Al llegar al final,
 * se registra que se completó (sube el dominio del descriptor una vez); con
 * sesión se guarda, de invitado no.
 */

function tokenXsrf() {
    const par = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return par ? decodeURIComponent(par[1]) : '';
}

function esFinal(nodo) {
    return nodo?.fin === true || !nodo?.respuestas || nodo.respuestas.length === 0;
}

export default function Dialogo({ lengua, nombre, unidad, dialogo, se_guarda: seGuarda }) {
    // Los hooks se llaman SIEMPRE, antes de cualquier return: con diálogo nulo
    // se inicializan sin tocar nada (optional chaining) y la página de
    // «próximamente» se pinta después. Romper esto viola las reglas de hooks.
    const nodos = dialogo?.nodos ?? [];
    const mapa = useMemo(() => Object.fromEntries(nodos.map((n) => [n.id, n])), [nodos]);
    const primero = nodos[0];

    const [actual, setActual] = useState(primero?.id);
    const [historial, setHistorial] = useState(
        primero ? [{ quien: 'interlocutor', texto: primero.dice, audio: primero.audio }] : [],
    );
    const [pista, setPista] = useState(null);
    const [completado, setCompletado] = useState(esFinal(primero));

    if (!dialogo) {
        return (
            <AppLayout>
                <Head title={`Hablar · ${nombre} · Unidad ${unidad.n}`} />
                <div className="mx-auto max-w-2xl">
                    <Link href={`/corso/${lengua}/u${unidad.n}`} className="text-sm font-medium text-marca-700 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                        ← Unidad {unidad.n}
                    </Link>
                    <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Hablar</h1>
                    <p role="status" className="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        El interlocutor de esta unidad todavía no está publicado. Próximamente.
                    </p>
                </div>
            </AppLayout>
        );
    }

    async function registrarCompletado() {
        await fetch(`/api/v1/dialogos/${dialogo.id}/completado`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': tokenXsrf(),
            },
            body: '{}',
        });
    }

    function responder(respuesta) {
        if (respuesta.va === null || respuesta.va === undefined) {
            setPista(respuesta.pista);   // callejón: pista y se queda

            return;
        }

        const destino = mapa[respuesta.va];
        setPista(null);
        setHistorial((h) => [
            ...h,
            { quien: 'yo', texto: respuesta.texto },
            { quien: 'interlocutor', texto: destino.dice, audio: destino.audio },
        ]);
        setActual(destino.id);

        if (esFinal(destino)) {
            setCompletado(true);
            registrarCompletado();
        }
    }

    function reiniciar() {
        setActual(primero.id);
        setHistorial([{ quien: 'interlocutor', texto: primero.dice, audio: primero.audio }]);
        setPista(null);
        setCompletado(esFinal(primero));
    }

    const nodo = mapa[actual];

    return (
        <AppLayout>
            <Head title={`Hablar · ${nombre} · Unidad ${unidad.n}`} />

            <div className="mx-auto max-w-2xl">
                <Link href={`/corso/${lengua}/u${unidad.n}`} className="text-sm font-medium text-marca-700 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                    ← Unidad {unidad.n}
                </Link>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{dialogo.titulo}</h1>
                <p className="mt-1 text-sm text-slate-600">Elige qué responder. Si te equivocas, te doy una pista.</p>

                <div className="mt-6 space-y-3" aria-label="Conversación">
                    {historial.map((linea, i) => (
                        <div key={i} className={linea.quien === 'yo' ? 'flex justify-end' : 'flex justify-start'}>
                            <div
                                aria-live={i === historial.length - 1 && linea.quien === 'interlocutor' ? 'polite' : undefined}
                                className={`max-w-[80%] rounded-lg px-3 py-2 ${
                                    linea.quien === 'yo'
                                        ? 'bg-marca-600 text-white'
                                        : 'border border-slate-200 bg-white text-slate-900'
                                }`}
                            >
                                <span className="sr-only">{linea.quien === 'yo' ? 'Tú: ' : 'Interlocutor: '}</span>
                                {linea.texto}
                                {linea.audio && (
                                    <audio src={linea.audio} controls className="mt-2 w-full" />
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {pista && (
                    <p role="status" className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        💡 {pista}
                    </p>
                )}

                {!completado && (
                    <div className="mt-6 flex flex-col gap-2">
                        {nodo.respuestas.map((r, i) => (
                            <button
                                key={i}
                                type="button"
                                onClick={() => responder(r)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-left text-sm font-medium text-slate-800 hover:border-marca-500 hover:bg-marca-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                            >
                                {r.texto}
                            </button>
                        ))}
                    </div>
                )}

                {completado && (
                    <div className="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p role="status" className="text-sm font-semibold text-emerald-900">
                            ✅ ¡Completaste la conversación!
                        </p>
                        {!seGuarda && (
                            <p className="mt-1 text-sm text-emerald-800">
                                Esto no se ha guardado.{' '}
                                <a href="/entrar" className="font-medium underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                                    Entra desde tu aula
                                </a>{' '}
                                para conservar tu avance.
                            </p>
                        )}
                        <button
                            type="button"
                            onClick={reiniciar}
                            className="mt-3 rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                        >
                            Empezar de nuevo
                        </button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
