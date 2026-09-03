import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Producir from '../producir';
import { violacionesGraves } from '../../test/helpers';

// Fuera de una app Inertia real: dobles de Head/usePage/Link. `auth` mutable
// para ver la página como alumno y como visitante — la única diferencia.
let auth = { user: { id: 1, name: 'Ana' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth } }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>{children}</a>
    ),
}));

const PRODUCTIVOS = [
    { descriptor_id: 'e1', code: 'A1.EE.2', statement: 'Escribe una nota de presentación.', tipo: 'escritura' },
    { descriptor_id: 'p1', code: 'A1.PO.1', statement: 'Preséntate en voz alta.', tipo: 'voz' },
];

const PROPS = {
    lengua: 'it', nombre: 'Italiano',
    unidad: { n: 2, titulo: 'Yo y los míos' },
    productivos: PRODUCTIVOS, se_guarda: true,
};

afterEach(() => {
    auth = { user: { id: 1, name: 'Ana' } };
    vi.unstubAllGlobals();
});

describe('producir — la tarea de producción', () => {
    it('ofrece la tarea de escritura y la de voz de la unidad', () => {
        render(<Producir {...PROPS} />);
        expect(screen.getByText(/Escribe una nota/i)).toBeInTheDocument();
        expect(screen.getByText(/Preséntate en voz alta/i)).toBeInTheDocument();
    });

    it('la escritura no se puede enviar vacía y sí tras escribir', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal('fetch', fetchMock);
        const user = userEvent.setup();
        render(<Producir {...PROPS} />);

        const enviar = screen.getByRole('button', { name: /^enviar$/i });
        expect(enviar).toBeDisabled();

        await user.type(screen.getByLabelText(/escribe tres o cuatro frases/i),
            'Mi chiamo Ana e sono di Quito.');
        expect(enviar).toBeEnabled();

        await user.click(enviar);
        expect(fetchMock).toHaveBeenCalledWith('/api/v1/producciones', expect.objectContaining({ method: 'POST' }));
        // El cuerpo lleva el tipo escritura y el descriptor.
        const cuerpo = JSON.parse(fetchMock.mock.calls[0][1].body);
        expect(cuerpo).toMatchObject({ tipo: 'escritura', objective_id: 'e1', lengua: 'it' });

        expect(await screen.findByRole('status')).toHaveTextContent(/enviado/i);
    });

    it('el visitante ve el aviso y no puede enviar', () => {
        auth = { user: null };
        render(<Producir {...PROPS} se_guarda={false} />);
        expect(screen.getByText(/entra desde tu aula/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/escribe tres o cuatro frases/i)).toBeDisabled();
    });

    it('sin soporte de grabación, la tarea de voz lo DICE en vez de romperse', () => {
        // jsdom no trae MediaRecorder: el componente degrada con un aviso.
        render(<Producir {...PROPS} />);
        expect(screen.getByText(/no permite grabar/i)).toBeInTheDocument();
    });

    it.each([
        ['con sesión', { user: { id: 1, name: 'Ana' } }],
        ['sin sesión', { user: null }],
    ])('accesibilidad %s: sin violaciones serias', async (_n, a) => {
        auth = a;
        const { container } = render(<Producir {...PROPS} se_guarda={!!a.user} />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
