import { Head, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';

/**
 * La puerta del currículo: los marcos disponibles y el árbol de EC-MINEDEC
 * hasta grado. Desde aquí todo es navegable sin que Moodle te lance a nada.
 */
function Rama({ nodo }) {
    return (
        <li>
            <Link href={`/catalogo/${nodo.id}`} className="underline hover:text-slate-900">
                {nodo.title}
            </Link>
            {nodo.children?.length > 0 && (
                <ul className="ml-5 mt-1 list-disc space-y-1">
                    {nodo.children.map((hijo) => (
                        <Rama key={hijo.id} nodo={hijo} />
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function Catalogo({ frameworks, tree }) {
    return (
        <AppLayout title="Catálogo del currículo">
            <Head title="Catálogo" />

            <section aria-labelledby="marcos" className="mb-8">
                <h2 id="marcos" className="mb-2 text-lg font-medium">
                    Marcos curriculares
                </h2>
                <ul className="space-y-1 text-sm text-slate-700">
                    {frameworks.map((marco) => (
                        <li key={marco.id}>
                            {marco.label} <span className="text-slate-500">({marco.code})</span>
                        </li>
                    ))}
                </ul>
            </section>

            <section aria-labelledby="arbol">
                <h2 id="arbol" className="mb-2 text-lg font-medium">
                    Currículo Nacional (EC-MINEDEC)
                </h2>
                {tree.length === 0 ? (
                    <p role="status">El currículo aún no está sembrado en esta instalación.</p>
                ) : (
                    <ul className="list-disc space-y-2 pl-5">
                        {tree.map((nodo) => (
                            <Rama key={nodo.id} nodo={nodo} />
                        ))}
                    </ul>
                )}
            </section>

            <p className="mt-8">
                <Link href="/buscar" className="underline">
                    ¿Buscas una destreza concreta? Usa la búsqueda.
                </Link>
            </p>
        </AppLayout>
    );
}
