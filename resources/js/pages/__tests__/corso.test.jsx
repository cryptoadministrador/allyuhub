import { render, screen, within } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Corso from '../corso';
import CorsoUnidad from '../corso-unidad';
import { violacionesGraves } from '../../test/helpers';

let sesion = { user: { id: 1, name: 'Ana' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: sesion } }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const UNIDADES = [
    { n: 1, titulo: 'Primer contacto', resumen: 'Saludar y presentarte.', estado: 'en-curso', dominio: 0.4, url: '/corso/it/u1' },
    { n: 2, titulo: 'Yo y los míos', resumen: 'Tu familia.', estado: 'disponible', dominio: 0, url: '/corso/it/u2' },
    { n: 9, titulo: 'Repaso', resumen: 'Consolidar.', estado: 'proximamente', dominio: 0, url: '/corso/it/u9' },
];

const PORTADA = {
    lengua: 'it',
    nombre: 'Italiano',
    unidades: UNIDADES,
    siguiente: { lengua: 'it', unidad: 1, titulo: 'Primer contacto', url: '/corso/it/u1' },
    racha: { dias: 3, viva: true },
    se_guarda: true,
};

afterEach(() => {
    sesion = { user: { id: 1, name: 'Ana' } };
});

describe('corso — la portada del curso', () => {
    it('pinta cada unidad con su estado y su título', () => {
        render(<Corso {...PORTADA} />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Italiano');
        // «Primer contacto» sale dos veces: en el banner de siguiente y en la
        // lista. Basta con que esté.
        expect(screen.getAllByText(/Primer contacto/).length).toBeGreaterThanOrEqual(1);
        // Los tres estados de las tres unidades del fixture, cada uno en texto.
        expect(screen.getByText('En curso')).toBeInTheDocument();
        expect(screen.getByText('Disponible')).toBeInTheDocument();
        expect(screen.getByText('Próximamente')).toBeInTheDocument();
    });

    it('la ÚNICA cosa que hacer ahora es un enlace destacado y único', () => {
        render(<Corso {...PORTADA} />);

        const siguiente = screen.getByRole('link', { name: /sigue por aquí/i });
        expect(siguiente).toHaveAttribute('href', '/corso/it/u1');
    });

    it('una unidad «próximamente» no es un enlace: no se puede entrar a lo vacío', () => {
        render(<Corso {...PORTADA} />);

        // La unidad 9 (próximamente) no lleva a ningún sitio.
        const prox = screen.getByText(/Unidad 9/).closest('[aria-disabled="true"]');
        expect(prox).not.toBeNull();
        expect(within(prox).queryByRole('link')).toBeNull();
    });

    it('la racha viva se anuncia con sesión', () => {
        render(<Corso {...PORTADA} />);
        // El número va en <strong>, que parte el nodo de texto: se busca el
        // trozo contiguo y el número aparte.
        expect(screen.getByText(/días seguidos practicando/i)).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
    });

    it('sin sesión no se anuncia la racha y avisa de que no se guarda', () => {
        sesion = { user: null };
        render(<Corso {...PORTADA} racha={{ dias: 0, viva: false }} se_guarda={false} />);
        expect(screen.queryByText(/días seguidos/i)).not.toBeInTheDocument();
        expect(screen.getByText(/tu avance no se guarda/i)).toBeInTheDocument();
    });

    it.each([
        ['con sesión', { user: { id: 1, name: 'Ana' } }],
        ['sin sesión', { user: null }],
    ])('accesibilidad %s: sin violaciones serias', async (_n, auth) => {
        sesion = auth;
        const { container } = render(<Corso {...PORTADA} se_guarda={!!auth.user} />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});

const UNIDAD = {
    lengua: 'it',
    nombre: 'Italiano',
    unidad: { n: 1, titulo: 'Primer contacto', resumen: 'Saludar y presentarte.' },
    estado: 'en-curso',
    dominio: 0.4,
    puedo: [
        {
            descriptor_id: 'd1', code: 'A1.CO.2',
            statement: 'Puedo comprender saludos y fórmulas de cortesía.',
            dominio: 0.6, dominado: false, has_items: true,
            url_practicar: '/practicar/d1?lengua=it', has_leccion: true, leccion_url: '/recurso/r1',
        },
        {
            descriptor_id: 'd2', code: 'A1.IO.3',
            statement: 'Puedo presentarme y dar mis datos.',
            dominio: 1, dominado: true, has_items: false,
            url_practicar: null, has_leccion: false, leccion_url: null,
        },
    ],
    siguiente: null,
};

describe('corso-unidad — la unidad', () => {
    it('pinta los «Puedo…» como objetivos, con Aprende y Practica', () => {
        render(<CorsoUnidad {...UNIDAD} />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Primer contacto');
        expect(screen.getByText(/comprender saludos/i)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /aprende/i })).toHaveAttribute('href', '/recurso/r1');
        expect(screen.getByRole('link', { name: /^practica$/i })).toHaveAttribute('href', '/practicar/d1?lengua=it');
    });

    it('un descriptor sin ítems lo DICE en vez de dar un enlace muerto', () => {
        render(<CorsoUnidad {...UNIDAD} />);
        expect(screen.getByText(/ejercicios próximamente/i)).toBeInTheDocument();
    });

    it('una unidad sin contenido lo declara, no se queda en blanco', () => {
        render(<CorsoUnidad {...UNIDAD} puedo={[]} />);
        expect(screen.getByRole('status')).toHaveTextContent(/no tiene contenido publicado/i);
    });

    it('accesibilidad: sin violaciones serias', async () => {
        const { container } = render(<CorsoUnidad {...UNIDAD} />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
