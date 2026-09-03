import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { describe, expect, it, vi } from 'vitest';
import { violacionesGraves } from '../../test/helpers';

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 9, name: 'Prof. Rossi' } } } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
    router: { post: postMock },
}));

import DocenteRevisar from '../docente-revisar';

const PIEZA = (extra = {}) => ({
    tipo: 'item', id: 'i1', titulo: 'Completa: Mi ___ Ana.', lengua: 'it',
    kind: 'hueco', url: '/docente/revisar/item/i1', nota: null, vista: false, ...extra,
});

const PROPS = {
    lengua: 'it',
    lenguas: ['fr', 'it', 'de', 'zh'],
    estado: 'pendientes',
    docente: { id: 9, name: 'Prof. Rossi' },
    total: 2,
    unidades: [{
        n: 1, titulo: 'Primer contacto', total: 2, todo_visto: false,
        descriptores: [{
            code: 'A1.CO.2', statement: 'Puedo comprender saludos.',
            piezas: [PIEZA(), PIEZA({ id: 'i2', url: '/docente/revisar/item/i2' })],
        }],
    }],
};

describe('docente-revisar — la cola de revisión', () => {
    it('dice cuántas faltan y agrupa por unidad y descriptor', () => {
        render(<DocenteRevisar {...PROPS} />);
        expect(screen.getByText(/2 pieza\(s\) esperan tu firma/i)).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: /Unidad 1 · Primer contacto/ })).toBeInTheDocument();
        expect(screen.getByText(/A1\.CO\.2 · Puedo comprender saludos/)).toBeInTheDocument();
        // Cada pieza es un enlace a abrirla tal como la ve el alumno.
        expect(screen.getAllByRole('link', { name: /Completa/ })).toHaveLength(2);
    });

    it('dice con qué nombre se firma: la autoría deja de ser anónima', () => {
        render(<DocenteRevisar {...PROPS} />);
        expect(screen.getByText(/tu nombre queda en lo que publiques/i)).toBeInTheDocument();
        // Sale en el nav (AppLayout) y en la frase de la firma: basta con que esté.
        expect(screen.getAllByText('Prof. Rossi').length).toBeGreaterThanOrEqual(1);
    });

    it('firmar la unidad entera está BLOQUEADO mientras quede algo sin abrir', async () => {
        render(<DocenteRevisar {...PROPS} />);
        const boton = screen.getByRole('button', { name: /firmar la unidad entera/i });
        expect(boton).toBeDisabled();
        expect(screen.getByText(/ábrelas todas/i)).toBeInTheDocument();
    });

    it('con todo abierto, firmar la unidad entera manda la unidad y la lengua', async () => {
        const user = userEvent.setup();
        const props = {
            ...PROPS,
            unidades: [{
                ...PROPS.unidades[0],
                todo_visto: true,
                descriptores: [{
                    ...PROPS.unidades[0].descriptores[0],
                    piezas: [PIEZA({ vista: true }), PIEZA({ id: 'i2', vista: true })],
                }],
            }],
        };
        render(<DocenteRevisar {...props} />);

        await user.click(screen.getByRole('button', { name: /firmar la unidad entera/i }));
        expect(postMock).toHaveBeenCalledWith('/docente/revisar/unidad',
            { unidad: 1, lengua: 'it' }, expect.anything());
    });

    it('una pieza devuelta enseña su nota a quien la corrija', () => {
        const props = {
            ...PROPS,
            unidades: [{
                ...PROPS.unidades[0],
                descriptores: [{
                    ...PROPS.unidades[0].descriptores[0],
                    piezas: [PIEZA({ nota: { nota: 'El ejemplo 3 está mal.', docente: 'Prof. Rossi', cuando: '2026-09-04' } })],
                }],
            }],
        };
        render(<DocenteRevisar {...props} />);
        expect(screen.getByText(/El ejemplo 3 está mal/)).toBeInTheDocument();
    });

    it('sin nada pendiente lo declara, no queda en blanco', () => {
        render(<DocenteRevisar {...PROPS} unidades={[]} total={0} />);
        expect(screen.getByRole('status')).toHaveTextContent(/no queda nada por revisar/i);
    });

    it('accesibilidad: sin violaciones serias', async () => {
        const { container } = render(<DocenteRevisar {...PROPS} />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
