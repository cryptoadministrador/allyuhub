import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { describe, expect, it, vi } from 'vitest';
import { violacionesGraves } from '../../test/helpers';

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

// useForm con estado REAL: escribir la nota es lo que habilita el botón, y eso
// es justo lo que se prueba («nada se devuelve ni se retira sin nota»).
vi.mock('@inertiajs/react', async () => {
    const react = await vi.importActual('react');

    return {
        Head: () => null,
        usePage: () => ({ props: { auth: { user: { id: 9, name: 'Prof. Rossi' } } } }),
        Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
        router: { post: postMock },
        useForm: (inicial) => {
            const [data, setData] = react.useState(inicial);

            return {
                data,
                setData: (k, v) => setData((d) => ({ ...d, [k]: v })),
                post: postMock,
                processing: false,
                errors: {},
                reset: () => setData(inicial),
            };
        },
    };
});

import DocenteRevisarPieza from '../docente-revisar-pieza';

const LECCION = {
    pieza: { tipo: 'leccion', id: 'v1', firmada: false, lengua: 'it', titulo: 'Saludos', code: 'A1.CO.2' },
    notas: [],
    recurso: {
        id: 'r1', slug: 'saludos', kind: 'reading', title: 'Saludos', summary: 'Hola',
        duration_min: 5, bloques: [{ tipo: 'parrafo', texto: { es: 'Ciao significa hola.' } }], bundle_url: null,
    },
    destrezas: [{ id: 'd1', native_code: 'A1.CO.2', statement: 'Puedo comprender saludos.' }],
    objective: null,
};

describe('docente-revisar-pieza — la pieza tal como la ve el alumno', () => {
    it('pinta la lección con la MISMA página del alumno', () => {
        render(<DocenteRevisarPieza {...LECCION} />);
        // El contenido real de la lección, no una maqueta de revisión.
        expect(screen.getByText(/Ciao significa hola/)).toBeInTheDocument();
    });

    it('dice si la pieza se ve o no, y ofrece firmar', async () => {
        const user = userEvent.setup();
        render(<DocenteRevisarPieza {...LECCION} />);

        expect(screen.getByText(/sin firmar/i)).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: /^firmar$/i }));
        expect(postMock).toHaveBeenCalledWith('/docente/revisar/leccion/v1/firmar', expect.anything());
    });

    it('devolver NO se puede sin escribir la nota', async () => {
        const user = userEvent.setup();
        render(<DocenteRevisarPieza {...LECCION} />);

        await user.click(screen.getByRole('button', { name: /devolver con nota/i }));
        const confirmar = screen.getByRole('button', { name: /^devolver$/i });
        expect(confirmar).toBeDisabled();

        await user.type(screen.getByLabelText(/qué hay que corregir/i), 'El ejemplo 3 está mal.');
        expect(confirmar).toBeEnabled();
    });

    it('una pieza firmada ofrece RETIRAR la firma, y tampoco sin nota', async () => {
        const user = userEvent.setup();
        render(<DocenteRevisarPieza {...LECCION} pieza={{ ...LECCION.pieza, firmada: true }} />);

        expect(screen.getByText(/firmada · se ve/i)).toBeInTheDocument();
        // El primero es el que ABRE el formulario; tras pulsarlo aparece el
        // segundo, el que confirma — y ese nace deshabilitado sin nota.
        await user.click(screen.getAllByRole('button', { name: /retirar firma/i })[0]);
        expect(screen.getByLabelText(/por qué se retira/i)).toBeInTheDocument();
        const confirmar = screen.getAllByRole('button', { name: /retirar firma/i })[1];
        expect(confirmar).toBeDisabled();

        await user.type(screen.getByLabelText(/por qué se retira/i), 'Errata en el enunciado.');
        expect(confirmar).toBeEnabled();
    });

    it('el historial de revisión se ve cuando lo hay', () => {
        render(<DocenteRevisarPieza {...LECCION} notas={[
            { accion: 'devolver', nota: 'Falta un ejemplo.', docente: 'Prof. Rossi', cuando: '2026-09-04' },
        ]} />);
        expect(screen.getByText(/Falta un ejemplo/)).toBeInTheDocument();
    });

    it('accesibilidad: sin violaciones serias', async () => {
        const { container } = render(<DocenteRevisarPieza {...LECCION} />);
        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
