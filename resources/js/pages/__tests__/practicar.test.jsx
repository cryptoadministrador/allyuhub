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
    // El billete firmado que emite `next`. El cliente NO lo lee ni lo
    // construye: lo guarda y lo devuelve tal cual al responder.
    billete: 'billete-del-intento-1',
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
            respuestaJson(200, {
                ...ITEM_PROPIO,
                attempt_no: 2,
                billete: 'billete-del-intento-2',
                statement: { es: 'Segundo: μs = 0.7…' },
            }),
        );

        const user = userEvent.setup();
        render(<Practicar objective={OBJETIVO} mastery={null} />);
        await screen.findByText(/μs = 0\.5/);

        // El primer ítem se pide con intento=1.
        expect(fetchMock.mock.calls[0][0]).toContain('intento=1');

        await user.type(screen.getByLabelText(/tu respuesta/i), '26.6');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Correcto.');

        // Y al responder, devuelve EL BILLETE del ítem que tiene delante, tal
        // cual vino. El número de intento y la semilla van firmados dentro: el
        // cliente no los elige, y el servidor ya no los deduce contando filas
        // en otro instante (que es lo que corregía al alumno contra números que
        // no había visto).
        const enviado = JSON.parse(fetchMock.mock.calls[1][1].body);
        expect(enviado.billete).toBe('billete-del-intento-1');
        expect(enviado.intento).toBeUndefined();

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

// ================= ESCUCHA: el primer tipo de lenguas =================

const ITEM_ESCUCHA = {
    item_id: 'item-escucha',
    kind: 'escucha',
    objective_id: 'obj-frances',
    objective_code: 'EXT.FR.1.1.1',
    objective_statement: 'Comprender saludos muy básicos.',
    attempt_no: 1,
    billete: 'billete-escucha-1',
    statement: { es: 'Escucha el saludo. ¿Qué dice?' },
    options: [
        { key: 'a', text: { es: 'Buenos días' } },
        { key: 'b', text: { es: 'Buenas noches' } },
        { key: 'c', text: { es: 'Hasta luego' } },
    ],
    audio_src: '/audio/aabbccddeeff0011.mp3',
    reason: 'práctica normal',
    se_guarda: true,
};

const OBJETIVO_FRANCES = {
    id: 'obj-frances',
    native_code: 'EXT.FR.1.1.1',
    statement: 'Comprender saludos muy básicos.',
    has_items: true,
};

describe('Practicar — ítems de escucha', () => {
    it('pinta el reproductor nativo con el clip y las opciones como radios', async () => {
        encolarFetch(respuestaJson(200, ITEM_ESCUCHA));
        const { container } = render(<Practicar objective={OBJETIVO_FRANCES} mastery={null} />);
        await screen.findByText(/escucha el saludo/i);

        // <audio> del navegador: 0 KB de librería, teclado y lector de
        // pantalla gratis con `controls`.
        const audio = container.querySelector('audio');
        expect(audio).not.toBeNull();
        expect(audio).toHaveAttribute('src', '/audio/aabbccddeeff0011.mp3');
        expect(audio).toHaveAttribute('controls');

        expect(screen.getAllByRole('radio')).toHaveLength(3);
    });

    it('la transcripción NO está en el DOM antes de responder', async () => {
        encolarFetch(
            respuestaJson(200, { ...ITEM_ESCUCHA, transcripcion: undefined }),
        );
        const { container } = render(<Practicar objective={OBJETIVO_FRANCES} mastery={null} />);
        await screen.findByText(/escucha el saludo/i);

        // El servidor no la manda (eso lo fija EscuchaTest); aquí se fija que
        // el componente tampoco la INVENTA ni deja un hueco con su nombre.
        expect(container.textContent).not.toMatch(/transcripci/i);
    });

    it('tras responder, la transcripción del veredicto se lee', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, ITEM_ESCUCHA),
            respuestaJson(201, {
                id: 'a1', attempt_no: 1, is_correct: false, expected_key: 'a',
                answer_key: 'b', transcripcion: 'Bonjour !', se_guarda: true,
            }),
            respuestaJson(200, []),
        );
        render(<Practicar objective={OBJETIVO_FRANCES} mastery={null} />);
        await screen.findByText(/escucha el saludo/i);

        await user.click(screen.getByRole('radio', { name: /buenas noches/i }));
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Incorrecto.');

        // Lo que decía el clip, visible y rotulado: es la mitad del ejercicio.
        expect(screen.getByText('Bonjour !')).toBeInTheDocument();
        expect(screen.getByText(/lo que decía el audio/i)).toBeInTheDocument();
        // Y la opción buena se nombra por su texto, como en choice.
        expect(screen.getByText(/buenos días/i)).toBeInTheDocument();
    });

    it('si el clip no carga, lo dice y el ejercicio no se rompe', async () => {
        const { fireEvent } = await import('@testing-library/react');
        encolarFetch(respuestaJson(200, ITEM_ESCUCHA));
        const { container } = render(<Practicar objective={OBJETIVO_FRANCES} mastery={null} />);
        await screen.findByText(/escucha el saludo/i);

        fireEvent.error(container.querySelector('audio'));

        // Sin red no hay escucha, y se DICE — nada de un reproductor muerto.
        expect(screen.getByRole('status')).toHaveTextContent(/audio no se pudo cargar/i);
        // El formulario sigue: puede intentarlo igual o pasar al siguiente.
        expect(screen.getByRole('button', { name: /comprobar/i })).toBeInTheDocument();
    });

    it.each([
        ['con sesión', { user: { id: 1, name: 'Ana' } }],
        ['sin sesión', { user: null }],
    ])('accesibilidad %s: cero violaciones serias', async (_n, quien) => {
        auth = quien;
        encolarFetch(respuestaJson(200, { ...ITEM_ESCUCHA, se_guarda: !!quien.user }));
        const { container } = render(<Practicar objective={OBJETIVO_FRANCES} mastery={null} />);
        await screen.findByText(/escucha el saludo/i);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});

// ================= LOS CUATRO TIPOS DE LENGUA =================

const OBJETIVO_ALEMAN = {
    id: 'obj-aleman', native_code: 'EXT.DE.1.1.1',
    statement: 'Producir frases muy básicas.', has_items: true,
};

const ITEM_HUECO = {
    item_id: 'item-hueco', kind: 'hueco',
    objective_id: 'obj-frances', objective_code: 'EXT.FR.1.1.2',
    objective_statement: 'Producir frases muy básicas.',
    attempt_no: 1, billete: 'billete-hueco-1',
    statement: { es: 'Completa: « Tu habites ___ ? »' },
    reason: 'práctica normal', se_guarda: true,
};

const ITEM_ORDEN = {
    item_id: 'item-orden', kind: 'orden',
    objective_id: 'obj-aleman', objective_code: 'EXT.DE.1.1.1',
    objective_statement: 'Producir frases muy básicas.',
    attempt_no: 1, billete: 'billete-orden-1',
    statement: { es: 'Ordena la frase.' },
    // Servidas YA barajadas por el servidor: el orden de pintado no es el bueno.
    options: [
        { key: 'w4', text: { de: 'zur Schule' } },
        { key: 'w2', text: { de: 'gehe' } },
        { key: 'w3', text: { de: 'ich' } },
        { key: 'w1', text: { de: 'morgen' } },
    ],
    reason: 'práctica normal', se_guarda: true,
};

const ITEM_PARES = {
    item_id: 'item-pares', kind: 'pares',
    objective_id: 'obj-chino', objective_code: 'EXT.ZH.1.1.1',
    objective_statement: 'Reconocer caracteres básicos.',
    attempt_no: 1, billete: 'billete-pares-1',
    statement: { es: 'Empareja carácter, pinyin y significado.' },
    options: [
        { key: 'c1', col: 'a', text: { zh: '你' } },
        { key: 'c2', col: 'a', text: { zh: '好' } },
        { key: 'p2', col: 'b', text: { zh: 'hao3' } },
        { key: 'p1', col: 'b', text: { zh: 'ni3' } },
        { key: 's1', col: 'c', text: { es: 'tu / usted' } },
        { key: 's2', col: 'c', text: { es: 'bien / bueno' } },
    ],
    reason: 'práctica normal', se_guarda: true,
};

const ITEM_DICTADO = {
    item_id: 'item-dictado', kind: 'dictado',
    objective_id: 'obj-italiano', objective_code: 'EXT.IT.1.1.1',
    objective_statement: 'Escribir lo que se oye.',
    attempt_no: 1, billete: 'billete-dictado-1',
    statement: { es: 'Escucha y escribe exactamente lo que oyes.' },
    audio_src: '/audio/aabbccddeeff0011.mp3',
    reason: 'práctica normal', se_guarda: true,
};

describe('Practicar — hueco y dictado (escribir la forma)', () => {
    it('hueco: se escribe la respuesta y viaja como respuesta.texto', async () => {
        const user = userEvent.setup();
        const fetchMock = encolarFetch(
            respuestaJson(200, ITEM_HUECO),
            respuestaJson(201, {
                id: 'a1', attempt_no: 1, is_correct: true, detalle: null,
                esperado: 'où', texto: 'où', se_guarda: true,
            }),
            respuestaJson(200, []),
        );
        render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/tu habites/i);

        await user.type(screen.getByRole('textbox', { name: /tu respuesta/i }), 'où');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Correcto.');

        const enviado = JSON.parse(fetchMock.mock.calls[1][1].body);
        expect(enviado.respuesta).toEqual({ texto: 'où' });
        expect(enviado.answer).toBeUndefined();
        expect(enviado.answer_key).toBeUndefined();
    });

    it('hueco: el error de acento y el de palabra dicen cosas DISTINTAS', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, ITEM_HUECO),
            respuestaJson(201, {
                id: 'a1', attempt_no: 1, is_correct: false, detalle: 'acento',
                esperado: 'où', texto: 'ou', se_guarda: true,
            }),
            respuestaJson(200, []),
        );
        render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/tu habites/i);

        await user.type(screen.getByRole('textbox', { name: /tu respuesta/i }), 'ou');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Incorrecto.');

        // «Te falta un acento» no es «esa palabra no es»: dos errores, dos
        // mensajes. El alumno tiene que saber cuál cometió.
        expect(screen.getByText(/acento/i)).toBeInTheDocument();
        expect(screen.getByText(/où/)).toBeInTheDocument();
    });

    it('dictado: reproduce el clip, se escribe, y el veredicto trae lo que decía', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, ITEM_DICTADO),
            respuestaJson(201, {
                id: 'a1', attempt_no: 1, is_correct: false, detalle: 'palabra',
                esperado: 'perché no?', texto: 'perke', transcripcion: 'perché no?',
                se_guarda: true,
            }),
            respuestaJson(200, []),
        );
        const { container } = render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/escribe exactamente/i);

        expect(container.querySelector('audio')).toHaveAttribute(
            'src', '/audio/aabbccddeeff0011.mp3',
        );

        await user.type(screen.getByRole('textbox', { name: /tu respuesta/i }), 'perke');
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Incorrecto.');

        expect(screen.getByText(/lo que decía el audio/i)).toBeInTheDocument();
    });
});

describe('Practicar — orden (tocar, no arrastrar)', () => {
    it('se construye la frase TOCANDO y viaja como secuencia de ids', async () => {
        const user = userEvent.setup();
        const fetchMock = encolarFetch(
            respuestaJson(200, ITEM_ORDEN),
            respuestaJson(201, {
                id: 'a1', attempt_no: 1, is_correct: true,
                ids: ['w3', 'w2', 'w1', 'w4'], secuencia_correcta: ['w3', 'w2', 'w1', 'w4'],
                se_guarda: true,
            }),
            respuestaJson(200, []),
        );
        render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/ordena la frase/i);

        const banco = screen.getByRole('group', { name: /palabras disponibles/i });
        await user.click(within(banco).getByRole('button', { name: 'ich' }));
        await user.click(within(banco).getByRole('button', { name: 'gehe' }));
        await user.click(within(banco).getByRole('button', { name: 'morgen' }));
        await user.click(within(banco).getByRole('button', { name: 'zur Schule' }));
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Correcto.');

        // Los IDS en el orden TOCADO, no la posición pintada: el barajado no
        // participa en la corrección por construcción.
        const enviado = JSON.parse(fetchMock.mock.calls[1][1].body);
        expect(enviado.respuesta).toEqual({ ids: ['w3', 'w2', 'w1', 'w4'] });
    });

    it('tocar una palabra elegida la devuelve al banco', async () => {
        const user = userEvent.setup();
        encolarFetch(respuestaJson(200, ITEM_ORDEN));
        render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/ordena la frase/i);

        const banco = screen.getByRole('group', { name: /palabras disponibles/i });
        const frase = screen.getByRole('group', { name: /tu frase/i });

        await user.click(within(banco).getByRole('button', { name: 'ich' }));
        expect(within(frase).getByRole('button', { name: 'ich' })).toBeInTheDocument();

        await user.click(within(frase).getByRole('button', { name: 'ich' }));
        expect(within(frase).queryByRole('button', { name: 'ich' })).toBeNull();
        expect(within(banco).getByRole('button', { name: 'ich' })).toBeInTheDocument();
    });

    it('con la frase a medias avisa en vez de mandar', async () => {
        const user = userEvent.setup();
        const fetchMock = encolarFetch(respuestaJson(200, ITEM_ORDEN));
        render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/ordena la frase/i);

        const banco = screen.getByRole('group', { name: /palabras disponibles/i });
        await user.click(within(banco).getByRole('button', { name: 'ich' }));
        await user.click(screen.getByRole('button', { name: /comprobar/i }));

        expect(screen.getByRole('alert')).toHaveTextContent(/faltan palabras/i);
        expect(fetchMock.mock.calls).toHaveLength(1);   // no hubo POST
    });

    it('al fallar enseña un orden correcto CON PALABRAS, no con ids', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, ITEM_ORDEN),
            respuestaJson(201, {
                id: 'a1', attempt_no: 1, is_correct: false,
                ids: ['w4', 'w2', 'w3', 'w1'], secuencia_correcta: ['w3', 'w2', 'w1', 'w4'],
                se_guarda: true,
            }),
            respuestaJson(200, []),
        );
        render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/ordena la frase/i);

        const banco = screen.getByRole('group', { name: /palabras disponibles/i });
        for (const palabra of ['zur Schule', 'gehe', 'ich', 'morgen']) {
            await user.click(within(banco).getByRole('button', { name: palabra }));
        }
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Incorrecto.');

        expect(screen.getByText(/ich gehe morgen zur Schule/)).toBeInTheDocument();
    });
});

describe('Practicar — pares (tres columnas, tocando)', () => {
    it('una selección por columna forma la pareja y viaja como tuplas de ids', async () => {
        const user = userEvent.setup();
        const fetchMock = encolarFetch(
            respuestaJson(200, ITEM_PARES),
            respuestaJson(201, {
                id: 'a1', attempt_no: 1, is_correct: true, parejas_correctas: 2, total: 2,
                parejas: [['c1', 'p1', 's1'], ['c2', 'p2', 's2']],
                parejas_esperadas: [['c1', 'p1', 's1'], ['c2', 'p2', 's2']],
                se_guarda: true,
            }),
            respuestaJson(200, []),
        );
        render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/empareja/i);

        // Toca uno de cada columna: se forma la pareja sola.
        await user.click(screen.getByRole('button', { name: '你' }));
        await user.click(screen.getByRole('button', { name: 'ni3' }));
        await user.click(screen.getByRole('button', { name: 'tu / usted' }));
        await user.click(screen.getByRole('button', { name: '好' }));
        await user.click(screen.getByRole('button', { name: 'hao3' }));
        await user.click(screen.getByRole('button', { name: 'bien / bueno' }));

        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Correcto.');

        const enviado = JSON.parse(fetchMock.mock.calls[1][1].body);
        expect(enviado.respuesta).toEqual({
            parejas: [['c1', 'p1', 's1'], ['c2', 'p2', 's2']],
        });
    });

    it('al fallar dice cuántas clavó de cuántas', async () => {
        const user = userEvent.setup();
        encolarFetch(
            respuestaJson(200, ITEM_PARES),
            respuestaJson(201, {
                id: 'a1', attempt_no: 1, is_correct: false, parejas_correctas: 1, total: 2,
                parejas: [['c1', 'p1', 's2'], ['c2', 'p2', 's1']],
                parejas_esperadas: [['c1', 'p1', 's1'], ['c2', 'p2', 's2']],
                se_guarda: true,
            }),
            respuestaJson(200, []),
        );
        render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/empareja/i);

        await user.click(screen.getByRole('button', { name: '你' }));
        await user.click(screen.getByRole('button', { name: 'ni3' }));
        await user.click(screen.getByRole('button', { name: 'bien / bueno' }));
        await user.click(screen.getByRole('button', { name: '好' }));
        await user.click(screen.getByRole('button', { name: 'hao3' }));
        await user.click(screen.getByRole('button', { name: 'tu / usted' }));
        await user.click(screen.getByRole('button', { name: /comprobar/i }));
        await screen.findByText('Incorrecto.');

        expect(screen.getByText(/1 de 2/)).toBeInTheDocument();
    });

    it('deshacer una pareja devuelve sus elementos a las columnas', async () => {
        const user = userEvent.setup();
        encolarFetch(respuestaJson(200, ITEM_PARES));
        render(<Practicar objective={OBJETIVO_ALEMAN} mastery={null} />);
        await screen.findByText(/empareja/i);

        await user.click(screen.getByRole('button', { name: '你' }));
        await user.click(screen.getByRole('button', { name: 'ni3' }));
        await user.click(screen.getByRole('button', { name: 'tu / usted' }));

        const quitar = screen.getByRole('button', { name: /quitar pareja/i });
        await user.click(quitar);

        // Los tres vuelven a poder tocarse.
        expect(screen.getByRole('button', { name: '你' })).toBeEnabled();
        expect(screen.queryByRole('button', { name: /quitar pareja/i })).toBeNull();
    });
});

describe('Practicar — accesibilidad de los tipos de lengua', () => {
    it.each([
        ['hueco', ITEM_HUECO],
        ['orden', ITEM_ORDEN],
        ['pares', ITEM_PARES],
        ['dictado', ITEM_DICTADO],
    ])('%s: cero violaciones serias, con y sin sesión', async (_k, fixture) => {
        for (const quien of [{ user: { id: 1, name: 'Ana' } }, { user: null }]) {
            auth = quien;
            encolarFetch(respuestaJson(200, { ...fixture, se_guarda: !!quien.user }));
            const { container, unmount } = render(
                <Practicar objective={OBJETIVO_ALEMAN} mastery={null} />,
            );
            await screen.findByText(new RegExp(fixture.statement.es.slice(0, 12)));

            expect(violacionesGraves(await axe(container))).toEqual([]);
            unmount();
        }
    });
});
