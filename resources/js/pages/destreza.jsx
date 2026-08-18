import { Head, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import DistintivoVerificacion from '../components/DistintivoVerificacion';
import Migas from '../components/Migas';
import { estiloDeAsignatura } from '../lib/color';

const RELACIONES = {
    exact: 'equivalente a',
    narrower: 'más acotada que',
    broader: 'más amplia que',
    related: 'relacionada con',
};

/**
 * La ficha de una destreza: migas, estado de verificación (en texto),
 * práctica (o el porqué de que no la haya), recursos publicados,
 * equivalencias REVISADAS y prerrequisitos.
 */
export default function Destreza({
    objective,
    asignatura,
    breadcrumbs,
    resources,
    alignments,
    prerequisites,
}) {
    const estilo = estiloDeAsignatura(asignatura?.color);

    return (
        <AppLayout>
            <Head title={objective.native_code} />
            <Migas breadcrumbs={breadcrumbs} actual={objective.native_code} />

            <div style={estilo}>
            {/* La cabecera hereda el acento de la asignatura, con el código en
                monoespaciado: es un identificador, no una frase. */}
            <div
                className="mb-6 rounded-lg border border-l-4 border-slate-200 bg-white p-4"
                style={{ borderLeftColor: 'var(--acento)' }}
            >
                <div className="flex items-center gap-3">
                    {asignatura?.icon && (
                        <span
                            aria-hidden="true"
                            className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-2xl"
                            style={{ background: 'var(--acento-suave)' }}
                        >
                            {asignatura.icon}
                        </span>
                    )}
                    <div>
                        <h1
                            className="font-mono text-xl font-semibold"
                            style={{ color: 'var(--acento-tinta)' }}
                        >
                            {objective.native_code}
                        </h1>
                        {asignatura && (
                            <p className="text-sm text-slate-600">{asignatura.title}</p>
                        )}
                    </div>
                </div>

                <p className="mt-3">
                    <DistintivoVerificacion verificada={objective.is_verified} conExplicacion />
                </p>
                {!objective.is_verified && (
                    <p className="mt-2 text-sm text-slate-600">
                        El enunciado de abajo es un marcador provisional, no la redacción del
                        currículo oficial.
                    </p>
                )}

                <p className="mt-3 text-lg leading-relaxed text-slate-900">{objective.statement}</p>
            </div>

            <section aria-labelledby="practica" className="mb-8">
                <h2 id="practica" className="mb-2 text-lg font-medium">
                    Práctica
                </h2>
                {objective.has_items ? (
                    <Link
                        href={`/practicar/${objective.id}`}
                        className="inline-block rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                    >
                        Practicar esta destreza
                    </Link>
                ) : (
                    <>
                        {/*
                          * Un botón que lleva a un 404 es peor que un botón ausente.
                          * Y `disabled` a secas es peor todavía: un botón nativo
                          * deshabilitado NO es enfocable, así que quien navega con
                          * teclado o lector de pantalla nunca aterriza en él y nunca
                          * oye el aria-describedby que explica por qué. Como esta es
                          * LA interacción de la ficha en 1001 de las 1010 destrezas,
                          * se usa aria-disabled: sigue en el orden de tabulación y
                          * anuncia «no disponible» junto a su explicación (auditoría).
                          */}
                        <button
                            type="button"
                            aria-disabled="true"
                            onClick={(e) => e.preventDefault()}
                            aria-describedby="sin-ejercicios"
                            className="cursor-not-allowed rounded bg-slate-200 px-4 py-2 font-medium text-slate-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                        >
                            Practicar esta destreza
                        </button>
                        <p id="sin-ejercicios" className="mt-2 text-sm text-slate-600">
                            Esta destreza todavía no tiene ejercicios de práctica: el banco de
                            ítems crece a medida que se verifica el currículo oficial.
                        </p>
                    </>
                )}
            </section>

            <section aria-labelledby="recursos" className="mb-8">
                <h2 id="recursos" className="mb-2 text-lg font-medium">
                    Laboratorios y recursos
                </h2>
                {resources.length === 0 ? (
                    <p role="status" className="text-sm text-slate-600">
                        Ningún recurso publicado está alineado con esta destreza todavía.
                    </p>
                ) : (
                    <ul className="list-disc space-y-1 pl-5">
                        {resources.map((recurso) => (
                            <li key={recurso.id}>
                                <Link href={`/recurso/${recurso.id}`} className="underline">
                                    {recurso.title}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby="equivalencias" className="mb-8">
                <h2 id="equivalencias" className="mb-2 text-lg font-medium">
                    Equivalencias con Cambridge e IB
                </h2>
                {alignments.length === 0 ? (
                    // Estado vacío HONESTO: hoy hay 0 alineaciones revisadas, y es correcto.
                    <p role="status" className="text-sm text-slate-600">
                        Las equivalencias con Cambridge e IB están propuestas pero aún no
                        revisadas por un docente. No se muestran hasta que alguien las firme.
                    </p>
                ) : (
                    <ul className="list-disc space-y-1 pl-5">
                        {alignments.map((eq) => (
                            <li key={`${eq.framework}-${eq.native_code}`}>
                                <Link href={`/destreza/${eq.objective_id}`} className="underline">
                                    {eq.framework} · {eq.native_code}
                                </Link>{' '}
                                <span className="text-sm text-slate-600">
                                    ({RELACIONES[eq.relation] ?? eq.relation})
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby="prerrequisitos">
                <h2 id="prerrequisitos" className="mb-2 text-lg font-medium">
                    Antes de esta destreza
                </h2>
                {prerequisites.length === 0 ? (
                    <p role="status" className="text-sm text-slate-600">
                        Sin prerrequisitos registrados.
                    </p>
                ) : (
                    <ul className="list-disc space-y-1 pl-5">
                        {prerequisites.map((previa) => (
                            <li key={previa.id}>
                                <Link href={`/destreza/${previa.id}`} className="underline">
                                    {previa.native_code}
                                </Link>{' '}
                                <span className="text-sm text-slate-600">{previa.statement}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
            </div>
        </AppLayout>
    );
}
