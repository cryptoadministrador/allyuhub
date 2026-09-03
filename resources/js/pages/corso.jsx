import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import Anillo from '../components/Anillo';

/**
 * LA PORTADA DEL CURSO. Nueve unidades y UNA sola cosa que hacer ahora — un
 * alumno que entra y ve nueve unidades iguales no elige: se va.
 *
 * Todo el estado (qué unidad está abierta, el dominio, la racha, el siguiente
 * paso) viene calculado del servidor. Aquí solo se pinta.
 */

const ESTADOS = {
    completada: { texto: 'Terminada', clases: 'border-emerald-200 bg-emerald-50 text-emerald-900' },
    'en-curso': { texto: 'En curso', clases: 'border-marca-200 bg-marca-50 text-marca-900' },
    disponible: { texto: 'Disponible', clases: 'border-slate-200 bg-white text-slate-900' },
    proximamente: { texto: 'Próximamente', clases: 'border-slate-200 bg-slate-50 text-slate-500' },
};

export default function Corso({ lengua, nombre, unidades, siguiente, racha, repasos, se_guarda: seGuarda }) {
    const { props: compartidas } = usePage();
    const invitado = !compartidas.auth?.user;

    return (
        <AppLayout>
            <Head title={`Curso de ${nombre}`} />

            <div className="mx-auto max-w-3xl">
                <header className="mb-6">
                    <p className="text-sm font-medium uppercase tracking-wide text-marca-700">Curso · A1</p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                        {nombre}
                    </h1>
                    {racha.viva && racha.dias > 0 && (
                        <p className="mt-2 text-sm text-slate-700">
                            🔥 Llevas <strong>{racha.dias}</strong>{' '}
                            {racha.dias === 1 ? 'día' : 'días'} seguidos practicando.
                        </p>
                    )}
                </header>

                {/* LA ÚNICA COSA QUE HACER AHORA, arriba y sola. */}
                {siguiente && (
                    <Link
                        href={siguiente.url}
                        className="mb-6 block rounded-lg border-l-4 border border-marca-600 bg-marca-50 p-4 transition-shadow hover:shadow-md focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                        style={{ borderLeftColor: 'var(--acento, #4338ca)' }}
                    >
                        <p className="text-sm font-medium text-marca-700">Sigue por aquí</p>
                        <p className="mt-1 text-lg font-semibold text-slate-900">
                            Unidad {siguiente.unidad} · {siguiente.titulo}
                        </p>
                    </Link>
                )}

                {repasos && repasos.pendientes > 0 && repasos.siguiente && (
                    <Link
                        href={repasos.siguiente.url}
                        className="mb-6 block rounded-lg border border-amber-300 bg-amber-50 p-4 transition-shadow hover:shadow-md focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                    >
                        <p className="text-sm font-medium text-amber-800">Repaso</p>
                        <p className="mt-1 text-base font-semibold text-slate-900">
                            Te tocan {repasos.pendientes}{' '}
                            {repasos.pendientes === 1 ? 'repaso' : 'repasos'} para no olvidar lo aprendido.
                        </p>
                    </Link>
                )}

                {invitado && (
                    <p className="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Estás viendo el curso como visitante: puedes practicarlo entero, pero{' '}
                        <strong>tu avance no se guarda</strong>.{' '}
                        <a href="/entrar" className="font-medium underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                            Entra desde tu aula
                        </a>{' '}
                        para conservarlo.
                    </p>
                )}

                <ol className="space-y-3">
                    {unidades.map((u) => {
                        const estilo = ESTADOS[u.estado] ?? ESTADOS.disponible;
                        const disponible = u.estado !== 'proximamente';
                        const Contenido = (
                            <div className={`flex items-center gap-4 rounded-lg border p-4 ${estilo.clases}`}>
                                <Anillo
                                    valor={u.dominio}
                                    etiqueta={`Unidad ${u.n}`}
                                    tamano={56}
                                    grosor={6}
                                    decorativo
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-2">
                                        <span className="text-base font-semibold">
                                            Unidad {u.n} · {u.titulo}
                                        </span>
                                        <span className="rounded-full border px-2 py-0.5 text-xs font-medium">
                                            {estilo.texto}
                                        </span>
                                    </p>
                                    <p className="mt-1 text-sm leading-relaxed opacity-90">{u.resumen}</p>
                                </div>
                            </div>
                        );

                        return (
                            <li key={u.n}>
                                {disponible ? (
                                    <Link
                                        href={u.url}
                                        className="block rounded-lg transition-shadow hover:shadow-md focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                    >
                                        {Contenido}
                                    </Link>
                                ) : (
                                    <div aria-disabled="true">{Contenido}</div>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </div>
        </AppLayout>
    );
}
