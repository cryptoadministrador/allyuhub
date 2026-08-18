import { Head, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import TarjetaNodo from '../components/TarjetaNodo';

/**
 * La puerta del currículo. El árbol es de tres alturas —nivel › subnivel ›
 * grado— y solo la última se pinta como TARJETA: un grado es el sitio al que
 * de verdad quiere ir un alumno («1.º BGU»), y las dos alturas de arriba son
 * el andamio que lo agrupa. Antes eran tres listas de viñetas iguales y no se
 * distinguía la estructura del destino.
 */

export default function Catalogo({ frameworks, tree }) {
    return (
        <AppLayout title="Catálogo del currículo">
            <Head title="Catálogo" />

            <p className="mb-6 max-w-2xl text-slate-700">
                Todo el currículo nacional, grado por grado. Entra en el tuyo, elige una destreza y
                practica: no hace falta que tu docente te la asigne.
            </p>

            {tree.length === 0 ? (
                <p role="status" className="rounded-lg border border-slate-200 bg-white p-4">
                    El currículo aún no está sembrado en esta instalación.
                </p>
            ) : (
                tree.map((nivel) => (
                    <section key={nivel.id} aria-labelledby={`nivel-${nivel.id}`} className="mb-8">
                        <h2
                            id={`nivel-${nivel.id}`}
                            className="mb-3 border-b border-slate-200 pb-1 text-lg font-semibold tracking-tight text-slate-900"
                        >
                            {nivel.title}
                        </h2>

                        {(nivel.children ?? []).map((subnivel) => (
                            <div key={subnivel.id} className="mb-5">
                                <h3 className="mb-2 text-sm font-medium uppercase tracking-wide text-slate-500">
                                    {subnivel.title}
                                </h3>
                                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {(subnivel.children ?? []).map((grado) => (
                                        <TarjetaNodo key={grado.id} nodo={grado} como="h4" />
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </section>
                ))
            )}

            <section
                aria-labelledby="marcos"
                className="mt-8 rounded-lg border border-slate-200 bg-white p-4"
            >
                <h2 id="marcos" className="text-base font-semibold text-slate-900">
                    Marcos curriculares cargados
                </h2>
                <ul className="mt-2 space-y-1 text-sm text-slate-700">
                    {frameworks.map((marco) => (
                        <li key={marco.id}>
                            {marco.label} <span className="text-slate-500">({marco.code})</span>
                        </li>
                    ))}
                </ul>
            </section>

            <p className="mt-6">
                <Link href="/buscar" className="underline hover:text-marca-700">
                    ¿Buscas una destreza concreta? Usa la búsqueda.
                </Link>
            </p>
        </AppLayout>
    );
}
