import { describe, expect, it } from 'vitest';
import semilla from '../../../../database/data/curriculo-semilla.json';
import {
    contraste,
    estiloDeAsignatura,
    luminancia,
    mezclaConBlanco,
    NEGRO_TINTA,
    tintaDeAcento,
    tintaSobre,
} from '../color';

/** Los 15 colores REALES del currículo, no una copia a mano. */
const asignaturas = Object.values(
    semilla.grados.flatMap((g) => g.asignaturas).reduce((acc, a) => ({ [a.codigo]: a, ...acc }), {}),
);

describe('la función de contraste', () => {
    it('reproduce los valores canónicos de WCAG', () => {
        expect(contraste('#ffffff', '#000000')).toBeCloseTo(21, 5);
        expect(contraste('#000000', '#ffffff')).toBeCloseTo(21, 5); // simétrica
        expect(contraste('#777777', '#ffffff')).toBeCloseTo(4.48, 2);
        expect(contraste('#ffffff', '#ffffff')).toBeCloseTo(1, 5);
    });

    it('mide la luminancia con la curva sRGB, no con el promedio ingenuo', () => {
        // El verde pesa 0.7152: un promedio plano daría lo mismo para los tres.
        expect(luminancia('#00ff00')).toBeGreaterThan(luminancia('#ff0000'));
        expect(luminancia('#ff0000')).toBeGreaterThan(luminancia('#0000ff'));
        expect(luminancia('#ffffff')).toBeCloseTo(1, 5);
        expect(luminancia('#000000')).toBeCloseTo(0, 5);
    });
});

describe('cada color de asignatura del currículo', () => {
    it('tiene los 15 colores esperados', () => {
        expect(asignaturas).toHaveLength(15);
    });

    it.each(asignaturas.map((a) => [a.codigo, a.color, a.nombre]))(
        '%s (%s) — el texto encima cumple AA 4.5:1',
        (codigo, color) => {
            const tinta = tintaSobre(color);
            expect(contraste(color, tinta)).toBeGreaterThanOrEqual(4.5);
        },
    );

    it.each(asignaturas.map((a) => [a.codigo, a.color]))(
        '%s (%s) — su versión de texto sobre blanco cumple AA 4.5:1',
        (codigo, color) => {
            expect(contraste(tintaDeAcento(color), '#ffffff')).toBeGreaterThanOrEqual(4.5);
        },
    );

    it.each(asignaturas.map((a) => [a.codigo, a.color]))(
        '%s (%s) — la tinta oscura sobre su fondo suave cumple AA 4.5:1',
        (codigo, color) => {
            expect(contraste(mezclaConBlanco(color, 0.14), NEGRO_TINTA)).toBeGreaterThanOrEqual(4.5);
        },
    );
});

/**
 * Prueba de que el oráculo de arriba MUERDE: hay colores que no cumplen con
 * ninguna de las dos tintas, y si alguien mete uno en el JSON, el test cae.
 */
it('un gris intermedio no cumple con ninguna tinta — el umbral no es decorativo', () => {
    const gris = '#7c7c7c'; // 4.17:1 con blanco, 4.28:1 con la tinta oscura
    expect(contraste(gris, '#ffffff')).toBeLessThan(4.5);
    expect(contraste(gris, NEGRO_TINTA)).toBeLessThan(4.5);
    expect(contraste(gris, tintaSobre(gris))).toBeLessThan(4.5);
});

describe('estiloDeAsignatura', () => {
    it('devuelve las cuatro variables CSS', () => {
        const estilo = estiloDeAsignatura('#3aa675');
        expect(estilo['--acento']).toBe('#3aa675');
        expect(estilo['--acento-suave']).toMatch(/^#[0-9a-f]{6}$/);
        expect(estilo['--acento-tinta']).toMatch(/^#[0-9a-f]{6}$/);
        expect(estilo['--acento-sobre']).toBe(NEGRO_TINTA);
    });

    it('degrada a un gris neutro si el nodo no trae color', () => {
        for (const malo of [undefined, null, '', 'rojo', '#ggg', 'javascript:alert(1)']) {
            expect(estiloDeAsignatura(malo)['--acento']).toBe('#64748b');
        }
    });

    it('no deja pasar un valor arbitrario a la variable CSS', () => {
        // Un attrs manipulado no debe poder inyectar `url(...)` ni `expression(...)`.
        expect(estiloDeAsignatura('#3aa675; background:url(http://x)')['--acento']).toBe('#64748b');
    });
});
