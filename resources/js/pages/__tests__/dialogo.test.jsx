import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Dialogo from '../dialogo';
import { violacionesGraves } from '../../test/helpers';

let auth = { user: { id: 1, name: 'Ana' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth } }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>{children}</a>
    ),
}));

const DIALOGO = {
    id: 'd1', titulo: 'Il primo giorno', objective_code: 'A1.IO.1',
    nodos: [
        {
            id: 'inicio', dice: 'Ciao! Come ti chiami?',
            respuestas: [
                { texto: 'Mi chiamo Ana.', va: 'fin' },
                { texto: 'Bene, grazie.', va: null, pista: 'Te preguntan tu nombre, no cómo estás.' },
            ],
        },
        { id: 'fin', dice: 'A presto!', fin: true },
    ],
};

const PROPS = {
    lengua: 'it', nombre: 'Italiano', unidad: { n: 1, titulo: 'Primer contacto' },
    dialogo: DIALOGO, se_guarda: true,
};

afterEach(() => {
    auth = { user: { id: 1, name: 'Ana' } };
    vi.unstubAllGlobals();
});

describe('dialogo — el interlocutor guionizado', () => {
    it('arranca con la línea del interlocutor y las respuestas', () => {
        render(<Dialogo {...PROPS} />);
        expect(screen.getByText('Ciao! Come ti chiami?')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Mi chiamo Ana.' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Bene, grazie.' })).toBeInTheDocument();
    });

    it('un callejón da una PISTA y se queda: no es un error', async () => {
        const user = userEvent.setup();
        render(<Dialogo {...PROPS} />);

        await user.click(screen.getByRole('button', { name: 'Bene, grazie.' }));
        expect(screen.getByText(/te preguntan tu nombre/i)).toBeInTheDocument();
        // Sigue en el mismo nodo: las respuestas continúan disponibles.
        expect(screen.getByRole('button', { name: 'Mi chiamo Ana.' })).toBeInTheDocument();
    });

    it('la respuesta buena avanza y completar registra el final', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal('fetch', fetchMock);
        const user = userEvent.setup();
        render(<Dialogo {...PROPS} />);

        await user.click(screen.getByRole('button', { name: 'Mi chiamo Ana.' }));

        expect(await screen.findByText(/completaste la conversación/i)).toBeInTheDocument();
        await waitFor(() => expect(fetchMock).toHaveBeenCalledWith(
            '/api/v1/dialogos/d1/completado', expect.objectContaining({ method: 'POST' }),
        ));
    });

    it('el visitante completa y ve que no se guarda', async () => {
        auth = { user: null };
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
        const user = userEvent.setup();
        render(<Dialogo {...PROPS} se_guarda={false} />);

        await user.click(screen.getByRole('button', { name: 'Mi chiamo Ana.' }));
        expect(await screen.findByText(/no se ha guardado/i)).toBeInTheDocument();
    });

    it('sin diálogo firmado lo DICE en vez de quedar en blanco', () => {
        render(<Dialogo {...PROPS} dialogo={null} />);
        expect(screen.getByText(/todavía no está publicado/i)).toBeInTheDocument();
    });

    it('accesibilidad: sin violaciones serias', async () => {
        const { container } = render(<Dialogo {...PROPS} />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
