import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../layouts/AppLayout';
import DistintivoVerificacion from '../components/DistintivoVerificacion';
import Migas from '../components/Migas';

/**
 * Un nodo del árbol: sus hijos y sus destrezas (paginadas de 50 en 50 desde
 * la API — un bloque real puede tener cientos). Cada destreza declara su
 * estado (verificada/provisional) y si tiene ejercicios, en texto.
 */
export default function CatalogoNodo({ node, breadcrumbs, children, objectives }) {
    const [filas, setFilas] = useState(objectives.data);
    const [pagina, setPagina] = useState(objectives.current_page);
    const [ultimaPagina, setUltimaPagina] = useState(objectives.last_page);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(false);
    const total = objectives.total;

    async function cargarMas() {
        setCargando(true);
        setError(false);
        try {
            const r = await fetch(`/api/v1/nodes/${node.id}/objectives?page=${pagina + 1}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!r.ok) throw new Error('respuesta no OK');
            const json = await r.json();
            setFilas((previas) => {
                // Dedupe por id: si el total cambió entre páginas (import en
                // caliente), el corte por offset puede repetir filas (auditoría).
                const vistas = new Set(previas.map((p) => p.id));

                return [
                    ...previas,
                    ...json.data
                        .filter((o) => !vistas.has(o.id))
                        .map((o) => ({
                            id: o.id,
                            native_code: o.native_code,
                            statement: o.statement?.es ?? '',
                            is_verified: Boolean(o.is_verified),
                            has_items: Boolean(o.has_items),
                        })),
                ];
            });
            setPagina(json.current_page);
            setUltimaPagina(json.last_page ?? ultimaPagina);
        } catch {
            setError(true);
        } finally {
            setCargando(false);
        }
    }

    return (
        <AppLayout title={node.title}>
            <Head title={node.title} />
            <Migas breadcrumbs={breadcrumbs} actual={node.title} />

            {children.length > 0 && (
                <section aria-labelledby="sub-nodos" className="mb-8">
                    <h2 id="sub-nodos" className="mb-2 text-lg font-medium">
                        Dentro de {node.title}
                    </h2>
                    <ul className="list-disc space-y-1 pl-5">
                        {children.map((hijo) => (
                            <li key={hijo.id}>
                                <Link href={`/catalogo/${hijo.id}`} className="underline hover:text-slate-900">
                                    {hijo.title}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section aria-labelledby="destrezas">
                <h2 id="destrezas" className="mb-2 text-lg font-medium">
                    Destrezas
                </h2>

                {/* El lector de pantalla se entera de cuántas hay y cuántas se ven. */}
                <p role="status" aria-live="polite" className="mb-3 text-sm text-slate-600">
                    {total === 0
                        ? 'Este nodo no tiene destrezas directas.'
                        : `Mostrando ${filas.length} de ${total} destrezas.`}
                </p>

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
                            <p className="mt-1 text-xs text-slate-500">
                                {destreza.has_items
                                    ? 'Con ejercicios de práctica.'
                                    : 'Aún sin ejercicios de práctica.'}
                            </p>
                        </li>
                    ))}
                </ul>

                {error && (
                    <p role="alert" className="mt-3">
                        No pudimos cargar más destrezas. Inténtalo de nuevo.
                    </p>
                )}

                {/* pagina < ultimaPagina: con un ?page desbordado el servidor
                    devuelve data=[] y sin este guard el botón pediría páginas
                    vacías para siempre (auditoría). */}
                {filas.length < total && pagina < ultimaPagina && (
                    <button
                        type="button"
                        onClick={cargarMas}
                        disabled={cargando}
                        className="mt-4 rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 disabled:opacity-50"
                    >
                        {cargando ? 'Cargando…' : 'Cargar más destrezas'}
                    </button>
                )}
            </section>
        </AppLayout>
    );
}
