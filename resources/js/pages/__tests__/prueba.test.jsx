import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Prueba from '../prueba';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

let auth = { user: { id: 1, name: 'Ana' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

const SERVIDA = {
    lengua: 'it', unidad: 1, intento: 1, total: 2, se_guarda: true,
    items: [
        {
            item_id: 'i-choice', kind: 'choice', objective_code: 'A1.CO.2',
            objective_statement: 'Puedo comprender saludos.', attempt_no: 1, billete: 'b-1',
            statement: { es: '¿Cómo se dice hola?' },
            options: [{ key: 'a', text: { es: 'ciao' } }, { key: 'b', text: { es: 'grazie' } }],
        },
        {
            item_id: 'i-hueco', kind: 'hueco', objective_code: 'A1.CO.2',
            objective_statement: 'Puedo comprender saludos.', attempt_no: 1, billete: 'b-2',
            statement: { es: 'Completa: Mi ___ Ana.' },
        },
    ],
};

const RESULTADO = {
    nota: 1, total: 2, aprobada: false, intento: 1, se_guarda: true,
    desglose: { 'A1.CO.2': { aciertos: 1, total: 2 } },
    veredictos: [
        { item_id: 'i-choice', is_correct: true, expected_key: 'a' },
        { item_id: 'i-hueco', is_correct: false, esperado: 'chiamo', texto: 'zzz', detalle: 'palabra' },
    ],
};

/** fetch por método: GET sirve la prueba, POST la corrige. */
function fetchDePrueba() {
    const mock = vi.fn((url, opciones = {}) => Promise.resolve(
        opciones.method === 'POST' ? respuestaJson(200, RESULTADO) : respuestaJson(200, SERVIDA),
    ));
    vi.stubGlobal('fetch', mock);

    return mock;
}

const PROPS = {
    lengua: 'it', nombre: 'Italiano', unidad: { n: 1, titulo: 'Primer contacto' },
    historial: [], aprobado: 8, tamano: 10, se_guarda: true,
};

afterEach(() => {
    auth = { user: { id: 1, name: 'Ana' } };
    vi.unstubAllGlobals();
});

describe('prueba — la prueba de unidad', () => {
    it('pinta el primer ejercicio y NO enseña ningún veredicto', async () => {
        fetchDePrueba();
        render(<Prueba {...PROPS} />);

        expect(await screen.findByText('¿Cómo se dice hola?')).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveTextContent(/Ejercicio 1 de 2/);
        expect(screen.queryByText(/correcto/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/la respuesta correcta era/i)).not.toBeInTheDocument();
    });

    it('no avanza sin responder, y lo dice', async () => {
        fetchDePrueba();
        const user = userEvent.setup();
        render(<Prueba {...PROPS} />);
        await screen.findByText('¿Cómo se dice hola?');

        await user.click(screen.getByRole('button', { name: /siguiente/i }));
        expect(screen.getByRole('alert')).toHaveTextContent(/elige una de las opciones/i);
        expect(screen.getByRole('status')).toHaveTextContent(/Ejercicio 1 de 2/);
    });

    it('entrega las dos respuestas con su billete y enseña la nota con el desglose', async () => {
        const fetchMock = fetchDePrueba();
        const user = userEvent.setup();
        render(<Prueba {...PROPS} />);
        await screen.findByText('¿Cómo se dice hola?');

        await user.click(screen.getByRole('radio', { name: 'ciao' }));
        await user.click(screen.getByRole('button', { name: /siguiente/i }));
        expect(screen.getByRole('status')).toHaveTextContent(/Ejercicio 2 de 2/);

        await user.type(screen.getByLabelText(/tu respuesta/i), 'zzz');
        await user.click(screen.getByRole('button', { name: /entregar la prueba/i }));

        await waitFor(() => expect(screen.getByText('1 / 2')).toBeInTheDocument());

        // El POST lleva las dos respuestas, cada una con SU billete tal cual.
        const post = fetchMock.mock.calls.find(([, o]) => o?.method === 'POST');
        const cuerpo = JSON.parse(post[1].body);
        expect(cuerpo.intento).toBe(1);
        expect(cuerpo.respuestas).toEqual([
            { item_id: 'i-choice', billete: 'b-1', answer_key: 'a' },
            { item_id: 'i-hueco', billete: 'b-2', respuesta: { texto: 'zzz' } },
        ]);

        // Desglose por descriptor, y el veredicto ítem a ítem SOLO ahora.
        expect(screen.getByText(/Puedo comprender saludos/)).toBeInTheDocument();
        expect(screen.getByText(/1 de 2/)).toBeInTheDocument();
        expect(screen.getByText(/La respuesta era: «chiamo»/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /repetir la prueba/i })).toBeInTheDocument();
    });

    it('el visitante lo hace entero y ve que no se guarda', async () => {
        auth = { user: null };
        fetchDePrueba();
        render(<Prueba {...PROPS} se_guarda={false} />);
        await screen.findByText('¿Cómo se dice hola?');
        expect(screen.getByText(/no se guarda/i)).toBeInTheDocument();
    });

    it('accesibilidad: sin violaciones serias', async () => {
        fetchDePrueba();
        const { container } = render(<Prueba {...PROPS} />);
        await screen.findByText('¿Cómo se dice hola?');
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
