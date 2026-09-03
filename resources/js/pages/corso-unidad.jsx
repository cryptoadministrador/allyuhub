import { Head, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import Anillo from '../components/Anillo';

/**
 * LA UNIDAD. Sus «Puedo…» pintados como OBJETIVOS DEL ALUMNO —no como metadato
 * del currículo—: es lo que hace que el MCER sirva para algo dentro de la
 * cabeza de un chico de 15 años. Cada objetivo lleva a leer y a practicar.
 */
export default function CorsoUnidad({ lengua, nombre, unidad, estado, dominio, puedo, siguiente }) {
    return (
        <AppLayout>
            <Head title={`Unidad ${unidad.n} · ${nombre}`} />

            <div className="mx-auto max-w-2xl">
                <p className="text-sm">
                    <Link href={`/corso/${lengua}`} className="text-marca-700 underline hover:text-marca-900 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                        ← Curso de {nombre}
                    </Link>
                </p>

                <header className="mb-6 mt-2 flex items-center gap-4">
                    <Anillo valor={dominio} etiqueta={`Unidad ${unidad.n}`} tamano={72} grosor={7} decorativo />
                    <div>
                        <p className="text-sm font-medium uppercase tracking-wide text-marca-700">
                            Unidad {unidad.n}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{unidad.titulo}</h1>
                        <p className="mt-1 text-slate-700">{unidad.resumen}</p>
                    </div>
                </header>

                <section aria-labelledby="puedo" className="mb-8">
                    <h2 id="puedo" className="mb-3 text-lg font-semibold tracking-tight text-slate-900">
                        Al terminar esta unidad, vas a poder…
                    </h2>

                    {puedo.length === 0 ? (
                        <p role="status" className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            Esta unidad todavía no tiene contenido publicado. Próximamente.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {puedo.map((p) => (
                                <li
                                    key={p.descriptor_id}
                                    className={`rounded-lg border border-l-4 p-4 ${
                                        p.dominado
                                            ? 'border-emerald-200 border-l-emerald-600 bg-emerald-50'
                                            : 'border-slate-200 border-l-marca-500 bg-white'
                                    }`}
                                >
                                    <p className="flex items-start gap-2 text-base leading-relaxed text-slate-900">
                                        <span aria-hidden="true">{p.dominado ? '✓' : '○'}</span>
                                        <span>{p.statement}</span>
                                    </p>
                                    <p className="mt-3 flex flex-wrap gap-3 text-sm">
                                        {p.has_leccion && (
                                            <Link
                                                href={p.leccion_url}
                                                className="font-medium text-marca-700 underline hover:text-marca-900 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                            >
                                                Aprende
                                            </Link>
                                        )}
                                        {p.has_items ? (
                                            <Link
                                                href={p.url_practicar}
                                                className="font-medium text-marca-700 underline hover:text-marca-900 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                            >
                                                Practica
                                            </Link>
                                        ) : (
                                            <span className="text-slate-500">Ejercicios próximamente</span>
                                        )}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
