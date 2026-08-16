import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../layouts/AppLayout';

/**
 * El panel del docente: el curso de Moodle, su trayecto (o el aviso de que
 * falta asignarlo) y la tabla de alumnos con su avance contra el trayecto —
 * los más rezagados primero, y el rezago DICHO EN TEXTO, no solo con color.
 * La privacidad manda: aquí solo llegan id y nombre de cada alumno.
 */

function fecha(iso) {
    if (!iso) return 'nunca';

    return new Date(iso).toLocaleDateString('es-EC', { day: 'numeric', month: 'short', year: 'numeric' });
}

function FilaDetalle({ contextId, alumno, colSpan }) {
    const [estado, setEstado] = useState('cargando');
    const [destrezas, setDestrezas] = useState([]);

    useEffect(() => {
        let vivo = true;
        (async () => {
            try {
                const r = await fetch(`/docente/${contextId}/alumno/${alumno.id}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (!vivo) return;
                if (!r.ok) return setEstado('error');
                setDestrezas((await r.json()).destrezas);
                setEstado('listo');
            } catch {
                if (vivo) setEstado('error');
            }
        })();

        return () => {
            vivo = false;
        };
    }, [contextId, alumno.id]);

    return (
        <tr>
            <td colSpan={colSpan} className="bg-slate-50 px-4 py-3">
                {estado === 'cargando' && <p role="status">Cargando el detalle…</p>}
                {estado === 'error' && <p role="alert">No pudimos cargar el detalle de {alumno.name}.</p>}
                {estado === 'listo' && destrezas.length === 0 && (
                    <p role="status">Sin trayecto asignado no hay detalle por destreza.</p>
                )}
                {estado === 'listo' && destrezas.length > 0 && (
                    <ul className="space-y-2">
                        {destrezas.map((d) => {
                            const pct = Math.round((d.mastery ?? 0) * 100);

                            return (
                                <li key={d.native_code} className="flex items-center gap-3">
                                    <span className="w-32 shrink-0 font-mono text-xs">{d.native_code}</span>
                                    <div
                                        role="progressbar"
                                        aria-label={`Dominio de ${d.native_code} de ${alumno.name}`}
                                        aria-valuenow={pct}
                                        aria-valuemin={0}
                                        aria-valuemax={100}
                                        className="h-2 w-40 overflow-hidden rounded-full bg-slate-200"
                                    >
                                        <div className="h-full bg-indigo-600" style={{ width: `${pct}%` }} />
                                    </div>
                                    <span className="text-xs text-slate-600">
                                        {pct} %{d.is_mastered ? ' — dominada' : ''}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </td>
        </tr>
    );
}

export default function Docente({ context, track, tracks, objectives_summary: resumen, students }) {
    const [trackElegido, setTrackElegido] = useState(track?.id ?? '');
    const [abierto, setAbierto] = useState(null);
    const conTrack = track !== null;
    const columnas = conTrack ? 6 : 3;

    function asignarTrack(e) {
        e.preventDefault();
        if (trackElegido !== '') {
            router.post(`/docente/${context.id}/track`, { track_id: trackElegido });
        }
    }

    return (
        <AppLayout title={context.title ?? 'Panel del curso'}>
            <Head title={`Docente — ${context.title ?? 'curso'}`} />

            <section aria-labelledby="trayecto" className="mb-8">
                <h2 id="trayecto" className="mb-2 text-lg font-medium">Trayecto del curso</h2>

                {conTrack ? (
                    <p className="mb-2">
                        Este curso sigue el trayecto <strong>{track.label}</strong> ({track.code}).
                    </p>
                ) : (
                    <p className="mb-2" role="status">
                        Este curso aún no tiene trayecto asignado: asígnalo para ver el
                        progreso de tus alumnos contra el currículo.
                    </p>
                )}

                <form onSubmit={asignarTrack} className="flex items-end gap-2">
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium">
                            {conTrack ? 'Cambiar de trayecto' : 'Elegir trayecto'}
                        </span>
                        <select
                            value={trackElegido}
                            onChange={(e) => setTrackElegido(e.target.value)}
                            className="rounded border border-slate-300 px-3 py-2 focus:outline-2 focus:outline-indigo-600"
                        >
                            <option value="">— elige —</option>
                            {tracks.map((t) => (
                                <option key={t.id} value={t.id}>{t.label} ({t.code})</option>
                            ))}
                        </select>
                    </label>
                    <button
                        type="submit"
                        className="rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600"
                    >
                        Guardar
                    </button>
                </form>

                {resumen && (
                    <p className="mt-3 text-sm text-slate-600">
                        El trayecto cubre {resumen.total} destrezas; {resumen.con_items} con
                        ejercicios de práctica en AllyuHub.
                    </p>
                )}
            </section>

            <section aria-labelledby="alumnos">
                <h2 id="alumnos" className="mb-2 text-lg font-medium">Alumnos</h2>

                {students.length === 0 ? (
                    <p role="status">
                        Todavía ningún alumno ha entrado a AllyuHub desde este curso. En
                        cuanto abran la actividad en Moodle, aparecerán aquí.
                    </p>
                ) : (
                    <table className="w-full border-collapse text-sm">
                        <caption className="mb-2 text-left text-sm text-slate-600">
                            {students.length} alumno{students.length === 1 ? '' : 's'}, los más
                            rezagados primero.
                        </caption>
                        <thead>
                            <tr className="border-b border-slate-300 text-left">
                                <th scope="col" className="py-2 pr-3">Alumno</th>
                                {conTrack && <th scope="col" className="py-2 pr-3">Dominadas</th>}
                                {conTrack && <th scope="col" className="py-2 pr-3">En progreso</th>}
                                {conTrack && <th scope="col" className="py-2 pr-3">Sin empezar</th>}
                                <th scope="col" className="py-2 pr-3">Último acceso</th>
                                {conTrack && <th scope="col" className="py-2">Detalle</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {students.map((alumno) => (
                                <FilaAlumno
                                    key={alumno.id}
                                    alumno={alumno}
                                    conTrack={conTrack}
                                    columnas={columnas}
                                    contextId={context.id}
                                    abierto={abierto === alumno.id}
                                    onToggle={() => setAbierto(abierto === alumno.id ? null : alumno.id)}
                                />
                            ))}
                        </tbody>
                    </table>
                )}
            </section>
        </AppLayout>
    );
}

function FilaAlumno({ alumno, conTrack, columnas, contextId, abierto, onToggle }) {
    const rezagado = conTrack && alumno.dominadas === 0 && alumno.en_progreso === 0;

    return (
        <>
            <tr className="border-b border-slate-200">
                <th scope="row" className="py-2 pr-3 text-left font-medium">
                    {alumno.name}
                    {rezagado && (
                        /* El rezago se dice con PALABRAS, no con un color. */
                        <span className="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-normal text-amber-900">
                            sin avance todavía
                        </span>
                    )}
                </th>
                {conTrack && <td className="py-2 pr-3">{alumno.dominadas}</td>}
                {conTrack && <td className="py-2 pr-3">{alumno.en_progreso}</td>}
                {conTrack && <td className="py-2 pr-3">{alumno.sin_empezar}</td>}
                <td className="py-2 pr-3">{fecha(alumno.last_launched_at)}</td>
                {conTrack && (
                    <td className="py-2">
                        <button
                            type="button"
                            aria-expanded={abierto}
                            onClick={onToggle}
                            className="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100 focus:outline-2 focus:outline-indigo-600"
                        >
                            {abierto ? 'Ocultar detalle' : 'Ver detalle'}
                        </button>
                    </td>
                )}
            </tr>
            {abierto && <FilaDetalle contextId={contextId} alumno={alumno} colSpan={columnas} />}
        </>
    );
}
