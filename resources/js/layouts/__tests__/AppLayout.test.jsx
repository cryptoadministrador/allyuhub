import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AppLayout from '../AppLayout';
import { violacionesGraves } from '../../test/helpers';

const paginaMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => paginaMock(),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

function montar({ url = '/inicio', user = { id: 1, name: 'Ana Estudiante' }, esDocente = false, contextos = [] } = {}) {
    paginaMock.mockReturnValue({
        url,
        props: { auth: { user, es_docente: esDocente, contextos } },
    });

    return render(
        <AppLayout title="Página de prueba">
            <p>contenido</p>
        </AppLayout>,
    );
}

beforeEach(() => {
    paginaMock.mockReset();
    vi.unstubAllGlobals();
});

describe('AppLayout — marca y navegación', () => {
    it('muestra el wordmark y la navegación completa del alumno', () => {
        montar();

        const nav = screen.getByRole('navigation', { name: /principal/i });
        for (const destino of ['Inicio', 'Catálogo', 'Buscar', 'Mi progreso']) {
            expect(within(nav).getByRole('link', { name: destino })).toBeInTheDocument();
        }
        expect(screen.getByRole('link', { name: /allyuhub/i })).toHaveAttribute('href', '/inicio');
        expect(screen.getByRole('contentinfo')).toHaveTextContent(/plataforma educativa/i);
    });

    it('marca la página actual con aria-current, y solo esa', () => {
        montar({ url: '/catalogo/abc' });

        const nav = screen.getByRole('navigation', { name: /principal/i });
        expect(within(nav).getByRole('link', { name: 'Catálogo' })).toHaveAttribute('aria-current', 'page');
        expect(within(nav).getByRole('link', { name: 'Inicio' })).not.toHaveAttribute('aria-current');

        const marcados = within(nav).getAllByRole('link').filter((a) => a.getAttribute('aria-current') === 'page');
        expect(marcados).toHaveLength(1);
    });

    it('«Panel del curso» NO existe para un alumno', () => {
        montar({ esDocente: false, contextos: [] });

        expect(screen.queryByRole('link', { name: /panel del curso/i })).not.toBeInTheDocument();
        expect(document.body.innerHTML).not.toContain('/docente/');
    });

    it('«Panel del curso» aparece para el docente de un curso y apunta a SU contexto', () => {
        montar({ esDocente: true, contextos: [{ id: 'ctx-1', title: 'Física 1.º BGU' }] });

        expect(screen.getByRole('link', { name: /panel del curso/i })).toHaveAttribute('href', '/docente/ctx-1');
    });

    it('con varios cursos ofrece un panel por curso, con su título', () => {
        montar({
            esDocente: true,
            contextos: [
                { id: 'ctx-1', title: 'Física 1.º BGU' },
                { id: 'ctx-2', title: 'Química 2.º BGU' },
            ],
        });

        expect(screen.getByRole('link', { name: /Física 1\.º BGU/ })).toHaveAttribute('href', '/docente/ctx-1');
        expect(screen.getByRole('link', { name: /Química 2\.º BGU/ })).toHaveAttribute('href', '/docente/ctx-2');
    });

    it('sin sesión no pinta navegación de alumno ni nombre', () => {
        montar({ user: null });

        expect(screen.queryByRole('link', { name: 'Mi progreso' })).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: /allyuhub/i })).toBeInTheDocument();
    });
});

describe('AppLayout — móvil (360 px) y teclado', () => {
    it('el menú colapsable se abre y cierra SOLO con teclado', async () => {
        const user = userEvent.setup();
        montar();

        const boton = screen.getByRole('button', { name: /abrir menú/i });
        expect(boton).toHaveAttribute('aria-expanded', 'false');

        boton.focus();
        await user.keyboard('{Enter}');
        expect(boton).toHaveAttribute('aria-expanded', 'true');
        expect(boton).toHaveAccessibleName(/cerrar menú/i);

        // El menú desplegable es navegable y lleva los mismos destinos.
        const menu = screen.getByRole('navigation', { name: /móvil/i });
        expect(within(menu).getByRole('link', { name: 'Mi progreso' })).toBeInTheDocument();

        await user.keyboard('{Enter}');
        expect(screen.getByRole('button', { name: /abrir menú/i })).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByRole('navigation', { name: /móvil/i })).not.toBeInTheDocument();
    });

    it('el botón del menú controla el panel que dice controlar', async () => {
        const user = userEvent.setup();
        montar();

        const boton = screen.getByRole('button', { name: /abrir menú/i });
        await user.click(boton);

        const id = boton.getAttribute('aria-controls');
        expect(id).toBeTruthy();
        expect(document.getElementById(id)).toBe(screen.getByRole('navigation', { name: /móvil/i }));
    });
});

describe('AppLayout — modo embebido en Moodle', () => {
    it('dentro de un iframe se compacta: sin navegación duplicada ni pie', () => {
        // window.top !== window.self ⇒ embebido (la función pura decide).
        vi.stubGlobal('window', { ...globalThis.window, self: {}, top: {} });
        montar();

        expect(screen.queryByRole('navigation', { name: /principal/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('contentinfo')).not.toBeInTheDocument();
        // Pero la marca y el contenido siguen ahí.
        expect(screen.getByText('AllyuHub')).toBeInTheDocument();
        expect(screen.getByText('contenido')).toBeInTheDocument();
        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Página de prueba');
    });
});

describe('AppLayout — accesibilidad', () => {
    it.each([
        ['alumno', { esDocente: false }],
        ['docente', { esDocente: true, contextos: [{ id: 'ctx-1', title: 'Física' }] }],
        ['sin sesión', { user: null }],
    ])('sin violaciones serias (%s)', async (_nombre, opciones) => {
        const { container } = montar(opciones);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });

    it('sin violaciones serias con el menú móvil abierto', async () => {
        const user = userEvent.setup();
        const { container } = montar();
        await user.click(screen.getByRole('button', { name: /abrir menú/i }));

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });

    it('mantiene el enlace de salto al contenido', () => {
        montar();

        expect(screen.getByRole('link', { name: /saltar al contenido/i })).toHaveAttribute('href', '#contenido');
        expect(document.getElementById('contenido')).toBeTruthy();
    });
});
