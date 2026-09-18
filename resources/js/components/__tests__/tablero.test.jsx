import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { readFileSync } from 'node:fs';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useState } from 'react';
import { Ejercicio, claseDeVeredicto, valorInicial } from '../Ejercicio';
import { dentroDe, indiceDeCaida, partirHueco } from '../Tablero';
import { violacionesGraves } from '../../test/helpers';

/**
 * EL ÁREA COMPARTIDA (PR 12): los tableros de orden y pares, y el hueco dentro
 * de la frase. El oráculo que más importa: TODO se juega con teclado, y
 * arrastrar se añade encima sin sustituir al clic.
 */

const ORDEN = {
    item_id: 'i-orden', kind: 'orden', statement: { es: 'Ordena la frase.' },
    options: [
        { key: 'w4', text: { de: 'zur Schule' } },
        { key: 'w2', text: { de: 'gehe' } },
        { key: 'w3', text: { de: 'ich' } },
        { key: 'w1', text: { de: 'morgen' } },
    ],
};

const PARES = {
    item_id: 'i-pares', kind: 'pares', statement: { es: 'Empareja carácter, pinyin y significado.' },
    options: [
        { key: 'c1', col: 'a', text: { zh: '你' } },
        { key: 'c2', col: 'a', text: { zh: '好' } },
        { key: 'p2', col: 'b', text: { zh: 'hao3' } },
        { key: 'p1', col: 'b', text: { zh: 'ni3' } },
        { key: 's1', col: 'c', text: { es: 'tu / usted' } },
        { key: 's2', col: 'c', text: { es: 'bien / bueno' } },
    ],
};

const HUECO = { item_id: 'i-hueco', kind: 'hueco', lengua: 'it', statement: { es: 'Completa: « Lei ___ la professoressa Rossi. »  (Ella ES la profesora Rossi.)' } };

/** El ejercicio con su estado, como lo tiene cualquier bucle. */
function Sujeto({ item, onValor = () => {} }) {
    const [valor, setValor] = useState(valorInicial());

    return (
        <form>
            <Ejercicio item={item} valor={valor} onChange={(v) => { setValor(v); onValor(v); }} nombre="t" />
        </form>
    );
}

const fraseDe = () => within(screen.getByRole('group', { name: /tu frase/i })).queryAllByRole('button').map((b) => b.textContent);

afterEach(() => vi.restoreAllMocks());

describe('indiceDeCaida — dónde cae una ficha (puro, sin layout)', () => {
    const fila = (n, top = 0) => Array.from({ length: n }, (_, i) => ({ left: i * 100, right: i * 100 + 90, width: 90, top, bottom: top + 30 }));

    it('sin fichas, al principio; por encima, al principio; por debajo, al final', () => {
        expect(indiceDeCaida([], 50, 10)).toBe(0);
        expect(indiceDeCaida(fila(3, 100), 50, 10)).toBe(0);
        expect(indiceDeCaida(fila(3, 100), 50, 300)).toBe(3);
    });

    it('en la fila, cae ANTES de la primera ficha cuyo centro queda a la derecha', () => {
        const r = fila(3);
        expect(indiceDeCaida(r, 10, 15)).toBe(0);      // antes de la primera
        expect(indiceDeCaida(r, 60, 15)).toBe(1);      // pasado el centro de la primera (45)
        expect(indiceDeCaida(r, 130, 15)).toBe(1);     // antes del centro de la segunda (145)
        expect(indiceDeCaida(r, 290, 15)).toBe(3);     // más allá de todas
    });

    it('con dos filas, la fila la decide la Y', () => {
        const r = [...fila(2, 0), ...fila(2, 40)];
        expect(indiceDeCaida(r, 10, 50)).toBe(2);
        expect(indiceDeCaida(r, 500, 50)).toBe(4);
    });

    it('dentroDe admite un margen alrededor', () => {
        const rect = { left: 100, right: 200, top: 100, bottom: 150 };
        expect(dentroDe(rect, 95, 120)).toBe(true);
        expect(dentroDe(rect, 80, 120)).toBe(false);
    });
});

describe('partirHueco', () => {
    it('parte por el primer «___» y devuelve los dos trozos', () => {
        expect(partirHueco('Completa: « Lei ___ la prof. »')).toEqual(['Completa: « Lei ', ' la prof. »']);
        expect(partirHueco('Nazionalità: ______')).toEqual(['Nazionalità: ', '']);
        expect(partirHueco('Sin hueco')).toBeNull();
        expect(partirHueco(undefined)).toBeNull();
    });
});

describe('orden — la frase se construye arriba, con teclado', () => {
    it('tocar coloca al final; las flechas mueven la ficha dentro de la frase y el foco la sigue', async () => {
        const user = userEvent.setup();
        render(<Sujeto item={ORDEN} />);
        const banco = screen.getByRole('group', { name: /palabras disponibles/i });

        await user.click(within(banco).getByRole('button', { name: 'gehe' }));
        await user.click(within(banco).getByRole('button', { name: 'ich' }));
        await user.click(within(banco).getByRole('button', { name: 'morgen' }));
        expect(fraseDe()).toEqual(['gehe', 'ich', 'morgen']);

        const frase = screen.getByRole('group', { name: /tu frase/i });
        within(frase).getByRole('button', { name: 'ich' }).focus();
        await user.keyboard('{ArrowLeft}');
        expect(fraseDe()).toEqual(['ich', 'gehe', 'morgen']);
        // jsdom conserva el foco al mover un nodo; un navegador real lo pierde
        // y el tablero lo devuelve a la ficha — aquí solo se ve que no se va.
        expect(document.activeElement).toHaveTextContent('ich');

        await user.keyboard('{ArrowLeft}');   // ya es la primera: no pasa nada
        expect(fraseDe()).toEqual(['ich', 'gehe', 'morgen']);

        await user.keyboard('{ArrowRight}{ArrowRight}');
        expect(fraseDe()).toEqual(['gehe', 'morgen', 'ich']);

        // Enter la devuelve al banco (el camino accesible de siempre).
        await user.keyboard('{Enter}');
        expect(fraseDe()).toEqual(['gehe', 'morgen']);
        expect(within(banco).getByRole('button', { name: 'ich' })).toBeInTheDocument();
    });

    it('la ayuda de teclado va enlazada a cada ficha de la frase', async () => {
        const user = userEvent.setup();
        render(<Sujeto item={ORDEN} />);
        await user.click(screen.getByRole('button', { name: 'ich' }));
        const ficha = within(screen.getByRole('group', { name: /tu frase/i })).getByRole('button', { name: 'ich' });
        expect(ficha).toHaveAccessibleDescription(/flechas izquierda y derecha/i);
    });
});

/**
 * ARRASTRAR, ENCIMA DEL CLIC. jsdom no mide nada, así que la geometría se
 * simula: la línea ocupa (0,0)-(400,60); las fichas de la línea van a
 * 100 px cada una; el banco vive por debajo (y > 100).
 */
function simularGeometria() {
    vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function () {
        if (this.getAttribute?.('aria-label') === 'Tu frase') {
            return { left: 0, top: 0, right: 400, bottom: 60, width: 400, height: 60 };
        }
        const enLinea = this.closest?.('[aria-label="Tu frase"]');
        if (enLinea && this.tagName === 'BUTTON') {
            const i = Array.from(enLinea.querySelectorAll('button')).indexOf(this);

            return { left: i * 100 + 10, top: 15, right: i * 100 + 90, bottom: 45, width: 80, height: 30 };
        }

        return { left: 0, top: 200, right: 80, bottom: 230, width: 80, height: 30 };
    });
}

if (typeof window.PointerEvent === 'undefined') {
    // jsdom no trae PointerEvent: con MouseEvent basta para clientX/clientY.
    window.PointerEvent = class extends MouseEvent {
        constructor(tipo, init = {}) {
            super(tipo, init);
            this.pointerId = init.pointerId ?? 1;
            this.pointerType = init.pointerType ?? 'mouse';
        }
    };
}

const arrastrar = (el, desde, hasta) => {
    fireEvent.pointerDown(el, { clientX: desde.x, clientY: desde.y, button: 0, pointerId: 1 });
    fireEvent.pointerMove(el, { clientX: hasta.x, clientY: hasta.y, pointerId: 1 });
    fireEvent.pointerUp(el, { clientX: hasta.x, clientY: hasta.y, pointerId: 1 });
    fireEvent.click(el);   // el clic que el navegador dispara tras soltar
};

describe('orden — arrastrar se añade encima', () => {
    it('una ficha del banco soltada en la frase cae en su sitio, y el clic de después no la devuelve', async () => {
        const user = userEvent.setup();
        simularGeometria();
        render(<Sujeto item={ORDEN} />);
        const banco = screen.getByRole('group', { name: /palabras disponibles/i });
        await user.click(within(banco).getByRole('button', { name: 'gehe' }));
        await user.click(within(banco).getByRole('button', { name: 'morgen' }));
        expect(fraseDe()).toEqual(['gehe', 'morgen']);

        // «ich» cae entre las dos (x=130: pasado el centro de la 1.ª, antes del de la 2.ª).
        arrastrar(within(banco).getByRole('button', { name: 'ich' }), { x: 40, y: 215 }, { x: 130, y: 30 });
        expect(fraseDe()).toEqual(['gehe', 'ich', 'morgen']);
    });

    it('un movimiento corto NO es arrastre: es el clic de siempre', async () => {
        simularGeometria();
        render(<Sujeto item={ORDEN} />);
        const banco = screen.getByRole('group', { name: /palabras disponibles/i });
        arrastrar(within(banco).getByRole('button', { name: 'ich' }), { x: 40, y: 215 }, { x: 42, y: 216 });
        expect(fraseDe()).toEqual(['ich']);
    });

    it('una ficha de la frase soltada fuera vuelve al banco; dentro, se reordena', async () => {
        const user = userEvent.setup();
        simularGeometria();
        render(<Sujeto item={ORDEN} />);
        const banco = screen.getByRole('group', { name: /palabras disponibles/i });
        for (const p of ['gehe', 'ich', 'morgen']) await user.click(within(banco).getByRole('button', { name: p }));

        const frase = screen.getByRole('group', { name: /tu frase/i });
        arrastrar(within(frase).getByRole('button', { name: 'morgen' }), { x: 250, y: 30 }, { x: 20, y: 30 });
        expect(fraseDe()).toEqual(['morgen', 'gehe', 'ich']);

        arrastrar(within(frase).getByRole('button', { name: 'gehe' }), { x: 150, y: 30 }, { x: 150, y: 300 });
        expect(fraseDe()).toEqual(['morgen', 'ich']);
        expect(within(banco).getByRole('button', { name: 'gehe' })).toBeInTheDocument();
    });
});

describe('pares — la unión se ve y se deshace tocándola', () => {
    it('cada pareja formada lleva su número en las tres columnas; Enter también une', async () => {
        const user = userEvent.setup();
        render(<Sujeto item={PARES} />);

        screen.getByRole('button', { name: '你' }).focus();
        await user.keyboard('{Enter}');
        expect(screen.getByRole('button', { name: '你' })).toHaveAttribute('aria-pressed', 'true');
        await user.click(screen.getByRole('button', { name: 'ni3' }));
        await user.click(screen.getByRole('button', { name: 'tu / usted' }));

        const unidas = screen.getAllByRole('button', { name: /^pareja 1: 你 — ni3 — tu \/ usted\. deshacer$/i });
        expect(unidas).toHaveLength(3);
        // Una por columna, cada una con su «1» a la vista.
        expect(unidas.every((b) => b.textContent.startsWith('1'))).toBe(true);

        await user.click(screen.getByRole('button', { name: '好' }));
        await user.click(screen.getByRole('button', { name: 'hao3' }));
        await user.click(screen.getByRole('button', { name: 'bien / bueno' }));
        expect(screen.getAllByRole('button', { name: /^pareja 2:/i })).toHaveLength(3);

        // Deshacer la primera con el teclado: sus tres vuelven a estar sueltos.
        unidas[1].focus();
        await user.keyboard('{Enter}');
        expect(screen.getByRole('button', { name: '你' })).toHaveAttribute('aria-pressed', 'false');
        expect(screen.getByRole('button', { name: 'ni3' })).toHaveAttribute('aria-pressed', 'false');
        // La otra sigue unida (y pasa a ser la primera: se numera por orden).
        expect(screen.getAllByRole('button', { name: /^pareja 1: 好 — hao3 — bien \/ bueno/i })).toHaveLength(3);
        expect(screen.queryByRole('button', { name: /^pareja 2:/i })).toBeNull();
    });
});

describe('hueco — el campo vive dentro de la frase', () => {
    it('la consigna se parte por «___» y el campo ocupa ese sitio, con su nombre accesible', () => {
        render(<Sujeto item={HUECO} />);
        const campo = screen.getByRole('textbox', { name: /tu respuesta/i });
        const frase = campo.closest('p');
        expect(frase).toHaveTextContent(/Completa: « Lei\s+la professoressa Rossi\. »/);
        expect(frase.textContent).not.toContain('___');
        // Y no hay un segundo campo debajo.
        expect(screen.getAllByRole('textbox')).toHaveLength(1);
    });

    it('sin «___» el campo va debajo, con su etiqueta visible', () => {
        render(<Sujeto item={{ ...HUECO, statement: { es: 'Nazionalità de Sofía, que es de Quito.' } }} />);
        const campo = screen.getByRole('textbox', { name: /tu respuesta/i });
        expect(campo.closest('p')).toBeNull();
        expect(screen.getByText('Tu respuesta')).toBeInTheDocument();
    });
});

describe('microanimación con freno', () => {
    it('el marco del veredicto solo se anima tras motion-safe:', () => {
        expect(claseDeVeredicto(true)).toContain('motion-safe:animate-pulso');
        expect(claseDeVeredicto(false)).toContain('motion-safe:animate-sacudida');
        for (const clase of [claseDeVeredicto(true), claseDeVeredicto(false)]) {
            expect(clase.split(' ').filter((c) => c.includes('animate-')).every((c) => c.startsWith('motion-safe:'))).toBe(true);
        }
    });

    it('las dos animaciones existen en el CSS y son cortas', () => {
        const css = readFileSync('resources/css/app.css', 'utf8');
        expect(css).toMatch(/--animate-pulso:\s*pulso\s+\d{3}ms/);
        expect(css).toMatch(/--animate-sacudida:\s*sacudida\s+\d{3}ms/);
        expect(css).toMatch(/@keyframes pulso\s*\{/);
        expect(css).toMatch(/@keyframes sacudida\s*\{/);
    });
});

describe('accesibilidad de los tres tableros', () => {
    it.each([['orden', ORDEN], ['pares', PARES], ['hueco', HUECO]])('%s: sin violaciones serias, antes y después de tocar', async (_, item) => {
        const user = userEvent.setup();
        const { container } = render(<Sujeto item={item} />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
        const primero = screen.queryAllByRole('button')[0];
        if (primero) await user.click(primero);
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
