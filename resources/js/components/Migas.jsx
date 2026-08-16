import { Link } from '@inertiajs/react';

/** Migas de pan del catálogo: cada miga navega a su nodo. */
export default function Migas({ breadcrumbs, actual }) {
    if (!breadcrumbs?.length && !actual) return null;

    return (
        <nav aria-label="Estás en" className="mb-4 text-sm text-slate-600">
            <ol className="flex flex-wrap items-center gap-1">
                <li>
                    <Link href="/catalogo" className="underline hover:text-slate-900">
                        Catálogo
                    </Link>
                    <span aria-hidden="true"> › </span>
                </li>
                {breadcrumbs.map((miga) => (
                    <li key={miga.id}>
                        <Link href={`/catalogo/${miga.id}`} className="underline hover:text-slate-900">
                            {miga.title}
                        </Link>
                        <span aria-hidden="true"> › </span>
                    </li>
                ))}
                {actual && <li aria-current="page">{actual}</li>}
            </ol>
        </nav>
    );
}
