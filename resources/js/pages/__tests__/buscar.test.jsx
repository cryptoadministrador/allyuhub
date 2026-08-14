import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Buscar from '../buscar';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 1, name: 'Ana' } } } }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const RESULTADO_API = {
    id: 'd1',
    native_code: 'CN.F.5.1.12',
    statement: { es: 'Determinar el coeficiente de rozamiento entre dos superficies.' },
    is_verified: true,
    has_items: true,
    node: { title: { es: 'Física' } },
};

beforeEach(() => vi.unstubAllGlobals());

describe('buscar — estados y anuncios', () => {
    it('con menos de 3 letras pide más letras y NO llama a la API', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const user = userEvent.setup();
        render(<Buscar q="" results={null} />);

        await user.type(screen.getByRole('searchbox'), 'ab');

        await waitFor(() =>
            expect(screen.getByRole('status')).toHaveTextContent(/escribe al menos 3 letras/i),
        );
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('busca con debounce, anuncia el número de resultados y los pinta con su distintivo', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respuestaJson(200, [RESULTADO_API])));

        const user = userEvent.setup();
        render(<Buscar q="" results={null} />);

        await user.type(screen.getByRole('searchbox'), 'rozamiento');

        // El anuncio llega por el aria-live (role=status).
        await waitFor(
            () => expect(screen.getByRole('status')).toHaveTextContent('1 resultado para «rozamiento».'),
            { timeout: 2000 },
        );
        expect(screen.getByRole('link', { name: 'CN.F.5.1.12' })).toBeInTheDocument();
        expect(screen.getByText('Verificada con el currículo oficial')).toBeInTheDocument();
        expect(screen.getByText('En: Física')).toBeInTheDocument();

        // La URL lleva el q: compartible y recargable.
        expect(window.location.search).toContain('q=rozamiento');
    });

    it('«sin resultados» es un estado distinto de «escribe más letras»', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respuestaJson(200, [])));

        const user = userEvent.setup();
        render(<Buscar q="" results={null} />);

        await user.type(screen.getByRole('searchbox'), 'unicornio');

        expect(await screen.findByText(/sin resultados/i, {}, { timeout: 2000 })).toBeInTheDocument();
        expect(screen.queryByText(/escribe al menos 3 letras/i)).not.toBeInTheDocument();
    });

    it('el primer pintado del servidor se muestra sin repetir la búsqueda', () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        render(<Buscar
            q="rozamiento"
            results={[{
                id: 'd1', native_code: 'CN.F.5.1.12', statement: 'Determinar…',
                is_verified: true, has_items: true, node_title: 'Física',
            }]}
        />);

        expect(screen.getByRole('link', { name: 'CN.F.5.1.12' })).toBeInTheDocument();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('accesibilidad: sin violaciones serias con resultados y en vacío', async () => {
        const conDatos = render(<Buscar
            q="rozamiento"
            results={[{
                id: 'd1', native_code: 'CN.F.5.1.12', statement: 'Determinar…',
                is_verified: false, has_items: false, node_title: '',
            }]}
        />);
        expect(violacionesGraves(await axe(conDatos.container))).toEqual([]);
        conDatos.unmount();

        const vacia = render(<Buscar q="" results={null} />);
        expect(violacionesGraves(await axe(vacia.container))).toEqual([]);
    });
});
