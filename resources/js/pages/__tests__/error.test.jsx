import { render, screen } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { describe, expect, it, vi } from 'vitest';
import ErrorPagina from '../error';
import { violacionesGraves } from '../../test/helpers';

// El estado de sesión se controla desde el mock: la página cambia sus salidas.
let auth = { user: { id: 1, name: 'Ana' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ url: '/roto', props: { auth } }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

describe('página de error con marca', () => {
    it('el 404 se explica en palabras, no solo con el número', () => {
        auth = { user: { id: 1, name: 'Ana' } };
        render(<ErrorPagina status={404} />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Esa página no existe');
        expect(screen.getByText('Error 404')).toBeInTheDocument();
        expect(screen.getByText(/no es culpa tuya/i)).toBeInTheDocument();
    });

    it('el 403 dice otra cosa que el 404 (no es el mismo cartel)', () => {
        auth = { user: { id: 1, name: 'Ana' } };
        render(<ErrorPagina status={403} />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Aquí no puedes entrar');
        expect(screen.queryByText(/esa página no existe/i)).not.toBeInTheDocument();
    });

    it('un estado desconocido no deja al alumno sin mensaje', () => {
        auth = { user: { id: 1, name: 'Ana' } };
        render(<ErrorPagina status={418} />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Algo no salió bien');
        expect(screen.getByText('Error 418')).toBeInTheDocument();
    });

    it('con sesión ofrece salidas dentro de la app', () => {
        auth = { user: { id: 1, name: 'Ana' } };
        render(<ErrorPagina status={404} />);

        expect(screen.getByRole('link', { name: /volver a mi inicio/i })).toHaveAttribute('href', '/inicio');
        expect(screen.getByRole('link', { name: /ir al catálogo/i })).toHaveAttribute('href', '/catalogo');
        expect(screen.queryByRole('link', { name: /aula virtual/i })).not.toBeInTheDocument();
    });

    /**
     * Contenido abierto: al visitante SÍ se le ofrece el catálogo —es suyo—
     * pero nunca una página que lo rebotaría a /entrar. Ese es el oráculo que
     * este test conserva; lo que cambió es qué páginas rebotan.
     */
    it('sin sesión ofrece el catálogo y NO lo que le rebotaría a /entrar', () => {
        auth = { user: null };
        render(<ErrorPagina status={404} />);

        expect(screen.getByRole('link', { name: /explorar el currículo/i }))
            .toHaveAttribute('href', '/catalogo');
        expect(screen.getByRole('link', { name: /aula virtual/i })).toHaveAttribute('href', '/entrar');

        ['/inicio', '/progreso'].forEach((ruta) => {
            expect(document.querySelector(`a[href="${ruta}"]`)).toBeNull();
        });
    });

    it.each([
        ['404 con sesión', 404, { user: { id: 1, name: 'Ana' } }],
        ['404 sin sesión', 404, { user: null }],
    ])('accesibilidad: %s sin violaciones serias', async (_n, status, sesion) => {
        auth = sesion;
        const { container } = render(<ErrorPagina status={status} />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
