import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useState } from 'react';
import { Ejercicio, Veredicto, reintentoDe, valorInicial } from '../Ejercicio';
import Practicar from '../../pages/Practicar';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

/**
 * PR 13 · EL BUCLE DE «OTRA VEZ» en el cliente: el veredicto trae la pista y
 * el billete del reintento; el mismo ítem se vuelve a intentar con ESE
 * billete (mismo endpoint); al segundo fallo llega el andamiaje del servidor
 * y se pinta como botones acotados; al tercero, la respuesta y «Siguiente».
 */

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 1, name: 'Ana' } } } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

const OBJETIVO = { id: 'obj-fr', native_code: 'A1.CO.2', statement: 'Puedo comprender saludos.', has_items: true };

const HUECO = {
    item_id: 'i-hueco', kind: 'hueco', lengua: 'fr', objective_id: 'obj-fr', objective_code: 'A1.CO.2',
    objective_statement: 'Puedo comprender saludos.', attempt_no: 1, billete: 'b-1',
    statement: { es: 'Completa: « Tu habites ___ ? »' }, reason: 'práctica normal', se_guarda: true,
};
const ORDEN = {
    item_id: 'i-orden', kind: 'orden', statement: { es: 'Ordena.' },
    options: [{ key: 'w2', text: { de: 'gehe' } }, { key: 'w1', text: { de: 'ich' } }, { key: 'w3', text: { de: 'morgen' } }],
};
const PARES = {
    item_id: 'i-pares', kind: 'pares', statement: { es: 'Empareja.' },
    options: [
        { key: 'x1', col: 'a', text: { fr: 'un' } }, { key: 'x2', col: 'a', text: { fr: 'deux' } },
        { key: 'y1', col: 'b', text: { es: 'uno' } }, { key: 'y2', col: 'b', text: { es: 'dos' } },
    ],
};
const CHOICE = {
    item_id: 'i-choice', kind: 'choice', statement: { es: 'Elige.' },
    options: [{ key: 'a', text: { es: 'una' } }, { key: 'b', text: { es: 'otra' } }, { key: 'c', text: { es: 'tercera' } }],
};

function encolarFetch(...respuestas) {
    const mock = vi.fn();
    for (const r of respuestas) mock.mockImplementationOnce(() => Promise.resolve(r));
    mock.mockImplementation(() => Promise.resolve(respuestaJson(200, [])));
    vi.stubGlobal('fetch', mock);

    return mock;
}

function Sujeto({ item }) {
    const [valor, setValor] = useState(() => reintentoDe(item, { otra_vez: { billete: 'x', attempt_no: 2, reintento: 1 }, andamiaje: item.andamiaje })?.valor ?? valorInicial());

    return <form><Ejercicio item={item} valor={valor} onChange={setValor} nombre="t" /></form>;
}

afterEach(() => vi.unstubAllGlobals());

describe('reintentoDe — qué arranca en la vuelta siguiente', () => {
    it('sin otra_vez no hay vuelta', () => {
        expect(reintentoDe(HUECO, { is_correct: true })).toBeNull();
        expect(reintentoDe(HUECO, { is_correct: false, reintento: 2 })).toBeNull();
    });

    it('el ítem lleva el billete y el número del reintento; el error de acento conserva el texto', () => {
        const r = reintentoDe(HUECO, { is_correct: false, detalle: 'acento', texto: 'ou', otra_vez: { billete: 'b-2', attempt_no: 2, reintento: 1 } });
        expect(r.item).toMatchObject({ item_id: 'i-hueco', billete: 'b-2', attempt_no: 2, reintento: 1, andamiaje: null });
        expect(r.valor.respuesta).toBe('ou');

        const palabra = reintentoDe(HUECO, { is_correct: false, detalle: 'palabra', texto: 'zzz', otra_vez: { billete: 'b-2', attempt_no: 2, reintento: 1 } });
        expect(palabra.valor.respuesta).toBe('');
    });

    it('con andamiaje: el hueco no conserva texto, el orden arranca con la primera, los pares con las correctas', () => {
        const h = reintentoDe(HUECO, { detalle: 'acento', texto: 'ou', otra_vez: { billete: 'b-3', attempt_no: 3, reintento: 2 }, andamiaje: { opciones: ['où', 'ou', 'uo'] } });
        expect(h.valor.respuesta).toBe('');
        expect(h.item.andamiaje.opciones).toEqual(['où', 'ou', 'uo']);

        const o = reintentoDe(ORDEN, { otra_vez: { billete: 'b', attempt_no: 3, reintento: 2 }, andamiaje: { primero: 'w1' } });
        expect(o.valor.secuencia).toEqual(['w1']);

        const p = reintentoDe(PARES, { otra_vez: { billete: 'b', attempt_no: 3, reintento: 2 }, andamiaje: { correctas: [['x1', 'y1']] } });
        expect(p.valor.parejas).toEqual([['x1', 'y1']]);
    });
});

describe('Veredicto — la pista sin la solución mientras quede vuelta', () => {
    const OTRA = { billete: 'b', attempt_no: 2, reintento: 1 };

    it.each([
        ['hueco, acento', HUECO, { detalle: 'acento', texto: 'ou' }, /revisa el acento.*«ou»/],
        ['hueco, tono en chino', { ...HUECO, lengua: 'zh' }, { detalle: 'acento', texto: 'ni' }, /te falta el tono/],
        ['hueco, palabra', HUECO, { detalle: 'palabra', texto: 'zzz' }, /esa palabra no es/i],
        ['orden', ORDEN, {}, /no es ese orden/i],
        ['pares', PARES, { parejas_correctas: 1, total: 2 }, /tienes 1 de 2 parejas/i],
        ['choice', CHOICE, {}, /esa no es/i],
        ['numérico', { kind: 'numeric', statement: { es: 'x' } }, {}, /no es ese valor/i],
    ])('%s', (_, item, extra, esperado) => {
        render(<Veredicto item={item} resultado={{ is_correct: false, otra_vez: OTRA, ...extra }} />);
        expect(screen.getByText('Todavía no.')).toBeInTheDocument();
        expect(screen.getByText(esperado)).toBeInTheDocument();
        expect(document.body.textContent).not.toMatch(/undefined|era:/);
    });
});

describe('Ejercicio — el andamiaje se pinta como botones acotados', () => {
    it('hueco: tres opciones en vez de texto libre, y el hueco de la frase queda como raya', async () => {
        const user = userEvent.setup();
        render(<Sujeto item={{ ...HUECO, andamiaje: { opciones: ['où', 'ou', 'uo'] } }} />);
        expect(screen.queryByRole('textbox')).toBeNull();
        const radios = screen.getAllByRole('radio');
        expect(radios.map((r) => r.value)).toEqual(['où', 'ou', 'uo']);
        await user.click(screen.getByRole('radio', { name: 'où' }));
        expect(screen.getByRole('radio', { name: 'où' })).toBeChecked();
    });

    it('orden: la primera va fija («va primero»), no se toca ni se mueve por delante', async () => {
        const user = userEvent.setup();
        render(<Sujeto item={{ ...ORDEN, andamiaje: { primero: 'w1' } }} />);
        const frase = screen.getByRole('group', { name: /tu frase/i });
        expect(within(frase).getByLabelText('ich, va primero')).toBeInTheDocument();
        expect(within(frase).queryByRole('button', { name: 'ich' })).toBeNull();
        expect(within(screen.getByRole('group', { name: /palabras disponibles/i })).queryByRole('button', { name: 'ich' })).toBeNull();

        await user.click(screen.getByRole('button', { name: 'gehe' }));
        within(frase).getByRole('button', { name: 'gehe' }).focus();
        await user.keyboard('{ArrowLeft}');
        // «ich» sigue delante: la fija es el prefijo.
        expect(within(frase).getAllByText(/ich|gehe/).map((e) => e.textContent)).toEqual(['ich', 'gehe']);
    });

    it('orden: ni arrastrando se pasa por delante de la fija', async () => {
        const user = userEvent.setup();
        vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function () {
            if (this.getAttribute?.('aria-label') === 'Tu frase') return { left: 0, top: 0, right: 400, bottom: 60, width: 400, height: 60 };
            const linea = this.closest?.('[aria-label="Tu frase"]');
            if (linea && (this.tagName === 'BUTTON' || this.tagName === 'SPAN')) {
                const i = Array.from(linea.querySelectorAll('button, span[aria-label]')).indexOf(this);

                return { left: i * 100 + 10, top: 15, right: i * 100 + 90, bottom: 45, width: 80, height: 30 };
            }

            return { left: 0, top: 200, right: 80, bottom: 230, width: 80, height: 30 };
        });
        if (typeof window.PointerEvent === 'undefined') {
            window.PointerEvent = class extends MouseEvent {
                constructor(tipo, init = {}) { super(tipo, init); this.pointerId = init.pointerId ?? 1; this.pointerType = init.pointerType ?? 'mouse'; }
            };
        }
        render(<Sujeto item={{ ...ORDEN, andamiaje: { primero: 'w1' } }} />);
        await user.click(screen.getByRole('button', { name: 'gehe' }));
        await user.click(screen.getByRole('button', { name: 'morgen' }));
        const frase = screen.getByRole('group', { name: /tu frase/i });
        const morgen = within(frase).getByRole('button', { name: 'morgen' });
        // Se suelta a la izquierda del todo (x=5): cae DETRÁS de la fija, nunca delante.
        const { fireEvent } = await import('@testing-library/react');
        fireEvent.pointerDown(morgen, { clientX: 250, clientY: 30, button: 0, pointerId: 1 });
        fireEvent.pointerMove(morgen, { clientX: 5, clientY: 30, pointerId: 1 });
        fireEvent.pointerUp(morgen, { clientX: 5, clientY: 30, pointerId: 1 });
        expect(within(frase).getAllByText(/ich|gehe|morgen/).map((e) => e.textContent)).toEqual(['ich', 'morgen', 'gehe']);
        vi.restoreAllMocks();
    });

    it('pares: las correctas quedan formadas y no se deshacen; las demás se juegan', async () => {
        const user = userEvent.setup();
        render(<Sujeto item={{ ...PARES, andamiaje: { correctas: [['x1', 'y1']] } }} />);
        const fijas = screen.getAllByRole('button', { name: /pareja 1: un — uno\. correcta/i });
        expect(fijas).toHaveLength(2);
        expect(fijas.every((b) => b.disabled)).toBe(true);
        await user.click(screen.getByRole('button', { name: 'deux' }));
        await user.click(screen.getByRole('button', { name: 'dos' }));
        expect(screen.getAllByRole('button', { name: /pareja 2: deux — dos\. deshacer/i })).toHaveLength(2);
    });

    it('choice: la descartada queda inhabilitada y lo dice', () => {
        render(<Sujeto item={{ ...CHOICE, andamiaje: { descartar: ['c'] } }} />);
        expect(screen.getByRole('radio', { name: /tercera.*no es esta/i })).toBeDisabled();
        expect(screen.getByRole('radio', { name: 'una' })).toBeEnabled();
    });

    it('accesibilidad con andamiaje: sin violaciones serias', async () => {
        for (const item of [
            { ...HUECO, andamiaje: { opciones: ['où', 'ou', 'uo'] } },
            { ...ORDEN, andamiaje: { primero: 'w1' } },
            { ...PARES, andamiaje: { correctas: [['x1', 'y1']] } },
            { ...CHOICE, andamiaje: { descartar: ['c'] } },
        ]) {
            const { container, unmount } = render(<Sujeto item={item} />);
            expect(violacionesGraves(await axe(container))).toEqual([]);
            unmount();
        }
    });
});

describe('Practicar — el bucle entero: fallo, otra vez, andamiaje, tercera', () => {
    it('reintenta el MISMO ítem con el billete del veredicto, sin pedir next; a la tercera, siguiente', async () => {
        const user = userEvent.setup();
        const fetchMock = encolarFetch(
            respuestaJson(200, HUECO),
            respuestaJson(201, { attempt_no: 1, is_correct: false, detalle: 'acento', texto: 'ou', reintento: 0, se_guarda: true,
                otra_vez: { billete: 'b-2', attempt_no: 2, reintento: 1 } }),
            respuestaJson(200, []),   // mastery tras cada intento
            respuestaJson(201, { attempt_no: 2, is_correct: false, detalle: 'palabra', texto: 'ou', reintento: 1, se_guarda: true,
                otra_vez: { billete: 'b-3', attempt_no: 3, reintento: 2 }, andamiaje: { opciones: ['où', 'ou', 'uo'] } }),
            respuestaJson(200, []),
            respuestaJson(201, { attempt_no: 3, is_correct: true, esperado: 'où', texto: 'où', reintento: 2, se_guarda: true }),
            respuestaJson(200, []),
        );
        render(<Practicar objective={OBJETIVO} mastery={null} />);
        await screen.findByText(/tu habites/i);

        // 1.º: texto libre, falla por acento.
        await user.type(screen.getByRole('textbox', { name: /tu respuesta/i }), 'ou');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        expect(await screen.findByText('Todavía no.')).toBeInTheDocument();
        expect(screen.getByText(/revisa el acento/i)).toBeInTheDocument();
        expect(screen.queryByText(/siguiente ejercicio/i)).toBeNull();

        // «Otra vez»: el mismo ítem, el texto conservado, y NADIE pidió next.
        await user.click(screen.getByRole('button', { name: /otra vez \(2\.º intento de 3\)/i }));
        expect(screen.getByRole('textbox', { name: /tu respuesta/i })).toHaveValue('ou');
        expect(fetchMock.mock.calls.filter(([u]) => u.includes('/next'))).toHaveLength(1);

        // 2.º: falla → el POST fue con el billete b-2; llega el andamiaje.
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        expect(await screen.findByText(/esa palabra no es/i)).toBeInTheDocument();
        const posts = () => fetchMock.mock.calls.filter(([, o]) => o?.method === 'POST');
        expect(JSON.parse(posts()[1][1].body).billete).toBe('b-2');

        await user.click(screen.getByRole('button', { name: /otra vez \(3\.º intento de 3\)/i }));
        expect(screen.queryByRole('textbox')).toBeNull();
        await user.click(screen.getByRole('radio', { name: 'où' }));
        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        // 3.º: acierta con el billete b-3 y `respuesta.texto`, y ya hay «Siguiente».
        expect(await screen.findByText('Correcto.')).toBeInTheDocument();
        const tercero = JSON.parse(posts()[2][1].body);
        expect(tercero).toEqual({ respuesta: { texto: 'où' }, time_ms: expect.any(Number), billete: 'b-3' });
        expect(screen.getByRole('button', { name: /siguiente ejercicio/i })).toBeInTheDocument();
        // Tres POST al MISMO endpoint del ítem, y un solo next en todo el bucle.
        expect(posts().map(([u]) => u)).toEqual(Array(3).fill('/api/v1/practice/items/i-hueco/attempts'));
        expect(fetchMock.mock.calls.filter(([u]) => u.includes('/next'))).toHaveLength(1);
    });

    it('al tercer fallo enseña la respuesta y ofrece siguiente, no otra vez', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, HUECO),
            respuestaJson(201, { attempt_no: 3, is_correct: false, detalle: 'palabra', texto: 'zzz', esperado: 'où', reintento: 2, se_guarda: true }),
            respuestaJson(200, []),
        );
        render(<Practicar objective={OBJETIVO} mastery={null} />);
        await screen.findByText(/tu habites/i);
        await user.type(screen.getByRole('textbox', { name: /tu respuesta/i }), 'zzz');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        expect(await screen.findByText('Incorrecto.')).toBeInTheDocument();
        expect(screen.getByText(/la respuesta era: «où»/i)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /otra vez/i })).toBeNull();
        expect(screen.getByRole('button', { name: /siguiente ejercicio/i })).toBeInTheDocument();
    });
});
