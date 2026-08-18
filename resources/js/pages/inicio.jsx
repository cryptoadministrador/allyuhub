import { Head, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import Anillo from '../components/Anillo';
import { estiloDeAsignatura } from '../lib/color';
import { textoDeRazon } from '../lib/razones';

/**
 * La casa del alumno. Tres preguntas, en el orden en que se las hace:
 * ¿dónde iba? · ¿qué toca ahora? · ¿cómo voy?
 *
 * Nada de números sin palabras: el resumen se lee en una frase, y el anillo
 * de dominio lleva el porcentaje escrito dentro. Cada tarjeta toma el acento
 * de SU asignatura, así que de un vistazo se ve si lo de hoy es Física o
 * Matemática sin tener que leer el código curricular.
 */

function Tarjeta({ titulo, acento, children }) {
    return (
        <section
            style={{ ...estiloDeAsignatura(acento), borderLeftColor: 'var(--acento)' }}
            className="mb-6 rounded-lg border border-l-4 border-slate-200 bg-white p-4"
        >
            <h2 className="mb-3 text-base font-semibold text-slate-900">{titulo}</h2>
            {children}
        </section>
    );
}

/** El encabezado de una destreza: icono de la asignatura + código monoespaciado. */
function Encabezado({ destreza }) {
    return (
        <div className="flex items-center gap-3">
            {destreza.asignatura?.icon && (
                <span
                    aria-hidden="true"
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-xl"
                    style={{ background: 'var(--acento-suave)' }}
                >
                    {destreza.asignatura.icon}
                </span>
            )}
            <div className="min-w-0">
                <p className="font-mono text-sm font-semibold" style={{ color: 'var(--acento-tinta)' }}>
                    {destreza.native_code}
                </p>
                {destreza.asignatura && (
                    <p className="text-xs text-slate-600">{destreza.asignatura.title}</p>
                )}
            </div>
        </div>
    );
}

const BOTON =
    'inline-block rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600';

export default function Inicio({ continuar, siguiente, resumen }) {
    const razon = siguiente ? textoDeRazon(siguiente.reason) : null;
    const empezando = continuar === null;

    return (
        <AppLayout title="Tu aprendizaje">
            <Head title="Inicio" />

            <Tarjeta titulo="Continúa donde ibas" acento={continuar?.asignatura?.color}>
                {empezando ? (
                    <div>
                        <p className="text-slate-700">
                            Todavía no has practicado ninguna destreza. Explora el catálogo del
                            currículo y empieza por la que quieras: la práctica es abierta.
                        </p>
                        <p className="mt-3">
                            <Link href="/catalogo" className={BOTON}>
                                Explorar el catálogo
                            </Link>
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0">
                            <Encabezado destreza={continuar} />
                            <p className="mt-2 text-sm leading-relaxed text-slate-700">
                                {continuar.statement}
                            </p>
                            <p className="mt-3">
                                <Link href={`/practicar/${continuar.objective_id}`} className={BOTON}>
                                    Seguir practicando
                                </Link>
                            </p>
                        </div>

                        {/* El anillo dice el dominio con su número dentro: quien
                            no vea el arco lee el porcentaje igual. */}
                        <Anillo
                            valor={continuar.mastery}
                            etiqueta={`Dominio de ${continuar.native_code}`}
                            tamano={104}
                        />
                    </div>
                )}
            </Tarjeta>

            {siguiente && (
                <Tarjeta titulo="Tu siguiente paso" acento={siguiente.asignatura?.color}>
                    <p className="mb-3 rounded bg-marca-50 px-3 py-2 text-sm text-marca-900">
                        <span aria-hidden="true">{razon.icono} </span>
                        {razon.texto}
                    </p>
                    <Encabezado destreza={siguiente} />
                    <p className="mt-2 text-sm leading-relaxed text-slate-700">
                        {siguiente.statement}
                    </p>
                    <p className="mt-3">
                        <Link href={`/practicar/${siguiente.objective_id}`} className={BOTON}>
                            Empezar
                        </Link>
                    </p>
                </Tarjeta>
            )}

            <Tarjeta titulo="Cómo vas">
                {resumen.practicadas === 0 ? (
                    <p className="text-slate-700">
                        Aún no hay progreso que mostrar. En cuanto practiques, aquí verás cuántas
                        destrezas dominas.
                    </p>
                ) : (
                    <>
                        {/* Números Y palabras: la cifra sola no dice nada. */}
                        <p className="text-slate-700">
                            Has practicado <strong>{resumen.practicadas}</strong>{' '}
                            {resumen.practicadas === 1 ? 'destreza' : 'destrezas'}:{' '}
                            <strong>{resumen.dominadas}</strong>{' '}
                            {resumen.dominadas === 1 ? 'dominada' : 'dominadas'} y{' '}
                            <strong>{resumen.en_progreso}</strong> en progreso.
                        </p>
                        <p className="mt-3">
                            <Link href="/progreso" className="underline hover:text-marca-700">
                                Ver mi progreso por trayecto
                            </Link>
                        </p>
                    </>
                )}
            </Tarjeta>

            <nav aria-label="Accesos rápidos" className="text-sm">
                <ul className="flex flex-wrap gap-4">
                    <li>
                        <Link href="/catalogo" className="underline hover:text-marca-700">
                            Catálogo del currículo
                        </Link>
                    </li>
                    <li>
                        <Link href="/buscar" className="underline hover:text-marca-700">
                            Buscar una destreza
                        </Link>
                    </li>
                </ul>
            </nav>
        </AppLayout>
    );
}
