import { Head, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '../layouts/AppLayout';
import { Ejercicio, Veredicto, cuerpoDeRespuesta, estaIncompleta, forma, valorInicial } from '../components/Ejercicio';
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

/**
 * El aviso honesto del invitado. Va DOS veces —encima del ejercicio y dentro
 * del bloque de resultado— porque el momento en que un alumno se pregunta «¿me
 * ha contado esto?» es justo después de acertar, no al abrir la página.
 *
 * Con sesión no aparece ninguna de las dos: al alumno no se le repite en cada
 * pantalla algo que ya es su caso normal.
 */
function AvisoDeInvitado({ compacto = false }) {
    if (compacto) {
        return (
            <p className="mt-3 text-sm text-slate-700">
                Esto no se ha guardado.{' '}
                <a
                    href="/entrar"
                    className="font-medium underline hover:text-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                >
                    Entra desde tu aula virtual
                </a>{' '}
                para conservar tu avance.
            </p>
        );
    }

    return (
        <div className="mb-6 flex gap-3 rounded-lg border border-l-4 border-amber-200 border-l-amber-500 bg-amber-50 p-3">
            <span aria-hidden="true" className="text-xl leading-none">
                👋
            </span>
            <p className="text-sm leading-relaxed text-amber-900">
                Estás practicando como visitante: <strong>tu avance no se guarda</strong>.{' '}
                <a
                    href="/entrar"
                    className="font-medium underline hover:text-amber-950 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                >
                    Entra desde tu aula virtual
                </a>{' '}
                para conservarlo y que cuente en tu curso.
            </p>
        </div>
    );
}

/**
 * `revision` = el ID DEL ÍTEM que un docente está revisando, o null.
 *
 * En revisión la página es EXACTAMENTE la misma —esa es la idea: se revisa lo
 * que se publica, no una maqueta— y solo cambian dos URLs: el ejercicio se pide
 * a la API de revisión (la de práctica no sirve lo que aún no está firmado) y
 * la respuesta se corrige sin guardar nada. Ni intento, ni dominio, ni nota.
 */
export default function Practicar({ objective, mastery: masteryInicial, lengua = null, repaso = false, revision = null }) {
    // estado: cargando | listo | enviando | respondido | sin-items | sesion |
    //         demasiadas | error
    const [estado, setEstado] = useState('cargando');
    const [item, setItem] = useState(null);
    const [valor, setValor] = useState(valorInicial());
    const [resultado, setResultado] = useState(null);
    const [mastery, setMastery] = useState(masteryInicial);
    const [tanteo, setTanteo] = useState({ aciertos: 0, respondidos: 0 });
    const [faltaElegir, setFaltaElegir] = useState(false);
    const inicioItem = useRef(null);
    const inputRef = useRef(null);
    const feedbackRef = useRef(null);

    // Cómo se responde este ítem lo decide `Ejercicio.forma`, la MISMA pieza
    // que usa la prueba de unidad: el bucle de práctica no sabe de tipos.
    const { itemRoto, esOrden, esPares } = forma(item);

    const { props: compartidas } = usePage();
    const invitado = !compartidas.auth?.user;
    const invitadoRef = useRef(invitado);
    invitadoRef.current = invitado;

    // El invitado no tiene historial en el servidor del que deducir por qué
    // intento va, así que lo lleva él. En un ref y no en estado: `cargarSiguiente`
    // lo lee y no debe re-crearse (dispararía el efecto de carga en bucle).
    const intento = useRef(1);

    const cargarSiguiente = useCallback(async () => {
        setEstado('cargando');
        setResultado(null);
        setValor(valorInicial());
        setFaltaElegir(false);

        try {
            const r = await pedirJson(
                revision
                    // Revisión: un ítem CONCRETO, aún sin firmar. Mismo payload.
                    ? `/api/v1/revision/items/${revision}/next?intento=${intento.current}`
                    // La lengua del curso acompaña CADA petición: el servidor solo
                    // sirve italiano si se le pide italiano (regla cerrada).
                    : `/api/v1/objectives/${objective.id}/practice/next?intento=${intento.current}` +
                        (lengua ? `&lengua=${lengua}` : '') +
                        (repaso ? '&repaso=1' : ''),
            );

            if (r.status === 401) return setEstado('sesion');
            if (r.status === 404) return setEstado('sin-items');
            if (r.status === 429) return setEstado('demasiadas');
            if (!r.ok) return setEstado('error');

            const siguiente = await r.json();

            // La prop `auth` se renderizó cuando la sesión aún vivía; el
            // servidor es el único que sabe si AHORA se guarda. Si el alumno
            // creía tener sesión y ya no la tiene, se le dice — antes seguía
            // practicando en el vacío con la barra congelada (auditoría).
            if (!invitadoRef.current && siguiente.se_guarda === false) {
                return setEstado('sesion');
            }

            setItem(siguiente);
            inicioItem.current = Date.now();
            setEstado('listo');
        } catch {
            setEstado('error');
        }
    }, [objective.id, lengua, repaso, revision]);

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
        if (estado !== 'listo') return;

        // Sin responder no se envía — pero tampoco se calla. Antes era un
        // `return` mudo: quien navega con teclado o lector de pantalla pulsaba
        // «Comprobar» y no pasaba absolutamente nada, sin saber por qué.
        const incompleto = estaIncompleta(item, valor);
        if (incompleto) {
            setFaltaElegir(true);
            if (!esOrden && !esPares) inputRef.current?.focus();

            return;
        }
        setFaltaElegir(false);

        setEstado('enviando');
        try {
            const r = await pedirJson(revision
                // Revisión: se corrige con el MISMO motor y no se guarda nada.
                ? `/api/v1/revision/items/${item.item_id}/attempts`
                : `/api/v1/practice/items/${item.item_id}/attempts`, {
                method: 'POST',
                body: JSON.stringify({
                    // QUÉ CAMPO VIAJA lo decide `cuerpoDeRespuesta`, la misma
                    // pieza que usa la prueba de unidad: un choice manda la
                    // clave, un numérico el número, los de lengua `respuesta`.
                    ...cuerpoDeRespuesta(item, valor),
                    time_ms: Date.now() - inicioItem.current,
                    // EL BILLETE que vino con el ítem, tal cual. Dentro lleva
                    // firmados el número de intento y la semilla con la que se
                    // generaron los números que el alumno tiene delante, así
                    // que se corrige contra lo que vio y no contra lo que el
                    // servidor deduzca de la tabla un segundo más tarde.
                    // El cliente no lo lee ni lo construye: lo devuelve.
                    billete: item.billete,
                }),
            });

            if (r.status === 401) return setEstado('sesion');
            if (r.status === 409) return cargarSiguiente();   // intento duplicado: pedir el siguiente
            if (r.status === 429) return setEstado('demasiadas');
            if (!r.ok) return setEstado('error');

            const veredicto = await r.json();

            // Misma comprobación que al pedir el ítem: si el alumno creía tener
            // sesión y el servidor dice que esto no se ha guardado, se le avisa
            // en vez de darle un «Correcto» que no cuenta para nada.
            if (!invitado && veredicto.se_guarda === false) {
                return setEstado('sesion');
            }

            setResultado(veredicto);
            setEstado('respondido');

            if (invitado || revision) {
                // Ni el invitado ni la revisión tienen dominio que actualizar:
                // los dos llevan su tanteo aquí y solo aquí. El tanteo del
                // invitado vive AQUÍ y solo aquí: al recargar
                // desaparece, que es exactamente lo que dice el aviso. Y el
                // siguiente ejercicio necesita otro número de intento para no
                // repetir los mismos números.
                // El servidor acepta como mucho intento=500; al llegar se
                // vuelve a empezar en vez de pedir un 501 que dejaba la página
                // muerta con un mensaje falso (auditoría).
                intento.current = ((item.attempt_no ?? 1) % 500) + 1;
                setTanteo((t) => ({
                    aciertos: t.aciertos + (veredicto.is_correct ? 1 : 0),
                    respondidos: t.respondidos + 1,
                }));
            } else {
                await actualizarMastery();
            }
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
    // Dos medidas distintas, y a propósito. El alumno ve su DOMINIO —la EMA que
    // el servidor guarda y que viaja a Moodle—. El invitado no tiene dominio
    // que enseñar, así que ve sus aciertos de esta sesión: un número honesto,
    // calculado aquí, que no finge ser un expediente. Fingir un «dominio» de
    // invitado obligaría además a duplicar la fórmula del MasteryTracker en el
    // cliente, y esa es exactamente la clase de copia que acaba divergiendo.
    const porcentaje = (invitado || revision)
        ? (tanteo.respondidos === 0 ? 0 : Math.round((tanteo.aciertos / tanteo.respondidos) * 100))
        : (mastery === null || mastery === undefined ? 0 : Math.round(mastery * 100));

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

            {invitado && <AvisoDeInvitado />}

            {/* Barra de dominio: progressbar real, con texto además del color.
                La transición es explícita sobre `width` y se apaga si el
                sistema pide menos movimiento (motion-reduce): una barra que
                repta puede marear, y aquí se mueve en cada respuesta. */}
            <div className="mb-6">
                <div className="mb-1 flex justify-between text-sm">
                    <span id="etiqueta-dominio">
                        {invitado
                            ? 'Aciertos en esta visita'
                            : `Dominio de ${desviado ? codigo : 'la destreza'}`}
                    </span>
                    <span aria-hidden="true">
                        {invitado
                            ? `${tanteo.aciertos} de ${tanteo.respondidos}`
                            : `${porcentaje} %`}
                    </span>
                </div>
                <div
                    role="progressbar"
                    aria-labelledby="etiqueta-dominio"
                    aria-valuenow={porcentaje}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuetext={invitado
                        ? `${tanteo.aciertos} de ${tanteo.respondidos} correctos`
                        : `${porcentaje} por ciento`}
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
                <div role="alert" className="rounded-lg border border-l-4 border-amber-200 border-l-amber-500 bg-amber-50 p-4">
                    <p className="font-semibold text-amber-900">Tu sesión caducó</p>
                    <p className="mt-1 text-sm leading-relaxed text-amber-900">
                        Lo que respondas a partir de ahora no se guardaría.{' '}
                        <a className="font-medium underline" href="/entrar">
                            Vuelve a entrar desde tu aula virtual
                        </a>{' '}
                        y sigues donde ibas — lo que ya tenías guardado sigue ahí.
                    </p>
                    <p className="mt-3 text-sm text-amber-900">
                        También puedes{' '}
                        <a className="font-medium underline" href="/catalogo">
                            seguir practicando como visitante
                        </a>
                        , sin que cuente.
                    </p>
                </div>
            )}

            {estado === 'demasiadas' && (
                <div role="alert" className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p className="text-amber-900">
                        Has pedido muchos ejercicios seguidos. Espera un minuto y vuelve a
                        intentarlo — el límite es por conexión, así que si estás en el colegio
                        puede que lo hayáis alcanzado entre varios.
                    </p>
                    <button
                        type="button"
                        onClick={cargarSiguiente}
                        className="mt-3 rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                    >
                        Reintentar
                    </button>
                </div>
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

            {estado === 'listo' && itemRoto && (
                <div role="alert" className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p className="text-amber-900">
                        Este ejercicio está incompleto y no se puede responder. No es culpa
                        tuya: ya está avisado para que lo revisen.
                    </p>
                    <button
                        type="button"
                        onClick={cargarSiguiente}
                        className="mt-3 rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                    >
                        Probar con otro
                    </button>
                </div>
            )}

            {(estado === 'listo' || estado === 'enviando') && item && !itemRoto && (
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

                    <Ejercicio
                        item={item}
                        valor={valor}
                        onChange={(v) => {
                            setValor(v);
                            setFaltaElegir(false);
                        }}
                        faltaElegir={faltaElegir}
                        inputRef={inputRef}
                    />

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
                            <Veredicto item={item} resultado={resultado} />
                            {invitado && <AvisoDeInvitado compacto />}

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
