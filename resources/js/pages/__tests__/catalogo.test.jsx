import { render, screen, within } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Catalogo from '../catalogo';
import { violacionesGraves } from '../../test/helpers';

/**
 * La puerta del catálogo NO tenía ni un test (auditoría): se podía borrar el
 * `tree.map` entero —dejando `/catalogo` sin árbol del currículo— y la suite
 * seguía 30/30. Es la página que abre todo el Frente 1.
 */

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

const ARBOL = [
    {
        id: 'n-egb',
        title: 'Educación General Básica',
        children: [
            {
                id: 'n-sup',
                title: 'Básica Superior',
                children: [
                    { id: 'n-g8', title: '8.º de Básica', children: [] },
                    { id: 'n-g9', title: '9.º de Básica', children: [] },
                ],
            },
        ],
    },
    { id: 'n-bach', title: 'Bachillerato', children: [] },
];

const MARCOS = [
    { id: 'f1', code: 'EC-MINEDEC', label: 'Currículo Nacional del Ecuador' },
    { id: 'f2', code: 'CAIE-IGCSE', label: 'Cambridge IGCSE' },
];

describe('catalogo — la puerta del currículo', () => {
    it('lista los marcos con su código', () => {
        render(<Catalogo frameworks={MARCOS} tree={ARBOL} />);

        const seccion = screen.getByRole('region', { name: /marcos curriculares/i });
        expect(within(seccion).getByText(/Currículo Nacional del Ecuador/)).toBeInTheDocument();
        expect(within(seccion).getByText('(EC-MINEDEC)')).toBeInTheDocument();
        expect(within(seccion).getByText('(CAIE-IGCSE)')).toBeInTheDocument();
    });

    it('el árbol baja hasta el GRADO, y cada tarjeta enlaza a SU id', () => {
        render(<Catalogo frameworks={MARCOS} tree={ARBOL} />);

        // Nivel y subnivel son el andamio: se leen como encabezados. El grado
        // es el destino: es el único que se pinta como tarjeta enlazada. Sin
        // la recursión completa, los nietos no existen y esto se pone rojo.
        expect(screen.getByRole('heading', { name: 'Educación General Básica' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Básica Superior' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: '8.º de Básica' }))
            .toHaveAttribute('href', '/catalogo/n-g8');
        expect(screen.getByRole('link', { name: '9.º de Básica' }))
            .toHaveAttribute('href', '/catalogo/n-g9');

        // Y el nieto cuelga de su subnivel, no está suelto en la raíz.
        const subnivel = screen.getByRole('heading', { name: 'Básica Superior' }).parentElement;
        expect(within(subnivel).getByRole('link', { name: '8.º de Básica' })).toBeInTheDocument();
    });

    it('la tarjeta de grado usa la etiqueta CORTA y dice cuántas destrezas hay', () => {
        render(
            <Catalogo
                frameworks={MARCOS}
                tree={[
                    {
                        id: 'n-bgu',
                        title: 'Bachillerato',
                        children: [
                            {
                                id: 'n-sub',
                                title: 'BGU',
                                children: [
                                    {
                                        id: 'g11',
                                        title: 'Primer Año de Bachillerato General Unificado',
                                        corto: '1.º BGU',
                                        edad: 15,
                                        destrezas: 240,
                                        verificadas: 8,
                                        practicables: 3,
                                    },
                                ],
                            },
                        ],
                    },
                ]}
            />,
        );

        // El enlace se llama como lo llama un alumno, no como el documento.
        const enlace = screen.getByRole('link', { name: '1.º BGU' });
        expect(enlace).toHaveAttribute('href', '/catalogo/g11');

        const tarjeta = enlace.closest('li');
        // …y el nombre largo sigue estando, para quien lo necesite.
        expect(within(tarjeta).getByText('Primer Año de Bachillerato General Unificado')).toBeInTheDocument();
        expect(within(tarjeta).getByText('Desde los 15 años')).toBeInTheDocument();
        expect(within(tarjeta).getByText('240 destrezas')).toBeInTheDocument();
        expect(within(tarjeta).getByText('8 verificadas')).toBeInTheDocument();
        expect(within(tarjeta).getByText('3 con ejercicios')).toBeInTheDocument();
    });

    it('un grado sin metadatos se pinta igual, sin huecos ni «undefined»', () => {
        render(
            <Catalogo
                frameworks={MARCOS}
                tree={[
                    {
                        id: 'n1',
                        title: 'EGB',
                        children: [{ id: 'n2', title: 'Elemental', children: [{ id: 'g2', title: '2.º de Básica' }] }],
                    },
                ]}
            />,
        );

        const tarjeta = screen.getByRole('link', { name: '2.º de Básica' }).closest('li');
        expect(tarjeta.textContent).not.toMatch(/undefined|null|NaN/);
        expect(within(tarjeta).queryByText(/desde los/i)).not.toBeInTheDocument();
    });

    it('sin currículo sembrado lo dice, y lo dice anunciándolo', () => {
        render(<Catalogo frameworks={MARCOS} tree={[]} />);

        expect(screen.getByRole('status'))
            .toHaveTextContent(/currículo aún no está sembrado/i);
        expect(screen.queryByRole('heading', { name: 'Bachillerato' })).not.toBeInTheDocument();
    });

    it('ofrece la búsqueda como salida', () => {
        render(<Catalogo frameworks={MARCOS} tree={ARBOL} />);

        expect(screen.getByRole('link', { name: /usa la búsqueda/i }))
            .toHaveAttribute('href', '/buscar');
    });

    it.each([
        ['con árbol', ARBOL],
        ['vacía', []],
    ])('accesibilidad: portada %s sin violaciones serias', async (_nombre, arbol) => {
        const { container } = render(<Catalogo frameworks={MARCOS} tree={arbol} />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});

/**
 * ORÁCULO 9 — el catálogo es ahora la primera pantalla que ve alguien que
 * llega de fuera. Sin sesión tiene que verse igual de bien y de accesible.
 */
describe('catalogo — como VISITANTE', () => {
    afterEach(() => {
        sesion = { user: { id: 1, name: 'Ana' } };
    });

    it('el visitante ve exactamente el mismo currículo', () => {
        sesion = { user: null };
        render(<Catalogo frameworks={MARCOS} tree={ARBOL} />);

        expect(screen.getByRole('link', { name: '8.º de Básica' }))
            .toHaveAttribute('href', '/catalogo/n-g8');
        expect(screen.getByRole('heading', { name: 'Educación General Básica' })).toBeInTheDocument();
    });

    it('no tiene violaciones graves de accesibilidad', async () => {
        sesion = { user: null };
        const { container } = render(<Catalogo frameworks={MARCOS} tree={ARBOL} />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
