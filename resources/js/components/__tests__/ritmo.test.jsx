import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CierreDeTanda, Cronometro, InterruptorContrarreloj, Progreso } from '../Ritmo';
import Practicar from '../../pages/Practicar';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

/**
 * PR 14 · EL RITMO DE LA SESIÓN: «4 de 10» siempre a la vista, «3 seguidos»
 * dentro de la tanda, el cierre que dice cómo fue, y el contrarreloj OPCIONAL
 * (apagado por defecto, recordado por alumno). Sin ranking, sin fuego.
 */

let auth = { user: { id: 1, name: 'Ana' } };
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

const OBJETIVO = { id: 'obj', native_code: 'A1.CO.2', statement: 'Puedo comprender saludos.', has_items: true };
const item = (n) => ({
    item_id: `i-${n}`, kind: 'numeric', objective_id: 'obj', objective_code: 'A1.CO.2', objective_statement: 'Puedo comprender saludos.',
    attempt_no: 1, billete: `b-${n}`, statement: { es: `Ejercicio ${n}: calcula 2 + 3` }, params: {}, reason: 'práctica normal', se_guarda: true,
});
const veredicto = (ok) => (ok
    ? { attempt_no: 1, is_correct: true, expected: 5, answer: 5, reintento: 0, se_guarda: true }
    : { attempt_no: 1, is_correct: false, answer: 9, reintento: 2, se_guarda: true });   // tercer fallo: cierra el ítem

/** Una tanda entera de respuestas: por cada ítem, next + attempt + mastery. */
function encolarTanda(aciertos) {
    const mock = vi.fn();
    aciertos.forEach((ok, i) => {
        mock.mockImplementationOnce(() => Promise.resolve(respuestaJson(200, item(i + 1))));
        mock.mockImplementationOnce(() => Promise.resolve(respuestaJson(201, veredicto(ok))));
        mock.mockImplementationOnce(() => Promise.resolve(respuestaJson(200, [])));
    });
    mock.mockImplementation(() => Promise.resolve(respuestaJson(200, item(99))));
    vi.stubGlobal('fetch', mock);

    return mock;
}

afterEach(() => {
    auth = { user: { id: 1, name: 'Ana' } };
    vi.unstubAllGlobals();
    vi.useRealTimers();
    localStorage.clear();
});

describe('las piezas', () => {
    it('Progreso dice dónde vas y los seguidos solo desde dos', () => {
        const { rerender } = render(<Progreso k={4} total={10} seguidos={1} />);
        expect(screen.getByRole('status')).toHaveTextContent('Ejercicio 4 de 10');
        expect(screen.getByRole('status')).not.toHaveTextContent(/seguido/);
        rerender(<Progreso k={5} total={10} seguidos={3} />);
        expect(screen.getByRole('status')).toHaveTextContent('Ejercicio 5 de 10 · 3 seguidos');
    });

    it('CierreDeTanda dice cómo fue, del alumno consigo mismo', () => {
        render(<CierreDeTanda ritmo={{ respondidos: 10, aciertos: 8, seguidos: 2, mejor: 5 }} total={10} />);
        expect(screen.getByText('Tanda hecha: 8 de 10 · 2 seguidos al final · mejor racha: 5 seguidos')).toBeInTheDocument();
        expect(document.body.textContent).not.toMatch(/ranking|clasificaci|por detr|🔥|🎉/i);
    });

    it('el interruptor es un switch con nombre', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(<InterruptorContrarreloj activo={false} onChange={onChange} />);
        const sw = screen.getByRole('switch', { name: /contrarreloj/i });
        expect(sw).not.toBeChecked();
        await user.click(sw);
        expect(onChange).toHaveBeenCalledWith(true);
    });

    it('el cronómetro cuenta hacia atrás y avisa UNA vez al llegar a cero', () => {
        vi.useFakeTimers();
        const onAgotado = vi.fn();
        render(<Cronometro segundos={3} clave="a" onAgotado={onAgotado} />);
        expect(screen.getByRole('timer')).toHaveTextContent('0:03');
        act(() => { vi.advanceTimersByTime(1100); });
        expect(screen.getByRole('timer')).toHaveTextContent('0:02');
        act(() => { vi.advanceTimersByTime(2500); });
        expect(screen.getByRole('timer')).toHaveTextContent('0:00');
        act(() => { vi.advanceTimersByTime(2000); });
        expect(onAgotado).toHaveBeenCalledTimes(1);
    });
});

describe('Practicar — la tanda de diez', () => {
    it('«Ejercicio N de 10» siempre a la vista, los seguidos se rompen al fallar, y a los diez se cierra con cómo fue', async () => {
        const user = userEvent.setup();
        const aciertos = [true, true, true, false, true, true, false, true, true, true];
        encolarTanda(aciertos);
        render(<Practicar objective={OBJETIVO} mastery={0} />);

        for (let n = 1; n <= 10; n++) {
            await screen.findByText(`Ejercicio ${n}: calcula 2 + 3`);
            const status = screen.getAllByRole('status').find((e) => /Ejercicio \d+ de 10/.test(e.textContent));
            expect(status).toHaveTextContent(`Ejercicio ${n} de 10`);
            if (n === 4) expect(status).toHaveTextContent('3 seguidos');
            if (n === 5) expect(status).not.toHaveTextContent(/seguidos/);
            await user.type(screen.getByRole('spinbutton', { name: /tu respuesta/i }), '5');
            await user.click(screen.getByRole('button', { name: /comprobar/i }));
            await screen.findByText(aciertos[n - 1] ? 'Correcto.' : 'Incorrecto.');
            await user.click(screen.getByRole('button', { name: n < 10 ? /siguiente ejercicio/i : /ver cómo fue/i }));
        }

        expect(await screen.findByText('Tanda hecha: 8 de 10 · 3 seguidos al final')).toBeInTheDocument();
        // «Otra tanda» vuelve a empezar de cero.
        await user.click(screen.getByRole('button', { name: /otra tanda/i }));
        await screen.findByText(/Ejercicio 99/);
        expect(screen.getAllByRole('status').find((e) => /de 10/.test(e.textContent))).toHaveTextContent('Ejercicio 1 de 10');
    });

    it('un fallo rompe los seguidos EN EL ACTO aunque quede «otra vez», y el acierto a la segunda no suma', async () => {
        auth = { user: null };   // el invitado ve «Aciertos en esta visita: X de Y», que sale del ritmo
        const user = userEvent.setup();
        let n = 0;
        const respuestas = [
            () => respuestaJson(200, { attempt_no: 1, is_correct: true, expected: 5, answer: 5, reintento: 0, se_guarda: false }),
            () => respuestaJson(200, { attempt_no: 1, is_correct: true, expected: 5, answer: 5, reintento: 0, se_guarda: false }),
            () => respuestaJson(200, { attempt_no: 1, is_correct: false, answer: 9, reintento: 0, se_guarda: false, otra_vez: { billete: 'b-x', attempt_no: 2, reintento: 1 } }),
            () => respuestaJson(200, { attempt_no: 2, is_correct: true, expected: 5, answer: 5, reintento: 1, se_guarda: false }),
        ];
        let p = 0;
        vi.stubGlobal('fetch', vi.fn((url, opciones = {}) => {
            if (opciones.method === 'POST') return Promise.resolve(respuestas[p++]());
            if (url.includes('/next')) return Promise.resolve(respuestaJson(200, item(++n)));

            return Promise.resolve(respuestaJson(200, []));
        }));
        render(<Practicar objective={OBJETIVO} mastery={null} />);

        const status = () => screen.getAllByRole('status').find((e) => /de 10/.test(e.textContent));
        for (const k of [1, 2]) {
            await screen.findByText(`Ejercicio ${k}: calcula 2 + 3`);
            await user.type(screen.getByRole('spinbutton', { name: /tu respuesta/i }), '5');
            await user.click(screen.getByRole('button', { name: /comprobar/i }));
            await user.click(await screen.findByRole('button', { name: /siguiente ejercicio/i }));
        }
        await screen.findByText('Ejercicio 3: calcula 2 + 3');
        expect(status()).toHaveTextContent('Ejercicio 3 de 10 · 2 seguidos');

        // Falla (con vuelta): los seguidos se rompen ya, con el ejercicio abierto.
        await user.type(screen.getByRole('spinbutton', { name: /tu respuesta/i }), '9');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await user.click(await screen.findByRole('button', { name: /otra vez/i }));
        expect(status()).toHaveTextContent('Ejercicio 3 de 10');
        expect(status()).not.toHaveTextContent(/seguidos/);

        // Acierta a la segunda: cierra el ejercicio, pero no suma.
        await user.type(screen.getByRole('spinbutton', { name: /tu respuesta/i }), '5');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await user.click(await screen.findByRole('button', { name: /siguiente ejercicio/i }));
        await screen.findByText('Ejercicio 4: calcula 2 + 3');
        expect(status()).toHaveTextContent('Ejercicio 4 de 10');
        expect(status()).not.toHaveTextContent(/seguidos/);
        expect(screen.getByText('2 de 3')).toBeInTheDocument();   // aciertos en esta visita: el de la vuelta no cuenta
    });

    it('el contrarreloj empieza APAGADO; encendido, hay reloj y al agotarse cuenta como fallo sin mandar nada', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        // Por URL, no en cola: aquí no hay POST (el tiempo se agota) y una cola
        // se desfasaría.
        let n = 0;
        const fetchMock = vi.fn((url, opciones = {}) => {
            if (opciones.method === 'POST') return Promise.resolve(respuestaJson(201, veredicto(true)));
            if (url.includes('/next')) return Promise.resolve(respuestaJson(200, item(++n)));

            return Promise.resolve(respuestaJson(200, []));
        });
        vi.stubGlobal('fetch', fetchMock);
        render(<Practicar objective={OBJETIVO} mastery={0} />);
        await screen.findByText(/Ejercicio 1:/);
        expect(screen.queryByRole('timer')).toBeNull();
        expect(screen.getByRole('switch', { name: /contrarreloj/i })).not.toBeChecked();

        await user.click(screen.getByRole('switch', { name: /contrarreloj/i }));
        expect(screen.getByRole('timer')).toHaveTextContent('0:20');
        expect(localStorage.getItem('contrarreloj:1')).toBe('1');

        const posts = () => fetchMock.mock.calls.filter(([, o]) => o?.method === 'POST').length;
        await act(async () => { vi.advanceTimersByTime(21000); });
        expect(await screen.findByText('Se acabó el tiempo.')).toBeInTheDocument();
        expect(posts()).toBe(0);   // no se mandó ningún intento

        await user.click(screen.getByRole('button', { name: /siguiente ejercicio/i }));
        await screen.findByText(/Ejercicio 2:/);
        expect(screen.getAllByRole('status').find((e) => /de 10/.test(e.textContent))).toHaveTextContent('Ejercicio 2 de 10');
    });

    it('el interruptor se recuerda por alumno: otro alumno lo ve apagado', async () => {
        localStorage.setItem('contrarreloj:1', '1');
        auth = { user: { id: 2, name: 'Beto' } };
        encolarTanda([true]);
        render(<Practicar objective={OBJETIVO} mastery={0} />);
        await screen.findByText(/Ejercicio 1:/);
        expect(screen.getByRole('switch', { name: /contrarreloj/i })).not.toBeChecked();
        expect(screen.queryByRole('timer')).toBeNull();
    });

    it('accesibilidad con progreso, interruptor y reloj', async () => {
        localStorage.setItem('contrarreloj:1', '1');
        encolarTanda([true]);
        const { container } = render(<Practicar objective={OBJETIVO} mastery={0} />);
        await screen.findByText(/Ejercicio 1:/);
        expect(screen.getByRole('timer')).toBeInTheDocument();
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
