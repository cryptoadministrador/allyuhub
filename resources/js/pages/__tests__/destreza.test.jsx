import { render, screen } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Destreza from '../destreza';
import { violacionesGraves } from '../../test/helpers';

// `sesion` es mutable: el contenido es abierto, así que la misma página se
// pinta para un alumno y para un visitante y hay que poder probar las dos.
let sesion = { user: { id: 1, name: 'Ana' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: sesion } }),
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
        expect(boton).toHaveAttribute('aria-disabled', 'true');
        expect(boton).toHaveAccessibleDescription(/todavía no tiene ejercicios/i);
        expect(screen.queryByRole('link', { name: /practicar esta destreza/i })).not.toBeInTheDocument();

        // REGRESIÓN (auditoría): con `disabled` nativo el botón NO es enfocable,
        // así que quien navega con teclado nunca oye la explicación — y esta es
        // la interacción de la ficha en 1001 de las 1010 destrezas.
        expect(boton).not.toBeDisabled();
        boton.focus();
        expect(boton).toHaveFocus();
    });

    it('ORÁCULO 4: un marcador lo dice en texto y explica el porqué', () => {
        render(<Destreza {...props({
            objective: { ...props().objective, is_verified: false },
        })} />);

        expect(screen.getByText(/provisional: texto de relleno/i)).toBeInTheDocument();
        // La explicación completa, no solo la etiqueta: sin esto se podía
        // quitar el `conExplicacion` del distintivo y nadie se enteraba.
        expect(screen.getByText(/se sustituirá al importar el currículo oficial del MINEDUC/i))
            .toBeInTheDocument();
        expect(screen.getByText(/marcador provisional, no la redacción del currículo oficial/i))
            .toBeInTheDocument();
        // Y una verificada NO lleva ninguna de las dos cosas.
        expect(screen.queryByText(/verificada con el currículo oficial/i)).not.toBeInTheDocument();
    });

    it('ORÁCULO 4 (contrario): una verificada no se disfraza de marcador', () => {
        render(<Destreza {...props()} />);

        expect(screen.getByText(/verificada con el currículo oficial/i)).toBeInTheDocument();
        expect(screen.queryByText(/provisional/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/se sustituirá al importar/i)).not.toBeInTheDocument();
    });

    it('el enunciado de la destreza se pinta', () => {
        render(<Destreza {...props()} />);

        expect(screen.getByText('Determinar el coeficiente de rozamiento.')).toBeInTheDocument();
    });

    it('los recursos publicados y los prerrequisitos se pintan con su enlace', () => {
        render(<Destreza {...props({
            resources: [{ id: 'r1', slug: 'plano-inclinado', title: 'Laboratorio: plano inclinado', kind: 'lab', duration_min: 15 }],
            prerequisites: [{ id: 'p1', native_code: 'CN.F.5.1.9', statement: 'Plano inclinado', is_verified: true }],
        })} />);

        expect(screen.getByRole('link', { name: /Laboratorio: plano inclinado/i }))
            .toHaveAttribute('href', '/recurso/r1');
        const prerreq = screen.getByRole('link', { name: /CN\.F\.5\.1\.9/ });
        expect(prerreq).toHaveAttribute('href', '/destreza/p1');
        expect(screen.getByText(/Plano inclinado/)).toBeInTheDocument();
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

/**
 * ORÁCULO 9 — la ficha es el sitio desde el que un visitante entra a practicar:
 * el botón tiene que estar ahí y llevar a la página abierta.
 */
describe('destreza — como VISITANTE', () => {
    afterEach(() => {
        sesion = { user: { id: 1, name: 'Ana' } };
    });

    it('ofrece practicar sin pedir sesión', () => {
        sesion = { user: null };
        render(<Destreza {...props()} />);

        expect(screen.getByRole('link', { name: /practicar esta destreza/i }))
            .toHaveAttribute('href', `/practicar/${props().objective.id}`);
    });

    it('no tiene violaciones graves de accesibilidad', async () => {
        sesion = { user: null };
        const { container } = render(<Destreza {...props()} />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
