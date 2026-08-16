import { render, screen, waitFor, within } from '@testing-library/react';
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
    /**
     * ORÁCULO 4 en la TERCERA superficie. El endurecimiento con `within()` se
     * aplicó a la lista del nodo y no se propagó aquí: con un solo resultado
     * verificado en el fixture, cambiar `verificada={destreza.is_verified}` por
     * `verificada` dejaba la suite en verde y pintaba los 1001 marcadores como
     * «Verificada con el currículo oficial» en toda la búsqueda (auditoría).
     */
    it('ORÁCULO 4: cada resultado lleva SU distintivo, no el del vecino', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respuestaJson(200, [
            RESULTADO_API,
            {
                id: 'd2',
                native_code: 'CN.4.1.1',
                statement: { es: 'Marcador generado para navegar el árbol.' },
                is_verified: false,
                has_items: false,
                node: { title: { es: 'Ciencias Naturales' } },
            },
        ])));

        const user = userEvent.setup();
        render(<Buscar q="" results={null} />);
        await user.type(screen.getByRole('searchbox'), 'rozamiento');

        const filas = await screen.findAllByRole('listitem');
        expect(filas).toHaveLength(2);

        const verificada = filas.find((f) => within(f).queryByText('CN.F.5.1.12'));
        const marcador = filas.find((f) => within(f).queryByText('CN.4.1.1'));

        expect(within(verificada).getByText(/verificada con el currículo oficial/i)).toBeInTheDocument();
        expect(within(verificada).queryByText(/provisional/i)).not.toBeInTheDocument();

        expect(within(marcador).getByText(/provisional: texto de relleno/i)).toBeInTheDocument();
        expect(within(marcador).queryByText(/verificada con el currículo/i)).not.toBeInTheDocument();
    });

    it('cada resultado enlaza a SU ficha', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respuestaJson(200, [
            RESULTADO_API,
            { ...RESULTADO_API, id: 'd2', native_code: 'CN.4.1.1', statement: { es: 'Otra.' } },
        ])));

        const user = userEvent.setup();
        render(<Buscar q="" results={null} />);
        await user.type(screen.getByRole('searchbox'), 'rozamiento');

        expect(await screen.findByRole('link', { name: 'CN.F.5.1.12' }))
            .toHaveAttribute('href', '/destreza/d1');
        expect(screen.getByRole('link', { name: 'CN.4.1.1' }))
            .toHaveAttribute('href', '/destreza/d2');
    });

    /**
     * REGRESIÓN del hallazgo ALTO del bucle C. Inertia guarda su página en
     * window.history.state; pisarlo con null hace que handlePopstateEvent se
     * trague el evento y el botón Atrás deje de funcionar dentro del iframe de
     * Moodle. El arreglo estaba, pero NADA lo defendía: revertirlo dejaba la
     * suite en verde (auditoría). Además la url de la página tiene que viajar
     * dentro del estado, o al volver con Atrás se pierde el q.
     */
    it('la URL sigue al tecleo SIN destruir el estado de Inertia', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respuestaJson(200, [RESULTADO_API])));
        window.history.replaceState(
            { page: { url: '/buscar', component: 'buscar' } }, '', '/buscar',
        );

        const user = userEvent.setup();
        render(<Buscar q="" results={null} />);
        await user.type(screen.getByRole('searchbox'), 'rozamiento');

        expect(window.location.search).toBe('?q=rozamiento');
        expect(window.history.state).not.toBeNull();
        expect(window.history.state.page.component).toBe('buscar');
        expect(window.history.state.page.url).toBe('/buscar?q=rozamiento');
    });

    /**
     * El comentario de PageController::buscar afirmaba que «la página lo
     * explica»; no explicaba nada: el role=status quedaba VACÍO y el alumno
     * veía su texto sin una sola pista (auditoría).
     */
    it('una consulta demasiado larga se explica en vez de enmudecer', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        render(<Buscar q={'ñ'.repeat(200)} results={null} />);

        await waitFor(() =>
            expect(screen.getByRole('status')).toHaveTextContent(/hasta 120 caracteres/i),
        );
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('un 422 del servidor se explica en vez de enmudecer', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respuestaJson(422, { message: 'no' })));

        const user = userEvent.setup();
        render(<Buscar q="" results={null} />);
        await user.type(screen.getByRole('searchbox'), 'rozamiento');

        await waitFor(() =>
            expect(screen.getByRole('status')).toHaveTextContent(/no admite ese texto/i),
        );
    });

    /**
     * El anuncio MENTÍA: al abortar una petición, su `finally` apagaba
     * «Buscando…» y la región viva leía el recuento anterior junto a la
     * consulta nueva — «3 resultados para «rozami»» cuando los 3 eran de
     * «roza» (auditoría). Ahora el recuento va atado a SU consulta.
     */
    it('el anuncio nunca mezcla el recuento viejo con la consulta nueva', async () => {
        let resolver;
        const fetchMock = vi.fn().mockImplementation((_url, opciones) => new Promise((res, rej) => {
            opciones.signal.addEventListener('abort', () => {
                const e = new Error('abortada');
                e.name = 'AbortError';
                rej(e);
            });
            resolver = () => res(respuestaJson(200, [RESULTADO_API]));
        }));
        vi.stubGlobal('fetch', fetchMock);

        const user = userEvent.setup();
        render(<Buscar q="roza" results={[{
            id: 'd9', native_code: 'CN.9.9.9', statement: 'Vieja', is_verified: true,
            has_items: false, node_title: 'Física',
        }]} />);

        await user.type(screen.getByRole('searchbox'), 'mi');

        // Mientras la nueva consulta está en vuelo, el anuncio NO puede
        // atribuirle a «rozami» el recuento que era de «roza».
        await waitFor(() => {
            const anuncio = screen.getByRole('status').textContent;
            expect(anuncio).not.toMatch(/resultados? para «rozami»/);
        });

        // El debounce son 300 ms: hay que esperar a que el fetch exista antes
        // de poder resolverlo.
        await waitFor(() => expect(fetchMock).toHaveBeenCalled());
        resolver();
        await waitFor(() =>
            expect(screen.getByRole('status')).toHaveTextContent(/1 resultado para «rozami»/),
        );
    });

});
