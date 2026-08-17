import { Head, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { textoDeRazon } from '../lib/razones';

/**
 * La casa del alumno. Tres preguntas, en el orden en que se las hace:
 * ¿dónde iba? · ¿qué toca ahora? · ¿cómo voy?
 *
 * Nada de números sin palabras: el resumen se lee en una frase.
 */

function Tarjeta({ titulo, children }) {
    return (
        <section className="mb-6 rounded-lg border border-slate-200 bg-white p-4">
            <h2 className="mb-2 text-base font-semibold text-slate-900">{titulo}</h2>
            {children}
        </section>
    );
}

function BarraDominio({ valor, etiqueta }) {
    const pct = Math.round((valor ?? 0) * 100);

    return (
        <div className="mt-2">
            <div
                role="progressbar"
                aria-label={etiqueta}
                aria-valuenow={pct}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuetext={`${pct} por ciento`}
                className="h-2 overflow-hidden rounded-full bg-slate-200"
            >
                <div className="h-full rounded-full bg-marca-600" style={{ width: `${pct}%` }} />
            </div>
            <p className="mt-1 text-xs text-slate-600">Dominio: {pct} %</p>
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

            <Tarjeta titulo="Continúa donde ibas">
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
                    <div>
                        <p className="font-medium">{continuar.native_code}</p>
                        <p className="text-sm text-slate-700">{continuar.statement}</p>
                        <BarraDominio
                            valor={continuar.mastery}
                            etiqueta={`Dominio de ${continuar.native_code}`}
                        />
                        <p className="mt-3">
                            <Link href={`/practicar/${continuar.objective_id}`} className={BOTON}>
                                Seguir practicando
                            </Link>
                        </p>
                    </div>
                )}
            </Tarjeta>

            {siguiente && (
                <Tarjeta titulo="Tu siguiente paso">
                    <p className="mb-2 rounded bg-marca-50 px-3 py-2 text-sm text-marca-900">
                        <span aria-hidden="true">{razon.icono} </span>
                        {razon.texto}
                    </p>
                    <p className="font-medium">{siguiente.native_code}</p>
                    <p className="text-sm text-slate-700">{siguiente.statement}</p>
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
