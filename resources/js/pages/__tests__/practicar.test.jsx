import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Practicar from '../Practicar';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

// Fuera de una app Inertia real: Head y usePage se sustituyen por dobles.
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 1, name: 'Ana Estudiante' } } } }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const OBJETIVO = {
    id: 'obj-rozamiento',
    native_code: 'CN.F.5.1.12',
    statement: 'Determinar el coeficiente de rozamiento entre dos superficies.',
    has_items: true,
};

const ITEM_PROPIO = {
    item_id: 'item-1',
    objective_id: 'obj-rozamiento',
    objective_code: 'CN.F.5.1.12',
    objective_statement: 'Determinar el coeficiente de rozamiento entre dos superficies.',
    attempt_no: 1,
    statement: { es: 'Si μs = 0.5, calcula el ángulo crítico en grados.' },
    params: { mu: 0.5 },
    answer_unit: '°',
    tolerance: 0.5,
    tolerance_kind: 'abs',
    reason: 'práctica normal',
};

const ITEM_DESVIADO = {
    ...ITEM_PROPIO,
    item_id: 'item-prerreq',
    objective_id: 'obj-plano',
    objective_code: 'CN.F.5.1.9',
    objective_statement: 'Explicar el movimiento en el plano inclinado.',
    statement: { es: 'Un bloque de 4 kg reposa sobre un plano de 30°…' },
    reason: 'refuerzo de prerrequisito',
};

/** Mock de fetch por cola: cada llamada consume la siguiente respuesta. */
function encolarFetch(...respuestas) {
    const mock = vi.fn();
    for (const r of respuestas) mock.mockResolvedValueOnce(r);
    vi.stubGlobal('fetch', mock);

    return mock;
}

beforeEach(() => {
    vi.unstubAllGlobals();
    document.cookie = 'XSRF-TOKEN=token-de-prueba';
});

describe('Practicar — el bucle completo como lo vive un alumno', () => {
    it('carga el ítem, responde, ve el feedback y pasa al siguiente', async () => {
        const fetchMock = encolarFetch(
            respuestaJson(200, ITEM_PROPIO),
            respuestaJson(201, { id: 'a1', attempt_no: 1, is_correct: true, expected: 26.565, answer: 26.6 }),
            respuestaJson(200, [{ objective_id: 'obj-rozamiento', mastery: 0.35 }]),
            respuestaJson(200, { ...ITEM_PROPIO, attempt_no: 2, statement: { es: 'Segundo ejercicio: μs = 0.3…' } }),
        );

        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO} mastery={0} />);

        // 1) El enunciado instanciado aparece.
        expect(await screen.findByText(/μs = 0\.5/)).toBeInTheDocument();

        // 2) Responde y envía.
        await user.type(screen.getByLabelText(/tu respuesta/i), '26.6');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        // 3) Feedback con texto, no solo color; el esperado se revela DESPUÉS.
        expect(await screen.findByText('Correcto.')).toBeInTheDocument();
        expect(screen.getByText(/valor esperado/i)).toBeInTheDocument();

        // 4) La barra de dominio se actualizó con el mastery nuevo (35 %).
        await waitFor(() =>
            expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '35'),
        );

        // 5) Siguiente ejercicio.
        await user.click(screen.getByRole('button', { name: /siguiente ejercicio/i }));
        expect(await screen.findByText(/Segundo ejercicio/)).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledTimes(4);
    });

    it('REGRESIÓN PR #10: con un ítem desviado, la cabecera habla del ítem, no de la URL', async () => {
        encolarFetch(respuestaJson(200, ITEM_DESVIADO));

        render(<Practicar objective={OBJETIVO} mastery={0.8} />);
        await screen.findByText(/plano de 30/);

        // El h1 y el enunciado de cabecera son los del ítem devuelto…
        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('CN.F.5.1.9');
        expect(screen.getByText(/movimiento en el plano inclinado/i)).toBeInTheDocument();
        // …no los de la destreza de la URL.
        expect(screen.getByRole('heading', { level: 1 })).not.toHaveTextContent('CN.F.5.1.12');

        // La etiqueta de la barra dice de QUÉ destreza es el dominio.
        expect(screen.getByText(/dominio de CN\.F\.5\.1\.9/i)).toBeInTheDocument();
        // Y el alumno ve POR QUÉ le tocó esto.
        expect(screen.getByText(/repasemos algo anterior/i)).toBeInTheDocument();
    });

    it('no filtra nada sensible al DOM aunque la API lo enviara', async () => {
        // Payload envenenado a propósito: defensa en profundidad del cliente.
        encolarFetch(respuestaJson(200, {
            ...ITEM_PROPIO,
            solution_expr: 'rad2deg(atan(mu))',
            seed: 'abcdef1234567890secreto',
            expected: 26.56505,
        }));

        render(<Practicar objective={OBJETIVO} mastery={0} />);
        await screen.findByText(/μs = 0\.5/);

        const html = document.body.innerHTML;
        expect(html).not.toContain('solution_expr');
        expect(html).not.toContain('rad2deg');
        expect(html).not.toContain('abcdef1234567890secreto');
        expect(html).not.toContain('26.56505');   // expected ANTES de responder: jamás
    });

    it('todo el bucle se completa solo con teclado', async () => {
        encolarFetch(
            respuestaJson(200, ITEM_PROPIO),
            respuestaJson(201, { id: 'a1', attempt_no: 1, is_correct: false, expected: 26.565, answer: 5 }),
            respuestaJson(200, [{ objective_id: 'obj-rozamiento', mastery: 0.1 }]),
            respuestaJson(200, { ...ITEM_PROPIO, attempt_no: 2, statement: { es: 'Otro ejercicio más…' } }),
        );

        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO} mastery={0} />);
        await screen.findByText(/μs = 0\.5/);

        // El foco ya está en el campo (gestión de foco); escribe y Enter envía.
        expect(screen.getByLabelText(/tu respuesta/i)).toHaveFocus();
        await user.keyboard('5{Enter}');

        expect(await screen.findByText('Incorrecto.')).toBeInTheDocument();

        // Tab lleva al botón «Siguiente ejercicio»; Enter lo activa.
        await user.tab();
        expect(screen.getByRole('button', { name: /siguiente ejercicio/i })).toHaveFocus();
        await user.keyboard('{Enter}');

        expect(await screen.findByText(/Otro ejercicio más/)).toBeInTheDocument();
    });
});

describe('Practicar — estados degradados', () => {
    it('401: sesión caducada con enlace a /entrar', async () => {
        encolarFetch(respuestaJson(401, { message: 'Unauthenticated.' }));

        render(<Practicar objective={OBJETIVO} mastery={0} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(/sesión caducó/i);
        expect(screen.getByRole('link', { name: /vuelve a entrar/i })).toHaveAttribute('href', '/entrar');
    });

    it('409 al responder: pide el siguiente ítem sin romperse', async () => {
        const fetchMock = encolarFetch(
            respuestaJson(200, ITEM_PROPIO),
            respuestaJson(409, { message: 'Intento duplicado' }),
            respuestaJson(200, { ...ITEM_PROPIO, attempt_no: 2, statement: { es: 'Ejercicio recuperado tras 409' } }),
        );

        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO} mastery={0} />);
        await screen.findByText(/μs = 0\.5/);

        await user.type(screen.getByLabelText(/tu respuesta/i), '1');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        expect(await screen.findByText(/Ejercicio recuperado tras 409/)).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('5xx: mensaje de error con reintento que funciona', async () => {
        encolarFetch(
            respuestaJson(500, { message: 'boom' }),
            respuestaJson(200, ITEM_PROPIO),
        );

        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO} mastery={0} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(/no pudimos cargar/i);
        await user.click(screen.getByRole('button', { name: /reintentar/i }));

        expect(await screen.findByText(/μs = 0\.5/)).toBeInTheDocument();
    });

    it('destreza sin ítems: estado vacío digno y CERO peticiones', async () => {
        const fetchMock = encolarFetch();

        render(<Practicar objective={{ ...OBJETIVO, has_items: false }} mastery={null} />);

        expect(await screen.findByRole('status')).toHaveTextContent(/todavía no tiene ejercicios/i);
        expect(fetchMock).not.toHaveBeenCalled();
    });
});

describe('Practicar — accesibilidad (axe) en sus estados', () => {
    it.each([
        ['con datos', () => encolarFetch(respuestaJson(200, ITEM_PROPIO)), /μs = 0\.5/],
        ['vacío', () => encolarFetch(), /todavía no tiene ejercicios/i],
        ['error', () => encolarFetch(respuestaJson(500, {})), /no pudimos cargar/i],
        ['sesión caducada', () => encolarFetch(respuestaJson(401, {})), /sesión caducó/i],
    ])('estado %s sin violaciones serias', async (nombre, prepara, esperado) => {
        prepara();
        const props = nombre === 'vacío'
            ? { objective: { ...OBJETIVO, has_items: false }, mastery: null }
            : { objective: OBJETIVO, mastery: 0.5 };

        const { container } = render(<Practicar {...props} />);
        await screen.findByText(esperado);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });

    it('la barra de dominio es un progressbar con nombre accesible', async () => {
        encolarFetch(respuestaJson(200, ITEM_PROPIO));
        render(<Practicar objective={OBJETIVO} mastery={0.5} />);
        await screen.findByText(/μs = 0\.5/);

        // aria-labelledby vivo: el progressbar TIENE nombre («Dominio de…»).
        expect(screen.getByRole('progressbar', { name: /dominio de/i })).toBeInTheDocument();
    });
});
