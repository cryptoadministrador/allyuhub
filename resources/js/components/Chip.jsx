/**
 * Etiqueta corta de metadatos (destrezas, ejercicios, edad).
 *
 * El tono NUNCA es el único portador del dato: el texto del chip ya lo dice
 * entero («12 con ejercicios»), y el color solo lo agrupa visualmente.
 */
const TONOS = {
    neutro: 'bg-slate-100 text-slate-700',
    marca: 'bg-marca-50 text-marca-900',
    verde: 'bg-emerald-50 text-emerald-900',
    ambar: 'bg-amber-50 text-amber-900',
};

export default function Chip({ tono = 'neutro', icono, children }) {
    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                TONOS[tono] ?? TONOS.neutro
            }`}
        >
            {icono && <span aria-hidden="true">{icono}</span>}
            {children}
        </span>
    );
}
