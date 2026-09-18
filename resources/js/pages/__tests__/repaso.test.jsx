import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Repaso from '../repaso';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

let auth = { user: { id: 1, name: 'Ana' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

const DIARIO = {
    lengua: 'it', fecha: '2026-09-11', total: 2, se_guarda: true,
    racha: { dias: 2, viva: true, activo_hoy: false },
    items: [
        {
            item_id: 'i-1', kind: 'hueco', prioridad: 1, repaso: true, billete: 'b-1',
            objective_statement: 'Puedo comprender saludos.', statement: { es: 'Completa: Mi ___ Ana.' },
        },
        {
            item_id: 'i-2', kind: 'choice', prioridad: 3, repaso: false, billete: 'b-2',
            objective_statement: 'Puedo decir la hora.', statement: { es: '¿Qué hora es?' },
            options: [{ key: 'a', text: { es: 'le tre' } }, { key: 'b', text: { es: 'ciao' } }],
        },
    ],
};

function fetchDeRepaso() {
    const mock = vi.fn((url, opciones = {}) => {
        if (opciones.method === 'POST') return Promise.resolve(respuestaJson(201, { is_correct: true, esperado: 'chiamo', se_guarda: true }));
        if (url.includes('/racha')) return Promise.resolve(respuestaJson(200, { dias: 3, viva: true, activo_hoy: true }));

        return Promise.resolve(respuestaJson(200, DIARIO));
    });
    vi.stubGlobal('fetch', mock);

    return mock;
}

const PROPS = { lengua: 'it', nombre: 'Italiano', racha: { dias: 2, viva: true }, se_guarda: true };

afterEach(() => {
    auth = { user: { id: 1, name: 'Ana' } };
    vi.unstubAllGlobals();
});

describe('repaso — tu repaso de hoy', () => {
    it('arranca con el primer ítem y dice de qué prioridad es', async () => {
        fetchDeRepaso();
        render(<Repaso {...PROPS} />);
        expect(await screen.findByText(/Completa: Mi/)).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveTextContent(/1 de 2 · Repaso/);
        expect(screen.getByText(/Racha: 2 días/)).toBeInTheDocument();
    });

    it('se juega como la práctica: comprueba, ve el veredicto, sigue, y al final la racha del servidor', async () => {
        const fetchMock = fetchDeRepaso();
        const user = userEvent.setup();
        render(<Repaso {...PROPS} />);
        await screen.findByText(/Completa: Mi/);

        await user.type(screen.getByLabelText(/tu respuesta/i), 'chiamo');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        expect(await screen.findByText(/Correcto\./)).toBeInTheDocument();

        // El POST va al endpoint de intentos de siempre, con el billete tal cual.
        const post = fetchMock.mock.calls.find(([, o]) => o?.method === 'POST');
        expect(post[0]).toBe('/api/v1/practice/items/i-1/attempts');
        expect(JSON.parse(post[1].body)).toEqual({ respuesta: { texto: 'chiamo' }, billete: 'b-1' });

        await user.click(screen.getByRole('button', { name: /siguiente/i }));
        expect(await screen.findByText('¿Qué hora es?')).toBeInTheDocument();
        await user.click(screen.getByRole('radio', { name: 'le tre' }));
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await user.click(await screen.findByRole('button', { name: /terminar el repaso/i }));

        expect(await screen.findByText(/Repaso hecho: 2\/2 · 2 seguidos al final/)).toBeInTheDocument();
        // La racha final es la que dice el SERVIDOR (3), no la inicial (2).
        expect(screen.getAllByText(/Racha: 3 días/).length).toBeGreaterThan(0);
        expect(screen.queryByText(/Racha: 2 días/)).not.toBeInTheDocument();
    });

    /** El bucle de «otra vez» (PR 13) también aquí — y el acierto a la segunda no suma en «Repaso hecho». */
    it('otra vez en el repaso: el mismo ítem con su billete, y solo el primer intento suma', async () => {
        const mock = vi.fn((url, opciones = {}) => {
            if (opciones.method === 'POST') {
                const cuerpo = JSON.parse(opciones.body);
                return Promise.resolve(cuerpo.billete === 'b-1'
                    ? respuestaJson(201, { is_correct: false, detalle: 'palabra', texto: 'x', reintento: 0, se_guarda: true, otra_vez: { billete: 'b-1-bis', attempt_no: 2, reintento: 1 } })
                    : respuestaJson(201, { is_correct: true, esperado: 'chiamo', reintento: cuerpo.billete === 'b-1-bis' ? 1 : 0, se_guarda: true }));
            }
            if (url.includes('/racha')) return Promise.resolve(respuestaJson(200, { dias: 3, viva: true, activo_hoy: true }));

            return Promise.resolve(respuestaJson(200, DIARIO));
        });
        vi.stubGlobal('fetch', mock);
        const user = userEvent.setup();
        render(<Repaso {...PROPS} />);
        await screen.findByText(/Completa: Mi/);

        await user.type(screen.getByLabelText(/tu respuesta/i), 'x');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        expect(await screen.findByText('Todavía no.')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: /otra vez/i }));
        expect(screen.getByText(/1 de 2/)).toBeInTheDocument();   // el MISMO ítem
        await user.type(screen.getByLabelText(/tu respuesta/i), 'chiamo');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        expect(await screen.findByText(/Correcto\./)).toBeInTheDocument();
        expect(JSON.parse(mock.mock.calls.filter(([, o]) => o?.method === 'POST')[1][1].body).billete).toBe('b-1-bis');

        await user.click(screen.getByRole('button', { name: /siguiente/i }));
        await user.click(await screen.findByRole('radio', { name: 'le tre' }));
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await user.click(await screen.findByRole('button', { name: /terminar el repaso/i }));
        // El primero se acertó a la segunda: no suma. El segundo, a la primera: suma.
        expect(await screen.findByText(/Repaso hecho: 1\/2 · 1 seguido al final/)).toBeInTheDocument();
    });

    it('el visitante repasa sin racha y ve que no se guarda', async () => {
        auth = { user: null };
        fetchDeRepaso();
        render(<Repaso {...PROPS} racha={{ dias: 0, viva: false }} se_guarda={false} />);
        await screen.findByText(/Completa: Mi/);
        expect(screen.getByText(/no se guarda/i)).toBeInTheDocument();
        expect(screen.queryByText(/Racha:/)).not.toBeInTheDocument();
    });

    it('el contrarreloj está apagado por defecto y el progreso siempre a la vista', async () => {
        fetchDeRepaso();
        render(<Repaso {...PROPS} />);
        await screen.findByText(/Completa: Mi/);
        expect(screen.getByRole('switch', { name: /contrarreloj/i })).not.toBeChecked();
        expect(screen.queryByRole('timer')).toBeNull();
        expect(screen.getAllByRole('status').some((e) => /1 de 2 · Repaso/.test(e.textContent))).toBe(true);
    });

    it('sin nada que repasar lo dice', async () => {
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(respuestaJson(200, { ...DIARIO, total: 0, items: [] }))));
        render(<Repaso {...PROPS} />);
        expect(await screen.findByText(/no hay nada que repasar/i)).toHaveAttribute('role', 'status');
    });

    it('accesibilidad: sin violaciones serias', async () => {
        fetchDeRepaso();
        const { container } = render(<Repaso {...PROPS} />);
        await screen.findByText(/Completa: Mi/);
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
