<?php

namespace App\Services\Lesson;

use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use Illuminate\Support\Collection;

/**
 * Dónde aterriza algo anclado a un BLOQUE del currículo.
 *
 * El prefijo del código lo dice todo: `LL.4.3` es Lengua · Básica Superior ·
 * bloque 3 (Lectura). Eso no es una suposición, es la estructura del propio
 * código MINEDEC.
 *
 * Vive aquí, fuera del comando de práctica, porque el sembrador de LECCIONES
 * necesita exactamente la misma regla. Duplicarla habría sido garantizar que
 * un día divergen y una lección aterrice en una destreza distinta de la que
 * tiene el ejercicio — dos contenidos del mismo bloque en dos sitios.
 *
 * La ambigüedad de códigos es COBERTURA, no un error: el importador replica los
 * bloques por grado dentro de un subnivel, así que un mismo bloque vive en 8.º,
 * 9.º y 10.º, y los tres reciben lo suyo.
 */
class DestinosDeBloque
{
    /** Las versiones VIGENTES del marco. Una sola, para no sembrar por duplicado. */
    public static function versionesDe(string $marco): ?Collection
    {
        $version = FrameworkVersion::query()
            ->whereIn('framework_id', Framework::where('code', $marco)->select('id'))
            ->latest('valid_from')
            ->first();

        return $version === null ? null : collect([$version->id]);
    }

    /**
     * La destreza de código más bajo del bloque, UNA POR NODO.
     *
     * @return Collection<int, LearningObjective>
     */
    public static function para(
        string $prefijo,
        Collection $versiones,
        bool $soloVerificadas = true,
        ?int &$sinVerificar = null,
    ): Collection {
        $candidatas = LearningObjective::query()
            ->whereIn('version_id', $versiones)
            ->where('native_code', 'like', $prefijo.'.%')
            ->get(['id', 'node_id', 'native_code', 'is_verified']);

        if ($soloVerificadas) {
            $sinVerificar = ($sinVerificar ?? 0) + $candidatas->where('is_verified', false)->count();
            $candidatas = $candidatas->where('is_verified', true);
        }

        return $candidatas
            ->groupBy('node_id')
            ->map(fn (Collection $delNodo) => $delNodo
                // Orden CURRICULAR, no de cadena: M.4.1.2 antes que M.4.1.10.
                // Comparador explícito porque `sortBy([closure, closure])` NO
                // ordena por los closures — compara null con null.
                ->sort(fn ($a, $b) => [mb_strlen($a->native_code), $a->native_code]
                    <=> [mb_strlen($b->native_code), $b->native_code])
                ->first())
            ->values();
    }

    /** El prefijo de ÁREA de un código: los segmentos hasta el primero numérico. */
    public static function area(string $codigo): string
    {
        $area = [];
        foreach (explode('.', $codigo) as $parte) {
            if (is_numeric($parte)) {
                break;
            }
            $area[] = $parte;
        }

        return implode('.', $area);
    }

    /**
     * ¿Existe el ÁREA de este código en el grafo? — la pregunta que separa una
     * ERRATA de un HUECO de cobertura, y que nadie hacía.
     *
     * Producción: el banco pedía `CS.FL.5.1` y el grafo tiene `CS.F.5.1`. Dos
     * letras, Filosofía entera sin ejercicios, y el informe lo contó como un
     * hueco más entre «o el área no está importada, o no está verificada». Esa
     * disyuntiva con «o» dejó pasar la errata — y la diferencia ES computable:
     * si NINGUNA destreza del grafo empieza por el prefijo de área, el prefijo
     * no existe y es una errata que tiene que REVENTAR. Si el área existe pero
     * el bloque concreto no, eso sí es cobertura pendiente y se avisa.
     *
     * A propósito se mira SIN filtrar por verificación: un área que existe
     * solo sin verificar (CS.EC hoy) es un hueco legítimo, no una errata. Y se
     * mira sobre DESTREZAS, no sobre nodos: un área-nodo sin destrezas tampoco
     * puede recibir contenido, así que cuenta como inexistente.
     */
    public static function areaExiste(string $codigo, Collection $versiones): bool
    {
        $area = self::area($codigo);
        if ($area === '') {
            return false;
        }

        return LearningObjective::query()
            ->whereIn('version_id', $versiones)
            ->where('native_code', 'like', $area.'.%')
            ->exists();
    }

    /**
     * `LL.4.3.1` o `LL.4.3` → `LL · subnivel 4`. El área es todo lo anterior al
     * primer segmento numérico, así que `CN.F.5.1.9` da `CN.F · subnivel 5`:
     * Física no es una asignatura, es una rama de Ciencias Naturales en BGU.
     */
    public static function ambito(string $codigo): string
    {
        $partes = explode('.', $codigo);
        $area = [];

        foreach ($partes as $parte) {
            if (is_numeric($parte)) {
                return implode('.', $area)." · subnivel {$parte}";
            }
            $area[] = $parte;
        }

        return implode('.', $area).' · sin subnivel';
    }
}
