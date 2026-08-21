import { createElement } from 'react';

/**
 * Pinta una fórmula a partir del ÁRBOL MathML que envía el servidor.
 *
 * Tres cosas que este componente NO hace, y son el motivo de que exista:
 *
 *  - No interpreta LaTeX. Eso pasó en el servidor, al sembrar. Traerse KaTeX
 *    serían ~280 KB sobre un presupuesto de 450 (vamos por 366): lo reventaría
 *    él solo. MathML lo pinta el navegador de forma nativa, gratis.
 *  - No usa `dangerouslySetInnerHTML`. El árbol se convierte en elementos con
 *    `createElement`, así que un nodo llamado `script` no sería un `<script>`:
 *    sería un elemento desconocido e inerte. Y ni eso llega, porque el servidor
 *    solo emite nombres de su lista blanca.
 *  - No decide nada de accesibilidad por su cuenta: MathML es texto para el
 *    lector de pantalla, que lee «un medio» donde una imagen callaría.
 */

/** Los únicos elementos que puede emitir el servidor (App\Services\Lesson\MathML). */
const PERMITIDOS = new Set(['math', 'mn', 'mi', 'mo', 'mrow', 'mfrac', 'msup', 'msub', 'msqrt']);

function nodo(n, clave) {
    if (!n || !PERMITIDOS.has(n.e)) {
        // Cinturón: si algún día llegara un nodo que el servidor no debería
        // haber emitido, se cae al texto en vez de pintar un elemento raro.
        return n?.t ?? null;
    }

    if (n.t !== undefined) {
        return createElement(n.e, { key: clave }, n.t);
    }

    return createElement(
        n.e,
        { key: clave },
        (n.h ?? []).map((hijo, i) => nodo(hijo, i)),
    );
}

export default function Formula({ arbol, etiqueta }) {
    if (!arbol) {
        return null;
    }

    return (
        <div className="my-4 overflow-x-auto text-center">
            {/* `display="block"` centra y agranda, como una ecuación mostrada. */}
            {createElement(
                'math',
                { display: 'block', className: 'text-xl', 'aria-label': etiqueta || undefined },
                (arbol.h ?? []).map((h, i) => nodo(h, i)),
            )}
            {etiqueta && <p className="mt-1 text-sm text-slate-600">{etiqueta}</p>}
        </div>
    );
}
