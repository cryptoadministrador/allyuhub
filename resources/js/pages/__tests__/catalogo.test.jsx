import { render, screen, within } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { describe, expect, it, vi } from 'vitest';
import Catalogo from '../catalogo';
import { violacionesGraves } from '../../test/helpers';

/**
 * La puerta del catálogo NO tenía ni un test (auditoría): se podía borrar el
 * `tree.map` entero —dejando `/catalogo` sin árbol del currículo— y la suite
 * seguía 30/30. Es la página que abre todo el Frente 1.
 */

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 1, name: 'Ana' } } } }),
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

    it('el árbol se pinta RECURSIVO y cada nodo enlaza a SU id', () => {
        render(<Catalogo frameworks={MARCOS} tree={ARBOL} />);

        // Tres niveles: nivel → subnivel → grado. Sin la recursión, el nieto
        // no existe y este test se pone rojo.
        expect(screen.getByRole('link', { name: 'Educación General Básica' }))
            .toHaveAttribute('href', '/catalogo/n-egb');
        expect(screen.getByRole('link', { name: 'Básica Superior' }))
            .toHaveAttribute('href', '/catalogo/n-sup');
        expect(screen.getByRole('link', { name: '8.º de Básica' }))
            .toHaveAttribute('href', '/catalogo/n-g8');
        expect(screen.getByRole('link', { name: '9.º de Básica' }))
            .toHaveAttribute('href', '/catalogo/n-g9');
        expect(screen.getByRole('link', { name: 'Bachillerato' }))
            .toHaveAttribute('href', '/catalogo/n-bach');

        // Y el nieto cuelga de su padre, no está suelto en la raíz.
        const subnivel = screen.getByRole('link', { name: 'Básica Superior' }).closest('li');
        expect(within(subnivel).getByRole('link', { name: '8.º de Básica' })).toBeInTheDocument();
    });

    it('sin currículo sembrado lo dice, y lo dice anunciándolo', () => {
        render(<Catalogo frameworks={MARCOS} tree={[]} />);

        expect(screen.getByRole('status'))
            .toHaveTextContent(/currículo aún no está sembrado/i);
        expect(screen.queryByRole('link', { name: 'Bachillerato' })).not.toBeInTheDocument();
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
