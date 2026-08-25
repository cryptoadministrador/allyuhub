import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import Formula from '../components/Formula';

/**
 * EL LECTOR. Antes esto eran 23 líneas que no mostraban un recurso: mostraban
 * un enlace a un CDN externo que no existe.
 *
 * Ahora pinta la lección bloque a bloque. Nada de `dangerouslySetInnerHTML`:
 * cada tipo de bloque tiene su componente y el texto entra como texto, que es
 * lo que hace que un `<script>` en el contenido se lea como `<script>` en vez
 * de ejecutarse.
 *
 * Tipografía de lectura, no de interfaz: ancho de línea acotado (~65 caracteres
 * es lo que el ojo sigue sin perderse de renglón), interlineado holgado y
 * encabezados en orden.
 */

const AVISOS = {
    'error-tipico': {
        icono: '⚠️',
        titulo: 'Error típico',
        clases: 'border-amber-200 border-l-amber-500 bg-amber-50 text-amber-900',
    },
    ojo: {
        icono: '👀',
        titulo: 'Ojo',
        clases: 'border-marca-100 border-l-marca-600 bg-marca-50 text-marca-900',
    },
    truco: {
        icono: '💡',
        titulo: 'Truco',
        clases: 'border-emerald-200 border-l-emerald-600 bg-emerald-50 text-emerald-900',
    },
};

/**
 * El bloque de audio de una LECCIÓN. Reproductor nativo (0 KB de librería,
 * teclado y lector de pantalla gratis con `controls`) y la transcripción
 * SIEMPRE visible: es requisito de accesibilidad y además es pedagogía — un
 * alumno de A1 necesita poder leer lo que oye.
 *
 * Y si el clip no llega —la red de un colegio se cae—, el bloque DEGRADA a su
 * transcripción con un aviso: la lección de texto sigue funcionando entera.
 * El que esconde la transcripción hasta responder es el ítem de escucha, que
 * es otro camino y ni siquiera la recibe del servidor.
 */
function BloqueAudio({ bloque }) {
    const [fallo, setFallo] = useState(false);

    return (
        <figure className="my-5 rounded-lg border border-slate-200 bg-white p-4">
            {fallo ? (
                <p role="status" className="mb-2 rounded border border-amber-200 bg-amber-50 p-2 text-sm text-amber-900">
                    El audio no está disponible ahora mismo. Puedes leer lo que
                    dice:
                </p>
            ) : (
                <audio
                    controls
                    preload="none"
                    src={bloque.src}
                    onError={() => setFallo(true)}
                    aria-label="Audio de la lección"
                    className="w-full"
                />
            )}
            <figcaption className="mt-2">
                {Object.entries(bloque.texto).map(([lengua, texto]) => (
                    <p
                        key={lengua}
                        lang={lengua === 'es' ? undefined : lengua}
                        className={
                            lengua === 'es'
                                ? 'text-sm leading-relaxed text-slate-600'
                                : 'text-base font-medium leading-relaxed text-slate-900'
                        }
                    >
                        {texto}
                    </p>
                ))}
            </figcaption>
        </figure>
    );
}

function Bloque({ bloque }) {
    switch (bloque.tipo) {
        case 'parrafo':
            return <p className="my-4 leading-relaxed text-slate-800">{bloque.texto.es}</p>;

        case 'formula':
            return <Formula arbol={bloque.mathml} etiqueta={bloque.etiqueta?.es} />;

        case 'lista': {
            const Lista = bloque.ordenada ? 'ol' : 'ul';

            return (
                <Lista
                    className={`my-4 space-y-1 pl-6 text-slate-800 ${
                        bloque.ordenada ? 'list-decimal' : 'list-disc'
                    }`}
                >
                    {bloque.items.map((item, i) => (
                        <li key={i} className="leading-relaxed">
                            {item.es}
                        </li>
                    ))}
                </Lista>
            );
        }

        case 'aviso': {
            const estilo = AVISOS[bloque.variante] ?? AVISOS.ojo;

            return (
                <aside className={`my-5 flex gap-3 rounded-lg border border-l-4 p-4 ${estilo.clases}`}>
                    <span aria-hidden="true" className="text-xl leading-none">
                        {estilo.icono}
                    </span>
                    <div>
                        {/* El rótulo va en TEXTO, no solo en color: quien no
                            distinga el ámbar tiene que saber que esto es un
                            error típico y no una curiosidad. */}
                        <p className="text-sm font-semibold">{estilo.titulo}</p>
                        <p className="mt-1 text-sm leading-relaxed">{bloque.texto.es}</p>
                    </div>
                </aside>
            );
        }

        case 'ejemplo':
            return (
                <section className="my-6 rounded-lg border border-slate-200 bg-white p-4">
                    {/* h2, no h3: un ejemplo resuelto es una sección de la
                        lección al mismo nivel que el cierre. Con h3 se saltaba
                        de h1 a h3 y la jerarquía quedaba con un hueco, que es
                        justo por donde se pierde quien navega por encabezados. */}
                    <h2 className="text-base font-semibold text-slate-900">
                        {bloque.titulo?.es ?? 'Ejemplo resuelto'}
                    </h2>
                    <ol className="mt-3 space-y-3">
                        {bloque.pasos.map((paso, i) => (
                            <li key={i} className="flex gap-3">
                                <span
                                    aria-hidden="true"
                                    className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-marca-50 text-sm font-semibold text-marca-700"
                                >
                                    {i + 1}
                                </span>
                                <div className="min-w-0">
                                    <p className="leading-relaxed text-slate-800">{paso.texto.es}</p>
                                    {paso.formula && <Formula arbol={paso.formula} />}
                                </div>
                            </li>
                        ))}
                    </ol>
                </section>
            );

        case 'audio':
            return <BloqueAudio bloque={bloque} />;

        case 'imagen':
            return (
                <figure className="my-5">
                    <img src={bloque.src} alt={bloque.alt.es} className="mx-auto max-w-full rounded-lg" />
                </figure>
            );

        default:
            // No debería llegar: el validador del servidor rechaza los tipos
            // que no conoce. Si llegara, se calla en vez de romper la lectura.
            return null;
    }
}

export default function Recurso({ recurso, destrezas }) {
    const esLeccion = recurso.kind === 'reading';

    return (
        <AppLayout>
            <Head title={recurso.title} />

            <article className="mx-auto max-w-2xl">
                <header className="mb-6">
                    <p className="text-sm font-medium uppercase tracking-wide text-marca-700">
                        {esLeccion ? 'Lección' : 'Recurso'}
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                        {recurso.title}
                    </h1>
                    {recurso.summary && (
                        <p className="mt-2 text-lg leading-relaxed text-slate-700">{recurso.summary}</p>
                    )}
                    {recurso.duration_min && (
                        <p className="mt-2 text-sm text-slate-600">
                            Unos {recurso.duration_min} minutos de lectura.
                        </p>
                    )}
                </header>

                {recurso.bloques.length > 0 ? (
                    <div>
                        {recurso.bloques.map((bloque, i) => (
                            <Bloque key={i} bloque={bloque} />
                        ))}
                    </div>
                ) : recurso.bundle_url ? (
                    <p className="my-4">
                        <a
                            href={recurso.bundle_url}
                            className="inline-block rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                        >
                            Abrir el laboratorio
                        </a>
                    </p>
                ) : (
                    <p role="status" className="my-4 text-slate-700">
                        Este recurso todavía no tiene contenido publicado.
                    </p>
                )}

                {/* Cerrar la lección devolviendo al alumno a practicar: leer y
                    practicar son dos mitades del mismo bucle, no dos sitios. */}
                {destrezas.length > 0 && (
                    <footer className="mt-10 rounded-lg border border-slate-200 bg-white p-4">
                        <h2 className="text-base font-semibold text-slate-900">
                            {destrezas.length === 1 ? 'Ahora practica esta destreza' : 'Ahora practica'}
                        </h2>
                        <ul className="mt-3 space-y-2">
                            {destrezas.map((d) => (
                                <li key={d.id}>
                                    <Link
                                        href={`/practicar/${d.id}`}
                                        className="font-mono text-sm font-semibold text-marca-700 underline hover:text-marca-900 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                    >
                                        {d.native_code}
                                    </Link>
                                    <p className="text-sm leading-relaxed text-slate-700">{d.statement}</p>
                                </li>
                            ))}
                        </ul>
                    </footer>
                )}
            </article>
        </AppLayout>
    );
}
