import { afterEach, describe, expect, it } from 'vitest';
import { SEGUNDOS_POR_ITEM, TANDA, claveContrarreloj, guardarContrarreloj, leerContrarreloj, registrar, resumen, ritmoInicial, romper } from '../ritmo';

/** PR 14 · el ritmo, puro: dónde vas, cuántos seguidos, cómo fue. */
describe('ritmo', () => {
    it('la tanda son diez y el reloj veinte segundos', () => {
        expect(TANDA).toBe(10);
        expect(SEGUNDOS_POR_ITEM).toBe(20);
    });

    it('los seguidos suben con cada acierto a la primera y se rompen al fallar', () => {
        let r = ritmoInicial();
        r = registrar(r, true);
        r = registrar(r, true);
        r = registrar(r, true);
        expect(r).toEqual({ respondidos: 3, aciertos: 3, seguidos: 3, mejor: 3 });
        r = romper(r);            // falló: se rompe en el acto, el ejercicio sigue abierto
        expect(r.seguidos).toBe(0);
        r = registrar(r, false);  // …y al cerrarlo no suma
        expect(r).toEqual({ respondidos: 4, aciertos: 3, seguidos: 0, mejor: 3 });
        r = registrar(r, true);
        expect(r).toEqual({ respondidos: 5, aciertos: 4, seguidos: 1, mejor: 3 });
        // Y cerrar con fallo también rompe, sin `romper` antes.
        expect(registrar({ respondidos: 5, aciertos: 4, seguidos: 4, mejor: 4 }, false)).toEqual({ respondidos: 6, aciertos: 4, seguidos: 0, mejor: 4 });
    });

    it('el cierre dice cómo fue, y la mejor racha solo si fue otra', () => {
        expect(resumen({ respondidos: 10, aciertos: 8, seguidos: 2, mejor: 5 }, 10)).toBe('8 de 10 · 2 seguidos al final · mejor racha: 5 seguidos');
        expect(resumen({ respondidos: 10, aciertos: 10, seguidos: 10, mejor: 10 }, 10)).toBe('10 de 10 · 10 seguidos al final');
        expect(resumen({ respondidos: 3, aciertos: 1, seguidos: 1, mejor: 1 })).toBe('1 de 3 · 1 seguido al final');
        expect(resumen({ respondidos: 2, aciertos: 0, seguidos: 0, mejor: 0 })).toBe('0 de 2 · 0 seguidos al final');
    });
});

describe('contrarreloj — apagado por defecto, recordado por alumno', () => {
    afterEach(() => localStorage.clear());

    it('empieza apagado, se recuerda por alumno y no se mezcla con el invitado', () => {
        expect(leerContrarreloj(7)).toBe(false);
        expect(leerContrarreloj(null)).toBe(false);
        guardarContrarreloj(7, true);
        expect(leerContrarreloj(7)).toBe(true);
        expect(leerContrarreloj(8)).toBe(false);
        expect(leerContrarreloj(null)).toBe(false);
        expect(claveContrarreloj(null)).toBe('contrarreloj:invitado');
        guardarContrarreloj(7, false);
        expect(leerContrarreloj(7)).toBe(false);
    });

    it('sin almacenamiento sigue apagado, sin romper', () => {
        const original = Storage.prototype.getItem;
        Storage.prototype.getItem = () => { throw new Error('bloqueado'); };
        try {
            expect(leerContrarreloj(7)).toBe(false);
        } finally {
            Storage.prototype.getItem = original;
        }
    });
});
