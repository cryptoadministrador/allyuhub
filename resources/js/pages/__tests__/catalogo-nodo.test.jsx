import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CatalogoNodo from '../catalogo-nodo';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

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

const NODE = { id: 'nodo-1', node_type: 'bloque', native_code: null, title: 'Movimiento y fuerza' };
const MIGAS = [
    { id: 'n1', title: 'Bachillerato', node_type: 'nivel' },
    { id: 'n2', title: 'Física', node_type: 'asignatura' },
];

const VERIFICADA = {
    id: 'd1', native_code: 'CN.F.5.1.12',
    statement: 'Determinar el coeficiente de rozamiento.',
    is_verified: true, has_items: true,
};
const MARCADOR = {
    id: 'd2', native_code: 'CN.F.5.9.99',
    statement: 'Reconocer los contenidos del bloque…',
    is_verified: false, has_items: false,
};

function props(overrides = {}) {
    return {
        node: NODE,
        breadcrumbs: MIGAS,
        children: [],
        objectives: { data: [VERIFICADA, MARCADOR], total: 2, current_page: 1, last_page: 1, per_page: 50 },
        ...overrides,
    };
}

beforeEach(() => vi.unstubAllGlobals());

describe('catalogo-nodo — ORÁCULO 4: honestidad del catálogo', () => {
    it('una verificada y un marcador producen DOM DISTINTO, con el estado en texto', () => {
        render(<CatalogoNodo {...props()} />);

        // El distintivo es texto legible, no un color — y está EN la destreza
        // correcta (una mutación que invierta la condición debe morir aquí).
        const filaVerificada = screen.getByText('CN.F.5.1.12').closest('li');
        const filaMarcador = screen.getByText('CN.F.5.9.99').closest('li');

        expect(within(filaVerificada).getByText('Verificada con el currículo oficial')).toBeInTheDocument();
        expect(within(filaVerificada).getByText('Con ejercicios')).toBeInTheDocument();
        expect(within(filaVerificada).queryByText(/provisional/i)).not.toBeInTheDocument();

        expect(within(filaMarcador).getByText(/provisional: texto de relleno/i)).toBeInTheDocument();
        expect(within(filaMarcador).getByText('Sin ejercicios todavía')).toBeInTheDocument();
        expect(within(filaMarcador).queryByText(/verificada con el currículo/i)).not.toBeInTheDocument();
    });

    /**
     * El chip que dice si hay ejercicios NO puede ser solo un color: si se le
     * quita el texto, este test cae. (Regla de la misión: nada distinguido
     * únicamente por color.)
     */
    it('el estado va escrito, no solo pintado', () => {
        render(<CatalogoNodo {...props()} />);

        const fila = screen.getByText('CN.F.5.1.12').closest('li');
        // Sin contar los aria-hidden, el texto visible ya distingue las dos.
        expect(fila.textContent).toContain('Con ejercicios');
        expect(fila.textContent).toContain('Verificada con el currículo oficial');
    });

    it('anuncia cuántas destrezas se muestran (aria-live)', () => {
        render(<CatalogoNodo {...props()} />);

        expect(screen.getByRole('status')).toHaveTextContent('Mostrando 2 de 2 destrezas.');
    });
});

describe('catalogo-nodo — paginación real', () => {
    it('«Cargar más» trae la página siguiente de la API y la añade', async () => {
        const pagina1 = Array.from({ length: 50 }, (_, i) => ({
            ...MARCADOR, id: `p1-${i}`, native_code: `CN.X.${i}`,
        }));
        const fetchMock = vi.fn().mockResolvedValueOnce(respuestaJson(200, {
            data: [{ id: 'p2-1', native_code: 'CN.EXTRA.61', statement: { es: 'La 61' }, is_verified: false, has_items: false }],
            current_page: 2, last_page: 2, total: 51,
        }));
        vi.stubGlobal('fetch', fetchMock);

        const user = userEvent.setup();
        render(<CatalogoNodo {...props({
            objectives: { data: pagina1, total: 51, current_page: 1, last_page: 2, per_page: 50 },
        })} />);

        expect(screen.getByRole('status')).toHaveTextContent('Mostrando 50 de 51');

        await user.click(screen.getByRole('button', { name: /cargar más/i }));

        expect(await screen.findByText('CN.EXTRA.61')).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveTextContent('Mostrando 51 de 51');
        expect(fetchMock).toHaveBeenCalledWith(
            '/api/v1/nodes/nodo-1/objectives?page=2',
            expect.anything(),
        );
        // Al completar el total, el botón desaparece (enlace que no muere).
        expect(screen.queryByRole('button', { name: /cargar más/i })).not.toBeInTheDocument();
    });
});

describe('catalogo-nodo — el acento de la asignatura', () => {
    const FISICA = { id: 'n2', title: 'Física', icon: '⚛️', color: '#3aa675' };
    // Migas sin la asignatura: así «Física» aparece una sola vez y las
    // aserciones apuntan a la cabecera, no al rastro de navegación.
    const SOLO_NIVEL = [{ id: 'n1', title: 'Bachillerato', node_type: 'nivel' }];

    it('el bloque hereda el icono y el color de su asignatura', () => {
        const { container } = render(
            <CatalogoNodo {...props({ asignatura: FISICA, breadcrumbs: SOLO_NIVEL })} />,
        );

        // El icono acompaña al título del bloque…
        expect(screen.getByText('⚛️')).toHaveAttribute('aria-hidden', 'true');
        // …y el nombre de la asignatura está ESCRITO, no solo insinuado en verde.
        expect(screen.getByText('Física')).toBeInTheDocument();

        // El acento llega como variable CSS ya calculada (no el hex crudo).
        const conAcento = container.querySelector('[style*="--acento"]');
        expect(conAcento.getAttribute('style')).toContain('#3aa675');
        expect(conAcento.getAttribute('style')).toContain('--acento-tinta');
    });

    it('sin asignatura en la cadena, la página se pinta igual en gris', () => {
        const { container } = render(
            <CatalogoNodo {...props({ asignatura: null, breadcrumbs: SOLO_NIVEL })} />,
        );

        expect(screen.getByText('CN.F.5.1.12')).toBeInTheDocument();
        expect(container.querySelector('[style*="--acento"]').getAttribute('style'))
            .toContain('#64748b');
    });

    it('las tarjetas de asignatura llevan icono, color y cuentas', () => {
        render(
            <CatalogoNodo
                {...props({
                    node: { id: 'g11', node_type: 'grado', title: '1.º BGU' },
                    breadcrumbs: SOLO_NIVEL,
                    children: [
                        { id: 'a1', title: 'Física', icon: '⚛️', color: '#3aa675', destrezas: 12, practicables: 3 },
                        { id: 'a2', title: 'Matemática', icon: '📐', color: '#4a86e8', destrezas: 40, practicables: 0 },
                    ],
                })}
            />,
        );

        const fisica = screen.getByRole('link', { name: 'Física' });
        expect(fisica).toHaveAttribute('href', '/catalogo/a1');
        const tarjeta = fisica.closest('li');
        expect(within(tarjeta).getByText('12 destrezas')).toBeInTheDocument();
        expect(within(tarjeta).getByText('3 con ejercicios')).toBeInTheDocument();

        // Sin ejercicios no se inventa un chip de cero.
        const mate = screen.getByRole('link', { name: 'Matemática' }).closest('li');
        expect(within(mate).queryByText(/con ejercicios/)).not.toBeInTheDocument();
        expect(within(mate).getByText('40 destrezas')).toBeInTheDocument();
    });
});

describe('catalogo-nodo — accesibilidad', () => {
    it.each([
        ['con datos', props()],
        ['vacío', props({ objectives: { data: [], total: 0, current_page: 1, last_page: 1, per_page: 50 } })],
    ])('estado %s sin violaciones serias', async (_nombre, p) => {
        const { container } = render(<CatalogoNodo {...p} />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});

/** ORÁCULO 9 — el nodo del catálogo, también sin sesión. */
describe('catalogo-nodo — como VISITANTE', () => {
    afterEach(() => {
        sesion = { user: { id: 1, name: 'Ana' } };
    });

    it('lista las destrezas y no tiene violaciones graves', async () => {
        sesion = { user: null };
        const { container } = render(<CatalogoNodo {...props()} />);

        expect(screen.getByText('CN.F.5.1.12')).toBeInTheDocument();
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
