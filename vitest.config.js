import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

// Config de Vitest SEPARADA de vite.config.js: el plugin de Laravel exige un
// entorno artisan que aquí no existe ni hace falta.
export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['resources/js/test/setup.js'],
        include: ['resources/js/**/__tests__/*.test.{js,jsx}'],
        coverage: {
            provider: 'v8',
            include: ['resources/js/**/*.jsx'],
            exclude: ['resources/js/**/__tests__/**', 'resources/js/test/**'],
        },
    },
});
