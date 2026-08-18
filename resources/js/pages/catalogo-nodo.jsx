import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../layouts/AppLayout';
import Chip from '../components/Chip';
import DistintivoVerificacion from '../components/DistintivoVerificacion';
import Migas from '../components/Migas';
import TarjetaNodo from '../components/TarjetaNodo';
import { estiloDeAsignatura } from '../lib/color';

/**
 * Un nodo del árbol: sus hijos y sus destrezas (paginadas de 50 en 50 desde
 * la API — un bloque real puede tener cientos). Cada destreza declara su
 * estado (verificada/provisional) y si tiene ejercicios, en texto.
 *
 * Todo lo que cuelga de una asignatura hereda su acento (`asignatura.color`),
 * así que un bloque de Física se ve de Física aunque el bloque no tenga color
 * propio. Sin asignatura en la cadena —un grado, un subnivel— el acento es el
 * gris neutro de `estiloDeAsignatura`.
 */

/** Cabecera con el icono de la asignatura, cuando la hay. */
function CabeceraNodo({ node, asignatura }) {
    const propia = node.node_type === 'asignatura';
    const icono = node.icon ?? (propia ? null : asignatura?.icon);

    return (
        <div className="mb-6 flex items-center gap-3">
            {icono && (
                <span
                    aria-hidden="true"
                    className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-2xl"
                    style={{ background: 'var(--acento-suave)' }}
                >
                    {icono}
                </span>
            )}
            <div>
                <h1 className="text-xl font-semibold tracking-tight text-slate-900">
                    {node.title}
                </h1>
                {!propia && asignatura && (
                    <p className="text-sm text-slate-600">{asignatura.title}</p>
                )}
            </div>
        </div>
    );
}
export default function CatalogoNodo({ node, asignatura, breadcrumbs, children, objectives }) {
    const [filas, setFilas] = useState(objectives.data);
    const [pagina, setPagina] = useState(objectives.current_page);
    const [ultimaPagina, setUltimaPagina] = useState(objectives.last_page);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(false);
    const total = objectives.total;
    // El nodo pinta con SU color si lo tiene (una asignatura) y si no, con el
    // de la asignatura que lo contiene. Sin ninguno de los dos, gris.
    const estilo = estiloDeAsignatura(node.color ?? asignatura?.color);

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
        <AppLayout>
            <Head title={node.title} />
            <Migas breadcrumbs={breadcrumbs} actual={node.title} />

            <div style={estilo}>
                <CabeceraNodo node={node} asignatura={asignatura} />

                {children.length > 0 && (
                    <section aria-labelledby="sub-nodos" className="mb-8">
                        <h2
                            id="sub-nodos"
                            className="mb-3 text-lg font-semibold tracking-tight text-slate-900"
                        >
                            Dentro de {node.title}
                        </h2>
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {children.map((hijo) => (
                                <TarjetaNodo key={hijo.id} nodo={hijo} />
                            ))}
                        </ul>
                    </section>
                )}

                <section aria-labelledby="destrezas">
                    <h2
                        id="destrezas"
                        className="mb-2 text-lg font-semibold tracking-tight text-slate-900"
                    >
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
                            <li
                                key={destreza.id}
                                style={estilo}
                                className="relative rounded-lg border border-l-4 border-slate-200 bg-white p-4 transition-shadow focus-within:shadow-md hover:shadow-md"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    {/* El código curricular es un IDENTIFICADOR: en
                                    monoespaciado se compara de un vistazo
                                    (CN.F.5.1.2 vs CN.F.5.1.12) y no se lee como
                                    prosa. Lleva el color de la asignatura ya
                                    oscurecido a 4.5:1 sobre blanco. */}
                                    <Link
                                        href={`/destreza/${destreza.id}`}
                                        style={{ color: 'var(--acento-tinta)' }}
                                        className="font-mono text-sm font-semibold after:absolute after:inset-0 after:rounded-lg hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                    >
                                        {destreza.native_code}
                                    </Link>
                                    <DistintivoVerificacion verificada={destreza.is_verified} />
                                    {destreza.has_items ? (
                                        <Chip tono="ambar" icono="✎">
                                            Con ejercicios
                                        </Chip>
                                    ) : (
                                        <Chip>Sin ejercicios todavía</Chip>
                                    )}
                                </div>
                                <p className="mt-2 text-sm leading-relaxed text-slate-700">
                                    {destreza.statement}
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
                            className="mt-4 rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50"
                        >
                            {cargando ? 'Cargando…' : 'Cargar más destrezas'}
                        </button>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
