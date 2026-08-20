import { render, screen } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { describe, expect, it, vi } from 'vitest';
import Bienvenida from '../bienvenida';
import { violacionesGraves } from '../../test/helpers';

// Visitante SIN sesión: auth.user es null (así lo comparte HandleInertiaRequests).
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ url: '/', props: { auth: { user: null } } }),
    Link: ({ href, children, ...rest }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const CIFRAS = { destrezas: 1010, verificadas: 8, grados: 13, simuladores: 2 };

describe('portada pública', () => {
    it('presenta la plataforma con un solo título de nivel 1', () => {
        render(<Bienvenida cifras={CIFRAS} entrar="/entrar" />);

        const h1 = screen.getAllByRole('heading', { level: 1 });
        expect(h1).toHaveLength(1);
        expect(h1[0]).toHaveTextContent(/currículo ecuatoriano/i);
    });

    /**
     * DOS caminos, no una pared: explorar (que cualquiera puede hacer ya) y
     * entrar (que es lo que guarda). Lo que sigue prohibido es ofrecerle una
     * página con sesión, que lo devolvería a /entrar dando un rodeo.
     */
    it('ofrece explorar el currículo Y entrar, pero nada que exija sesión', () => {
        render(<Bienvenida cifras={CIFRAS} entrar="/entrar" />);

        expect(screen.getByRole('link', { name: /explora el currículo/i }))
            .toHaveAttribute('href', '/catalogo');

        const entradas = screen.getAllByRole('link', { name: /entrar desde tu aula virtual|cómo entrar/i });
        expect(entradas.length).toBeGreaterThanOrEqual(1);
        entradas.forEach((a) => expect(a).toHaveAttribute('href', '/entrar'));

        ['/inicio', '/progreso'].forEach((ruta) => {
            expect(document.querySelector(`a[href="${ruta}"]`)).toBeNull();
        });
    });

    it('escribe las cifras con su palabra, nunca el número solo', () => {
        render(<Bienvenida cifras={CIFRAS} entrar="/entrar" />);

        // Formato ecuatoriano: el punto separa los miles.
        expect(screen.getByText('1.010')).toBeInTheDocument();
        expect(screen.getByText('destrezas')).toBeInTheDocument();
        expect(screen.getByText('13')).toBeInTheDocument();
        expect(screen.getByText('grados')).toBeInTheDocument();
    });

    it('concuerda el singular cuando la cifra es 1', () => {
        render(<Bienvenida cifras={{ destrezas: 1, verificadas: 1, grados: 1, simuladores: 1 }} entrar="/entrar" />);

        expect(screen.getByText('destreza')).toBeInTheDocument();
        expect(screen.getByText('verificada')).toBeInTheDocument();
        expect(screen.getByText('grado')).toBeInTheDocument();
        expect(screen.getByText('simulador')).toBeInTheDocument();
    });

    /**
     * Las etiquetas de las cifras caben en una celda de ~158 px (la mitad de un
     * teléfono de 360 px menos los márgenes). Una frase entera ahí se parte en
     * cuatro líneas y descuadra la retícula: el matiz va en prosa, debajo.
     */
    it('las etiquetas de las cifras caben en un móvil de 360 px', () => {
        render(<Bienvenida cifras={CIFRAS} entrar="/entrar" />);

        ['destrezas', 'verificadas', 'grados', 'simuladores'].forEach((etiqueta) => {
            expect(screen.getByText(etiqueta).textContent.split(/\s+/)).toHaveLength(1);
        });
        // Y el matiz de «verificada» sigue explicado, solo que fuera de la celda.
        expect(screen.getByText(/cotejada palabra por palabra/i)).toBeInTheDocument();
    });

    it('sobrevive a una instalación recién desplegada (todo en cero)', () => {
        render(<Bienvenida cifras={{ destrezas: 0, verificadas: 0, grados: 0, simuladores: 0 }} entrar="/entrar" />);

        expect(screen.getAllByText('0')).toHaveLength(4);
        expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });

    it('los iconos decorativos no se leen en voz alta', () => {
        const { container } = render(<Bienvenida cifras={CIFRAS} entrar="/entrar" />);

        // Cada emoji va marcado; el significado lo lleva el texto de al lado.
        container.querySelectorAll('h3 span').forEach((s) => {
            expect(s).toHaveAttribute('aria-hidden', 'true');
        });
        // Los anillos del hero son adorno: no anuncian un progreso que no existe.
        expect(screen.queryByRole('progressbar')).not.toBeInTheDocument();
    });

    it('no tiene violaciones graves de accesibilidad', async () => {
        const { container } = render(<Bienvenida cifras={CIFRAS} entrar="/entrar" />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
