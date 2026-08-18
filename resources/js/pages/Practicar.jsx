import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '../layouts/AppLayout';
import { RAZONES_DESVIO } from '../lib/razones';

/**
 * El bucle de práctica: pide el siguiente ítem a la API (misma sesión),
 * muestra el enunciado instanciado, verifica en servidor y da
 * retroalimentación inmediata. El `reason` del selector adaptativo se
 * traduce a lenguaje de alumno. Nada sensible llega aquí: la API solo
 * revela `expected` DESPUÉS de responder.
 */

// Los textos viven en lib/razones.js: los comparte con /inicio para que el
// alumno no lea dos explicaciones distintas de la misma decisión del motor.
const RAZONES = RAZONES_DESVIO;

function tokenXsrf() {
    const par = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return par ? decodeURIComponent(par[1]) : '';
}

async function pedirJson(url, opciones = {}) {
    const respuesta = await fetch(url, {
        credentials: 'same-origin',
        ...opciones,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': tokenXsrf(),
            ...(opciones.headers ?? {}),
        },
    });

    return respuesta;
}

export default function Practicar({ objective, mastery: masteryInicial }) {
    // estado: cargando | listo | enviando | respondido | sin-items | sesion | error
    const [estado, setEstado] = useState('cargando');
    const [item, setItem] = useState(null);
    const [respuesta, setRespuesta] = useState('');
    const [resultado, setResultado] = useState(null);
    const [mastery, setMastery] = useState(masteryInicial);
    const inicioItem = useRef(null);
    const inputRef = useRef(null);
    const feedbackRef = useRef(null);

    const cargarSiguiente = useCallback(async () => {
        setEstado('cargando');
        setResultado(null);
        setRespuesta('');

        try {
            const r = await pedirJson(`/api/v1/objectives/${objective.id}/practice/next`);

            if (r.status === 401) return setEstado('sesion');
            if (r.status === 404) return setEstado('sin-items');
            if (!r.ok) return setEstado('error');

            setItem(await r.json());
            inicioItem.current = Date.now();
            setEstado('listo');
        } catch {
            setEstado('error');
        }
    }, [objective.id]);

    useEffect(() => {
        if (objective.has_items) {
            cargarSiguiente();
        } else {
            setEstado('sin-items');
        }
    }, [objective.has_items, cargarSiguiente]);

    // El foco acompaña el flujo: al ítem nuevo, al campo; al resultado, al feedback.
    useEffect(() => {
        if (estado === 'listo') inputRef.current?.focus();
        if (estado === 'respondido') feedbackRef.current?.focus();
    }, [estado]);

    async function enviar(evento) {
        evento.preventDefault();
        if (respuesta.trim() === '' || estado !== 'listo') return;

        setEstado('enviando');
        try {
            const r = await pedirJson(`/api/v1/practice/items/${item.item_id}/attempts`, {
                method: 'POST',
                body: JSON.stringify({
                    answer: Number(respuesta),
                    time_ms: Date.now() - inicioItem.current,
                }),
            });

            if (r.status === 401) return setEstado('sesion');
            if (r.status === 409) return cargarSiguiente();   // intento duplicado: pedir el siguiente
            if (!r.ok) return setEstado('error');

            setResultado(await r.json());
            setEstado('respondido');
            await actualizarMastery();
        } catch {
            setEstado('error');
        }
    }

    async function actualizarMastery() {
        try {
            const r = await pedirJson('/api/v1/practice/mastery');
            if (!r.ok) return;
            const filas = await r.json();
            const fila = filas.find((f) => f.objective_id === (item?.objective_id ?? objective.id));
            if (fila) setMastery(fila.mastery);
        } catch {
            // la barra simplemente no se actualiza; no es un error de flujo
        }
    }

    const razon = item && RAZONES[item.reason];
    const porcentaje = mastery === null || mastery === undefined ? 0 : Math.round(mastery * 100);

    // El selector adaptativo puede DESVIAR a otra destreza (refuerzo de un
    // prerrequisito o avance). La cabecera tiene que hablar de la destreza del
    // ítem que se está resolviendo, no de la de la URL: si no, el alumno lee
    // «Determinar el coeficiente de rozamiento» sobre un ejercicio de plano
    // inclinado, y la barra de dominio (que sí sigue a item.objective_id)
    // salta bajo una etiqueta que no le corresponde.
    const codigo = item?.objective_code ?? objective.native_code;
    const enunciado = item?.objective_statement ?? objective.statement;
    const desviado = Boolean(item && item.objective_id !== objective.id);

    return (
        <AppLayout title={`Practicar ${codigo}`}>
            <Head title={`Practicar ${codigo}`} />

            <p className="mb-4 text-sm text-slate-600">{enunciado}</p>

            {/* Barra de dominio: progressbar real, con texto además del color.
                La transición es explícita sobre `width` y se apaga si el
                sistema pide menos movimiento (motion-reduce): una barra que
                repta puede marear, y aquí se mueve en cada respuesta. */}
            <div className="mb-6">
                <div className="mb-1 flex justify-between text-sm">
                    <span id="etiqueta-dominio">
                        Dominio de {desviado ? codigo : 'la destreza'}
                    </span>
                    <span aria-hidden="true">{porcentaje} %</span>
                </div>
                <div
                    role="progressbar"
                    aria-labelledby="etiqueta-dominio"
                    aria-valuenow={porcentaje}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuetext={`${porcentaje} por ciento`}
                    className="h-3 overflow-hidden rounded-full bg-slate-200"
                >
                    <div
                        className="h-full rounded-full bg-marca-600 transition-[width] duration-700 ease-out motion-reduce:transition-none"
                        style={{ width: `${porcentaje}%` }}
                    />
                </div>
            </div>

            {estado === 'cargando' && <p role="status">Preparando tu siguiente ejercicio…</p>}

            {estado === 'sin-items' && (
                <p role="status">
                    Esta destreza todavía no tiene ejercicios de práctica. Vuelve a tu curso de
                    Moodle y elige otra actividad.
                </p>
            )}

            {estado === 'sesion' && (
                <p role="alert">
                    Tu sesión caducó. <a className="underline" href="/entrar">Vuelve a entrar desde Moodle</a>.
                </p>
            )}

            {estado === 'error' && (
                <div role="alert">
                    <p>No pudimos cargar el ejercicio (¿se cortó la conexión?).</p>
                    <button
                        type="button"
                        onClick={cargarSiguiente}
                        className="mt-2 rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                    >
                        Reintentar
                    </button>
                </div>
            )}

            {(estado === 'listo' || estado === 'enviando') && item && (
                <form onSubmit={enviar} aria-describedby={razon ? 'razon-adaptativa' : undefined}>
                    {/* El desvío adaptativo se explica en una TARJETA ámbar, no
                        en una línea suelta: es una decisión del motor que
                        cambia lo que el alumno tiene delante, y merece que se
                        note. El texto lo dice entero — el ámbar solo lo señala. */}
                    {razon && (
                        <div
                            id="razon-adaptativa"
                            className="mb-4 flex gap-3 rounded-lg border border-l-4 border-amber-200 border-l-amber-500 bg-amber-50 p-3"
                        >
                            <span aria-hidden="true" className="text-xl leading-none">
                                {razon.icono}
                            </span>
                            <p className="text-sm leading-relaxed text-amber-900">{razon.texto}</p>
                        </div>
                    )}

                    {/* El enunciado es LO QUE SE LEE: grande, con aire y sin
                        competir con nada. React escapa por defecto; el texto
                        viene de PDFs importados. */}
                    <p className="mb-5 rounded-lg border border-slate-200 bg-white p-4 text-xl leading-relaxed text-slate-900">
                        {item.statement.es}
                    </p>

                    <div className="mb-4 flex items-end gap-2">
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium">
                                Tu respuesta{item.answer_unit ? ` (en ${item.answer_unit})` : ''}
                            </span>
                            <input
                                ref={inputRef}
                                type="number"
                                inputMode="decimal"
                                step="any"
                                required
                                value={respuesta}
                                onChange={(e) => setRespuesta(e.target.value)}
                                className="w-40 rounded border border-slate-300 px-3 py-2 focus:outline-2 focus:outline-marca-600"
                            />
                        </label>
                        {item.answer_unit && (
                            <span className="pb-2 text-slate-600" aria-hidden="true">
                                {item.answer_unit}
                            </span>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={estado === 'enviando'}
                        className="rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50"
                    >
                        {estado === 'enviando' ? 'Comprobando…' : 'Comprobar'}
                    </button>
                </form>
            )}

            {/* aria-live: el resultado se anuncia a lectores de pantalla. */}
            <div aria-live="polite">
                {estado === 'respondido' && resultado && (
                    <div
                        ref={feedbackRef}
                        tabIndex={-1}
                        className={`flex gap-4 rounded-lg border border-l-4 p-4 ${
                            resultado.is_correct
                                ? 'border-emerald-200 border-l-emerald-600 bg-emerald-50'
                                : 'border-rose-200 border-l-rose-600 bg-rose-50'
                        }`}
                    >
                        {/* Icono GRANDE + texto: jamás solo el color, y a un
                            tamaño que se ve de reojo desde el teclado. */}
                        <span
                            aria-hidden="true"
                            className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xl font-bold text-white ${
                                resultado.is_correct ? 'bg-emerald-600' : 'bg-rose-600'
                            }`}
                        >
                            {resultado.is_correct ? '✓' : '✗'}
                        </span>

                        <div>
                            <p
                                className={`text-lg font-semibold ${
                                    resultado.is_correct ? 'text-emerald-900' : 'text-rose-900'
                                }`}
                            >
                                {resultado.is_correct ? 'Correcto.' : 'Incorrecto.'}
                            </p>
                            <p className="mt-1 text-sm text-slate-700">
                                Tu respuesta: {resultado.answer}. Valor esperado:{' '}
                                {Math.round(resultado.expected * 1000) / 1000}
                                {item?.answer_unit ? ` ${item.answer_unit}` : ''}.
                            </p>
                            <button
                                type="button"
                                onClick={cargarSiguiente}
                                className="mt-3 rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                            >
                                Siguiente ejercicio
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
