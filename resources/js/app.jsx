import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import '../css/app.css';

createInertiaApp({
    title: (title) => (title ? `${title} — AllyuHub` : 'AllyuHub'),
    resolve: (name) =>
        // LAZY, no eager: con `eager` todo el sitio viajaba en un solo
        // app-*.js y un alumno de italiano se descargaba también los
        // simuladores de física y el panel del docente antes de ver la
        // primera palabra. Con el glob perezoso cada página es su chunk y el
        // entry lleva solo el marco (React + Inertia + layout).
        // Los __tests__ siguen EXCLUIDOS del glob o el bundle de producción
        // arrastra testing-library y axe (pasó: 320 KB → 861 KB).
        resolvePageComponent(
            `./pages/${name}.jsx`,
            import.meta.glob(['./pages/**/*.jsx', '!**/__tests__/**']),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
