import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Practicar from './Practicar';
import Recurso from './Recurso';

/**
 * UNA PIEZA EN REVISIÓN, tal como la ve el alumno.
 *
 * No hay visor de revisión: esta página RENDERIZA la página del alumno
 * (`Recurso.jsx` para una lección, `Practicar.jsx` para un ejercicio) y le pone
 * encima una barra de acciones. Un visor propio revisaría una cosa distinta de
 * la que se publica — que es exactamente el fallo que esto evita.
 *
 * Las dos traen su propio AppLayout, así que aquí NO se envuelve nada: la barra
 * va fija abajo, sobre la página del alumno.
 */

function Barra({ pieza }) {
    const [abierto, setAbierto] = useState(null);   // null | 'devolver' | 'desfirmar'
    const { data, setData, post, processing, errors, reset } = useForm({ nota: '' });

    function enviar(accion) {
        post(`/docente/revisar/${pieza.tipo}/${pieza.id}/${accion}`, {
            preserveScroll: true,
            onSuccess: () => { reset(); setAbierto(null); },
        });
    }

    return (
        <div className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-300 bg-white/95 p-3 shadow-lg backdrop-blur">
            <div className="mx-auto flex max-w-3xl flex-col gap-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-slate-700">
                        <Link
                            href="/docente/revisar"
                            className="font-medium text-marca-700 underline hover:text-marca-900 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                        >
                            ← Volver a revisar
                        </Link>
                        <span className="ml-3">
                            {pieza.firmada ? '✅ Firmada · se ve' : '⏳ Sin firmar · no se ve'}
                        </span>
                    </p>

                    <div className="flex flex-wrap gap-2">
                        {!pieza.firmada && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => enviar('firmar')}
                                    disabled={processing}
                                    className="rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50"
                                >
                                    Firmar
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setAbierto(abierto === 'devolver' ? null : 'devolver')}
                                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                >
                                    Devolver con nota
                                </button>
                            </>
                        )}
                        {pieza.firmada && (
                            <button
                                type="button"
                                onClick={() => setAbierto(abierto === 'desfirmar' ? null : 'desfirmar')}
                                className="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-800 hover:bg-rose-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                            >
                                Retirar firma
                            </button>
                        )}
                    </div>
                </div>

                {abierto && (
                    <div>
                        <label htmlFor="nota-revision" className="mb-1 block text-sm font-medium text-slate-700">
                            {abierto === 'devolver'
                                ? 'Qué hay que corregir (obligatorio)'
                                : 'Por qué se retira (obligatorio)'}
                        </label>
                        <textarea
                            id="nota-revision"
                            value={data.nota}
                            onChange={(e) => setData('nota', e.target.value)}
                            rows={2}
                            maxLength={1000}
                            className="w-full rounded-lg border border-slate-300 p-2 text-slate-900 focus:border-marca-500 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                        />
                        {errors.nota && (
                            <p role="alert" className="mt-1 text-sm text-rose-700">{errors.nota}</p>
                        )}
                        <button
                            type="button"
                            onClick={() => enviar(abierto)}
                            disabled={processing || data.nota.trim().length < 3}
                            className="mt-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50"
                        >
                            {abierto === 'devolver' ? 'Devolver' : 'Retirar firma'}
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function DocenteRevisarPieza({ pieza, notas, recurso, destrezas, objective }) {
    return (
        <>
            {recurso
                ? <Recurso recurso={recurso} destrezas={destrezas} />
                : <Practicar objective={objective} mastery={null} revision={pieza.id} />}

            {notas.length > 0 && (
                <div className="mx-auto max-w-3xl px-4 pb-40">
                    <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-600">
                        Historial de revisión
                    </h2>
                    <ul className="space-y-2">
                        {notas.map((n, i) => (
                            <li key={i} className="rounded-lg border border-slate-200 bg-slate-50 p-2 text-sm text-slate-700">
                                <span className="font-medium">{n.accion}</span>
                                {n.docente ? ` · ${n.docente}` : ''} · {n.cuando}
                                {n.nota && <p className="mt-1">{n.nota}</p>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Hueco para que la barra fija no tape el final de la página. */}
            <div className="h-32" aria-hidden="true" />
            <Barra pieza={pieza} />
        </>
    );
}
