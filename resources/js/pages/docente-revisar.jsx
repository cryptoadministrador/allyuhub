import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';

/**
 * LA COLA DE REVISIÓN DEL DOCENTE: lo pendiente de firma, agrupado por unidad y
 * descriptor, con cuántas piezas faltan.
 *
 * Cada pieza se abre TAL COMO LA VE EL ALUMNO. Firmar la unidad entera es un
 * atajo que exige haber abierto todas: no es un `--todo`, y el servidor lo
 * comprueba (lo visto se apunta en la sesión al abrir cada pieza).
 */

const KINDS = {
    reading: 'Lección',
    choice: 'Opción múltiple',
    escucha: 'Escucha',
    hueco: 'Hueco',
    orden: 'Ordenar',
    pares: 'Parejas',
    dictado: 'Dictado',
    numeric: 'Numérico',
};

export default function DocenteRevisar({ lengua, lenguas, estado, docente, unidades, total }) {
    const firmadas = estado === 'firmadas';

    function enlace(params) {
        const p = new URLSearchParams();
        const l = 'lengua' in params ? params.lengua : lengua;
        const e = 'estado' in params ? params.estado : estado;
        if (l) p.set('lengua', l);
        if (e === 'firmadas') p.set('estado', 'firmadas');
        const q = p.toString();

        return `/docente/revisar${q ? `?${q}` : ''}`;
    }

    function firmarUnidad(n) {
        router.post('/docente/revisar/unidad', { unidad: n, lengua }, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title="Revisar contenido" />

            <div className="mx-auto max-w-3xl">
                <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                    Revisar contenido
                </h1>
                <p className="mt-1 text-slate-700">
                    {firmadas
                        ? `${total} pieza(s) firmadas. Puedes retirar cualquiera con una nota.`
                        : `${total} pieza(s) esperan tu firma. Nada de esto lo ve todavía un alumno.`}
                </p>
                <p className="mt-1 text-sm text-slate-600">
                    Firmas como <strong>{docente.name}</strong>: tu nombre queda en lo que publiques.
                </p>

                {/* Filtro de lengua. El texto va SIEMPRE, no solo el color. */}
                <nav aria-label="Filtrar por lengua" className="mt-4 flex flex-wrap gap-2">
                    <Link
                        href={enlace({ lengua: null })}
                        aria-current={lengua === null ? 'page' : undefined}
                        className={`rounded-lg border px-3 py-1 text-sm font-medium focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 ${
                            lengua === null ? 'border-marca-600 bg-marca-50 text-marca-900' : 'border-slate-300 text-slate-700'
                        }`}
                    >
                        Todas
                    </Link>
                    {lenguas.map((l) => (
                        <Link
                            key={l}
                            href={enlace({ lengua: l })}
                            aria-current={lengua === l ? 'page' : undefined}
                            className={`rounded-lg border px-3 py-1 text-sm font-medium uppercase focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 ${
                                lengua === l ? 'border-marca-600 bg-marca-50 text-marca-900' : 'border-slate-300 text-slate-700'
                            }`}
                        >
                            {l}
                        </Link>
                    ))}
                </nav>

                <nav aria-label="Estado" className="mt-2 flex gap-2">
                    <Link
                        href={enlace({ estado: 'pendientes' })}
                        aria-current={!firmadas ? 'page' : undefined}
                        className={`rounded-lg border px-3 py-1 text-sm font-medium focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 ${
                            !firmadas ? 'border-marca-600 bg-marca-50 text-marca-900' : 'border-slate-300 text-slate-700'
                        }`}
                    >
                        Pendientes
                    </Link>
                    <Link
                        href={enlace({ estado: 'firmadas' })}
                        aria-current={firmadas ? 'page' : undefined}
                        className={`rounded-lg border px-3 py-1 text-sm font-medium focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 ${
                            firmadas ? 'border-marca-600 bg-marca-50 text-marca-900' : 'border-slate-300 text-slate-700'
                        }`}
                    >
                        Firmadas
                    </Link>
                </nav>

                {unidades.length === 0 ? (
                    <p role="status" className="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        {firmadas ? 'No hay nada firmado todavía.' : 'No queda nada por revisar. 🎉'}
                    </p>
                ) : (
                    <ol className="mt-6 space-y-6">
                        {unidades.map((u) => (
                            <li key={u.n} className="rounded-lg border border-slate-200 bg-white p-4">
                                <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                                    <h2 className="text-lg font-semibold tracking-tight text-slate-900">
                                        {u.n === 0 ? 'Sin unidad' : `Unidad ${u.n} · ${u.titulo}`}
                                    </h2>
                                    <span className="text-sm text-slate-600">
                                        {u.total} {firmadas ? 'firmada(s)' : 'pendiente(s)'}
                                    </span>
                                </div>

                                {u.descriptores.map((d) => (
                                    <section key={d.code} className="mb-3">
                                        <h3 className="text-sm font-medium text-marca-800">
                                            {d.code} · {d.statement}
                                        </h3>
                                        <ul className="mt-1 space-y-1">
                                            {d.piezas.map((p) => (
                                                <li key={`${p.tipo}:${p.id}`}>
                                                    <Link
                                                        href={p.url}
                                                        className="flex flex-wrap items-baseline gap-2 rounded-lg border border-slate-200 p-2 text-sm hover:border-marca-400 hover:bg-marca-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                                    >
                                                        <span className="rounded-full border border-slate-300 px-2 py-0.5 text-xs font-medium text-slate-700">
                                                            {KINDS[p.kind] ?? p.kind}
                                                        </span>
                                                        {p.lengua && (
                                                            <span className="text-xs font-medium uppercase text-slate-500">
                                                                {p.lengua}
                                                            </span>
                                                        )}
                                                        <span className="min-w-0 flex-1 text-slate-800">{p.titulo}</span>
                                                        <span className="text-xs text-slate-500">
                                                            {p.vista ? '👁️ Abierta' : 'Sin abrir'}
                                                        </span>
                                                    </Link>
                                                    {p.nota && (
                                                        <p className="mt-1 rounded-lg border border-amber-200 bg-amber-50 p-2 text-sm text-amber-900">
                                                            ↩︎ Devuelta{p.nota.docente ? ` por ${p.nota.docente}` : ''} el {p.nota.cuando}: {p.nota.nota}
                                                        </p>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                ))}

                                {!firmadas && (
                                    <div className="mt-3 border-t border-slate-200 pt-3">
                                        <button
                                            type="button"
                                            onClick={() => firmarUnidad(u.n)}
                                            disabled={!u.todo_visto}
                                            className="rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50"
                                        >
                                            Firmar la unidad entera ({u.total})
                                        </button>
                                        {!u.todo_visto && (
                                            <p className="mt-1 text-sm text-slate-600">
                                                Ábrelas todas para poder firmar la unidad de una vez.
                                            </p>
                                        )}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </AppLayout>
    );
}
