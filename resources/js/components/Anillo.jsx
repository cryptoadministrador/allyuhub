/**
 * Anillo de progreso en SVG propio — sin librería de gráficas: un `stroke-dasharray`
 * y dos círculos pesan 0 KB y el presupuesto del bundle es sagrado.
 *
 * Accesible por construcción: el porcentaje va también como TEXTO en el centro
 * (el anillo nunca es el único portador del dato) y el conjunto se anuncia como
 * una sola imagen con su etiqueta, en vez de leerse círculo a círculo.
 */
export default function Anillo({
    valor,
    etiqueta,
    tamano = 96,
    grosor = 8,
    color = 'var(--acento, #4338ca)',
    decorativo = false,
}) {
    const fraccion = Math.max(0, Math.min(1, Number.isFinite(valor) ? valor : 0));
    const porcentaje = Math.round(fraccion * 100);

    // Un radio negativo (grosor mayor que el tamaño) hace que el navegador
    // descarte el círculo entero y registre un error: mejor un anillo plano.
    const radio = Math.max(0, (tamano - grosor) / 2);
    const circunferencia = 2 * Math.PI * radio;
    const centro = tamano / 2;

    const accesibilidad = decorativo
        ? { 'aria-hidden': true }
        : {
              // `progressbar` y no `img`: el anillo ES una medida. Así la
              // tecnología de apoyo anuncia el valor —y su cambio— en vez de
              // describir un dibujo.
              role: 'progressbar',
              'aria-label': etiqueta,
              'aria-valuenow': porcentaje,
              'aria-valuemin': 0,
              'aria-valuemax': 100,
              'aria-valuetext': `${porcentaje} por ciento`,
          };

    return (
        <svg
            width={tamano}
            height={tamano}
            viewBox={`0 0 ${tamano} ${tamano}`}
            className="shrink-0"
            {...accesibilidad}
        >
            <circle
                cx={centro}
                cy={centro}
                r={radio}
                fill="none"
                stroke="#e2e8f0"
                strokeWidth={grosor}
            />
            <circle
                cx={centro}
                cy={centro}
                r={radio}
                fill="none"
                stroke={color}
                strokeWidth={grosor}
                strokeLinecap="round"
                strokeDasharray={circunferencia}
                strokeDashoffset={circunferencia * (1 - fraccion)}
                transform={`rotate(-90 ${centro} ${centro})`}
            />
            <text
                x={centro}
                y={centro}
                textAnchor="middle"
                dominantBaseline="central"
                className="fill-slate-900 font-semibold"
                style={{ fontSize: tamano * 0.26 }}
            >
                {porcentaje}%
            </text>
        </svg>
    );
}
