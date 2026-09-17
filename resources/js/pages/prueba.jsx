import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Ejercicio, Veredicto, cuerpoDeRespuesta, estaIncompleta, valorInicial } from '../components/Ejercicio';
import AppLayout from '../layouts/AppLayout';

/**
 * LA PRUEBA DE UNIDAD: diez ítems de corrido, nota al final.
 *
 * La misma pieza que la práctica pinta cada ejercicio (`Ejercicio`) y cuenta
 * cada resultado (`Veredicto`); lo único distinto es CUÁNDO: aquí se contesta
 * todo y se corrige al entregar. Sin pista, sin veredicto, sin tiempo límite.
 * Aprobada con ≥ 8/10, la unidad queda «completada»; suspendida, se repite
 * sin límite y la siguiente trae otra semilla. El invitado la hace entera y ve
 * su nota — no se guarda nada.
 */

function tokenXsrf() {
    const par = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return par ? decodeURIComponent(par[1]) : '';
}

async function pedirJson(url, opciones = {}) {
    return fetch(url, {
        credentials: 'same-origin',
        ...opciones,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': tokenXsrf(),
        },
    });
}

export default function Prueba({ lengua, nombre, unidad, historial, aprobado, tamano, se_guarda: seGuarda }) {
    const { props: compartidas } = usePage();
    const invitado = !compartidas.auth?.user;

    // estado: cargando | respondiendo | entregando | entregada | error
    const [estado, setEstado] = useState('cargando');
    const [prueba, setPrueba] = useState(null);      // {intento, total, items}
    const [valores, setValores] = useState([]);       // uno por ítem
    const [k, setK] = useState(0);                    // ítem en pantalla
    const [faltaElegir, setFaltaElegir] = useState(false);
    const [resultado, setResultado] = useState(null);
    const inputRef = useRef(null);
    // El invitado lleva su número de intento; el alumno lo decide el servidor.
    const intentoInvitado = useRef(1);

    const cargar = useCallback(async () => {
        setEstado('cargando');
        setResultado(null);
        setK(0);
        setFaltaElegir(false);
        try {
            const r = await pedirJson(`/api/v1/pruebas/${lengua}/u${unidad.n}?intento=${intentoInvitado.current}`);
            if (!r.ok) return setEstado('error');
            const p = await r.json();
            setPrueba(p);
            setValores(p.items.map(() => valorInicial()));
            setEstado('respondiendo');
        } catch {
            setEstado('error');
        }
    }, [lengua, unidad.n]);

    useEffect(() => { cargar(); }, [cargar]);
    useEffect(() => { if (estado === 'respondiendo') inputRef.current?.focus(); }, [estado, k]);

    const item = prueba?.items[k];
    const valor = valores[k];

    function siguiente() {
        if (estaIncompleta(item, valor)) return setFaltaElegir(true);
        setFaltaElegir(false);
        setK(k + 1);
    }

    async function entregar() {
        if (estaIncompleta(item, valor)) return setFaltaElegir(true);
        setEstado('entregando');
        try {
            const r = await pedirJson(`/api/v1/pruebas/${lengua}/u${unidad.n}`, {
                method: 'POST',
                body: JSON.stringify({
                    intento: prueba.intento,
                    respuestas: prueba.items.map((it, i) => ({
                        item_id: it.item_id,
                        billete: it.billete,   // tal cual vino: el cliente no lo lee
                        ...cuerpoDeRespuesta(it, valores[i]),
                    })),
                }),
            });
            if (!r.ok) return setEstado('error');
            setResultado(await r.json());
            setEstado('entregada');
        } catch {
            setEstado('error');
        }
    }

    function repetir() {
        intentoInvitado.current += 1;
        cargar();
    }

    const mejor = historial.reduce((m, h) => Math.max(m, h.nota), 0);

    return (
        <AppLayout>
            <Head title={`Prueba · Unidad ${unidad.n} · ${nombre}`} />

            <div className="mx-auto max-w-2xl">
                <Link href={`/corso/${lengua}/u${unidad.n}`} className="text-sm font-medium text-marca-700 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                    ← Unidad {unidad.n}
                </Link>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
                    Prueba · {unidad.titulo}
                </h1>
                <p className="mt-1 text-sm text-slate-700">
                    {tamano} ejercicios, sin pistas. Ves la nota al final; con {aprobado} o más, la unidad queda completada.
                    {historial.length > 0 && ` Tu mejor nota hasta ahora: ${mejor}/${historial[0].total}.`}
                </p>
                {invitado && (
                    <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Estás haciendo la prueba como visitante: verás tu nota, pero <strong>no se guarda</strong>.
                    </p>
                )}

                {estado === 'cargando' && <p role="status" className="mt-6">Preparando tu prueba…</p>}
                {estado === 'error' && (
                    <div role="alert" className="mt-6">
                        <p>No pudimos cargar la prueba (¿se cortó la conexión?).</p>
                        <button type="button" onClick={cargar} className="mt-2 rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                            Reintentar
                        </button>
                    </div>
                )}

                {(estado === 'respondiendo' || estado === 'entregando') && item && (
                    <form
                        className="mt-6"
                        onSubmit={(e) => { e.preventDefault(); k + 1 < prueba.total ? siguiente() : entregar(); }}
                    >
                        <p className="mb-2 text-sm font-medium text-slate-600" role="status">
                            Ejercicio {k + 1} de {prueba.total}
                            {item.objective_statement ? ` · ${item.objective_statement}` : ''}
                        </p>
                        <Ejercicio
                            item={item}
                            valor={valor}
                            onChange={(v) => {
                                setValores(valores.map((x, i) => (i === k ? v : x)));
                                setFaltaElegir(false);
                            }}
                            faltaElegir={faltaElegir}
                            inputRef={inputRef}
                            nombre={`p${k}`}
                        />
                        <div className="flex flex-wrap gap-2">
                            {k > 0 && (
                                <button type="button" onClick={() => { setFaltaElegir(false); setK(k - 1); }}
                                    className="rounded border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                                    Anterior
                                </button>
                            )}
                            <button type="submit" disabled={estado === 'entregando'}
                                className="rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50">
                                {k + 1 < prueba.total ? 'Siguiente' : estado === 'entregando' ? 'Corrigiendo…' : 'Entregar la prueba'}
                            </button>
                        </div>
                    </form>
                )}

                {estado === 'entregada' && resultado && (
                    <div className="mt-6" aria-live="polite">
                        <div className={`rounded-lg border border-l-4 p-4 ${resultado.aprobada ? 'border-emerald-200 border-l-emerald-600 bg-emerald-50' : 'border-amber-200 border-l-amber-500 bg-amber-50'}`}>
                            <p className="text-2xl font-semibold text-slate-900">
                                {resultado.nota} / {resultado.total}
                            </p>
                            <p className="mt-1 text-sm text-slate-800">
                                {resultado.aprobada
                                    ? '✅ Prueba aprobada: la unidad queda completada.'
                                    : 'Todavía no. Repasa lo que falló y vuelve a intentarlo: la siguiente prueba trae otros ejercicios.'}
                                {!seGuarda && ' Esto no se ha guardado.'}
                            </p>
                            <ul className="mt-3 space-y-1 text-sm text-slate-800">
                                {Object.entries(resultado.desglose).map(([code, d]) => {
                                    const enunciado = prueba.items.find((it) => it.objective_code === code)?.objective_statement ?? code;

                                    return (
                                        <li key={code}>
                                            <span className="font-medium">{enunciado}</span>: {d.aciertos} de {d.total}
                                        </li>
                                    );
                                })}
                            </ul>
                            <button type="button" onClick={repetir}
                                className="mt-3 rounded bg-marca-600 px-4 py-2 font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                                {resultado.aprobada ? 'Hacer otra prueba' : 'Repetir la prueba'}
                            </button>
                        </div>

                        <h2 className="mt-6 text-lg font-semibold text-slate-900">Ejercicio a ejercicio</h2>
                        <ol className="mt-2 space-y-3">
                            {resultado.veredictos.map((v, i) => {
                                const it = prueba.items.find((x) => x.item_id === v.item_id);

                                return (
                                    <li key={v.item_id} className={`rounded-lg border border-l-4 p-3 ${v.is_correct ? 'border-emerald-200 border-l-emerald-600 bg-emerald-50' : 'border-rose-200 border-l-rose-600 bg-rose-50'}`}>
                                        <p className="mb-1 text-sm text-slate-700">{i + 1}. {it?.statement?.es}</p>
                                        <Veredicto item={it} resultado={v} />
                                    </li>
                                );
                            })}
                        </ol>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
