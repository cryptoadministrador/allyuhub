import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';

/**
 * La página de error con la marca de la app (404 y 403).
 *
 * Un enlace muerto dentro del iframe de Moodle era, hasta ahora, la pantalla
 * blanca de Symfony: sin cabecera, sin salida y con pinta de que la plataforma
 * se rompió. Aquí el alumno ve dónde está y qué puede hacer, y quien tenga
 * sesión recibe además atajos a los sitios a los que sí puede ir.
 */

const MENSAJES = {
    404: {
        titulo: 'Esa página no existe',
        texto:
            'El enlace que seguiste apunta a algo que se movió o que nunca estuvo aquí. ' +
            'No es culpa tuya, y nada de tu progreso se ha perdido.',
    },
    403: {
        titulo: 'Aquí no puedes entrar',
        texto:
            'Esta parte de AllyuHub es de otra persona —el panel de un curso que no es tuyo, ' +
            'por ejemplo—. Si crees que sí deberías verla, díselo a tu docente.',
    },
};

const GENERICO = {
    titulo: 'Algo no salió bien',
    texto: 'La página que pediste no se pudo mostrar. Vuelve a intentarlo desde el inicio.',
};

export default function ErrorPagina({ status }) {
    const { props } = usePage();
    const conSesion = Boolean(props.auth?.user);
    const mensaje = MENSAJES[status] ?? GENERICO;

    return (
        <AppLayout>
            <Head title={mensaje.titulo} />

            <section className="rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
                {/* El número es contexto, no el mensaje: el mensaje va en palabras. */}
                <p className="font-mono text-sm font-semibold text-slate-500">Error {status}</p>
                <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                    {mensaje.titulo}
                </h1>
                <p className="mt-3 max-w-xl text-slate-700">{mensaje.texto}</p>

                <ul className="mt-6 flex flex-wrap gap-3">
                    {conSesion ? (
                        <>
                            <li>
                                <Link
                                    href="/inicio"
                                    className="inline-block rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                >
                                    Volver a mi inicio
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/catalogo"
                                    className="inline-block rounded border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                >
                                    Ir al catálogo
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/buscar"
                                    className="inline-block rounded border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                >
                                    Buscar una destreza
                                </Link>
                            </li>
                        </>
                    ) : (
                        <li>
                            <a
                                href="/entrar"
                                className="inline-block rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                            >
                                Entrar desde tu aula virtual
                            </a>
                        </li>
                    )}
                </ul>
            </section>
        </AppLayout>
    );
}
