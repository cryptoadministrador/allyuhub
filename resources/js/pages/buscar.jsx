import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '../layouts/AppLayout';
import DistintivoVerificacion from '../components/DistintivoVerificacion';

/**
 * Búsqueda de destrezas: debounce de 300 ms, cancelación de la petición
 * anterior (AbortController) y la URL siempre lleva el q (compartible).
 * Dos estados vacíos distintos: «escribe al menos 3 letras» y «sin resultados».
 */

/** Recorta a 160 puntos de código (sin partir pares sustitutos) con elipsis. */
function recortar(texto) {
    const puntos = Array.from(texto);

    return puntos.length > 160 ? puntos.slice(0, 159).join('') + '…' : texto;
}
export default function Buscar({ q: qInicial, results: resultadosIniciales }) {
    const [q, setQ] = useState(qInicial ?? '');
    const [resultados, setResultados] = useState(resultadosIniciales);
    const [buscando, setBuscando] = useState(false);
    const [fallo, setFallo] = useState(false);
    const abortRef = useRef(null);
    const debounceRef = useRef(null);
    const primeraCarga = useRef(true);

    useEffect(() => {
        // El primer pintado ya viene del servidor: no repetir la búsqueda.
        if (primeraCarga.current) {
            primeraCarga.current = false;

            return undefined;
        }

        // La URL acompaña al tecleo: un resultado se puede compartir y recargar.
        // OJO (auditoría): se PRESERVA window.history.state — Inertia guarda ahí
        // su página, y pisarlo con null rompe el botón Atrás dentro del iframe.
        const url = q.trim().length > 0 ? `/buscar?q=${encodeURIComponent(q.trim())}` : '/buscar';
        window.history.replaceState(window.history.state, '', url);

        // Cualquier cambio de q invalida lo que esté en vuelo: sin esto, bajar
        // de 3 letras dejaba resolverse un fetch viejo y pintaba resultados
        // obsoletos bajo el mensaje de «escribe al menos 3 letras» (auditoría).
        abortRef.current?.abort();

        if (q.trim().length < 3) {
            setResultados(null);
            setBuscando(false);

            return undefined;
        }

        clearTimeout(debounceRef.current);
        setBuscando(true);
        setFallo(false);

        debounceRef.current = setTimeout(async () => {
            abortRef.current = new AbortController();
            try {
                const r = await fetch(
                    `/api/v1/objectives/search?q=${encodeURIComponent(q.trim())}`,
                    {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                        signal: abortRef.current.signal,
                    },
                );
                if (r.status === 422) {
                    // Por debajo del mínimo: es un mensaje, no un error.
                    setResultados(null);

                    return;
                }
                if (!r.ok) throw new Error('respuesta no OK');
                const filas = await r.json();
                setResultados(filas.map((o) => ({
                    id: o.id,
                    native_code: o.native_code,
                    statement: recortar(o.statement?.es ?? ''),
                    is_verified: Boolean(o.is_verified),
                    has_items: Boolean(o.has_items),
                    node_title: o.node?.title?.es ?? '',
                })));
            } catch (e) {
                if (e.name !== 'AbortError') setFallo(true);
            } finally {
                setBuscando(false);
            }
        }, 300);

        return () => clearTimeout(debounceRef.current);
    }, [q]);

    const corto = q.trim().length > 0 && q.trim().length < 3;

    return (
        <AppLayout title="Buscar destrezas">
            <Head title="Buscar" />

            <form role="search" onSubmit={(e) => e.preventDefault()} className="mb-6">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium">
                        Busca por código o por texto del enunciado
                    </span>
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="rozamiento, CN.F.5.1.12…"
                        maxLength={120}
                        className="w-full max-w-md rounded border border-slate-300 px-3 py-2 focus:outline-2 focus:outline-indigo-600"
                    />
                </label>
            </form>

            {/* El lector de pantalla se entera de que la lista cambió. */}
            <p role="status" aria-live="polite" className="mb-4 text-sm text-slate-600">
                {buscando && 'Buscando…'}
                {!buscando && corto && 'Escribe al menos 3 letras para buscar.'}
                {!buscando && !corto && resultados !== null
                    && `${resultados.length} resultado${resultados.length === 1 ? '' : 's'} para «${q.trim()}».`}
            </p>

            {fallo && (
                <p role="alert" className="mb-4">
                    La búsqueda falló (¿conexión?). Sigue escribiendo o inténtalo de nuevo.
                </p>
            )}

            {resultados !== null && resultados.length === 0 && !buscando && (
                <p className="text-slate-600">
                    Sin resultados. Prueba con otra palabra del enunciado o con el código de la
                    destreza.
                </p>
            )}

            {resultados !== null && resultados.length > 0 && (
                <ul className="space-y-3">
                    {resultados.map((destreza) => (
                        <li key={destreza.id} className="rounded border border-slate-200 p-3">
                            <p>
                                <Link href={`/destreza/${destreza.id}`} className="font-medium underline">
                                    {destreza.native_code}
                                </Link>{' '}
                                <DistintivoVerificacion verificada={destreza.is_verified} />
                            </p>
                            <p className="mt-1 text-sm text-slate-700">{destreza.statement}</p>
                            {destreza.node_title !== '' && (
                                <p className="mt-1 text-xs text-slate-500">En: {destreza.node_title}</p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
