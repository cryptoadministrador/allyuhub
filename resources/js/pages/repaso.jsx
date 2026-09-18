import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Ejercicio, Veredicto, cuerpoDeRespuesta, estaIncompleta, valorInicial } from '../components/Ejercicio';
import AppLayout from '../layouts/AppLayout';

/**
 * «TU REPASO DE HOY»: hasta diez ítems que el servidor elige por prioridad
 * (vencidos del repaso espaciado, fallos recientes, relleno). Se juega COMO LA
 * PRÁCTICA —corrección ítem a ítem, veredicto, siguiente— y cada respuesta va
 * al endpoint de intentos de siempre con el billete que vino con el ítem.
 * Al terminar: «Repaso hecho: N/10» y la racha, con el número y nada más.
 */

function tokenXsrf() {
    const par = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return par ? decodeURIComponent(par[1]) : '';
}

async function pedirJson(url, opciones = {}) {
    return fetch(url, {
        credentials: 'same-origin',
        ...opciones,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': tokenXsrf() },
    });
}

const PRIORIDAD = { 1: 'Repaso', 2: 'Lo que fallaste', 3: 'Nuevo' };

export default function Repaso({ lengua, nombre, racha: rachaInicial, se_guarda: seGuarda }) {
    const { props: compartidas } = usePage();
    const invitado = !compartidas.auth?.user;

    // estado: cargando | listo | enviando | respondido | hecho | vacio | error
    const [estado, setEstado] = useState('cargando');
    const [items, setItems] = useState([]);
    const [k, setK] = useState(0);
    const [valor, setValor] = useState(valorInicial());
    const [faltaElegir, setFaltaElegir] = useState(false);
    const [resultado, setResultado] = useState(null);
    const [aciertos, setAciertos] = useState(0);
    const [racha, setRacha] = useState(rachaInicial);
    const inputRef = useRef(null);
    const feedbackRef = useRef(null);

    const cargar = useCallback(async () => {
        setEstado('cargando');
        try {
            const r = await pedirJson(`/api/v1/practice/repaso-diario?lengua=${lengua}`);
            if (!r.ok) return setEstado('error');
            const d = await r.json();
            setItems(d.items);
            setRacha(d.racha);
            setK(0);
            setAciertos(0);
            setValor(valorInicial());
            setEstado(d.total === 0 ? 'vacio' : 'listo');
        } catch {
            setEstado('error');
        }
    }, [lengua]);

    useEffect(() => { cargar(); }, [cargar]);
    useEffect(() => {
        if (estado === 'listo') inputRef.current?.focus();
        if (estado === 'respondido') feedbackRef.current?.focus();
    }, [estado, k]);

    const item = items[k];

    async function comprobar(e) {
        e.preventDefault();
        if (estado !== 'listo') return;
        if (estaIncompleta(item, valor)) return setFaltaElegir(true);
        setFaltaElegir(false);
        setEstado('enviando');
        try {
            // El MISMO endpoint que la práctica, con el billete que vino con el ítem.
            const r = await pedirJson(`/api/v1/practice/items/${item.item_id}/attempts`, {
                method: 'POST',
                body: JSON.stringify({ ...cuerpoDeRespuesta(item, valor), billete: item.billete }),
            });
            if (r.status === 409) return siguiente();   // ya registrado en otra pestaña: se pasa
            if (!r.ok) return setEstado('error');
            const v = await r.json();
            if (v.is_correct) setAciertos((a) => a + 1);
            setResultado(v);
            setEstado('respondido');
        } catch {
            setEstado('error');
        }
    }

    async function siguiente() {
        setResultado(null);
        setValor(valorInicial());
        if (k + 1 < items.length) {
            setK(k + 1);
            setEstado('listo');

            return;
        }
        // Repaso hecho: la racha se lee del servidor, que es quien la cuenta.
        try {
            const r = await pedirJson('/api/v1/practice/racha');
            if (r.ok) setRacha(await r.json());
        } catch { /* la racha de antes sigue en pantalla */ }
        setEstado('hecho');
    }

    return (
        <AppLayout>
            <Head title={`Tu repaso de hoy · ${nombre}`} />

            <div className="mx-auto max-w-2xl">
                <Link href={`/corso/${lengua}`} className="text-sm font-medium text-marca-700 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                    ← Curso de {nombre}
                </Link>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Tu repaso de hoy</h1>
                {!invitado && racha.viva && racha.dias > 0 && (
                    <p className="mt-1 text-sm text-slate-700">Racha: {racha.dias} {racha.dias === 1 ? 'día' : 'días'}.</p>
                )}
                {invitado && (
                    <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Estás repasando como visitante: verás la corrección, pero <strong>no se guarda</strong> y no hay racha.
                    </p>
                )}

                {estado === 'cargando' && <p role="status" className="mt-6">Preparando tu repaso…</p>}
                {estado === 'vacio' && (
                    <p role="status" className="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        Hoy no hay nada que repasar en este curso todavía. Empieza por una unidad.
                    </p>
                )}
                {estado === 'error' && (
                    <div role="alert" className="mt-6">
                        <p>No pudimos cargar el repaso (¿se cortó la conexión?).</p>
                        <button type="button" onClick={cargar} className="mt-2 rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">Reintentar</button>
                    </div>
                )}

                {(estado === 'listo' || estado === 'enviando') && item && (
                    <form className="mt-6" onSubmit={comprobar}>
                        <p className="mb-2 text-sm font-medium text-slate-600" role="status">
                            {k + 1} de {items.length} · {PRIORIDAD[item.prioridad]}
                            {item.objective_statement ? ` · ${item.objective_statement}` : ''}
                        </p>
                        <Ejercicio
                            item={item}
                            valor={valor}
                            onChange={(v) => { setValor(v); setFaltaElegir(false); }}
                            faltaElegir={faltaElegir}
                            inputRef={inputRef}
                            nombre={`r${k}`}
                        />
                        <button type="submit" disabled={estado === 'enviando'}
                            className="rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50">
                            {estado === 'enviando' ? 'Comprobando…' : 'Comprobar'}
                        </button>
                    </form>
                )}

                <div aria-live="polite">
                    {estado === 'respondido' && resultado && item && (
                        <div ref={feedbackRef} tabIndex={-1}
                            className={`mt-6 flex gap-4 rounded-lg border border-l-4 p-4 ${resultado.is_correct ? 'border-emerald-200 border-l-emerald-600 bg-emerald-50' : 'border-rose-200 border-l-rose-600 bg-rose-50'}`}>
                            <span aria-hidden="true" className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xl font-bold text-white ${resultado.is_correct ? 'bg-emerald-600' : 'bg-rose-600'}`}>
                                {resultado.is_correct ? '✓' : '✗'}
                            </span>
                            <div>
                                <Veredicto item={item} resultado={resultado} />
                                <button type="button" onClick={siguiente}
                                    className="mt-3 rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                                    {k + 1 < items.length ? 'Siguiente' : 'Terminar el repaso'}
                                </button>
                            </div>
                        </div>
                    )}

                    {estado === 'hecho' && (
                        <div className="mt-6 rounded-lg border border-l-4 border-emerald-200 border-l-emerald-600 bg-emerald-50 p-4">
                            <p className="text-xl font-semibold text-slate-900">Repaso hecho: {aciertos}/{items.length}</p>
                            {!invitado && racha.viva && (
                                <p className="mt-1 text-sm text-slate-800">Racha: {racha.dias} {racha.dias === 1 ? 'día' : 'días'}.</p>
                            )}
                            {invitado && <p className="mt-1 text-sm text-slate-800">Esto no se ha guardado.</p>}
                            <Link href={`/corso/${lengua}`} className="mt-3 inline-block rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                                Volver al curso
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
