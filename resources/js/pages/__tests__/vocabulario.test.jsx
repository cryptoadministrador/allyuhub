import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Vocabulario from '../vocabulario';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

let auth = { user: { id: 1, name: 'Ana' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

const TARJETAS = [
    { id: 't-ciao', palabra: 'ciao', lectura: null, significado: 'hola / adiós', ejemplo: { texto: 'Ciao, Marco!', es: '¡Hola, Marco!' }, audio: null, conocida: false, hoy: true },
    { id: 't-grazie', palabra: 'grazie', lectura: null, significado: 'gracias', ejemplo: { texto: 'Grazie mille!', es: '¡Muchas gracias!' }, audio: null, conocida: false, hoy: true },
    { id: 't-prego', palabra: 'prego', lectura: null, significado: 'de nada', ejemplo: { texto: '— Grazie! — Prego!', es: '— ¡Gracias! — ¡De nada!' }, audio: null, conocida: true, hoy: false },
];

const PROPS = { lengua: 'it', nombre: 'Italiano', unidad: { n: 1, titulo: 'Saludos' }, tarjetas: TARJETAS, se_guarda: true };

function conFetch() {
    const mock = vi.fn(() => Promise.resolve(respuestaJson(201, { conocida: true, se_guarda: true })));
    vi.stubGlobal('fetch', mock);

    return mock;
}

afterEach(() => {
    auth = { user: { id: 1, name: 'Ana' } };
    vi.unstubAllGlobals();
});

describe('vocabulario — tarjetas de la unidad', () => {
    it('arranca por el anverso, con la cuenta y solo el mazo de hoy', () => {
        conFetch();
        render(<Vocabulario {...PROPS} />);
        expect(screen.getByRole('status')).toHaveTextContent('Sabes 1 de 3 palabras · Hoy: 2 tarjetas');
        const carta = screen.getByRole('button', { name: /anverso/i });
        expect(carta).toHaveTextContent('ciao');
        expect(carta).toHaveAttribute('aria-pressed', 'false');
        expect(screen.queryByText('hola / adiós')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /la sé/i })).not.toBeInTheDocument();
    });

    it('se da la vuelta con el teclado y enseña significado y ejemplo', async () => {
        conFetch();
        const user = userEvent.setup();
        render(<Vocabulario {...PROPS} />);
        screen.getByRole('button', { name: /anverso/i }).focus();
        await user.keyboard('{Enter}');
        const carta = screen.getByRole('button', { name: /reverso/i });
        expect(carta).toHaveAttribute('aria-pressed', 'true');
        expect(carta).toHaveTextContent('hola / adiós');
        expect(carta).toHaveTextContent('Ciao, Marco!');
        expect(carta).toHaveTextContent('¡Hola, Marco!');
        expect(screen.getByRole('button', { name: /la sé/i })).toBeInTheDocument();
    });

    it('«todavía no» manda la tarjeta al final del mazo; «la sé» la saca hasta mañana', async () => {
        const fetchMock = conFetch();
        const user = userEvent.setup();
        render(<Vocabulario {...PROPS} />);

        await user.click(screen.getByRole('button', { name: /anverso/i }));
        await user.click(screen.getByRole('button', { name: /todavía no/i }));
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ conocida: false });
        expect(fetchMock.mock.calls[0][0]).toBe('/api/v1/vocabulario/t-ciao/estado');
        // Sigue «grazie», y «ciao» vuelve después.
        expect(screen.getByRole('button', { name: /anverso/i })).toHaveTextContent('grazie');
        expect(screen.getByRole('status')).toHaveTextContent('Hoy: 2 tarjetas');

        await user.click(screen.getByRole('button', { name: /anverso/i }));
        await user.click(screen.getByRole('button', { name: /la sé/i }));
        expect(JSON.parse(fetchMock.mock.calls[1][1].body)).toEqual({ conocida: true });
        expect(screen.getByRole('status')).toHaveTextContent('Sabes 2 de 3 palabras · Hoy: 1 tarjeta');
        expect(screen.getByRole('button', { name: /anverso/i })).toHaveTextContent('ciao');

        await user.click(screen.getByRole('button', { name: /anverso/i }));
        await user.click(screen.getByRole('button', { name: /la sé/i }));
        expect(screen.getByText('Por hoy, listo.')).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveTextContent('Sabes 3 de 3 palabras');
        expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('en chino el anverso lleva el carácter grande y el pinyin debajo', () => {
        conFetch();
        const zh = [{ id: 't-nihao', palabra: '你好', lectura: 'nǐ hǎo', significado: 'hola', ejemplo: { texto: '你好，李明！', es: '¡Hola, Li Ming!' }, audio: null, conocida: false, hoy: true }];
        render(<Vocabulario {...PROPS} lengua="zh" nombre="Chino" tarjetas={zh} />);
        const carta = screen.getByRole('button', { name: /anverso/i });
        expect(carta).toHaveTextContent('你好');
        expect(carta).toHaveTextContent('nǐ hǎo');
        expect(carta.querySelector('[lang="zh"]')).toHaveClass('text-6xl');
    });

    it('sin audio no hay reproductor; con audio, uno nativo', () => {
        conFetch();
        const { unmount } = render(<Vocabulario {...PROPS} />);
        expect(document.querySelector('audio')).toBeNull();
        unmount();
        render(<Vocabulario {...PROPS} tarjetas={[{ ...TARJETAS[0], audio: '/audio/abcdef0123456789.mp3' }]} />);
        expect(document.querySelector('audio')).toHaveAttribute('src', '/audio/abcdef0123456789.mp3');
    });

    it('el visitante ve que no se guarda y juega igual', async () => {
        auth = { user: null };
        conFetch();
        const user = userEvent.setup();
        render(<Vocabulario {...PROPS} se_guarda={false} />);
        expect(screen.getByText(/no se guarda/i)).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: /anverso/i }));
        await user.click(screen.getByRole('button', { name: /la sé/i }));
        expect(screen.getByRole('status')).toHaveTextContent('Sabes 2 de 3 palabras');
    });

    it('en revisión no llama a la API ni enlaza a la unidad', async () => {
        const fetchMock = conFetch();
        const user = userEvent.setup();
        render(<Vocabulario {...PROPS} tarjetas={[TARJETAS[0]]} se_guarda={false} revision />);
        expect(screen.queryByRole('link', { name: /unidad 1/i })).not.toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: /anverso/i }));
        await user.click(screen.getByRole('button', { name: /la sé/i }));
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('accesibilidad: sin violaciones serias, por las dos caras', async () => {
        conFetch();
        const user = userEvent.setup();
        const { container } = render(<Vocabulario {...PROPS} />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
        await user.click(screen.getByRole('button', { name: /anverso/i }));
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
