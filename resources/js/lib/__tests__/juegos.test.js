import { afterEach, describe, expect, it } from 'vitest';
import { barajar, columnasDeEmparejar, generador, grupoCompleto, guardarMarca, hash32, leerMarca, rondasDeCualSobra, tableroDeMemoria } from '../juegos';

/** PR 15 · los juegos, la parte pura: barajar con semilla y armar cada juego. */

const T = (n, lengua = 'it') => Array.from({ length: n }, (_, i) => ({
    id: `t${i + 1}`, palabra: `palabra${i + 1}`, lectura: lengua === 'zh' ? `pin${i + 1}` : null, significado: `sig${i + 1}`,
}));
const INTRUSAS = [{ id: 'x1', palabra: 'lunedi', lectura: null, significado: 'lunes', unidad: 3 }, { id: 'x2', palabra: 'martedi', lectura: null, significado: 'martes', unidad: 4 }];

describe('barajar con semilla', () => {
    it('la misma semilla, el mismo orden; otra, otro; y no pierde ni repite', () => {
        const lista = T(12);
        const a = barajar(lista, 'una');
        const b = barajar(lista, 'una');
        const c = barajar(lista, 'otra');
        expect(a).toEqual(b);
        expect(a).not.toEqual(c);
        expect(a.map((t) => t.id).sort()).toEqual(lista.map((t) => t.id).sort());
        expect(lista[0].id).toBe('t1');   // no muta la original
    });

    it('el generador es determinista y queda en [0, 1)', () => {
        const g1 = generador('s');
        const g2 = generador('s');
        const v = Array.from({ length: 50 }, () => g1());
        expect(Array.from({ length: 50 }, () => g2())).toEqual(v);
        expect(v.every((x) => x >= 0 && x < 1)).toBe(true);
        expect(hash32('a')).not.toBe(hash32('b'));
    });
});

describe('memoria', () => {
    it('it/fr/de: dos cartas por tarjeta; chino: tres (carácter, pinyin, significado)', () => {
        const it = tableroDeMemoria(T(8), 'it', 's');
        expect(it.tamano).toBe(2);
        expect(it.cartas).toHaveLength(12);   // seis grupos como mucho
        expect(new Set(it.cartas.map((c) => c.tarjeta)).size).toBe(6);

        const zh = tableroDeMemoria(T(4, 'zh'), 'zh', 's');
        expect(zh.tamano).toBe(3);
        expect(zh.cartas).toHaveLength(12);
        const caras = zh.cartas.filter((c) => c.tarjeta === 't1').map((c) => c.cara).sort();
        expect(caras).toEqual(['lectura', 'palabra', 'significado']);
    });

    it('un grupo se gana con todas las caras de la MISMA tarjeta', () => {
        const { cartas, tamano } = tableroDeMemoria(T(3, 'zh'), 'zh', 's');
        const de = (id) => cartas.filter((c) => c.tarjeta === id);
        expect(grupoCompleto(de('t1'), tamano)).toBe(true);
        expect(grupoCompleto(de('t1').slice(0, 2), tamano)).toBe(false);
        expect(grupoCompleto([...de('t1').slice(0, 2), de('t2')[0]], tamano)).toBe(false);
    });
});

describe('emparejar contra el reloj', () => {
    it('las dos columnas van barajadas por separado', () => {
        const { palabras, significados } = columnasDeEmparejar(T(10), 's');
        expect(palabras.map((t) => t.id)).not.toEqual(significados.map((t) => t.id));
        expect(palabras.map((t) => t.id).sort()).toEqual(significados.map((t) => t.id).sort());
    });
});

describe('¿cuál sobra?', () => {
    it('cada ronda: tres de la unidad y UNA intrusa, barajadas; sin intrusas o con menos de tres, nada', () => {
        const rondas = rondasDeCualSobra(T(7), INTRUSAS, 's');
        expect(rondas.length).toBeGreaterThan(0);
        for (const r of rondas) {
            expect(r.opciones).toHaveLength(4);
            const intrusas = r.opciones.filter((o) => o.unidad !== null);
            expect(intrusas).toHaveLength(1);
            expect(intrusas[0].id).toBe(r.intrusa);
        }
        // La intrusa no va siempre en el mismo sitio.
        expect(new Set(rondas.map((r) => r.opciones.findIndex((o) => o.id === r.intrusa))).size).toBeGreaterThan(1);

        expect(rondasDeCualSobra(T(2), INTRUSAS, 's')).toEqual([]);
        expect(rondasDeCualSobra(T(7), [], 's')).toEqual([]);
    });
});

describe('la marca personal', () => {
    afterEach(() => localStorage.clear());

    it('es por alumno, lengua y unidad, y solo se guarda si mejora', () => {
        expect(leerMarca(1, 'it', 1)).toBeNull();
        expect(guardarMarca(1, 'it', 1, 5)).toBe(5);
        expect(guardarMarca(1, 'it', 1, 3)).toBe(5);
        expect(guardarMarca(1, 'it', 1, 7)).toBe(7);
        expect(leerMarca(1, 'it', 2)).toBeNull();
        expect(leerMarca(2, 'it', 1)).toBeNull();
        expect(leerMarca(null, 'it', 1)).toBeNull();
    });
});
