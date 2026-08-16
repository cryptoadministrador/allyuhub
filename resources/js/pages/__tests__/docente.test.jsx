import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Docente from '../docente';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

const routerPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 9, name: 'Docente Pérez' } } } }),
    router: { post: (...args) => routerPost(...args) },
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const CONTEXT = { id: 'ctx-1', title: 'Física 1.º BGU', label: 'FIS1' };
const TRACK = { id: 't-ord', code: 'ORD', label: 'Ordinaria' };
const TRACKS = [TRACK, { id: 't-bi', code: 'PCEI-BI', label: 'Bachillerato Intensivo' }];

const ALUMNOS = [
    { id: 2, name: 'Beatriz Rezagada', dominadas: 0, en_progreso: 0, sin_empezar: 3, last_launched_at: '2026-08-01T10:00:00Z' },
    { id: 1, name: 'Ana Avanzada', dominadas: 1, en_progreso: 1, sin_empezar: 1, last_launched_at: '2026-08-14T10:00:00Z' },
];

function props(overrides = {}) {
    return {
        context: CONTEXT,
        track: TRACK,
        tracks: TRACKS,
        objectives_summary: { total: 3, con_items: 2 },
        students: ALUMNOS,
        ...overrides,
    };
}

beforeEach(() => {
    vi.unstubAllGlobals();
    routerPost.mockClear();
});

describe('docente — el panel', () => {
    it('sin track: aviso claro, selector, y la tabla no inventa conteos', () => {
        render(<Docente {...props({ track: null, objectives_summary: null })} />);

        expect(screen.getByRole('status')).toHaveTextContent(/aún no tiene trayecto asignado/i);
        // Sin track no hay columnas de conteo ni botón de detalle.
        expect(screen.queryByRole('columnheader', { name: /dominadas/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /ver detalle/i })).not.toBeInTheDocument();
        // Pero los alumnos y su último acceso sí se ven.
        expect(screen.getByRole('rowheader', { name: /Beatriz Rezagada/ })).toBeInTheDocument();
    });

    it('con track: tabla de verdad, rezagado EN TEXTO y orden del servidor respetado', () => {
        render(<Docente {...props()} />);

        const tabla = screen.getByRole('table');
        expect(within(tabla).getByRole('columnheader', { name: 'Alumno' })).toBeInTheDocument();
        expect(within(tabla).getByRole('columnheader', { name: 'Dominadas' })).toBeInTheDocument();

        // La primera fila del cuerpo es la rezagada (el orden viene del servidor).
        const filas = within(tabla).getAllByRole('rowheader');
        expect(filas[0]).toHaveTextContent('Beatriz Rezagada');
        // El rezago se dice con palabras, no con un color.
        expect(within(filas[0]).getByText('sin avance todavía')).toBeInTheDocument();
        expect(within(filas[1]).queryByText('sin avance todavía')).not.toBeInTheDocument();

        expect(screen.getByText(/cubre 3 destrezas; 2 con/i)).toBeInTheDocument();
    });

    it('el detalle expandible pide el mastery por destreza y lo pinta con porcentaje', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respuestaJson(200, {
            destrezas: [
                { native_code: 'CN.F.5.1.9', mastery: 0.85, is_mastered: true },
                { native_code: 'CN.F.5.1.12', mastery: 0.35, is_mastered: false },
            ],
        })));

        const user = userEvent.setup();
        render(<Docente {...props()} />);

        const boton = screen.getAllByRole('button', { name: /ver detalle/i })[0];
        expect(boton).toHaveAttribute('aria-expanded', 'false');
        await user.click(boton);
        expect(boton).toHaveAttribute('aria-expanded', 'true');

        expect(await screen.findByText('CN.F.5.1.9')).toBeInTheDocument();
        expect(screen.getByText(/85 %.*dominada/)).toBeInTheDocument();
        expect(screen.getByRole('progressbar', { name: /CN\.F\.5\.1\.9 de Beatriz/i }))
            .toHaveAttribute('aria-valuenow', '85');
    });

    it('asignar trayecto envía el POST con el track elegido', async () => {
        const user = userEvent.setup();
        render(<Docente {...props({ track: null, objectives_summary: null })} />);

        await user.selectOptions(screen.getByLabelText(/elegir trayecto/i), 't-bi');
        await user.click(screen.getByRole('button', { name: /guardar/i }));

        expect(routerPost).toHaveBeenCalledWith('/docente/ctx-1/track', { track_id: 't-bi' });
    });

    it('sin alumnos: estado vacío digno', () => {
        render(<Docente {...props({ students: [] })} />);

        expect(screen.getByText(/todavía ningún alumno ha entrado/i)).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('ORÁCULO 5: nada sensible en el DOM', () => {
        render(<Docente {...props()} />);

        const html = document.body.innerHTML;
        expect(html).not.toContain('email');
        expect(html).not.toContain('lti_sub');
        expect(html).not.toContain('solution_expr');
        expect(html).not.toContain('@');   // ni un correo colado
    });

    it.each([
        ['con track y alumnos', props()],
        ['sin track', props({ track: null, objectives_summary: null })],
        ['sin alumnos', props({ students: [] })],
    ])('accesibilidad: %s sin violaciones serias', async (_nombre, p) => {
        const { container } = render(<Docente {...p} />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
