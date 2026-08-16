import { render, screen, within } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Inicio from '../inicio';
import { violacionesGraves } from '../../test/helpers';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        url: '/inicio',
        props: { auth: { user: { id: 1, name: 'Ana Estudiante' }, es_docente: false, contextos: [] } },
    }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const CONTINUAR = {
    objective_id: 'obj-1',
    native_code: 'CN.F.5.1.9',
    statement: 'Explicar el movimiento en el plano inclinado.',
    mastery: 0.35,
};

const SIGUIENTE = {
    objective_id: 'obj-2',
    native_code: 'CN.4.3.5',
    statement: 'Explicar las fuerzas que actúan sobre los objetos.',
    reason: 'refuerzo de prerrequisito',
};

function props(overrides = {}) {
    return {
        continuar: CONTINUAR,
        siguiente: SIGUIENTE,
        resumen: { dominadas: 2, en_progreso: 3, practicadas: 5 },
        ...overrides,
    };
}

beforeEach(() => vi.unstubAllGlobals());

describe('inicio — el alumno que ya practicó', () => {
    it('ofrece continuar por su última destreza, con dominio y enlace', () => {
        render(<Inicio {...props()} />);

        const seccion = screen.getByRole('heading', { name: /continúa donde ibas/i }).closest('section');
        expect(within(seccion).getByText('CN.F.5.1.9')).toBeInTheDocument();
        expect(within(seccion).getByRole('link', { name: /seguir practicando/i }))
            .toHaveAttribute('href', '/practicar/obj-1');
        expect(within(seccion).getByRole('progressbar', { name: /dominio de CN\.F\.5\.1\.9/i }))
            .toHaveAttribute('aria-valuenow', '35');
    });

    it('explica el siguiente paso con la razón EN LENGUAJE DE ALUMNO', () => {
        render(<Inicio {...props()} />);

        const seccion = screen.getByRole('heading', { name: /tu siguiente paso/i }).closest('section');
        expect(within(seccion).getByText(/repasemos algo anterior/i)).toBeInTheDocument();
        expect(within(seccion).getByText('CN.4.3.5')).toBeInTheDocument();
        expect(within(seccion).getByRole('link', { name: /empezar/i }))
            .toHaveAttribute('href', '/practicar/obj-2');
    });

    it('usa el texto de AVANCE cuando el motor manda avanzar', () => {
        render(<Inicio {...props({ siguiente: { ...SIGUIENTE, reason: 'avance' } })} />);

        expect(screen.getByText(/a por lo siguiente/i)).toBeInTheDocument();
        expect(screen.queryByText(/repasemos algo anterior/i)).not.toBeInTheDocument();
    });

    it('la práctica normal también tiene su texto (no deja el hueco vacío)', () => {
        render(<Inicio {...props({ siguiente: { ...SIGUIENTE, reason: 'práctica normal' } })} />);

        expect(screen.getByText(/sigue practicando esta destreza/i)).toBeInTheDocument();
    });

    it('el resumen se lee en palabras, no solo en cifras', () => {
        render(<Inicio {...props()} />);

        const seccion = screen.getByRole('heading', { name: /cómo vas/i }).closest('section');
        expect(seccion).toHaveTextContent(/has practicado\s*5\s*destrezas/i);
        expect(seccion).toHaveTextContent(/2\s*dominadas/i);
        expect(seccion).toHaveTextContent(/3\s*en progreso/i);
    });
});

describe('inicio — el alumno nuevo', () => {
    it('recibe una invitación honesta, no una pantalla vacía ni cifras falsas', () => {
        render(<Inicio {...props({
            continuar: null,
            siguiente: null,
            resumen: { dominadas: 0, en_progreso: 0, practicadas: 0 },
        })} />);

        expect(screen.getByText(/todavía no has practicado ninguna destreza/i)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /explorar el catálogo/i })).toHaveAttribute('href', '/catalogo');
        expect(screen.getByText(/aún no hay progreso que mostrar/i)).toBeInTheDocument();
        // Sin datos no se inventa un «siguiente paso».
        expect(screen.queryByRole('heading', { name: /tu siguiente paso/i })).not.toBeInTheDocument();
    });
});

describe('inicio — accesos y accesibilidad', () => {
    it('ofrece catálogo y búsqueda', () => {
        render(<Inicio {...props()} />);

        const accesos = screen.getByRole('navigation', { name: /accesos rápidos/i });
        expect(within(accesos).getByRole('link', { name: /catálogo del currículo/i })).toHaveAttribute('href', '/catalogo');
        expect(within(accesos).getByRole('link', { name: /buscar una destreza/i })).toHaveAttribute('href', '/buscar');
    });

    it('un solo h1 y jerarquía de encabezados sin saltos', () => {
        render(<Inicio {...props()} />);

        expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
        expect(screen.getAllByRole('heading', { level: 2 }).length).toBeGreaterThanOrEqual(3);
        expect(screen.queryAllByRole('heading', { level: 4 })).toHaveLength(0);
    });

    it.each([
        ['con datos', props()],
        ['alumno nuevo', props({ continuar: null, siguiente: null, resumen: { dominadas: 0, en_progreso: 0, practicadas: 0 } })],
        ['sin siguiente paso', props({ siguiente: null })],
    ])('sin violaciones serias (%s)', async (_nombre, p) => {
        const { container } = render(<Inicio {...p} />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });

    it('no pinta nada sensible', () => {
        render(<Inicio {...props()} />);

        const html = document.body.innerHTML;
        expect(html).not.toContain('solution_expr');
        expect(html).not.toContain('seed');
        expect(html).not.toContain('deg2rad');
    });
});
