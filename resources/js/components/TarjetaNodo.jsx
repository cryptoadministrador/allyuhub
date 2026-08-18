import { Link } from '@inertiajs/react';
import Chip from './Chip';
import { estiloDeAsignatura } from '../lib/color';

/**
 * La tarjeta de un nodo del catálogo: un grado o una asignatura.
 *
 * El color de la asignatura entra por variables CSS calculadas en el cliente
 * (`estiloDeAsignatura`) y se queda en el BORDE y en el fondo suave del icono;
 * el texto encima usa la tinta que cumple 4.5:1 — ningún color del currículo
 * llega a ese contraste con blanco. Y si el nodo no trae color, la tarjeta se
 * pinta igual en gris: el catálogo funciona antes de `curriculo:estilos`.
 *
 * El enlace envuelve solo el título y se estira con `after:absolute`: el lector
 * de pantalla anuncia «1.º BGU, enlace» y no el párrafo de metadatos entero.
 */
export default function TarjetaNodo({ nodo, como: Titular = 'h3' }) {
    const estilo = estiloDeAsignatura(nodo.color);
    const titular = nodo.corto ?? nodo.title;
    const subtitulo = nodo.corto && nodo.corto !== nodo.title ? nodo.title : null;

    return (
        // El borde de color es decoración pura: el nombre de la asignatura
        // manda, y quien no distinga el color no se pierde nada.
        <li
            style={{ ...estilo, borderLeftColor: 'var(--acento)' }}
            className="relative rounded-lg border border-l-4 border-slate-200 bg-white p-4 transition-shadow focus-within:shadow-md hover:shadow-md"
        >
            <div className="flex items-start gap-3">
                {nodo.icon && (
                    <span
                        aria-hidden="true"
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-xl"
                        style={{ background: 'var(--acento-suave)' }}
                    >
                        {nodo.icon}
                    </span>
                )}

                <div className="min-w-0">
                    <Titular className="text-base font-semibold text-slate-900">
                        <Link
                            href={`/catalogo/${nodo.id}`}
                            className="after:absolute after:inset-0 after:rounded-lg hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                        >
                            {titular}
                        </Link>
                    </Titular>

                    {subtitulo && <p className="mt-0.5 text-sm text-slate-600">{subtitulo}</p>}

                    <p className="mt-2 flex flex-wrap items-center gap-1.5">
                        {nodo.edad != null && <Chip>Desde los {nodo.edad} años</Chip>}
                        {nodo.destrezas != null && (
                            <Chip tono="marca">
                                {nodo.destrezas} {nodo.destrezas === 1 ? 'destreza' : 'destrezas'}
                            </Chip>
                        )}
                        {nodo.verificadas > 0 && (
                            <Chip tono="verde" icono="✔">
                                {nodo.verificadas} verificadas
                            </Chip>
                        )}
                        {nodo.practicables > 0 && (
                            <Chip tono="ambar" icono="✎">
                                {nodo.practicables} con ejercicios
                            </Chip>
                        )}
                    </p>
                </div>
            </div>
        </li>
    );
}
