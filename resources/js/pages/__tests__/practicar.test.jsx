import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Practicar from '../Practicar';
import { respuestaJson, violacionesGraves } from '../../test/helpers';

// Fuera de una app Inertia real: Head y usePage se sustituyen por dobles.
// `auth` es mutable para poder practicar la misma página como alumno y como
// visitante: es la ÚNICA diferencia entre los dos, y eso es lo que se prueba.
let auth = { user: { id: 1, name: 'Ana Estudiante' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth } }),
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
    auth = { user: { id: 1, name: 'Ana Estudiante' } };
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

    /**
     * FRENTE 3: el bloque de resultado se vio ANTES como una franja fina que
     * se perdía debajo del formulario. Ahora es una tarjeta con marca lateral,
     * icono grande y veredicto en palabras — y las palabras son el oráculo:
     * si alguien vuelve a dejar solo el color, esto cae.
     */
    it('el veredicto se lee, no se adivina por el color', async () => {
        encolarFetch(
            respuestaJson(200, ITEM_PROPIO),
            respuestaJson(201, { id: 'a1', attempt_no: 1, is_correct: false, expected: 26.565, answer: 3 }),
            respuestaJson(200, []),
        );

        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO} mastery={0} />);
        await screen.findByText(/μs = 0\.5/);

        await user.type(screen.getByLabelText(/tu respuesta/i), '3');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        const veredicto = await screen.findByText('Incorrecto.');
        const tarjeta = veredicto.closest('[tabindex="-1"]');

        // El símbolo acompaña, pero no habla: es decorativo.
        expect(within(tarjeta).getByText('✗')).toHaveAttribute('aria-hidden', 'true');
        // Y el dato duro sigue ahí, con su unidad.
        expect(within(tarjeta).getByText(/tu respuesta: 3\./i)).toBeInTheDocument();
        expect(tarjeta.textContent).toContain('26.565');
    });

    it('el desvío adaptativo se explica en su propio bloque, atado al formulario', async () => {
        encolarFetch(respuestaJson(200, ITEM_DESVIADO));

        render(<Practicar objective={OBJETIVO} mastery={0.8} />);
        const aviso = await screen.findByText(/repasemos algo anterior/i);

        // El formulario lo declara como su descripción: un lector de pantalla
        // oye POR QUÉ le cambiaron el ejercicio antes de teclear la respuesta.
        const bloque = aviso.closest('#razon-adaptativa');
        expect(bloque).not.toBeNull();
        expect(document.querySelector('form')).toHaveAttribute(
            'aria-describedby',
            'razon-adaptativa',
        );
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
    /**
     * EL ESCENARIO REAL de caducidad, y el que antes no tenía test.
     *
     * Desde que la práctica es abierta, los cuatro endpoints NO devuelven 401
     * nunca: a un alumno con la sesión muerta se le atiende como visitante. El
     * cliente no puede darse cuenta por su prop `auth` —se renderizó cuando la
     * sesión aún vivía—, así que lo único que lo delata es el `se_guarda: false`
     * de la respuesta. Sin esta comprobación, el alumno seguía practicando en
     * el vacío: corrección real, «Correcto.», barra congelada y nada guardado.
     */
    it('sesión caducada a media práctica: el servidor lo dice y la página avisa', async () => {
        encolarFetch(respuestaJson(200, { ...ITEM_PROPIO, se_guarda: false }));

        render(<Practicar objective={OBJETIVO} mastery={0.42} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(/sesión caducó/i);
        expect(screen.getByRole('link', { name: /vuelve a entrar/i })).toHaveAttribute('href', '/entrar');
        // Y NO se le pinta el ejercicio como si nada.
        expect(screen.queryByLabelText(/tu respuesta/i)).not.toBeInTheDocument();
    });

    it('si caduca justo al responder, tampoco se le da un «Correcto» que no cuenta', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, { ...ITEM_PROPIO, se_guarda: true }),
            respuestaJson(200, { attempt_no: 1, is_correct: true, expected: 26.565, answer: 26.6, se_guarda: false }),
        );

        render(<Practicar objective={OBJETIVO} mastery={0.42} />);
        await screen.findByText(/μs = 0\.5/);

        await user.type(screen.getByLabelText(/tu respuesta/i), '26.6');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        expect(await screen.findByRole('alert')).toHaveTextContent(/sesión caducó/i);
        expect(screen.queryByText('Correcto.')).not.toBeInTheDocument();
    });

    /** Al VISITANTE `se_guarda: false` es su caso normal: no es una caducidad. */
    it('al visitante ese mismo se_guarda:false no le saca ningún aviso de sesión', async () => {
        auth = { user: null };
        encolarFetch(respuestaJson(200, { ...ITEM_PROPIO, se_guarda: false }));

        render(<Practicar objective={OBJETIVO} mastery={null} />);

        expect(await screen.findByText(/μs = 0\.5/)).toBeInTheDocument();
        expect(screen.queryByText(/sesión caducó/i)).not.toBeInTheDocument();
    });

    /**
     * El 401 sigue manejado como cinturón: hoy ninguna de las cuatro rutas lo
     * emite, pero si mañana alguna vuelve a exigir sesión, no puede acabar en
     * «¿se cortó la conexión?».
     */
    it('401: sesión caducada con enlace a /entrar (defensa, hoy inalcanzable)', async () => {
        encolarFetch(respuestaJson(401, { message: 'Unauthenticated.' }));

        render(<Practicar objective={OBJETIVO} mastery={0} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(/sesión caducó/i);
    });

    it('429: el límite se explica, no se disfraza de conexión cortada', async () => {
        encolarFetch(respuestaJson(429, { message: 'Too Many Attempts.' }));

        render(<Practicar objective={OBJETIVO} mastery={null} />);

        const aviso = await screen.findByRole('alert');
        expect(aviso).toHaveTextContent(/muchos ejercicios seguidos/i);
        expect(aviso).not.toHaveTextContent(/se cortó la conexión/i);
        expect(within(aviso).getByRole('button', { name: /reintentar/i })).toBeInTheDocument();
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
        ['sesión caducada', () => encolarFetch(respuestaJson(200, { ...ITEM_PROPIO, se_guarda: false })), /sesión caducó/i],
        ['límite alcanzado', () => encolarFetch(respuestaJson(429, {})), /muchos ejercicios seguidos/i],
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

/**
 * ORÁCULOS 7 y 9 de la misión «contenido abierto»: el visitante practica de
 * verdad, se le corrige de verdad, y en ningún momento se le hace creer que
 * aquello queda registrado.
 */
describe('Practicar — como VISITANTE (sin sesión)', () => {
    beforeEach(() => {
        auth = { user: null };
    });

    it('avisa, sin letra pequeña, de que su avance no se guarda', async () => {
        encolarFetch(respuestaJson(200, ITEM_PROPIO));
        render(<Practicar objective={OBJETIVO} mastery={null} />);
        await screen.findByText(/μs = 0\.5/);

        const aviso = screen.getByText(/tu avance no se guarda/i);
        expect(aviso).toBeInTheDocument();
        // Y el aviso lleva a la puerta, no es solo un lamento.
        expect(screen.getAllByRole('link', { name: /entra desde tu aula virtual/i })[0])
            .toHaveAttribute('href', '/entrar');
    });

    it('con sesión ese aviso NO aparece', async () => {
        auth = { user: { id: 1, name: 'Ana Estudiante' } };
        encolarFetch(respuestaJson(200, ITEM_PROPIO));
        render(<Practicar objective={OBJETIVO} mastery={0.4} />);
        await screen.findByText(/μs = 0\.5/);

        expect(screen.queryByText(/tu avance no se guarda/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/aciertos en esta visita/i)).not.toBeInTheDocument();
    });

    it('lleva su propio número de intento: el servidor no puede deducirlo', async () => {
        const fetchMock = encolarFetch(
            respuestaJson(200, ITEM_PROPIO),
            respuestaJson(200, { attempt_no: 1, is_correct: true, expected: 26.565, answer: 26.6, se_guarda: false }),
            respuestaJson(200, { ...ITEM_PROPIO, attempt_no: 2, statement: { es: 'Segundo: μs = 0.7…' } }),
        );

        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO} mastery={null} />);
        await screen.findByText(/μs = 0\.5/);

        // El primer ítem se pide con intento=1.
        expect(fetchMock.mock.calls[0][0]).toContain('intento=1');

        await user.type(screen.getByLabelText(/tu respuesta/i), '26.6');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Correcto.');

        // Y al responder, el intento que se corrige es el del ítem servido.
        expect(JSON.parse(fetchMock.mock.calls[1][1].body).intento).toBe(1);

        await user.click(screen.getByRole('button', { name: /siguiente ejercicio/i }));
        await screen.findByText(/Segundo:/);

        // El siguiente avanza: sin esto el visitante repetiría los mismos números.
        expect(fetchMock.mock.calls[2][0]).toContain('intento=2');
    });

    it('su marcador es de ACIERTOS de la visita, no un dominio inventado', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, ITEM_PROPIO),
            respuestaJson(200, { attempt_no: 1, is_correct: true, expected: 26.565, answer: 26.6, se_guarda: false }),
            respuestaJson(200, { ...ITEM_PROPIO, attempt_no: 2 }),
            respuestaJson(200, { attempt_no: 2, is_correct: false, expected: 30, answer: 1, se_guarda: false }),
        );

        render(<Practicar objective={OBJETIVO} mastery={null} />);
        await screen.findByText(/μs = 0\.5/);

        // Arranca en cero y se llama por su nombre: nada de «dominio».
        const barra = screen.getByRole('progressbar');
        expect(barra).toHaveAccessibleName(/aciertos en esta visita/i);
        expect(barra).toHaveAttribute('aria-valuenow', '0');

        await user.type(screen.getByLabelText(/tu respuesta/i), '26.6');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Correcto.');
        expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '100');
        expect(screen.getByText('1 de 1')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /siguiente ejercicio/i }));
        await screen.findByText(/μs = 0\.5/);
        await user.type(screen.getByLabelText(/tu respuesta/i), '1');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Incorrecto.');

        // 1 de 2 = 50 %.
        expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '50');
        expect(screen.getByText('1 de 2')).toBeInTheDocument();
    });

    it('el aviso se repite justo donde surge la duda: en el resultado', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, ITEM_PROPIO),
            respuestaJson(200, { attempt_no: 1, is_correct: true, expected: 26.565, answer: 26.6, se_guarda: false }),
        );

        render(<Practicar objective={OBJETIVO} mastery={null} />);
        await screen.findByText(/μs = 0\.5/);
        await user.type(screen.getByLabelText(/tu respuesta/i), '26.6');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        const veredicto = await screen.findByText('Correcto.');
        const tarjeta = veredicto.closest('[tabindex="-1"]');
        expect(within(tarjeta).getByText(/esto no se ha guardado/i)).toBeInTheDocument();
    });

    it('no tiene violaciones graves de accesibilidad', async () => {
        encolarFetch(respuestaJson(200, ITEM_PROPIO));
        const { container } = render(<Practicar objective={OBJETIVO} mastery={null} />);
        await screen.findByText(/μs = 0\.5/);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});

/**
 * OPCIÓN MÚLTIPLE. El mismo bucle, otra forma de responder: lo que llega del
 * servidor son POSICIONES ya barajadas («1».."4") y su texto, jamás cuál es la
 * buena. La página no decide nada — solo recoge la elección y la manda.
 */
const ITEM_CHOICE = {
    item_id: 'item-choice',
    kind: 'choice',
    objective_id: 'obj-lengua',
    objective_code: 'LL.4.1.1',
    objective_statement: 'Reconocer la variedad lingüística.',
    attempt_no: 1,
    statement: { es: '¿Cuál de estas palabras es un sustantivo?' },
    options: [
        { key: 'c', text: { es: 'Rápidamente' } },
        { key: 'a', text: { es: 'Montaña' } },
        { key: 'd', text: { es: 'Corrió' } },
        { key: 'b', text: { es: 'Azul' } },
    ],
    reason: 'práctica normal',
    se_guarda: true,
};

const OBJETIVO_LENGUA = {
    id: 'obj-lengua',
    native_code: 'LL.4.1.1',
    statement: 'Reconocer la variedad lingüística.',
    has_items: true,
};

describe('Practicar — ítems de opción múltiple', () => {
    it('pinta las opciones como un grupo de radios accesible', async () => {
        encolarFetch(respuestaJson(200, ITEM_CHOICE));
        render(<Practicar objective={OBJETIVO_LENGUA} mastery={0} />);

        await screen.findByText(/sustantivo/);

        const grupo = screen.getByRole('group', { name: /elige una respuesta/i });
        const radios = within(grupo).getAllByRole('radio');
        expect(radios).toHaveLength(4);

        // El nombre accesible de cada opción es SU TEXTO, no una letra suelta.
        expect(within(grupo).getByRole('radio', { name: /montaña/i })).toBeInTheDocument();
        // Una sola respuesta: mismo `name`, así que son excluyentes.
        expect(new Set(radios.map((r) => r.name)).size).toBe(1);
        // Y no hay campo numérico a la vez.
        expect(screen.queryByLabelText(/tu respuesta/i)).not.toBeInTheDocument();
    });

    /**
     * ORÁCULO VACUO CORREGIDO (auditoría). La versión anterior buscaba las
     * cadenas «correcta|is_correct|expected» en el DOM: palabras que la página
     * no renderiza jamás, así que pasaba con el bug puesto. Lo que hay que
     * comprobar es que NADA distingue a la opción buena de los distractores —
     * ni un atributo, ni una clase, ni el orden.
     */
    it('ninguna opción se distingue de las demás antes de responder', async () => {
        encolarFetch(respuestaJson(200, ITEM_CHOICE));
        const { container } = render(<Practicar objective={OBJETIVO_LENGUA} mastery={0} />);
        await screen.findByText(/sustantivo/);

        const radios = screen.getAllByRole('radio');

        // Todos los radios llevan EXACTAMENTE los mismos atributos, salvo el
        // valor (la clave) y el id que React pueda poner.
        const formas = radios.map((r) =>
            [...r.attributes]
                .map((a) => a.name)
                .filter((n) => n !== 'value')
                .sort()
                .join(','),
        );
        expect(new Set(formas).size).toBe(1);

        // Y sus etiquetas comparten clase: ninguna resaltada de antemano.
        const clases = radios.map((r) => r.closest('label').className);
        expect(new Set(clases).size).toBe(1);
    });

    it('manda la CLAVE elegida como answer_key, no un número suelto', async () => {
        const fetchMock = encolarFetch(
            respuestaJson(200, ITEM_CHOICE),
            respuestaJson(201, { attempt_no: 1, is_correct: true, expected_key: 'a', answer_key: 'a', se_guarda: true }),
        );

        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO_LENGUA} mastery={0} />);
        await screen.findByText(/sustantivo/);

        await user.click(screen.getByRole('radio', { name: /montaña/i }));
        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        await screen.findByText('Correcto.');
        const enviado = JSON.parse(fetchMock.mock.calls[1][1].body);
        expect(enviado.answer_key).toBe('a');
        expect(enviado).not.toHaveProperty('answer');
    });

    it('al fallar dice cuál era la buena, con su texto', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, ITEM_CHOICE),
            respuestaJson(201, { attempt_no: 1, is_correct: false, expected_key: 'a', answer_key: 'b', se_guarda: true }),
            respuestaJson(200, []),
        );

        render(<Practicar objective={OBJETIVO_LENGUA} mastery={0} />);
        await screen.findByText(/sustantivo/);

        await user.click(screen.getByRole('radio', { name: /azul/i }));
        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        const veredicto = await screen.findByText('Incorrecto.');
        const tarjeta = veredicto.closest('[tabindex="-1"]');
        // La explicación nombra la opción buena por su TEXTO, que es lo que el
        // alumno recuerda — no «la posición 2», que no significa nada.
        expect(tarjeta).toHaveTextContent(/montaña/i);
    });

    /**
     * Antes este test solo comprobaba que NO se enviaba nada, lo que bendecía
     * el silencio: quien navega con teclado pulsaba «Comprobar» y no ocurría
     * absolutamente nada, sin explicación (auditoría).
     */
    it('al enviar sin elegir lo dice, y devuelve el foco a las opciones', async () => {
        const fetchMock = encolarFetch(respuestaJson(200, ITEM_CHOICE));
        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO_LENGUA} mastery={0} />);
        await screen.findByText(/sustantivo/);

        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        expect(fetchMock).toHaveBeenCalledTimes(1);   // solo el next
        expect(await screen.findByRole('alert')).toHaveTextContent(/elige una de las opciones/i);
        expect(screen.getAllByRole('radio')[0]).toHaveFocus();

        // Y al elegir, el aviso se retira solo.
        await user.click(screen.getByRole('radio', { name: /montaña/i }));
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    /** Un choice sin opciones no puede dejar la página en blanco. */
    it('un ítem roto se explica en vez de reventar', async () => {
        encolarFetch(respuestaJson(200, { ...ITEM_CHOICE, options: undefined }));
        render(<Practicar objective={OBJETIVO_LENGUA} mastery={0} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(/incompleto/i);
        expect(screen.getByRole('button', { name: /probar con otro/i })).toBeInTheDocument();
        // Y no se le ofrece responder algo que no existe.
        expect(screen.queryByRole('button', { name: /comprobar/i })).not.toBeInTheDocument();
    });

    it('se resuelve entero con teclado', async () => {
        const fetchMock = encolarFetch(
            respuestaJson(200, ITEM_CHOICE),
            respuestaJson(201, { attempt_no: 1, is_correct: true, expected_key: 'a', answer_key: 'a', se_guarda: true }),
        );
        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO_LENGUA} mastery={0} />);
        await screen.findByText(/sustantivo/);

        // La página ya deja el foco en la primera opción al servir el ítem
        // (mismo gesto que con el campo numérico), así que las flechas mueven
        // la selección sin tener que buscar el grupo a tientas.
        expect(screen.getByRole('radio', { name: /rápidamente/i })).toHaveFocus();

        await user.keyboard('{ArrowDown}');
        expect(screen.getByRole('radio', { name: /montaña/i })).toBeChecked();

        await user.tab();
        await user.keyboard('{Enter}');

        await screen.findByText('Correcto.');
        expect(JSON.parse(fetchMock.mock.calls[1][1].body).answer_key).toBe('a');
    });

    it('el visitante ve su aviso también en un choice', async () => {
        auth = { user: null };
        encolarFetch(respuestaJson(200, { ...ITEM_CHOICE, se_guarda: false }));
        render(<Practicar objective={OBJETIVO_LENGUA} mastery={null} />);
        await screen.findByText(/sustantivo/);

        expect(screen.getByText(/tu avance no se guarda/i)).toBeInTheDocument();
    });

    it('no tiene violaciones graves de accesibilidad', async () => {
        encolarFetch(respuestaJson(200, ITEM_CHOICE));
        const { container } = render(<Practicar objective={OBJETIVO_LENGUA} mastery={0} />);
        await screen.findByText(/sustantivo/);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
