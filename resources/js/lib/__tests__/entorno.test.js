import { describe, expect, it } from 'vitest';
import { estaEmbebido } from '../entorno';

/**
 * FRENTE 0 — la decisión «¿estamos dentro del iframe de Moodle?» se extrae a
 * una función PURA para poder probarla: el header se compacta cuando la app
 * corre embebida y no puede depender de una prop del servidor (la sesión no
 * sabe si esta pestaña concreta está embebida).
 */
describe('estaEmbebido', () => {
    it('en ventana completa devuelve false', () => {
        const win = {};
        win.self = win;
        win.top = win;

        expect(estaEmbebido(win)).toBe(false);
    });

    it('dentro de un iframe devuelve true', () => {
        const padre = {};
        const win = { top: padre };
        win.self = win;

        expect(estaEmbebido(win)).toBe(true);
    });

    it('si el navegador BLOQUEA leer window.top (iframe de otro origen) asume embebido', () => {
        const win = {};
        win.self = win;
        Object.defineProperty(win, 'top', {
            get() {
                throw new DOMException('Blocked a frame with origin from accessing a cross-origin frame.');
            },
        });

        // Es el caso REAL de Moodle: origen distinto, el acceso lanza. Asumir
        // «no embebido» pintaría la navegación duplicada dentro del LMS.
        expect(estaEmbebido(win)).toBe(true);
    });

    it('sin window (SSR o test) devuelve false en vez de reventar', () => {
        expect(estaEmbebido(undefined)).toBe(false);
        expect(estaEmbebido(null)).toBe(false);
    });
});
