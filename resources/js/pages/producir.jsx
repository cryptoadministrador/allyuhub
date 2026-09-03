import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '../layouts/AppLayout';

/**
 * LA TAREA DE PRODUCCIÓN de una unidad: el alumno ESCRIBE o GRABA, y su profe
 * lo corrige. El motor no corrige producción — aquí solo se envía.
 *
 * Abierta como el resto del curso: un invitado ve la tarea y el aviso de que
 * hace falta entrar para enviarla. Cero librerías: `<textarea>` y el
 * `MediaRecorder` nativo. Si el navegador no sabe grabar, la tarea de voz lo
 * DICE en vez de romperse.
 */

function tokenXsrf() {
    const par = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return par ? decodeURIComponent(par[1]) : '';
}

async function enviar(cuerpo, esFormData) {
    return fetch('/api/v1/producciones', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': tokenXsrf(),
            ...(esFormData ? {} : { 'Content-Type': 'application/json' }),
        },
        body: esFormData ? cuerpo : JSON.stringify(cuerpo),
    });
}

function Enviado() {
    return (
        <p role="status" className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
            ✅ Enviado. Tu profe lo revisará y te dará una devolución.
        </p>
    );
}

function TareaEscritura({ tarea, unidad, lengua, puedeEnviar }) {
    const [texto, setTexto] = useState('');
    const [estado, setEstado] = useState('idle'); // idle | enviando | enviado | error
    const corto = texto.trim().length < 20;

    async function onEnviar() {
        setEstado('enviando');
        const r = await enviar({
            objective_id: tarea.descriptor_id,
            unidad,
            lengua,
            tipo: 'escritura',
            texto,
        }, false);
        setEstado(r.ok ? 'enviado' : 'error');
    }

    if (estado === 'enviado') {
        return <Enviado />;
    }

    return (
        <div>
            <label htmlFor={`t-${tarea.descriptor_id}`} className="mb-2 block text-sm font-medium text-slate-700">
                Escribe tres o cuatro frases
            </label>
            <textarea
                id={`t-${tarea.descriptor_id}`}
                value={texto}
                onChange={(e) => setTexto(e.target.value)}
                rows={5}
                maxLength={2000}
                disabled={!puedeEnviar || estado === 'enviando'}
                className="w-full rounded-lg border border-slate-300 p-3 text-slate-900 focus:border-marca-500 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:bg-slate-100"
            />
            <div className="mt-2 flex items-center justify-between">
                <span className="text-xs text-slate-500">{texto.trim().length} caracteres</span>
                <button
                    type="button"
                    onClick={onEnviar}
                    disabled={!puedeEnviar || corto || estado === 'enviando'}
                    className="rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50"
                >
                    {estado === 'enviando' ? 'Enviando…' : 'Enviar'}
                </button>
            </div>
            {estado === 'error' && (
                <p role="alert" className="mt-2 text-sm text-rose-700">
                    No se pudo enviar. Revisa que has escrito al menos una frase e inténtalo otra vez.
                </p>
            )}
        </div>
    );
}

function TareaVoz({ tarea, unidad, lengua, puedeEnviar }) {
    const puedeGrabar = typeof navigator !== 'undefined'
        && navigator.mediaDevices?.getUserMedia
        && typeof window !== 'undefined' && 'MediaRecorder' in window;

    const [estado, setEstado] = useState('idle'); // idle | grabando | revisar | enviando | enviado | error
    const [segundos, setSegundos] = useState(0);
    const [url, setUrl] = useState(null);
    const grabadora = useRef(null);
    const trozos = useRef([]);
    const blob = useRef(null);
    const cron = useRef(null);

    useEffect(() => () => {
        if (cron.current) clearInterval(cron.current);
        if (url) URL.revokeObjectURL(url);
    }, [url]);

    async function grabar() {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mr = new MediaRecorder(stream);
        trozos.current = [];
        mr.ondataavailable = (e) => trozos.current.push(e.data);
        mr.onstop = () => {
            stream.getTracks().forEach((t) => t.stop());
            blob.current = new Blob(trozos.current, { type: mr.mimeType || 'audio/webm' });
            setUrl(URL.createObjectURL(blob.current));
            setEstado('revisar');
        };
        grabadora.current = mr;
        mr.start();
        setSegundos(0);
        setEstado('grabando');
        cron.current = setInterval(() => setSegundos((s) => {
            if (s + 1 >= 30) detener();

            return s + 1;
        }), 1000);
    }

    function detener() {
        if (cron.current) clearInterval(cron.current);
        if (grabadora.current && grabadora.current.state !== 'inactive') {
            grabadora.current.stop();
        }
    }

    async function onEnviar() {
        setEstado('enviando');
        const fd = new FormData();
        fd.append('objective_id', tarea.descriptor_id);
        fd.append('unidad', unidad);
        fd.append('lengua', lengua);
        fd.append('tipo', 'voz');
        fd.append('archivo', blob.current, 'grabacion.webm');
        const r = await enviar(fd, true);
        setEstado(r.ok ? 'enviado' : 'error');
    }

    if (!puedeGrabar) {
        return (
            <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                Tu navegador no permite grabar audio aquí. Abre el curso en un navegador reciente para hacer esta tarea.
            </p>
        );
    }

    if (estado === 'enviado') {
        return <Enviado />;
    }

    const mmss = `${Math.floor(segundos / 60)}:${String(segundos % 60).padStart(2, '0')}`;

    return (
        <div>
            <p role="status" aria-live="polite" className="mb-3 text-sm text-slate-700">
                {estado === 'grabando' && <>🔴 Grabando… {mmss} (máximo 0:30)</>}
                {estado === 'revisar' && 'Escucha tu grabación antes de enviarla.'}
                {estado === 'idle' && 'Pulsa grabar y di tu respuesta (20–30 segundos).'}
            </p>

            {estado === 'revisar' && url && (
                <audio src={url} controls className="mb-3 w-full" />
            )}

            <div className="flex flex-wrap gap-2">
                {estado === 'idle' && (
                    <button type="button" onClick={grabar} disabled={!puedeEnviar}
                        className="rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50">
                        Grabar
                    </button>
                )}
                {estado === 'grabando' && (
                    <button type="button" onClick={detener}
                        className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                        Detener
                    </button>
                )}
                {estado === 'revisar' && (
                    <>
                        <button type="button" onClick={grabar}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                            Volver a grabar
                        </button>
                        <button type="button" onClick={onEnviar} disabled={!puedeEnviar}
                            className="rounded-lg bg-marca-600 px-4 py-2 text-sm font-semibold text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600 disabled:opacity-50">
                            Enviar
                        </button>
                    </>
                )}
            </div>
            {estado === 'error' && (
                <p role="alert" className="mt-2 text-sm text-rose-700">
                    No se pudo enviar la grabación. Inténtalo otra vez.
                </p>
            )}
        </div>
    );
}

export default function Producir({ lengua, nombre, unidad, productivos, se_guarda: seGuarda }) {
    const { props: compartidas } = usePage();
    const invitado = !compartidas.auth?.user && !seGuarda;

    return (
        <AppLayout>
            <Head title={`Tarea · ${nombre} · Unidad ${unidad.n}`} />

            <div className="mx-auto max-w-2xl">
                <Link
                    href={`/corso/${lengua}/u${unidad.n}`}
                    className="text-sm font-medium text-marca-700 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600"
                >
                    ← Unidad {unidad.n}
                </Link>

                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
                    Tu tarea · {unidad.titulo}
                </h1>

                {invitado && (
                    <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Estás viendo la tarea como visitante.{' '}
                        <a href="/entrar" className="font-medium underline focus:outline-2 focus:outline-offset-2 focus:outline-marca-600">
                            Entra desde tu aula
                        </a>{' '}
                        para enviarla a tu profe.
                    </p>
                )}

                <ol className="mt-6 space-y-6">
                    {productivos.map((tarea) => (
                        <li key={tarea.descriptor_id} className="rounded-lg border border-slate-200 bg-white p-4">
                            <p className="mb-1 text-xs font-medium uppercase tracking-wide text-marca-700">
                                {tarea.tipo === 'voz' ? '🎙️ Habla' : '✍️ Escribe'}
                            </p>
                            <p className="mb-4 text-base font-medium text-slate-900">{tarea.statement}</p>
                            {tarea.tipo === 'voz'
                                ? <TareaVoz tarea={tarea} unidad={unidad.n} lengua={lengua} puedeEnviar={seGuarda} />
                                : <TareaEscritura tarea={tarea} unidad={unidad.n} lengua={lengua} puedeEnviar={seGuarda} />}
                        </li>
                    ))}
                </ol>
            </div>
        </AppLayout>
    );
}
