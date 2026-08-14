import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';

createInertiaApp({
    title: (title) => (title ? `${title} — AllyuHub` : 'AllyuHub'),
    resolve: (name) => {
        // Los __tests__ viven junto a su página: EXCLUIDOS del glob o el bundle
        // de producción arrastra testing-library y axe (pasó: 320 KB → 861 KB).
        const pages = import.meta.glob(['./pages/**/*.jsx', '!**/__tests__/**'], { eager: true });

        return pages[`./pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
