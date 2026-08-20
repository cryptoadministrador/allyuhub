import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { estaEmbebido } from '../lib/entorno';

/**
 * El marco de la plataforma: marca, navegación y pie.
 *
 * Dos modos, decididos por el navegador (no por el servidor):
 *  - VENTANA COMPLETA (launchcontainer=4, o el alumno entrando por su cuenta):
 *    cabecera con navegación y pie.
 *  - EMBEBIDO en el iframe de Moodle: se compacta a la marca y el contenido —
 *    duplicar la navegación dentro del LMS confunde y roba pantalla.
 *
 * Móvil primero: un alumno de PCEI entra desde un teléfono viejo, así que la
 * navegación vive en un menú colapsable operable con teclado.
 */

const DESTINOS = [
    { href: '/inicio', texto: 'Inicio' },
    { href: '/catalogo', texto: 'Catálogo' },
    { href: '/buscar', texto: 'Buscar' },
    { href: '/progreso', texto: 'Mi progreso' },
];

// Lo que puede visitar quien no ha entrado: el contenido es abierto, así que el
// visitante navega con la misma barra, no con una versión mutilada. Su casa y
// su progreso no están porque no existen todavía — no porque se le escondan.
const DESTINOS_INVITADO = [
    { href: '/catalogo', texto: 'Catálogo' },
    { href: '/buscar', texto: 'Buscar' },
];

/** ¿Este destino es la página actual? `/catalogo` cubre `/catalogo/{nodo}`. */
export function esActivo(url, href) {
    const ruta = (url || '/').split('?')[0];

    if (href === '/inicio') {
        return ruta === '/inicio' || ruta === '/';
    }

    return ruta === href || ruta.startsWith(`${href}/`);
}

/** Los enlaces al panel: «Panel del curso» si hay uno; por título si hay varios. */
function panelesDocente(contextos) {
    if (contextos.length === 1) {
        return [{ href: `/docente/${contextos[0].id}`, texto: 'Panel del curso' }];
    }

    return contextos.map((c) => ({
        href: `/docente/${c.id}`,
        texto: `Panel: ${c.title ?? 'curso'}`,
    }));
}

function EnlaceNav({ destino, url, className = '' }) {
    const activo = esActivo(url, destino.href);

    return (
        <Link
            href={destino.href}
            aria-current={activo ? 'page' : undefined}
            className={`rounded px-2 py-1 text-sm focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 ${
                activo
                    ? 'bg-marca-50 font-semibold text-marca-700 underline decoration-2 underline-offset-4'
                    : 'text-slate-700 hover:bg-slate-100 hover:text-marca-700'
            } ${className}`}
        >
            {destino.texto}
        </Link>
    );
}

export default function AppLayout({ title, children }) {
    const { props, url } = usePage();
    const auth = props.auth ?? {};
    const [menuAbierto, setMenuAbierto] = useState(false);
    const [embebido] = useState(() => estaEmbebido(typeof window === 'undefined' ? undefined : window));

    const destinos = auth.user
        ? [...DESTINOS, ...(auth.es_docente ? panelesDocente(auth.contextos ?? []) : [])]
        : DESTINOS_INVITADO;

    return (
        <div className="min-h-screen bg-slate-50">
            <a
                href="#contenido"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:shadow"
            >
                Saltar al contenido
            </a>

            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                    {/* Wordmark tipográfico: sobrio, esto lo usan colegios.
                        Sin sesión apunta a la portada: /inicio exige auth y
                        rebotaría al visitante a la pared de /entrar. */}
                    <Link
                        href={auth.user ? '/inicio' : '/'}
                        className="text-lg font-semibold tracking-tight text-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                    >
                        AllyuHub
                    </Link>

                    {!embebido && destinos.length > 0 && (
                        <>
                            {/* Escritorio */}
                            <nav aria-label="Navegación principal" className="hidden items-center gap-1 sm:flex">
                                {destinos.map((d) => (
                                    <EnlaceNav key={d.href} destino={d} url={url} />
                                ))}
                            </nav>

                            {/* Móvil */}
                            <button
                                type="button"
                                aria-expanded={menuAbierto}
                                aria-controls="menu-movil"
                                onClick={() => setMenuAbierto((v) => !v)}
                                className="rounded border border-slate-300 px-3 py-1.5 text-sm focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 sm:hidden"
                            >
                                {menuAbierto ? 'Cerrar menú' : 'Abrir menú'}
                            </button>
                        </>
                    )}

                    {auth.user ? (
                        <p className="hidden text-sm text-slate-600 sm:block">{auth.user.name}</p>
                    ) : (
                        // La puerta INVITA, no tapa: el visitante ya está dentro
                        // del contenido; esto es para que guarde lo que haga.
                        <a
                            href="/entrar"
                            className="hidden rounded border border-marca-600 px-3 py-1.5 text-sm font-medium text-marca-700 hover:bg-marca-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 sm:block"
                        >
                            Entrar y guardar mi avance
                        </a>
                    )}
                </div>

                {/* Se monta SIEMPRE y se oculta con `hidden`: si solo existiera
                    abierto, el aria-controls del botón apuntaría a un id
                    inexistente mientras está cerrado, y un lector de pantalla
                    no podría saltar al menú que dice controlar (auditoría). */}
                {!embebido && destinos.length > 0 && (
                    <nav
                        id="menu-movil"
                        hidden={!menuAbierto}
                        aria-label="Navegación móvil"
                        className="border-t border-slate-200 px-4 pb-3 sm:hidden"
                    >
                        <ul className="flex flex-col gap-1 pt-2">
                            {destinos.map((d) => (
                                <li key={d.href}>
                                    <EnlaceNav destino={d} url={url} className="block" />
                                </li>
                            ))}
                            {auth.user ? (
                                <li className="px-2 pt-2 text-sm text-slate-600">{auth.user.name}</li>
                            ) : (
                                <li className="pt-2">
                                    <a
                                        href="/entrar"
                                        className="block rounded px-2 py-1 text-sm font-medium text-marca-700 underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                                    >
                                        Entrar y guardar mi avance
                                    </a>
                                </li>
                            )}
                        </ul>
                    </nav>
                )}
            </header>

            <main id="contenido" tabIndex={-1} className="mx-auto max-w-5xl px-4 py-6">
                {title && <h1 className="mb-4 text-xl font-semibold tracking-tight">{title}</h1>}
                {children}
            </main>

            {!embebido && (
                <footer className="mx-auto max-w-5xl px-4 pb-8 pt-4 text-sm text-slate-600">
                    <p className="border-t border-slate-200 pt-4">
                        AllyuHub · plataforma educativa ·{' '}
                        <a
                            href="mailto:soporte@allyuhub.edu.ec"
                            className="underline hover:text-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                        >
                            Soporte
                        </a>
                    </p>
                </footer>
            )}
        </div>
    );
}
