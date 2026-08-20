import { Head, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import Anillo from '../components/Anillo';

/**
 * La portada pública: la única página de AllyuHub que se ve sin sesión.
 *
 * Cuenta qué es la plataforma y termina en una sola puerta — el aula virtual
 * del colegio — porque la identidad entra por LTI y no hay registro abierto.
 * Cero contenido del currículo: solo cifras agregadas (ver BienvenidaController).
 */

const VENTAJAS = [
    {
        icono: '♾️',
        titulo: 'Práctica que no se acaba',
        texto:
            'Cada ejercicio se genera con números distintos, así que puedes repetir una destreza ' +
            'las veces que necesites. La respuesta se comprueba en el servidor: nada de copiar la solución.',
    },
    {
        icono: '🧭',
        titulo: 'Se adapta a lo que te falta',
        texto:
            'Si un tema se te atraviesa, la plataforma retrocede al prerrequisito y te lo dice. ' +
            'Cuando lo dominas, avanza. Tú decides cuánto tiempo le dedicas.',
    },
    {
        icono: '🇪🇨',
        titulo: 'El currículo del Ecuador',
        texto:
            'Las destrezas son las del Ministerio de Educación, importadas de los documentos ' +
            'oficiales. También el currículo de PCEI para quien retoma sus estudios.',
    },
    {
        icono: '🏫',
        titulo: 'Dentro de tu aula virtual',
        texto:
            'Tu docente abre AllyuHub desde el Moodle del colegio y tus calificaciones vuelven ' +
            'allí solas. No hay que crear otra cuenta ni recordar otra contraseña.',
    },
];

function Cifra({ numero, singular, plural }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 text-center">
            <p className="text-2xl font-semibold tracking-tight text-marca-700">
                {numero.toLocaleString('es-EC')}
            </p>
            <p className="text-sm text-slate-600">{numero === 1 ? singular : plural}</p>
        </div>
    );
}

const BOTON =
    'inline-block rounded-md bg-marca-600 px-5 py-3 text-base font-medium text-white hover:bg-marca-700 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600';

const BOTON_SECUNDARIO =
    'inline-block rounded-md border border-marca-600 bg-white px-5 py-3 text-base font-medium text-marca-700 hover:bg-marca-50 focus:outline-2 focus:outline-offset-2 focus:outline-marca-600';

export default function Bienvenida({ cifras, entrar }) {
    return (
        <AppLayout>
            <Head title="Bienvenida" />

            <section className="rounded-xl border border-marca-100 bg-gradient-to-br from-marca-50 to-white p-6 sm:p-8">
                <div className="flex flex-col-reverse items-start gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div className="max-w-xl">
                        <p className="text-sm font-medium uppercase tracking-wide text-marca-700">
                            Plataforma educativa
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold leading-tight tracking-tight text-slate-900 sm:text-4xl">
                            Practica el currículo ecuatoriano a tu ritmo
                        </h1>
                        <p className="mt-4 text-lg text-slate-700">
                            AllyuHub acompaña a cada estudiante destreza por destreza, desde 1.º de
                            EGB hasta el bachillerato, y también a quien retoma sus estudios por
                            PCEI.
                        </p>
                        {/* DOS caminos, no una pared. El primero es el que
                            puede tomar cualquiera ahora mismo; el segundo, el
                            que hace que lo practicado cuente. */}
                        <p className="mt-6 flex flex-wrap gap-3">
                            <Link href="/catalogo" className={BOTON}>
                                Explora el currículo
                            </Link>
                            <a href={entrar} className={BOTON_SECUNDARIO}>
                                Entrar desde tu aula virtual
                            </a>
                        </p>
                        <p className="mt-3 text-sm text-slate-600">
                            Puedes ver el currículo y practicar sin registrarte. Entrar desde tu
                            aula virtual es lo que guarda tu avance y hace que cuente en tu curso.
                        </p>
                    </div>

                    {/* Decorativo: el dato real de esos anillos vive en /inicio. */}
                    <div className="flex gap-2" aria-hidden="true">
                        <Anillo valor={0.75} etiqueta="" tamano={88} decorativo />
                        <Anillo valor={0.4} etiqueta="" tamano={64} decorativo color="#3aa675" />
                    </div>
                </div>
            </section>

            <section className="mt-8">
                <h2 className="text-lg font-semibold tracking-tight text-slate-900">
                    Lo que ya está cargado
                </h2>
                <p className="mt-1 text-sm text-slate-600">
                    Cifras del currículo publicado en esta instalación.
                </p>
                {/* Etiquetas de una o dos palabras: en un teléfono de 360 px
                    cada celda mide ~158 px y una frase entera se deshilacha en
                    cuatro líneas. La explicación va debajo, en prosa. */}
                <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Cifra numero={cifras.destrezas} singular="destreza" plural="destrezas" />
                    <Cifra numero={cifras.verificadas} singular="verificada" plural="verificadas" />
                    <Cifra numero={cifras.grados} singular="grado" plural="grados" />
                    <Cifra numero={cifras.simuladores} singular="simulador" plural="simuladores" />
                </div>
                <p className="mt-3 text-sm text-slate-600">
                    «Verificada» significa cotejada palabra por palabra con el documento oficial
                    del Ministerio. Las demás llevan un enunciado provisional y lo dicen en su
                    ficha: aquí no se hace pasar un marcador por currículo.
                </p>
            </section>

            <section className="mt-8">
                <h2 className="text-lg font-semibold tracking-tight text-slate-900">
                    Cómo funciona
                </h2>
                <ul className="mt-3 grid gap-3 sm:grid-cols-2">
                    {VENTAJAS.map((v) => (
                        <li
                            key={v.titulo}
                            className="rounded-lg border border-slate-200 bg-white p-4"
                        >
                            <h3 className="flex items-center gap-2 text-base font-semibold text-slate-900">
                                <span aria-hidden="true" className="text-xl">
                                    {v.icono}
                                </span>
                                {v.titulo}
                            </h3>
                            <p className="mt-2 text-sm leading-relaxed text-slate-700">{v.texto}</p>
                        </li>
                    ))}
                </ul>
            </section>

            <section className="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-center">
                <h2 className="text-lg font-semibold tracking-tight text-slate-900">
                    ¿Ya tienes usuario en el aula virtual?
                </h2>
                <p className="mx-auto mt-2 max-w-lg text-slate-700">
                    Abre AllyuHub desde la actividad que preparó tu docente y tu avance se
                    guardará solo. Si te quedaste sin sesión, vuelve a entrar desde ahí: lo que
                    tenías sigue ahí.
                </p>
                <p className="mt-4">
                    <a href={entrar} className={BOTON}>
                        Cómo entrar
                    </a>
                </p>
            </section>
        </AppLayout>
    );
}
