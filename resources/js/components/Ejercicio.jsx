import { useState } from 'react';
import { TableroDeOrden, TableroDePares, partirHueco, primerTexto } from './Tablero';

/**
 * EL EJERCICIO, separado del bucle de práctica.
 *
 * `Practicar.jsx` pedía un ítem, lo pintaba, lo corregía y pedía el siguiente,
 * todo en un componente. La PRUEBA DE UNIDAD (PR 8) pinta los mismos siete
 * tipos pero no corrige uno a uno: contesta diez y corrige al final. Copiar
 * `Practicar.jsx` para eso habría dejado dos sitios donde se decide cómo se
 * responde un `pares` o qué campo viaja en el POST — que es justo la clase de
 * copia que diverge en silencio.
 *
 * Aquí vive lo que es DEL EJERCICIO y no del bucle:
 *
 *  - `forma(item)`: las tres formas de responder (por clave, por texto, por
 *    estructura) derivadas del kind, igual que `Tipos\Registro` en el servidor.
 *  - `valorInicial()` / `estaIncompleta()` / `cuerpoDeRespuesta()`: el estado de
 *    la respuesta, cuándo está a medias, y QUÉ CAMPO VIAJA en el POST. Mandar
 *    dos campos a la vez es un 422: cada tipo prohíbe el del otro.
 *  - `<Ejercicio>`: el enunciado, el audio si lo hay y la interfaz de responder.
 *  - `<Veredicto>`: cómo se cuenta el resultado, por tipo.
 *
 * Los TABLEROS de `orden` y `pares` y el hueco dentro de la frase viven en
 * `Tablero.jsx` (misión 4): tocar sigue siendo el camino accesible y arrastrar
 * se añade encima con eventos de puntero — 0 KB de librerías, como siempre.
 */

// Etiqueta visual de cada opción. Decorativa: el nombre accesible del radio es
// el texto de la opción. Con más de 6 opciones se cae a la POSICIÓN — nunca a
// la clave, que pintarla sería enseñar en pantalla lo que solo debe viajar en
// el `value`.
const LETRAS = ['A', 'B', 'C', 'D', 'E', 'F'];

/** El texto de la opción con esa clave, entre las que sirvió el servidor. */
export function textoDeOpcion(item, clave) {
    return (item?.options ?? []).find((o) => o.key === clave)?.text?.es ?? '';
}

/**
 * Tres FORMAS de responder, no siete tipos: por clave (choice/escucha), por
 * texto (hueco/dictado) y por estructura (orden/pares). El servidor hizo la
 * misma generalización (Tipos\Registro); aquí decide qué interfaz se pinta y
 * qué campo viaja en el POST.
 */
export function forma(item) {
    const esChoice = item?.kind === 'choice';
    const esEscucha = item?.kind === 'escucha';
    const esHueco = item?.kind === 'hueco';
    const esDictado = item?.kind === 'dictado';
    const esOrden = item?.kind === 'orden';
    const esPares = item?.kind === 'pares';
    const porClave = esChoice || esEscucha;
    const porTexto = esHueco || esDictado;
    const conAudio = esEscucha || esDictado;
    // Un ítem con opciones pero sin opciones en la base es un ítem roto, no
    // una pantalla en blanco: `options` es nullable y nada garantiza que esté.
    const opciones = porClave || esOrden || esPares ? (item?.options ?? []) : [];
    const itemRoto = (porClave || esOrden || esPares) && opciones.length === 0;
    const columnasDePares = esPares ? [...new Set(opciones.map((o) => o.col))].sort() : [];

    return { porClave, porTexto, esOrden, esPares, conAudio, opciones, itemRoto, columnasDePares };
}

/** El estado de una respuesta a medio formar. */
export function valorInicial() {
    return { respuesta: '', secuencia: [], parejas: [], pendiente: {} };
}

/** ¿Está la respuesta a medias? Sin responder no se envía — pero se dice. */
export function estaIncompleta(item, valor) {
    const f = forma(item);
    if (f.esOrden) return valor.secuencia.length !== f.opciones.length;
    if (f.esPares) return valor.parejas.length * f.columnasDePares.length !== f.opciones.length;

    return valor.respuesta.trim() === '';
}

/**
 * QUÉ CAMPO VIAJA. Un choice manda la POSICIÓN elegida tal cual la sirvió el
 * servidor; un numérico, el número; los de lengua, `respuesta`. Mandar dos
 * sería un 422: cada tipo prohíbe el campo del otro.
 */
export function cuerpoDeRespuesta(item, valor) {
    const f = forma(item);
    if (f.porClave) return { answer_key: valor.respuesta };
    if (f.porTexto) return { respuesta: { texto: valor.respuesta } };
    if (f.esOrden) return { respuesta: { ids: valor.secuencia } };
    if (f.esPares) return { respuesta: { parejas: valor.parejas } };

    return { answer: Number(valor.respuesta) };
}

function ReproductorDeEscucha({ src }) {
    const [fallo, setFallo] = useState(false);

    if (fallo) {
        // DEUDA CONOCIDA Y ASUMIDA (auditoría de #26): con el clip caído el
        // alumno puede contestar igual, y un acierto por azar cuenta para el
        // dominio. Con ITEMS_TO_MASTER = 2 hace falta acertar en dos ítems
        // DISTINTOS, así que el azar necesita mucha suerte — y bloquear el
        // formulario sería peor que el problema: castigaría el corte de red
        // del colegio con un ejercicio muerto. Se avisa y se deja pasar.
        return (
            <p role="status" className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                El audio no se pudo cargar. Revisa tu conexión, o pasa al
                siguiente ejercicio e inténtalo más tarde.
            </p>
        );
    }

    return (
        <div className="mb-4">
            {/* preload=auto: el clip pesa poco y el alumno lo va a oír seguro;
                que esté listo cuando pulse. La caché es inmutable, así que
                repetirlo no vuelve a la red. */}
            <audio
                controls
                preload="auto"
                src={src}
                onError={() => setFallo(true)}
                aria-label="Audio del ejercicio"
                className="w-full"
            />
        </div>
    );
}

/**
 * El enunciado, el audio si lo hay, y la interfaz de responder. CONTROLADO:
 * el valor vive en quien lo usa (el bucle de práctica o la prueba), y aquí
 * solo se pinta y se avisa de cada cambio.
 *
 * `nombre` distingue los grupos de radios cuando hay varios ejercicios en la
 * misma página (la prueba): dos `name="opcion"` iguales se pisarían.
 */
export function Ejercicio({ item, valor, onChange, faltaElegir = false, inputRef, nombre = 'opcion' }) {
    const { porClave, porTexto, esOrden, esPares, conAudio, opciones, columnasDePares } = forma(item);
    const idAviso = `falta-elegir-${nombre}`;
    const idPinyin = `${nombre}-pinyin`;
    // El hueco vive DENTRO de la frase: la consigna se parte por su «___» y
    // el campo ocupa ese sitio. Sin «___» (o en dictado) el campo va debajo.
    const partes = item.kind === 'hueco' ? partirHueco(item.statement?.es) : null;

    const cambiar = (parcial) => onChange({ ...valor, ...parcial });

    const campoDeTexto = (dentro) => (
        /* type=text con autocorrección fuera: el alumno escribe en la lengua
           que aprende y el móvil «corrigiéndole» al español es el enemigo. */
        <input
            ref={inputRef}
            type="text"
            autoComplete="off"
            autoCapitalize="off"
            autoCorrect="off"
            spellCheck="false"
            required
            aria-label={dentro ? 'Tu respuesta' : undefined}
            value={valor.respuesta}
            aria-describedby={[faltaElegir ? idAviso : null, item.lengua === 'zh' ? idPinyin : null].filter(Boolean).join(' ') || undefined}
            onChange={(e) => cambiar({ respuesta: e.target.value })}
            className={dentro
                ? 'mx-1 inline-block w-40 max-w-full rounded border border-slate-400 bg-marca-50 px-2 py-0.5 text-center align-baseline text-xl focus:outline-2 focus:outline-marca-600'
                : 'w-full max-w-md rounded border border-slate-300 px-3 py-2 focus:outline-2 focus:outline-marca-600'}
        />
    );

    return (
        <>
            {/* El enunciado es LO QUE SE LEE: grande, con aire y sin
                competir con nada. React escapa por defecto; el texto
                viene de PDFs importados. */}
            <p className="mb-5 rounded-lg border border-slate-200 bg-white p-4 text-xl leading-relaxed text-slate-900">
                {partes ? <>{partes[0]}{campoDeTexto(true)}{partes[1]}</> : item.statement.es}
            </p>

            {conAudio && <ReproductorDeEscucha src={item.audio_src} />}

            {porClave ? (
                /* fieldset + legend es EL patrón de un grupo de radios:
                   el lector de pantalla anuncia la pregunta antes de la
                   primera opción y las flechas mueven la selección
                   gratis. Nada de divs con role a mano. */
                <fieldset className="mb-4">
                    <legend className="mb-2 text-sm font-medium">
                        Elige una respuesta
                    </legend>
                    <div className="space-y-2">
                        {opciones.map((opcion, i) => (
                            <label
                                key={opcion.key}
                                className={`flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-marca-600 ${
                                    valor.respuesta === opcion.key
                                        ? 'border-marca-600 bg-marca-50'
                                        : 'border-slate-200 bg-white hover:bg-slate-50'
                                }`}
                            >
                                <input
                                    ref={i === 0 ? inputRef : undefined}
                                    type="radio"
                                    name={nombre}
                                    value={opcion.key}
                                    checked={valor.respuesta === opcion.key}
                                    onChange={(e) => cambiar({ respuesta: e.target.value })}
                                    aria-describedby={faltaElegir ? idAviso : undefined}
                                    className="h-4 w-4 shrink-0 accent-marca-600"
                                />
                                {/* La letra es un ancla visual para
                                    hablar del ejercicio en voz alta; el
                                    nombre accesible del radio es el
                                    TEXTO de la opción, no «B». */}
                                <span
                                    aria-hidden="true"
                                    className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700"
                                >
                                    {LETRAS[i] ?? i + 1}
                                </span>
                                <span className="text-base leading-relaxed text-slate-900">
                                    {opcion.text.es}
                                </span>
                            </label>
                        ))}
                    </div>
                </fieldset>
            ) : esOrden ? (
                <TableroDeOrden
                    opciones={opciones}
                    secuencia={valor.secuencia}
                    onSecuencia={(secuencia) => cambiar({ secuencia })}
                    nombre={nombre}
                    inputRef={inputRef}
                />
            ) : esPares ? (
                <TableroDePares
                    opciones={opciones}
                    columnas={columnasDePares}
                    parejas={valor.parejas}
                    pendiente={valor.pendiente}
                    onCambio={cambiar}
                    inputRef={inputRef}
                />
            ) : porTexto ? (
                <div className="mb-4">
                    {!partes && (
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium">Tu respuesta</span>
                            {campoDeTexto(false)}
                        </label>
                    )}
                    {item.lengua === 'zh' && (
                        // El tono es parte de la palabra. Sin teclado de tonos,
                        // el número vale: el motor acepta las dos formas.
                        <p id={idPinyin} className="mt-1 text-sm text-slate-600">
                            Escribe el pinyin con tonos (nǐ hǎo) o con el número del tono (ni3 hao3).
                        </p>
                    )}
                </div>
            ) : (
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
                            value={valor.respuesta}
                            aria-describedby={faltaElegir ? idAviso : undefined}
                            onChange={(e) => cambiar({ respuesta: e.target.value })}
                            className="w-40 rounded border border-slate-300 px-3 py-2 focus:outline-2 focus:outline-marca-600"
                        />
                    </label>
                    {item.answer_unit && (
                        <span className="pb-2 text-slate-600" aria-hidden="true">
                            {item.answer_unit}
                        </span>
                    )}
                </div>
            )}

            {faltaElegir && (
                <p
                    id={idAviso}
                    role="alert"
                    className="mb-3 text-sm font-medium text-rose-900"
                >
                    {porClave
                        ? 'Elige una de las opciones antes de comprobar.'
                        : esOrden
                          ? 'Te faltan palabras por colocar antes de comprobar.'
                          : esPares
                            ? 'Te faltan parejas por formar antes de comprobar.'
                            : 'Escribe tu respuesta antes de comprobar.'}
                </p>
            )}
        </>
    );
}

/**
 * El MARCO del veredicto (color + microanimación), escrito una vez para los
 * bucles que lo pintan (práctica, repaso). Acierto: un pulso corto; fallo: una
 * sacudida breve. Siempre tras `motion-safe:` — con `prefers-reduced-motion`
 * quedan los mismos colores, el mismo icono y el mismo texto, sin movimiento.
 */
export function claseDeVeredicto(esCorrecto) {
    return esCorrecto
        ? 'border-emerald-200 border-l-emerald-600 bg-emerald-50 motion-safe:animate-pulso'
        : 'border-rose-200 border-l-rose-600 bg-rose-50 motion-safe:animate-sacudida';
}

/**
 * Cómo se cuenta un resultado, por tipo. Solo el cuerpo: quien lo usa pone el
 * marco (color, icono, foco) y lo que venga después (siguiente, aviso).
 */
export function Veredicto({ item, resultado }) {
    const { porClave, porTexto, esOrden, esPares, conAudio, opciones } = forma(item);

    return (
        <>
            <p
                className={`text-lg font-semibold ${
                    resultado.is_correct ? 'text-emerald-900' : 'text-rose-900'
                }`}
            >
                {resultado.is_correct ? 'Correcto.' : 'Incorrecto.'}
            </p>
            {porTexto ? (
                <p className="mt-1 text-sm leading-relaxed text-slate-700">
                    {resultado.is_correct
                        ? 'Bien escrito.'
                        : resultado.detalle === 'acento'
                          ? `Casi: ${item.lengua === 'zh' ? 'te falta el tono' : 'revisa el acento'}. Escribiste «${resultado.texto}» y era «${resultado.esperado}».`
                          : `La respuesta era: «${resultado.esperado}».`}
                </p>
            ) : esOrden ? (
                <p className="mt-1 text-sm leading-relaxed text-slate-700">
                    {resultado.is_correct
                        ? 'La frase está bien construida.'
                        : `Un orden correcto era: ${(resultado.secuencia_correcta ?? [])
                              .map((k) => primerTexto(opciones, k))
                              .join(' ')}.`}
                </p>
            ) : esPares ? (
                <p className="mt-1 text-sm leading-relaxed text-slate-700">
                    {`Acertaste ${resultado.parejas_correctas} de ${resultado.total} parejas.`}
                </p>
            ) : porClave ? (
                <p className="mt-1 text-sm leading-relaxed text-slate-700">
                    {/* Se nombra la opción buena por su TEXTO,
                        que es lo que el alumno recuerda: «la 2»
                        no significa nada dos minutos después,
                        y la barajada cambia en cada intento. */}
                    {resultado.is_correct
                        ? 'Esa es.'
                        : `La respuesta correcta era: ${textoDeOpcion(item, resultado.expected_key)}.`}
                </p>
            ) : (
                <p className="mt-1 text-sm text-slate-700">
                    Tu respuesta: {resultado.answer}. Valor esperado:{' '}
                    {Math.round(resultado.expected * 1000) / 1000}
                    {item?.answer_unit ? ` ${item.answer_unit}` : ''}.
                </p>
            )}
            {conAudio && resultado.transcripcion && (
                /* AHORA sí: respondido el intento, leer lo que
                   se oyó es la otra mitad del ejercicio. Antes
                   de responder no está ni en el payload. */
                <p className="mt-2 rounded border border-slate-200 bg-white p-2 text-sm leading-relaxed text-slate-800">
                    <span className="font-semibold">Lo que decía el audio:</span>{' '}
                    <span lang={item?.lang}>{resultado.transcripcion}</span>
                </p>
            )}
        </>
    );
}
