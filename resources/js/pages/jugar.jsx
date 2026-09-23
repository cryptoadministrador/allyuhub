import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '../layouts/AppLayout';
import { Cronometro, InterruptorContrarreloj, useContrarreloj } from '../components/Ritmo';
import { columnasDeEmparejar, grupoCompleto, guardarMarca, leerMarca, rondasDeCualSobra, tableroDeMemoria } from '../lib/juegos';

/**
 * JUGAR CON LAS PALABRAS (misión 4, PR 15): tres juegos sobre el vocabulario
 * firmado de la unidad, sin escribir ni un dato nuevo. Memoria (parejas boca
 * abajo; en chino, tríos carácter · pinyin · significado), emparejar contra
 * el reloj (opcional: exige el interruptor) y ¿cuál sobra? (tres de la unidad
 * y una intrusa de otra).
 *
 * Los juegos NO dan dominio ni AGS. Al acertar marcan «la sé» en la tarjeta,
 * por el endpoint del mazo; el invitado juega entero y no escribe nada. Todo
 * se juega con teclado: cada carta es un botón. Puntos del alumno consigo
 * mismo (su marca anterior), y de nadie más.
 */

function tokenXsrf() {
    const par = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return par ? decodeURIComponent(par[1]) : '';
}

function laSe(id) {
    return fetch(`/api/v1/vocabulario/${id}/estado`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': tokenXsrf() },
        body: JSON.stringify({ conocida: true }),
    }).catch(() => null);
}

const BOTON = 'rounded px-4 py-2 font-medium focus:outline-2 focus:outline-offset-2 focus:outline-marca-600';
const PRIMARIO = `${BOTON} bg-marca-600 text-white hover:bg-marca-700`;
const CARTA = 'flex min-h-16 w-full items-center justify-center rounded-lg border-2 px-2 py-3 text-center text-base focus:outline-2 focus:outline-offset-2 focus:outline-marca-600';

// ---------------------------------------------------------------- memoria

function Memoria({ tarjetas, lengua, semilla, marcar }) {
    const [{ cartas, tamano }] = useState(() => tableroDeMemoria(tarjetas, lengua, semilla));
    const [levantadas, setLevantadas] = useState([]);       // ids boca arriba en esta jugada
    const [ganadas, setGanadas] = useState(new Set());        // tarjetas completadas
    const [jugadas, setJugadas] = useState(0);
    const [falladas, setFalladas] = useState(false);
    const temporizador = useRef(null);

    useEffect(() => () => clearTimeout(temporizador.current), []);

    const grupos = cartas.length / tamano;
    const completa = ganadas.size === grupos;

    function voltear(carta) {
        if (levantadas.includes(carta.id) || ganadas.has(carta.tarjeta) || falladas) return;
        const ahora = [...levantadas.map((id) => cartas.find((c) => c.id === id)), carta];
        setLevantadas(ahora.map((c) => c.id));
        if (ahora.length < tamano) return;

        setJugadas((j) => j + 1);
        if (grupoCompleto(ahora, tamano)) {
            setGanadas((g) => new Set([...g, carta.tarjeta]));
            setLevantadas([]);
            marcar(carta.tarjeta);
        } else {
            // Se ven un momento y vuelven boca abajo. Sin movimiento: solo tiempo.
            setFalladas(true);
            temporizador.current = setTimeout(() => { setLevantadas([]); setFalladas(false); }, 900);
        }
    }

    return (
        <div>
            <p role="status" className="mb-3 text-sm text-slate-700">
                {completa
                    ? `¡Memoria completa! ${grupos} ${tamano === 3 ? 'tríos' : 'parejas'} en ${jugadas} jugadas.`
                    : `${ganadas.size} de ${grupos} ${tamano === 3 ? 'tríos' : 'parejas'} · ${jugadas} jugadas`}
            </p>
            <div className={`grid gap-2 ${tamano === 3 ? 'grid-cols-3' : 'grid-cols-3 sm:grid-cols-4'}`}>
                {cartas.map((carta, i) => {
                    const arriba = levantadas.includes(carta.id) || ganadas.has(carta.tarjeta);
                    const ganada = ganadas.has(carta.tarjeta);

                    return (
                        <button
                            key={carta.id}
                            type="button"
                            disabled={ganada}
                            aria-pressed={arriba}
                            aria-label={arriba ? `Carta ${i + 1}: ${carta.texto}${ganada ? ', hecha' : ''}` : `Carta ${i + 1}, boca abajo`}
                            onClick={() => voltear(carta)}
                            lang={arriba && carta.lang ? carta.lang : undefined}
                            className={`${CARTA} ${ganada
                                ? 'border-emerald-600 bg-emerald-50 text-emerald-900'
                                : arriba
                                  ? 'border-marca-600 bg-marca-50 text-marca-900'
                                  : 'border-slate-300 bg-slate-100 text-slate-500 hover:bg-slate-200'}`}
                        >
                            {arriba ? carta.texto : '?'}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

// ---------------------------------------------------------------- contra el reloj

const SEGUNDOS_RELOJ = 60;

function ContraElReloj({ tarjetas, lengua, semilla, marcar, userId, unidad }) {
    const [contrarreloj, setContrarreloj] = useContrarreloj(userId);
    const [fase, setFase] = useState('antes');   // antes | jugando | fin
    const [{ palabras, significados }] = useState(() => columnasDeEmparejar(tarjetas, semilla));
    const [hechas, setHechas] = useState(new Set());
    const [elegida, setElegida] = useState({});   // {palabra?: id, significado?: id}
    const [fallo, setFallo] = useState(false);
    const [marca, setMarca] = useState(() => leerMarca(userId, lengua, unidad));

    function tocar(col, id) {
        if (fase !== 'jugando' || hechas.has(id)) return;
        const sel = { ...elegida, [col]: elegida[col] === id ? undefined : id };
        if (sel.palabra && sel.significado) {
            if (sel.palabra === sel.significado) {
                setHechas((h) => new Set([...h, id]));
                marcar(id);
                setFallo(false);
            } else {
                setFallo(true);
            }
            setElegida({});

            return;
        }
        setElegida(sel);
    }

    function terminar() {
        setFase('fin');
        setMarca(guardarMarca(userId, lengua, unidad, hechas.size));
    }

    if (!contrarreloj) {
        return (
            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p className="text-sm text-slate-700">
                    Este juego va contra el reloj: 60 segundos para emparejar todas las que puedas, contra tu propia marca y la de nadie más.
                    Se activa con el contrarreloj — empieza apagado a propósito.
                </p>
                <div className="mt-3"><InterruptorContrarreloj activo={contrarreloj} onChange={setContrarreloj} /></div>
            </div>
        );
    }

    return (
        <div>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p role="status" className="text-sm text-slate-700">
                    {fase === 'fin'
                        ? `${hechas.size} ${hechas.size === 1 ? 'pareja' : 'parejas'} en 60 segundos${marca !== null && marca > hechas.size ? ` · tu marca: ${marca}` : ' · tu mejor marca'}`
                        : `${hechas.size} de ${tarjetas.length} parejas${marca !== null ? ` · tu marca anterior: ${marca}` : ''}`}
                </p>
                <InterruptorContrarreloj activo={contrarreloj} onChange={setContrarreloj} />
            </div>
            {fase === 'antes' && (
                <button type="button" onClick={() => setFase('jugando')} className={PRIMARIO}>Empezar (60 s)</button>
            )}
            {fase === 'jugando' && <Cronometro segundos={SEGUNDOS_RELOJ} clave="reloj" onAgotado={terminar} />}
            {fase !== 'antes' && (
                <div className={`grid grid-cols-2 gap-3 ${fallo ? 'motion-safe:animate-sacudida' : ''}`} onAnimationEnd={() => setFallo(false)}>
                    {[['palabra', palabras], ['significado', significados]].map(([col, lista]) => (
                        <div key={col} role="group" aria-label={col === 'palabra' ? 'Palabras' : 'Significados'} className="space-y-2">
                            {lista.map((t) => (
                                <button
                                    key={`${col}:${t.id}`}
                                    type="button"
                                    disabled={fase !== 'jugando' || hechas.has(t.id)}
                                    aria-pressed={elegida[col] === t.id}
                                    lang={col === 'palabra' ? lengua : undefined}
                                    onClick={() => tocar(col, t.id)}
                                    className={`${CARTA} min-h-12 py-2 ${hechas.has(t.id)
                                        ? 'border-emerald-600 bg-emerald-50 text-emerald-900'
                                        : elegida[col] === t.id
                                          ? 'border-marca-600 bg-marca-50 text-marca-900'
                                          : 'border-slate-300 bg-white text-slate-900 hover:bg-slate-50'}`}
                                >
                                    {col === 'palabra' ? (lengua === 'zh' && t.lectura ? `${t.palabra} · ${t.lectura}` : t.palabra) : t.significado}
                                </button>
                            ))}
                        </div>
                    ))}
                </div>
            )}
            {fase === 'fin' && (
                <button type="button" onClick={() => { setHechas(new Set()); setElegida({}); setFase('jugando'); }} className={`${PRIMARIO} mt-4`}>
                    Otra vez
                </button>
            )}
        </div>
    );
}

// ---------------------------------------------------------------- ¿cuál sobra?

function CualSobra({ tarjetas, intrusas, lengua, semilla, unidad, marcar }) {
    const [rondas] = useState(() => rondasDeCualSobra(tarjetas, intrusas, semilla));
    const [r, setR] = useState(0);
    const [intentos, setIntentos] = useState(0);
    const [aciertos, setAciertos] = useState(0);
    const [veredicto, setVeredicto] = useState(null);   // null | 'bien' | 'casi' | 'revelada'

    if (rondas.length === 0) {
        return <p role="status" className="text-sm text-slate-700">Para este juego hacen falta tres palabras de la unidad y otra unidad con vocabulario firmado.</p>;
    }
    if (r >= rondas.length) {
        return (
            <div role="status" className="rounded-lg border border-l-4 border-emerald-200 border-l-emerald-600 bg-emerald-50 p-4">
                <p className="text-xl font-semibold text-slate-900">¿Cuál sobra?: {aciertos} de {rondas.length} a la primera.</p>
            </div>
        );
    }

    const ronda = rondas[r];
    const intrusa = ronda.opciones.find((o) => o.id === ronda.intrusa);

    function elegir(opcion) {
        if (veredicto === 'bien' || veredicto === 'revelada') return;
        if (opcion.id === ronda.intrusa) {
            if (intentos === 0) {
                setAciertos((a) => a + 1);
                // Reconocer las tres de la unidad a la primera es saberlas.
                ronda.opciones.filter((o) => o.id !== ronda.intrusa).forEach((o) => marcar(o.id));
            }
            setVeredicto('bien');
        } else if (intentos + 1 >= 2) {
            setVeredicto('revelada');
        } else {
            setIntentos((i) => i + 1);
            setVeredicto('casi');
        }
    }

    function siguiente() {
        setR((k) => k + 1);
        setIntentos(0);
        setVeredicto(null);
    }

    const cerrada = veredicto === 'bien' || veredicto === 'revelada';

    return (
        <div>
            <p role="status" className="mb-3 text-sm text-slate-700">Ronda {r + 1} de {rondas.length} · tres son de la unidad {unidad}. ¿Cuál sobra?</p>
            <div className={`grid grid-cols-2 gap-3 ${veredicto === 'casi' ? 'motion-safe:animate-sacudida' : ''}`}>
                {ronda.opciones.map((o) => (
                    <button
                        key={o.id}
                        type="button"
                        disabled={cerrada}
                        lang={lengua}
                        onClick={() => elegir(o)}
                        className={`${CARTA} ${cerrada && o.id === ronda.intrusa ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-slate-300 bg-white text-slate-900 hover:bg-slate-50'}`}
                    >
                        {lengua === 'zh' && o.lectura ? `${o.palabra} · ${o.lectura}` : o.palabra}
                    </button>
                ))}
            </div>
            <div aria-live="polite" className="mt-3">
                {veredicto === 'casi' && <p className="text-sm text-rose-900">Todavía no: esa sí es de esta unidad. Inténtalo otra vez.</p>}
                {(veredicto === 'bien' || veredicto === 'revelada') && (
                    <p className={`text-sm ${veredicto === 'bien' ? 'text-emerald-900' : 'text-slate-800'}`}>
                        {veredicto === 'bien' ? '¡Eso es! ' : 'Era '}«{intrusa.palabra}» ({intrusa.significado}) es de la unidad {intrusa.unidad}.
                    </p>
                )}
                {cerrada && (
                    <button type="button" onClick={siguiente} className={`${PRIMARIO} mt-2`}>
                        {r + 1 < rondas.length ? 'Siguiente' : 'Ver cómo fue'}
                    </button>
                )}
            </div>
        </div>
    );
}

// ---------------------------------------------------------------- la página

const JUEGOS = [
    ['memoria', 'Memoria'],
    ['reloj', 'Contra el reloj'],
    ['sobra', '¿Cuál sobra?'],
];

export default function Jugar({ lengua, nombre, unidad, tarjetas, intrusas, semilla, se_guarda: seGuarda }) {
    const { props: compartidas } = usePage();
    const invitado = !compartidas.auth?.user;
    const userId = compartidas.auth?.user?.id ?? null;
    const [juego, setJuego] = useState('memoria');
    const marcadas = useRef(new Set());

    // «La sé» al acertar: una vez por tarjeta y sesión, por el endpoint del
    // mazo. El invitado no escribe nada aunque lo llame (200, sin fila).
    function marcar(id) {
        if (marcadas.current.has(id)) return;
        marcadas.current.add(id);
        laSe(id);
    }

    return (
        <AppLayout>
            <Head title={`Jugar · Unidad ${unidad.n} · ${nombre}`} />
            <div className="mx-auto max-w-2xl">
                <Link href={`/corso/${lengua}/u${unidad.n}`} className="text-sm font-medium text-marca-700 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                    ← Unidad {unidad.n} · {unidad.titulo}
                </Link>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Juega con las palabras</h1>
                <p className="mt-1 text-sm text-slate-700">{tarjetas.length} palabras de la unidad. Lo que aciertes queda marcado como «la sé» en tus tarjetas.</p>
                {invitado && (
                    <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Estás jugando como visitante: <strong>no se guarda</strong> nada.
                    </p>
                )}

                <div role="group" aria-label="Elige un juego" className="mt-4 flex flex-wrap gap-2">
                    {JUEGOS.map(([clave, etiqueta]) => (
                        <button
                            key={clave}
                            type="button"
                            aria-pressed={juego === clave}
                            onClick={() => setJuego(clave)}
                            className={`${BOTON} border ${juego === clave ? 'border-marca-600 bg-marca-600 text-white' : 'border-slate-300 bg-white text-slate-800 hover:bg-slate-50'}`}
                        >
                            {etiqueta}
                        </button>
                    ))}
                </div>

                <section className="mt-5" aria-label={JUEGOS.find(([c]) => c === juego)[1]}>
                    {juego === 'memoria' && <Memoria key={semilla} tarjetas={tarjetas} lengua={lengua} semilla={semilla} marcar={marcar} />}
                    {juego === 'reloj' && <ContraElReloj tarjetas={tarjetas} lengua={lengua} semilla={semilla} marcar={marcar} userId={userId} unidad={unidad.n} />}
                    {juego === 'sobra' && <CualSobra tarjetas={tarjetas} intrusas={intrusas} lengua={lengua} semilla={semilla} unidad={unidad.n} marcar={marcar} />}
                </section>
            </div>
        </AppLayout>
    );
}
