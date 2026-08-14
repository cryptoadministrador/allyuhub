/**
 * ORÁCULO 4 (honestidad del catálogo): una destreza sin verificar se marca
 * SIEMPRE en texto — jamás solo con color o icono. 1001 de las 1010 destrezas
 * de la semilla son marcadores; presentarlas como currículo real sería mentir.
 */
export default function DistintivoVerificacion({ verificada, conExplicacion = false }) {
    if (verificada) {
        return (
            <span className="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-900">
                Verificada con el currículo oficial
            </span>
        );
    }

    return (
        <span className="rounded bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
            Provisional: texto de relleno
            {conExplicacion && ' — se sustituirá al importar el currículo oficial del MINEDUC'}
        </span>
    );
}
