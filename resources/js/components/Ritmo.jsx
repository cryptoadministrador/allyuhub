import { useEffect, useState } from 'react';
import { guardarContrarreloj, leerContrarreloj, resumen } from '../lib/ritmo';

/**
 * Las piezas del RITMO (misión 4, PR 14), compartidas por práctica y repaso:
 * dónde vas, cuántos seguidos, el cierre de la tanda y el contrarreloj
 * opcional. Texto siempre; ningún fuego, ningún confeti, ningún ranking.
 */

/** «Ejercicio 4 de 10 · 3 seguidos». Va en un `status` para que se anuncie al cambiar. */
export function Progreso({ k, total, seguidos = 0, etiqueta = 'Ejercicio', extra = null }) {
    return (
        <p className="mb-2 text-sm font-medium text-slate-600" role="status">
            {etiqueta} {k} de {total}
            {seguidos >= 2 && <> · <span className="text-marca-700">{seguidos} seguidos</span></>}
            {extra}
        </p>
    );
}

/** El cierre: cómo fue. Del alumno consigo mismo, y nada más. */
export function CierreDeTanda({ ritmo, total, titulo = 'Tanda hecha', children }) {
    return (
        <div className="mt-6 rounded-lg border border-l-4 border-emerald-200 border-l-emerald-600 bg-emerald-50 p-4">
            <p className="text-xl font-semibold text-slate-900">{titulo}: {resumen(ritmo, total)}</p>
            {children}
        </div>
    );
}

/** El interruptor: apagado por defecto y recordado por alumno (en este navegador). */
export function useContrarreloj(userId) {
    const [activo, setActivo] = useState(() => leerContrarreloj(userId));

    return [activo, (valor) => { setActivo(valor); guardarContrarreloj(userId, valor); }];
}

export function InterruptorContrarreloj({ activo, onChange }) {
    return (
        <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input
                type="checkbox"
                role="switch"
                checked={activo}
                onChange={(e) => onChange(e.target.checked)}
                className="h-4 w-4 accent-marca-600 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
            />
            Contrarreloj
        </label>
    );
}

/**
 * La cuenta atrás de un ejercicio. Arranca con `clave` (cambia por ítem) y
 * avisa una vez al llegar a cero. `role="timer"` y sin `aria-live`: un lector
 * de pantalla que anuncie cada segundo es peor que ningún reloj.
 */
export function Cronometro({ segundos, clave, onAgotado }) {
    const [restan, setRestan] = useState(segundos);

    useEffect(() => {
        setRestan(segundos);
        const inicio = Date.now();
        const id = setInterval(() => {
            const quedan = Math.max(0, segundos - Math.floor((Date.now() - inicio) / 1000));
            setRestan(quedan);
            if (quedan === 0) {
                clearInterval(id);
                onAgotado();
            }
        }, 250);

        return () => clearInterval(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [clave, segundos]);

    const mm = Math.floor(restan / 60);
    const ss = String(restan % 60).padStart(2, '0');

    return (
        <p role="timer" aria-label="Tiempo restante" className={`mb-2 text-sm font-medium tabular-nums ${restan <= 5 ? 'text-rose-800' : 'text-slate-700'}`}>
            ⏱ {mm}:{ss}
        </p>
    );
}
