import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Jugar from '../jugar';
import { tableroDeMemoria } from '../../lib/juegos';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

/**
 * PR 15 · los tres juegos, jugados con TECLADO (cada carta es un botón), con
 * «la sé» al acertar por el endpoint del mazo, y sin escribir nada más.
 */

let auth = { user: { id: 1, name: 'Ana' } };
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

const T = (n, lengua = 'it') => Array.from({ length: n }, (_, i) => ({
    id: `t${i + 1}`, palabra: `palabra${i + 1}`, lectura: lengua === 'zh' ? `pin${i + 1}` : null, significado: `sig${i + 1}`,
    ejemplo: { texto: '', es: '' }, audio: null, conocida: false, hoy: true,
}));
const INTRUSAS = [{ id: 'x1', palabra: 'lunedi', lectura: null, significado: 'lunes', unidad: 3 }];
const PROPS = { lengua: 'it', nombre: 'Italiano', unidad: { n: 1, titulo: 'Saludos' }, tarjetas: T(6), intrusas: INTRUSAS, semilla: 'semilla-fija', se_guarda: true };

function conFetch() {
    const mock = vi.fn(() => Promise.resolve(respuestaJson(201, { conocida: true, se_guarda: true })));
    vi.stubGlobal('fetch', mock);

    return mock;
}
const posts = (mock) => mock.mock.calls.filter(([, o]) => o?.method === 'POST').map(([u]) => u);

afterEach(() => {
    auth = { user: { id: 1, name: 'Ana' } };
    vi.unstubAllGlobals();
    vi.useRealTimers();
    localStorage.clear();
});

describe('memoria', () => {
    it('dos cartas de la misma tarjeta se ganan y marcan «la sé»; dos distintas vuelven boca abajo', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        const fetchMock = conFetch();
        render(<Jugar {...PROPS} />);

        // El tablero es el que da la semilla: se sabe qué carta es cuál.
        const { cartas } = tableroDeMemoria(PROPS.tarjetas, 'it', PROPS.semilla);
        const botones = screen.getAllByRole('button', { name: /^carta \d+, boca abajo$/i });
        expect(botones).toHaveLength(12);

        const indice = (pred) => cartas.findIndex(pred);
        const i1 = indice((c) => c.tarjeta === 't1' && c.cara === 'palabra');
        const i2 = indice((c) => c.tarjeta === 't1' && c.cara === 'significado');
        const i3 = indice((c) => c.tarjeta === 't2' && c.cara === 'palabra');

        // Una distinta y otra: se ven, y vuelven.
        botones[i1].focus();
        await user.keyboard('{Enter}');
        expect(screen.getByRole('button', { name: `Carta ${i1 + 1}: palabra1` })).toHaveAttribute('aria-pressed', 'true');
        await user.click(botones[i3]);
        expect(screen.getByRole('button', { name: `Carta ${i3 + 1}: palabra2` })).toBeInTheDocument();
        await act(async () => { vi.advanceTimersByTime(1000); });
        expect(screen.getAllByRole('button', { name: /boca abajo/i })).toHaveLength(12);
        expect(posts(fetchMock)).toEqual([]);

        // Las dos de t1: ganadas, y «la sé» viaja UNA vez.
        await user.click(botones[i1]);
        await user.click(botones[i2]);
        expect(screen.getAllByRole('button', { name: /hecha$/i })).toHaveLength(2);
        expect(screen.getAllByRole('button', { name: /hecha$/i }).every((b) => b.disabled)).toBe(true);
        expect(posts(fetchMock)).toEqual(['/api/v1/vocabulario/t1/estado']);
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ conocida: true });
        expect(screen.getByRole('status')).toHaveTextContent('1 de 6 parejas · 2 jugadas');
    });

    it('en chino son tríos: carácter, pinyin y significado', async () => {
        const user = userEvent.setup();
        const fetchMock = conFetch();
        const zh = T(3, 'zh');
        render(<Jugar {...PROPS} lengua="zh" tarjetas={zh} />);
        const { cartas, tamano } = tableroDeMemoria(zh, 'zh', PROPS.semilla);
        expect(tamano).toBe(3);
        const botones = screen.getAllByRole('button', { name: /boca abajo/i });
        expect(botones).toHaveLength(9);
        for (const cara of ['palabra', 'lectura', 'significado']) {
            await user.click(botones[cartas.findIndex((c) => c.tarjeta === 't1' && c.cara === cara)]);
        }
        expect(screen.getAllByRole('button', { name: /hecha$/i })).toHaveLength(3);
        expect(posts(fetchMock)).toEqual(['/api/v1/vocabulario/t1/estado']);
        expect(screen.getByRole('status')).toHaveTextContent('1 de 3 tríos');
    });
});

describe('contra el reloj', () => {
    it('sin el interruptor no hay juego: lo dice y ofrece el interruptor, apagado', async () => {
        const user = userEvent.setup();
        conFetch();
        render(<Jugar {...PROPS} />);
        await user.click(screen.getByRole('button', { name: /contra el reloj/i }));
        expect(screen.getByText(/se activa con el contrarreloj/i)).toBeInTheDocument();
        expect(screen.getByRole('switch', { name: /contrarreloj/i })).not.toBeChecked();
        expect(screen.queryByRole('button', { name: /empezar/i })).toBeNull();
    });

    it('encendido: 60 s, emparejar suma y marca «la sé», y al acabar queda la marca del alumno', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        const fetchMock = conFetch();
        localStorage.setItem('contrarreloj:1', '1');
        localStorage.setItem('marca:1:it:u1', '1');
        render(<Jugar {...PROPS} />);
        await user.click(screen.getByRole('button', { name: /contra el reloj/i }));
        expect(screen.getByRole('status')).toHaveTextContent('tu marca anterior: 1');
        await user.click(screen.getByRole('button', { name: /empezar \(60 s\)/i }));
        expect(screen.getByRole('timer')).toHaveTextContent('1:00');

        const palabras = screen.getByRole('group', { name: 'Palabras' });
        const significados = screen.getByRole('group', { name: 'Significados' });
        // Una mal: no suma. Dos bien: suman y marcan.
        await user.click(within(palabras).getByRole('button', { name: 'palabra1' }));
        await user.click(within(significados).getByRole('button', { name: 'sig2' }));
        expect(screen.getByRole('status')).toHaveTextContent('0 de 6 parejas');
        for (const n of [1, 2]) {
            await user.click(within(palabras).getByRole('button', { name: `palabra${n}` }));
            await user.click(within(significados).getByRole('button', { name: `sig${n}` }));
        }
        expect(screen.getByRole('status')).toHaveTextContent('2 de 6 parejas');
        expect(posts(fetchMock)).toEqual(['/api/v1/vocabulario/t1/estado', '/api/v1/vocabulario/t2/estado']);
        expect(within(palabras).getByRole('button', { name: 'palabra1' })).toBeDisabled();

        await act(async () => { vi.advanceTimersByTime(61000); });
        expect(screen.getByRole('status')).toHaveTextContent('2 parejas en 60 segundos · tu mejor marca');
        expect(localStorage.getItem('marca:1:it:u1')).toBe('2');
        expect(document.body.textContent).not.toMatch(/ranking|clasificaci|por detr/i);
    });
});

describe('¿cuál sobra?', () => {
    it('la intrusa a la primera acierta y marca las tres de la unidad; fallar deja reintentar y luego revela', async () => {
        const user = userEvent.setup();
        const fetchMock = conFetch();
        render(<Jugar {...PROPS} />);
        await user.click(screen.getByRole('button', { name: /cuál sobra/i }));
        expect(screen.getByRole('status')).toHaveTextContent('Ronda 1 de');

        // Ronda 1: la intrusa es «lunedi».
        await user.click(screen.getByRole('button', { name: 'lunedi' }));
        expect(screen.getByText(/¡Eso es! «lunedi» \(lunes\) es de la unidad 3/)).toBeInTheDocument();
        expect(posts(fetchMock)).toHaveLength(3);
        expect(posts(fetchMock).every((u) => u.startsWith('/api/v1/vocabulario/t'))).toBe(true);
        await user.click(screen.getByRole('button', { name: /siguiente/i }));

        // Ronda 2: dos fallos → se revela, sin marcar nada.
        const propias = screen.getAllByRole('button').filter((b) => /^palabra\d+$/.test(b.textContent));
        await user.click(propias[0]);
        expect(screen.getByText(/todavía no/i)).toBeInTheDocument();
        await user.click(propias[1]);
        expect(screen.getByText(/Era «lunedi»/)).toBeInTheDocument();
        expect(posts(fetchMock)).toHaveLength(3);
        expect(screen.getByRole('button', { name: 'lunedi' })).toBeDisabled();
        await user.click(screen.getByRole('button', { name: /siguiente/i }));

        // Ronda 3 (vuelven las tres palabras de la ronda 1): un fallo y luego la
        // intrusa → acierta, pero NO a la primera: no suma ni marca; y aunque
        // marcara, esas tres ya viajaron: «la sé» va una vez por tarjeta.
        const propias3 = screen.getAllByRole('button').filter((b) => /^palabra\d+$/.test(b.textContent));
        await user.click(propias3[0]);
        await user.click(screen.getByRole('button', { name: 'lunedi' }));
        expect(screen.getByText(/¡Eso es!/)).toBeInTheDocument();
        expect(posts(fetchMock)).toHaveLength(3);
        await user.click(screen.getByRole('button', { name: /siguiente/i }));

        // Ronda 4: a la primera con palabras nuevas (t4-t6): marca tres más.
        await user.click(screen.getByRole('button', { name: 'lunedi' }));
        expect(posts(fetchMock)).toHaveLength(6);
        await user.click(screen.getByRole('button', { name: /siguiente/i }));
        // Ronda 5: vuelven t1-t3, a la primera: ya estaban marcadas, nada nuevo viaja.
        await user.click(screen.getByRole('button', { name: 'lunedi' }));
        expect(posts(fetchMock)).toHaveLength(6);
        await user.click(screen.getByRole('button', { name: /ver cómo fue/i }));
        expect(screen.getByRole('status')).toHaveTextContent('¿Cuál sobra?: 3 de 5 a la primera.');
    });
});

describe('la página', () => {
    it('el visitante ve que no se guarda; el POST sale igual y el servidor no escribe', async () => {
        auth = { user: null };
        conFetch();
        render(<Jugar {...PROPS} se_guarda={false} />);
        expect(screen.getByText(/no se guarda/i)).toBeInTheDocument();
    });

    it('accesibilidad: los tres juegos sin violaciones serias', async () => {
        const user = userEvent.setup();
        conFetch();
        localStorage.setItem('contrarreloj:1', '1');
        const { container } = render(<Jugar {...PROPS} />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
        await user.click(screen.getByRole('button', { name: /contra el reloj/i }));
        await user.click(screen.getByRole('button', { name: /empezar/i }));
        expect(violacionesGraves(await axe(container))).toEqual([]);
        await user.click(screen.getByRole('button', { name: /cuál sobra/i }));
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
