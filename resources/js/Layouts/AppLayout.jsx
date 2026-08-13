import { usePage } from '@inertiajs/react';

/**
 * Marco común de la app. Vive dentro del iframe de Moodle: cabecera compacta,
 * sin navegación redundante con el LMS. El enlace de salto permite ir directo
 * al contenido con teclado (a11y).
 */
export default function AppLayout({ title, children }) {
    const { auth } = usePage().props;

    return (
        <div className="mx-auto min-h-screen max-w-3xl px-4 py-6">
            <a
                href="#contenido"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:shadow"
            >
                Saltar al contenido
            </a>

            <header className="mb-6 flex items-baseline justify-between gap-4 border-b border-slate-200 pb-3">
                <p className="text-lg font-semibold">AllyuHub</p>
                {auth.user && (
                    <p className="text-sm text-slate-600">{auth.user.name}</p>
                )}
            </header>

            <main id="contenido" tabIndex={-1}>
                {title && <h1 className="mb-4 text-xl font-semibold">{title}</h1>}
                {children}
            </main>
        </div>
    );
}
