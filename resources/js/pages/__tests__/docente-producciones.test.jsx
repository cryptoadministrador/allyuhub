import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { describe, expect, it, vi } from 'vitest';
import { violacionesGraves } from '../../test/helpers';

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

// useForm se dobla con estado REAL (useState) para que marcar radios y escribir
// el comentario habilite el botón, que es justo lo que se prueba.
vi.mock('@inertiajs/react', async () => {
    const react = await vi.importActual('react');

    return {
        Head: () => null,
        usePage: () => ({ props: { auth: { user: { id: 9, name: 'Prof' } } } }),
        Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
        useForm: (inicial) => {
            const [data, setData] = react.useState(inicial);

            return {
                data,
                setData: (k, v) => setData((d) => ({ ...d, [k]: v })),
                post: postMock,
                processing: false,
            };
        },
    };
});

import DocenteProducciones from '../docente-producciones';

const RUBRICA = {
    titulo: 'Escritura · A1',
    criterios: [
        { clave: 'tarea', titulo: 'Cumple la tarea', niveles: ['No', 'En parte', 'Del todo'] },
        { clave: 'vocabulario', titulo: 'Vocabulario', niveles: ['Pobre', 'Suficiente', 'Variado'] },
        { clave: 'gramatica', titulo: 'Gramática', niveles: ['Impide', 'No impide', 'Bien'] },
        { clave: 'ortografia', titulo: 'Ortografía', niveles: ['Dificulta', 'Puntual', 'Correcta'] },
    ],
};

const ESCRITA = {
    id: 'x1', tipo: 'escritura', unidad: 2, lengua: 'it', alumno: 'Ana', code: 'A1.EE.2',
    texto: 'Mi chiamo Ana.', audio_url: null, rubrica: RUBRICA, creada: '2026-09-03',
};

describe('docente-producciones — la cola del docente', () => {
    it('muestra la producción con la rúbrica del contenido', () => {
        render(<DocenteProducciones producciones={[ESCRITA]} lengua="it" />);
        expect(screen.getByText(/Ana · A1\.EE\.2/)).toBeInTheDocument();
        expect(screen.getByText('Mi chiamo Ana.')).toBeInTheDocument();
        // Los 4 criterios de la rúbrica, cada uno con sus 3 niveles.
        expect(screen.getByText('Cumple la tarea')).toBeInTheDocument();
        expect(screen.getAllByRole('radio')).toHaveLength(12);
    });

    it('no se puede guardar sin marcar todo y comentar; sí cuando está completa', async () => {
        const user = userEvent.setup();
        render(<DocenteProducciones producciones={[ESCRITA]} lengua="it" />);

        const guardar = screen.getByRole('button', { name: /guardar corrección/i });
        expect(guardar).toBeDisabled();

        // Un radio por criterio (el primero de cada grupo) + comentario.
        for (const criterio of RUBRICA.criterios) {
            const grupo = screen.getByRole('group', { name: criterio.titulo });
            await user.click(grupo.querySelectorAll('input[type="radio"]')[2]);
        }
        await user.type(screen.getByLabelText(/dos frases de devolución/i),
            'Muy bien la presentación. Cuida los artículos.');

        expect(guardar).toBeEnabled();
        await user.click(guardar);
        expect(postMock).toHaveBeenCalledWith('/docente/producciones/x1');
    });

    it('una producción de voz se oye por su ruta con permiso', () => {
        const voz = { ...ESCRITA, id: 'v1', tipo: 'voz', texto: null, audio_url: '/api/v1/producciones/v1/audio' };
        const { container } = render(<DocenteProducciones producciones={[voz]} lengua="it" />);
        const audio = container.querySelector('audio');
        expect(audio).toHaveAttribute('src', '/api/v1/producciones/v1/audio');
    });

    it('sin nada pendiente lo declara, no queda en blanco', () => {
        render(<DocenteProducciones producciones={[]} lengua={null} />);
        expect(screen.getByText(/no hay producciones pendientes/i)).toBeInTheDocument();
    });

    it('accesibilidad: sin violaciones serias', async () => {
        const { container } = render(<DocenteProducciones producciones={[ESCRITA]} lengua="it" />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
