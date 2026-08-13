import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import AppLayout from '../layouts/AppLayout';

/**
 * Progreso por trayecto: por cada fase del track, cuántas destrezas están
 * dominadas / en progreso / no iniciadas. Siempre con números y texto
 * (jamás solo color). El mapeo curso-de-Moodle → track no existe aún
 * (pendiente documentado): el alumno elige el trayecto en un selector.
 */
export default function Progreso({ tracks }) {
    const [trackCode, setTrackCode] = useState(tracks[0]?.code ?? '');
    // estado: cargando | listo | sesion | error | vacio
    const [estado, setEstado] = useState(tracks.length > 0 ? 'cargando' : 'vacio');
    const [fases, setFases] = useState([]);

    const cargar = useCallback(async (codigo) => {
        setEstado('cargando');
        try {
            const r = await fetch(
                `/api/v1/practice/progress?track=${encodeURIComponent(codigo)}`,
                { credentials: 'same-origin', headers: { Accept: 'application/json' } },
            );

            if (r.status === 401) return setEstado('sesion');
            if (!r.ok) return setEstado('error');

            setFases((await r.json()).phases);
            setEstado('listo');
        } catch {
            setEstado('error');
        }
    }, []);

    useEffect(() => {
        if (trackCode !== '') cargar(trackCode);
    }, [trackCode, cargar]);

    return (
        <AppLayout title="Mi progreso">
            <Head title="Mi progreso" />

            {tracks.length > 1 && (
                <label className="mb-6 block">
                    <span className="mb-1 block text-sm font-medium">Trayecto</span>
                    <select
                        value={trackCode}
                        onChange={(e) => setTrackCode(e.target.value)}
                        className="rounded border border-slate-300 px-3 py-2 focus:outline-2 focus:outline-indigo-600"
                    >
                        {tracks.map((track) => (
                            <option key={track.id} value={track.code}>
                                {track.label}
                            </option>
                        ))}
                    </select>
                </label>
            )}

            {estado === 'vacio' && <p role="status">Todavía no hay trayectos configurados.</p>}
            {estado === 'cargando' && <p role="status">Cargando tu progreso…</p>}
            {estado === 'sesion' && (
                <p role="alert">
                    Tu sesión caducó. <a className="underline" href="/entrar">Vuelve a entrar desde Moodle</a>.
                </p>
            )}
            {estado === 'error' && (
                <div role="alert">
                    <p>No pudimos cargar tu progreso.</p>
                    <button
                        type="button"
                        onClick={() => cargar(trackCode)}
                        className="mt-2 rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600"
                    >
                        Reintentar
                    </button>
                </div>
            )}

            {estado === 'listo' && (
                <ol className="space-y-4">
                    {fases.map((fase) => {
                        const total = fase.objectives_total;
                        const pct = total === 0 ? 0 : Math.round((fase.mastered / total) * 100);

                        return (
                            <li key={fase.phase_id} className="rounded border border-slate-200 p-4">
                                <h2 className="font-medium">
                                    {fase.label?.es ?? `Fase ${fase.seq}`}
                                    {fase.is_propedeutic && (
                                        <span className="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-900">
                                            propedéutica
                                        </span>
                                    )}
                                </h2>

                                <div
                                    role="progressbar"
                                    aria-valuenow={pct}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-label={`Destrezas dominadas de ${fase.label?.es ?? `la fase ${fase.seq}`}`}
                                    className="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"
                                >
                                    <div
                                        className="h-full rounded-full bg-green-600"
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>

                                {/* Números y palabras, no solo la barra de color. */}
                                <p className="mt-2 text-sm text-slate-700">
                                    {fase.mastered} dominadas · {fase.in_progress} en progreso ·{' '}
                                    {fase.not_started} sin empezar (de {total})
                                </p>
                            </li>
                        );
                    })}
                </ol>
            )}
        </AppLayout>
    );
}
