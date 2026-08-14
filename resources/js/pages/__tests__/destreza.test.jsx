import { render, screen } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { describe, expect, it, vi } from 'vitest';
import Destreza from '../destreza';
import { violacionesGraves } from '../../test/helpers';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 1, name: 'Ana' } } } }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

function props(overrides = {}) {
    return {
        objective: {
            id: 'd1', native_code: 'CN.F.5.1.12',
            statement: 'Determinar el coeficiente de rozamiento.',
            is_verified: true, has_items: true,
        },
        breadcrumbs: [{ id: 'n1', title: 'Física', node_type: 'asignatura' }],
        resources: [],
        alignments: [],
        prerequisites: [],
        ...overrides,
    };
}

describe('destreza — la ficha', () => {
    it('con ítems: el botón Practicar es un ENLACE vivo', () => {
        render(<Destreza {...props()} />);

        expect(screen.getByRole('link', { name: /practicar esta destreza/i }))
            .toHaveAttribute('href', '/practicar/d1');
    });

    it('sin ítems: botón deshabilitado CON explicación, jamás un enlace a un 404', () => {
        render(<Destreza {...props({
            objective: { ...props().objective, has_items: false },
        })} />);

        const boton = screen.getByRole('button', { name: /practicar esta destreza/i });
        expect(boton).toBeDisabled();
        expect(boton).toHaveAccessibleDescription(/todavía no tiene ejercicios/i);
        expect(screen.queryByRole('link', { name: /practicar esta destreza/i })).not.toBeInTheDocument();
    });

    it('ORÁCULO 4: un marcador lo dice en texto y explica el porqué', () => {
        render(<Destreza {...props({
            objective: { ...props().objective, is_verified: false },
        })} />);

        expect(screen.getByText(/provisional: texto de relleno/i)).toBeInTheDocument();
        expect(screen.getByText(/marcador provisional, no la redacción del currículo oficial/i))
            .toBeInTheDocument();
    });

    it('ORÁCULO 6: sin alineaciones revisadas, el vacío es HONESTO', () => {
        render(<Destreza {...props()} />);

        expect(screen.getByText(/propuestas pero aún no revisadas por un docente/i))
            .toBeInTheDocument();
    });

    it('con una alineación revisada, la equivalencia se pinta y el vacío desaparece', () => {
        render(<Destreza {...props({
            alignments: [{ native_code: '0625.1.5.1', relation: 'exact', framework: 'CAIE-IGCSE', objective_id: 'dc1' }],
        })} />);

        expect(screen.getByRole('link', { name: /CAIE-IGCSE · 0625\.1\.5\.1/ })).toBeInTheDocument();
        expect(screen.getByText(/\(equivalente a\)/)).toBeInTheDocument();
        expect(screen.queryByText(/aún no revisadas/i)).not.toBeInTheDocument();
    });

    it.each([
        ['completa', props({ alignments: [{ native_code: 'X', relation: 'exact', framework: 'IB-DP', objective_id: 'x1' }] })],
        ['vacía y provisional', props({ objective: { id: 'd2', native_code: 'CN.X', statement: 'Marcador', is_verified: false, has_items: false } })],
    ])('accesibilidad: ficha %s sin violaciones serias', async (_nombre, p) => {
        const { container } = render(<Destreza {...p} />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
