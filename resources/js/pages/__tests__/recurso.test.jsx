import { fireEvent, render, screen, within } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Recurso from '../Recurso';
import { violacionesGraves } from '../../test/helpers';

let sesion = { user: { id: 1, name: 'Ana' } };

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ url: '/recurso/x', props: { auth: sesion } }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

/** El árbol MathML tal y como lo emite App\Services\Lesson\MathML. */
const UN_MEDIO = {
    e: 'math',
    h: [
        {
            e: 'mfrac',
            h: [
                { e: 'mn', t: '1' },
                { e: 'mn', t: '2' },
            ],
        },
    ],
};

const LECCION = {
    id: 'r-1',
    slug: 'ecuaciones',
    kind: 'reading',
    title: 'Ecuaciones de primer grado',
    summary: 'Qué es una ecuación y cómo se despeja.',
    duration_min: 8,
    bundle_url: null,
    bloques: [
        { tipo: 'parrafo', texto: { es: 'Una ecuación es una igualdad con una incógnita.' } },
        { tipo: 'formula', latex: '\\frac{1}{2}', mathml: UN_MEDIO, etiqueta: { es: 'Un medio' } },
        {
            tipo: 'ejemplo',
            titulo: { es: 'Despejar paso a paso' },
            pasos: [
                { texto: { es: 'Restamos 3 a los dos lados.' }, formula: UN_MEDIO },
                { texto: { es: 'Dividimos entre 2.' } },
            ],
        },
        { tipo: 'lista', ordenada: true, items: [{ es: 'Agrupa.' }, { es: 'Despeja.' }] },
        { tipo: 'aviso', variante: 'error-tipico', texto: { es: 'Cambiar de signo solo un lado.' } },
        { tipo: 'imagen', src: '/img/recta.svg', alt: { es: 'Recta numérica del 0 al 10' } },
    ],
};

const DESTREZAS = [
    { id: 'obj-1', native_code: 'M.4.1.1', statement: 'Resolver ecuaciones de primer grado.' },
];

afterEach(() => {
    sesion = { user: { id: 1, name: 'Ana' } };
});

describe('Recurso — el lector de la lección', () => {
    it('pinta cada tipo de bloque con su forma', () => {
        render(<Recurso recurso={LECCION} destrezas={DESTREZAS} />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
            'Ecuaciones de primer grado',
        );
        expect(screen.getByText(/igualdad con una incógnita/)).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: /despejar paso a paso/i })).toBeInTheDocument();
        // La lista ordenada es una <ol> de verdad, no párrafos con guiones.
        const lista = screen.getByText('Agrupa.').closest('ol');
        expect(lista).not.toBeNull();
        expect(within(lista).getAllByRole('listitem')).toHaveLength(2);
        expect(screen.getByAltText('Recta numérica del 0 al 10')).toBeInTheDocument();
    });

    /**
     * EL ORÁCULO DE INYECCIÓN, medido sobre el DOM. Un texto con etiquetas
     * tiene que llegar como TEXTO: visible, legible y sin ejecutarse. Es lo que
     * cae si alguien mete un `dangerouslySetInnerHTML` «para que se vea mejor».
     */
    it('un texto con etiquetas se lee, no se ejecuta', () => {
        const veneno = '<script>alert(1)</script><img src=x onerror="alert(2)">';
        const { container } = render(
            <Recurso
                recurso={{ ...LECCION, bloques: [{ tipo: 'parrafo', texto: { es: veneno } }] }}
                destrezas={[]}
            />,
        );

        // Ni un script ni la imagen envenenada llegaron a ser elementos…
        expect(container.querySelector('script')).toBeNull();
        expect(container.querySelector('img[onerror]')).toBeNull();
        // …y el texto está ahí, entero, como texto.
        expect(screen.getByText(veneno)).toBeInTheDocument();
    });

    it('un tipo de bloque desconocido se calla en vez de romper la lectura', () => {
        render(
            <Recurso
                recurso={{
                    ...LECCION,
                    bloques: [
                        { tipo: 'de_un_futuro_que_no_existe', texto: { es: 'x' } },
                        { tipo: 'parrafo', texto: { es: 'Esto sí se lee.' } },
                    ],
                }}
                destrezas={[]}
            />,
        );

        expect(screen.getByText('Esto sí se lee.')).toBeInTheDocument();
    });

    // ---------- La fórmula ----------

    it('la fórmula se pinta como MathML de verdad, con sus elementos', () => {
        const { container } = render(<Recurso recurso={LECCION} destrezas={[]} />);

        const math = container.querySelector('math');
        expect(math).not.toBeNull();
        expect(math.querySelector('mfrac')).not.toBeNull();
        expect(math.querySelectorAll('mn')).toHaveLength(2);
        // El texto está DENTRO del MathML: es lo que lee el lector de pantalla.
        expect(math.textContent).toBe('12');
    });

    it('la fórmula lleva su etiqueta como nombre accesible y como texto', () => {
        const { container } = render(<Recurso recurso={LECCION} destrezas={[]} />);

        expect(container.querySelector('math[aria-label="Un medio"]')).not.toBeNull();
        expect(screen.getByText('Un medio')).toBeInTheDocument();
    });

    /**
     * El cinturón del componente: si el servidor emitiera un nodo que no está
     * en su lista blanca, se cae al texto en vez de crear un elemento raro.
     */
    it('un nodo fuera de la lista blanca no llega a ser un elemento', () => {
        const { container } = render(
            <Recurso
                recurso={{
                    ...LECCION,
                    bloques: [
                        {
                            tipo: 'formula',
                            latex: 'x',
                            mathml: { e: 'math', h: [{ e: 'script', t: 'alert(1)' }] },
                        },
                    ],
                }}
                destrezas={[]}
            />,
        );

        expect(container.querySelector('script')).toBeNull();
        expect(container.querySelector('math').textContent).toBe('alert(1)');
    });

    /**
     * A 360 px lo que desborda es la fórmula: una ecuación larga es una línea
     * que no parte. jsdom no mide, así que esto comprueba la ESTRUCTURA que
     * evita el desbordamiento —el contenedor con scroll propio y el `min-w-0`
     * que deja encoger al paso del ejemplo dentro del flex—, que es lo que se
     * rompe cuando alguien reordena las clases. No sustituye a mirar la página,
     * pero sí caza la regresión.
     */
    it('la fórmula scrollea en su caja, no empuja el ancho de la página', () => {
        const { container } = render(<Recurso recurso={LECCION} destrezas={[]} />);

        const caja = container.querySelector('math').closest('div');
        expect(caja.className).toContain('overflow-x-auto');

        // Y el texto del paso puede encoger dentro del flex del ejemplo.
        const paso = screen.getByText(/restamos 3 a los dos lados/i).parentElement;
        expect(paso.className).toContain('min-w-0');
    });

    // ---------- El audio ----------

    /**
     * El bloque de una LECCIÓN, no de un ítem: aquí la transcripción es
     * visible siempre — accesibilidad y pedagogía A1 a la vez. El que la
     * esconde hasta responder es el ítem de escucha, y vive en practicar.
     */
    it('el bloque de audio pinta reproductor nativo y transcripción visible', () => {
        const { container } = render(
            <Recurso
                recurso={{
                    ...LECCION,
                    bloques: [{
                        tipo: 'audio',
                        src: '/audio/aabbccddeeff0011.mp3',
                        texto: { fr: 'Bonjour, ça va ?', es: 'Buenos días, ¿qué tal?' },
                        duracion_s: 3,
                    }],
                }}
                destrezas={[]}
            />,
        );

        const audio = container.querySelector('audio');
        expect(audio).not.toBeNull();
        expect(audio).toHaveAttribute('src', '/audio/aabbccddeeff0011.mp3');
        expect(audio).toHaveAttribute('controls');

        // Las dos lenguas de la transcripción, legibles sin tocar nada.
        expect(screen.getByText('Bonjour, ça va ?')).toBeInTheDocument();
        expect(screen.getByText('Buenos días, ¿qué tal?')).toBeInTheDocument();
    });

    /**
     * SIN RED, LA LECCIÓN SIGUE EN PIE. Esto es un colegio en Ecuador: si el
     * clip no llega, el bloque cae a su transcripción con un aviso — no a un
     * reproductor muerto ni a una pantalla rota.
     */
    it('si el clip no carga, cae a la transcripción y lo dice', () => {
        const { container } = render(
            <Recurso
                recurso={{
                    ...LECCION,
                    bloques: [{
                        tipo: 'audio',
                        src: '/audio/aabbccddeeff0011.mp3',
                        texto: { fr: 'Bonjour !' },
                    }],
                }}
                destrezas={[]}
            />,
        );

        fireEvent.error(container.querySelector('audio'));

        expect(container.querySelector('audio')).toBeNull();
        expect(screen.getByRole('status')).toHaveTextContent(/audio no está disponible/i);
        // La transcripción sigue ahí: se puede seguir estudiando leyendo.
        expect(screen.getByText('Bonjour !')).toBeInTheDocument();
    });

    it('una lección con audio no tiene violaciones serias de accesibilidad', async () => {
        const { container } = render(
            <Recurso
                recurso={{
                    ...LECCION,
                    bloques: [
                        ...LECCION.bloques,
                        { tipo: 'audio', src: '/audio/aabbccddeeff0011.mp3', texto: { fr: 'Bonjour !' } },
                    ],
                }}
                destrezas={DESTREZAS}
            />,
        );

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });

    // ---------- El aviso ----------

    it('el aviso dice de qué tipo es en TEXTO, no solo en color', () => {
        render(<Recurso recurso={LECCION} destrezas={[]} />);

        expect(screen.getByText('Error típico')).toBeInTheDocument();
        expect(screen.getByText(/cambiar de signo solo un lado/i)).toBeInTheDocument();
    });

    // ---------- El cierre ----------

    it('cierra devolviendo al alumno a practicar la destreza', () => {
        render(<Recurso recurso={LECCION} destrezas={DESTREZAS} />);

        expect(screen.getByRole('link', { name: 'M.4.1.1' })).toHaveAttribute(
            'href',
            '/practicar/obj-1',
        );
    });

    it('un simulador sigue ofreciendo su bundle', () => {
        render(
            <Recurso
                recurso={{
                    ...LECCION,
                    kind: 'simulation',
                    bloques: [],
                    bundle_url: 'https://cdn.test/sims/x/1.0.0/',
                }}
                destrezas={[]}
            />,
        );

        expect(screen.getByRole('link', { name: /abrir el laboratorio/i })).toHaveAttribute(
            'href',
            'https://cdn.test/sims/x/1.0.0/',
        );
    });

    it('sin bloques ni bundle lo dice en vez de quedarse en blanco', () => {
        render(
            <Recurso recurso={{ ...LECCION, bloques: [], bundle_url: null }} destrezas={[]} />,
        );

        expect(screen.getByRole('status')).toHaveTextContent(/todavía no tiene contenido/i);
    });

    // ---------- Accesibilidad ----------

    it.each([
        ['con sesión', { user: { id: 1, name: 'Ana' } }],
        ['sin sesión', { user: null }],
    ])('accesibilidad %s: sin violaciones serias', async (_n, auth) => {
        sesion = auth;
        const { container } = render(<Recurso recurso={LECCION} destrezas={DESTREZAS} />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });

    it('los encabezados van en orden, sin saltos', () => {
        render(<Recurso recurso={LECCION} destrezas={DESTREZAS} />);

        const niveles = screen
            .getAllByRole('heading')
            .map((h) => Number(h.tagName.slice(1)));

        expect(niveles[0]).toBe(1);
        // Ningún salto de más de un nivel entre encabezados consecutivos.
        niveles.slice(1).forEach((n, i) => expect(n - niveles[i]).toBeLessThanOrEqual(1));
    });
});
