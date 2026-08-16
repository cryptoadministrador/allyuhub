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
    // Los resultados viajan CON la consulta que los produjo. Sin esto, al
    // abortar una petición el `finally` apagaba «Buscando…» y la región viva
    // leía el recuento anterior junto a la q nueva: «3 resultados para
    // «rozami»» cuando los 3 eran de «roza» (auditoría).
    const [resultados, setResultados] = useState(
        resultadosIniciales === null || resultadosIniciales === undefined
            ? null
            : { q: (qInicial ?? '').trim(), filas: resultadosIniciales },
    );
    const [buscando, setBuscando] = useState(false);
    const [fallo, setFallo] = useState(false);
    const [rechazada, setRechazada] = useState(false);
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
        const estado = window.history.state;
        window.history.replaceState(
            // Y dentro del estado, la url de la PÁGINA de Inertia: si solo se
            // cambia la barra de direcciones, al volver con Atrás/Adelante
            // Inertia restaura page.url = '/buscar' y el q se pierde.
            estado?.page && typeof estado.page === 'object'
                ? { ...estado, page: { ...estado.page, url } }
                : estado,
            '',
            url,
        );

        // Cualquier cambio de q invalida lo que esté en vuelo: sin esto, bajar
        // de 3 letras dejaba resolverse un fetch viejo y pintaba resultados
        // obsoletos bajo el mensaje de «escribe al menos 3 letras» (auditoría).
        abortRef.current?.abort();

        setRechazada(false);

        if (q.trim().length < 3) {
            setResultados(null);
            setBuscando(false);

            return undefined;
        }

        clearTimeout(debounceRef.current);
        setBuscando(true);
        setFallo(false);

        const consulta = q.trim();

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
                    // El servidor rechaza la consulta (menos de 3 caracteres o
                    // más de 120). El maxLength del input evita lo segundo al
                    // teclear, pero no al pegar ni al llegar por URL, y
                    // enmudecer deja al alumno mirando su texto sin explicación.
                    setResultados(null);
                    setRechazada(true);

                    return;
                }
                setRechazada(false);
                if (!r.ok) throw new Error('respuesta no OK');
                const filas = await r.json();
                setResultados({ q: consulta, filas: filas.map((o) => ({
                    id: o.id,
                    native_code: o.native_code,
                    statement: recortar(o.statement?.es ?? ''),
                    is_verified: Boolean(o.is_verified),
                    has_items: Boolean(o.has_items),
                    node_title: o.node?.title?.es ?? '',
                })) });
            } catch (e) {
                if (e.name === 'AbortError') return;   // otra consulta tomó el relevo
                setFallo(true);
            } finally {
                // Solo apaga «Buscando…» si sigue siendo ESTA la consulta viva:
                // al abortar, el finally del fetch muerto llegaba después del
                // setBuscando(true) del efecto nuevo y descubría el recuento viejo.
                if (abortRef.current?.signal.aborted !== true) setBuscando(false);
            }
        }, 300);

        return () => clearTimeout(debounceRef.current);
    }, [q]);

    const corto = q.trim().length > 0 && q.trim().length < 3;
    const largo = q.trim().length > 120;
    const filas = resultados === null ? null : resultados.filas;

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
                {!buscando && largo && 'La búsqueda admite hasta 120 caracteres: acorta el texto.'}
                {!buscando && rechazada && !corto && !largo
                    && 'La búsqueda no admite ese texto: prueba con una palabra del enunciado.'}
                {/* El recuento SOLO se anuncia si es el de la consulta actual. */}
                {!buscando && !corto && !largo && filas !== null && resultados.q === q.trim()
                    && `${filas.length} resultado${filas.length === 1 ? '' : 's'} para «${q.trim()}».`}
            </p>

            {fallo && (
                <p role="alert" className="mb-4">
                    La búsqueda falló (¿conexión?). Sigue escribiendo o inténtalo de nuevo.
                </p>
            )}

            {filas !== null && filas.length === 0 && !buscando && (
                <p className="text-slate-600">
                    Sin resultados. Prueba con otra palabra del enunciado o con el código de la
                    destreza.
                </p>
            )}

            {filas !== null && filas.length > 0 && (
                <ul className="space-y-3">
                    {filas.map((destreza) => (
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
