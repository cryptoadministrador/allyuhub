import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';

/**
 * LA COLA DEL DOCENTE: lo pendiente de sus alumnos, con la rúbrica de la unidad
 * al lado. La rúbrica VIENE DEL CONTENIDO (prop `rubrica`), no se escribe aquí.
 *
 * El docente marca 4 criterios × 3 niveles y deja dos frases. Corregir NO toca
 * el dominio ni la nota AGS: es la devolución de una persona, guardada tal cual.
 * La voz se oye por su ruta con permiso (`audio_url`), nunca inline.
 */

function Correccion({ produccion }) {
    const { data, setData, post, processing } = useForm({
        rubrica: {},
        comentario: '',
    });

    const claves = produccion.rubrica.criterios.map((c) => c.clave);
    const completa = claves.every((c) => data.rubrica[c] !== undefined)
        && data.comentario.trim().length >= 10;

    function marcar(clave, nivel) {
        setData('rubrica', { ...data.rubrica, [clave]: nivel });
    }

    function onSubmit(e) {
        e.preventDefault();
        post(`/docente/producciones/${produccion.id}`);
    }

    return (
        <form onSubmit={onSubmit} className="rounded-lg border border-slate-200 bg-white p-4">
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-base font-semibold text-slate-900">
                    {produccion.alumno ?? 'Alumno'} · {produccion.code}
                </h2>
                <span className="text-xs text-slate-500">
                    {produccion.tipo === 'voz' ? '🎙️ Voz' : '✍️ Escritura'} · Unidad {produccion.unidad} · {produccion.creada}
                </span>
            </div>

            {produccion.tipo === 'voz'
                ? <audio src={produccion.audio_url} controls className="mb-4 w-full" />
                : <p className="mb-4 whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-slate-800">{produccion.texto}</p>}

            <div className="space-y-3">
                {produccion.rubrica.criterios.map((criterio) => (
                    <fieldset key={criterio.clave} className="rounded-lg border border-slate-200 p-3">
                        <legend className="px-1 text-sm font-medium text-slate-800">{criterio.titulo}</legend>
                        <div className="mt-1 grid gap-2 sm:grid-cols-3">
                            {criterio.niveles.map((nivel, i) => (
                                <label key={i} className="flex items-start gap-2 text-sm text-slate-700">
                                    <input
                                        type="radio"
                                        name={`${produccion.id}-${criterio.clave}`}
                                        checked={data.rubrica[criterio.clave] === i}
                                        onChange={() => marcar(criterio.clave, i)}
                                        className="mt-0.5"
                                    />
                                    <span>{nivel}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                ))}
            </div>

            <label htmlFor={`c-${produccion.id}`} className="mt-4 mb-1 block text-sm font-medium text-slate-700">
                Dos frases de devolución
            </label>
            <textarea
                id={`c-${produccion.id}`}
                value={data.comentario}
                onChange={(e) => setData('comentario', e.target.value)}
                rows={3}
                maxLength={1000}
                className="w-full rounded-lg border border-slate-300 p-2 text-slate-900 focus:border-marca-500 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
            />

            <div className="mt-3 flex justify-end">
                <button
                    type="submit"
                    disabled={!completa || processing}
                    className="rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50"
                >
                    {processing ? 'Guardando…' : 'Guardar corrección'}
                </button>
            </div>
        </form>
    );
}

export default function DocenteProducciones({ producciones }) {
    return (
        <AppLayout>
            <Head title="Producciones por corregir" />

            <div className="mx-auto max-w-2xl">
                <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                    Producciones por corregir
                </h1>

                {producciones.length === 0 ? (
                    <p className="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        No hay producciones pendientes de tus alumnos.
                    </p>
                ) : (
                    <ol className="mt-6 space-y-6">
                        {producciones.map((p) => (
                            <li key={p.id}><Correccion produccion={p} /></li>
                        ))}
                    </ol>
                )}
            </div>
        </AppLayout>
    );
}
