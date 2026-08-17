<?php

namespace Tests\Feature;

use App\Console\Commands\ImportMineduc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FRENTE 2 — el parser del currículo real, probado contra FRAGMENTOS DEL PDF
 * OFICIAL (tests/fixtures/curriculo/*.txt), no contra texto inventado.
 *
 * Los dos casos que dan nombre a la misión salen de aquí:
 *  - «invertetros» = «inverte-» (columna derecha) unido con «tros» de
 *    «noso-tros» (columna IZQUIERDA de la línea siguiente).
 *  - «gimproblemas» = «gim-» unido con «problemas», que empieza otra columna.
 * Ambos nacían de deshifenar el texto de `pdftotext -layout` sin mirar de qué
 * columna venía cada trozo.
 */
class ImportMineducParserTest extends TestCase
{
    private function fixture(string $nombre): string
    {
        $ruta = dirname(__DIR__).'/fixtures/curriculo/'.$nombre;
        $this->assertFileExists($ruta, "Falta el fixture {$nombre}");

        return file_get_contents($ruta);
    }

    /** El texto tal y como lo dejaba el importador viejo: -layout + deshifenar. */
    private function comoAntes(string $layout): string
    {
        return preg_replace('/\s+/u', ' ', preg_replace('/-\R\s*/u', '', $layout));
    }

    /** El texto tal y como lo deja el parser nuevo. */
    private function comoAhora(string $texto): string
    {
        return preg_replace('/\s+/u', ' ',
            ImportMineduc::unirGuiones(ImportMineduc::reconstruirColumnas($texto)));
    }

    public function test_el_bug_invertetros_existia_de_verdad_y_esta_corregido(): void
    {
        $layout = $this->fixture('ccnn-indicadores-layout.txt');

        // 1) La prueba de que el bug era real: con el algoritmo viejo aparece.
        $this->assertStringContainsString('invertetros', $this->comoAntes($layout),
            'El fixture ya no reproduce el bug: ¿se editó a mano?');

        // 2) Y con el parser nuevo NO aparece, sino la palabra correcta.
        $ahora = $this->comoAhora($layout);
        $this->assertStringNotContainsString('invertetros', $ahora);
        $this->assertStringContainsString('vertebrados e invertebrados', $ahora);
        // La columna izquierda también se reconstruye: «noso-» + «tros».
        $this->assertStringContainsString('nosotros', $ahora);
    }

    public function test_el_bug_gimproblemas_existia_de_verdad_y_esta_corregido(): void
    {
        $layout = $this->fixture('ccnn-indicadores-layout.txt');

        $this->assertStringContainsString('gimproblemas', $this->comoAntes($layout),
            'El fixture ya no reproduce el bug: ¿se editó a mano?');

        $ahora = $this->comoAhora($layout);
        $this->assertStringNotContainsString('gimproblemas', $ahora);
        $this->assertStringContainsString('angiospermas y gimnospermas', $ahora);
    }

    public function test_reconstruir_columnas_actua_en_dos_columnas_y_no_toca_una_sola(): void
    {
        $raw = $this->fixture('ccnn-dcd-raw.txt');
        $layout = $this->fixture('ccnn-indicadores-layout.txt');

        // Una sola columna: intacto…
        $this->assertSame($raw, ImportMineduc::reconstruirColumnas($raw));
        // …pero en dos columnas SÍ reorganiza (si no, el test de arriba lo
        // pasaría igual un `return $text;` — era tautológico).
        $this->assertNotSame($layout, ImportMineduc::reconstruirColumnas($layout));
    }

    /**
     * REGRESIÓN del hallazgo CRÍTICO: en el modo por defecto de pdftotext, la
     * columna de la destreza y la de objetivos se ENTRELAZAN, y el enunciado
     * mutilado («…mediante la relaambiente físico.») pasaba el oráculo y, por
     * ser el más corto, GANABA el desempate. Fixture: región real del PDF.
     */
    public function test_un_enunciado_mutilado_nunca_le_gana_al_completo(): void
    {
        $entrelazado = $this->comoAhora($this->fixture('ccnn-dos-columnas-entrelazadas.txt'));

        // El fixture contiene de verdad el amasijo…
        $this->assertStringContainsString('relaambiente', $entrelazado);

        // …y el amasijo recortado PASA el oráculo (por eso hacía falta el
        // desempate por longitud: la validez sola no lo distingue).
        $mutilado = ImportMineduc::cortarInvasores(
            'Explicar la segunda ley de Newton, mediante la relaambiente físico.',
        );
        $this->assertTrue(ImportMineduc::evaluarCalidad($mutilado)['valido']);

        // Entre el mutilado y el completo, gana el COMPLETO.
        $completo = 'Explicar la segunda ley de Newton mediante la relación entre las magnitudes: '
            .'aceleración y fuerza que actúan sobre un objeto y su masa, mediante experimentaciones '
            .'formales o no formales.';
        $this->assertTrue(ImportMineduc::evaluarCalidad($completo)['valido']);

        $ganador = collect([['text' => $mutilado], ['text' => $completo]])
            ->sort(fn ($a, $b) => [
                ImportMineduc::evaluarCalidad($a['text'])['valido'] ? 0 : 1, -mb_strlen($a['text']),
            ] <=> [
                ImportMineduc::evaluarCalidad($b['text'])['valido'] ? 0 : 1, -mb_strlen($b['text']),
            ])->first();

        $this->assertSame($completo, $ganador['text']);
    }

    /** El recorte de cola no puede comerse la última frase de un enunciado. */
    public function test_el_recorte_respeta_los_enunciados_de_varias_frases(): void
    {
        $varias = 'Observar y describir el ciclo vital de las plantas. Registrar los cambios '
            .'en una bitácora durante cuatro semanas.';

        $this->assertSame($varias, ImportMineduc::cortarInvasores($varias));
    }

    /** Ni partir un decimal por su punto. */
    public function test_el_recorte_no_parte_un_decimal(): void
    {
        $conDecimal = 'Medir la masa de diferentes objetos con una precisión de 0.5 gramos y '
            .'registrar los resultados en una tabla.';

        $this->assertSame($conDecimal, ImportMineduc::cortarInvasores($conDecimal));
    }

    /** La notación científica del currículo NO es una palabra partida. */
    public function test_el_oraculo_acepta_la_notacion_cientifica(): void
    {
        foreach ([
            'Deducir y comunicar la importancia del pH a través de la medición de este parámetro en soluciones.',
            'Medir el volumen en mL y la energía en kWh, y registrar los datos obtenidos en la práctica.',
            'Indagar sobre el ADN y las TIC disponibles para el estudio de la herencia en los seres vivos.',
        ] as $stmt) {
            $this->assertTrue(ImportMineduc::evaluarCalidad($stmt)['valido'],
                "Falso positivo del criterio (b) sobre: {$stmt}");
        }
    }

    public function test_el_enunciado_real_se_extrae_literal_y_completo(): void
    {
        $texto = $this->comoAhora($this->fixture('ccnn-dcd-raw.txt'));

        // El enunciado oficial de CN.2.1.4, palabra por palabra.
        $this->assertStringContainsString(
            'Observar y describir las características de los animales y clasificarlos en '
            .'vertebrados e invertebrados, por la presencia o ausencia de columna vertebral.',
            $texto,
        );
        // Y el de CN.2.1.3, que era el que salía como «ciclo vital ción crít…».
        $this->assertStringContainsString(
            'Experimentar y predecir las etapas del ciclo vital de las plantas',
            $texto,
        );
    }

    /** Los indicadores I.CN.x NO son destrezas: no pueden colarse como tales. */
    public function test_los_indicadores_de_evaluacion_no_son_destrezas(): void
    {
        $texto = $this->comoAhora($this->fixture('ccnn-indicadores-layout.txt'));

        // El texto SÍ contiene «I.CN.2.2.1».
        $this->assertStringContainsString('I.CN.2.2.1', $texto);

        // Pero el regex de destrezas (con su lookbehind) no lo captura.
        $codeRe = '/(?<![A-Za-z]\.)\b((?:CN\.[FQB]|CS\.[HFC]|CN|CS|LL|M|ECA|EF|EG)\.(\d)\.(\d{1,2})\.(\d{1,3}))\.?\s/u';
        preg_match_all($codeRe, $texto, $m);
        $this->assertSame([], $m[1], 'Un indicador I.CN.x se está importando como destreza');
    }

    public function test_la_cola_de_mobiliario_de_pagina_se_recorta(): void
    {
        $limpio = ImportMineduc::cortarInvasores(
            'Usar modelos y describir la reproducción asexual en los seres vivos, identificar sus '
            .'tipos y deducir su importancia para la supervivencia de la especie. 156 Educación '
            .'General Básica Superior CIENCIAS NATURALES',
        );

        $this->assertStringEndsWith('para la supervivencia de la especie.', $limpio);
        $this->assertStringNotContainsString('CIENCIAS NATURALES', $limpio);
        $this->assertStringNotContainsString('156', $limpio);
    }

    // ---------- El oráculo de calidad, criterio por criterio ----------

    public function test_el_oraculo_acepta_un_enunciado_oficial_de_verdad(): void
    {
        $bueno = 'Observar y describir las características de los animales y clasificarlos en '
            .'vertebrados e invertebrados, por la presencia o ausencia de columna vertebral.';

        $this->assertTrue(ImportMineduc::evaluarCalidad($bueno)['valido']);
    }

    #[DataProvider('enunciadosInvalidos')]
    public function test_el_oraculo_rechaza(string $motivo, string $stmt): void
    {
        $r = ImportMineduc::evaluarCalidad($stmt);

        $this->assertFalse($r['valido'], "Debía rechazarse por «{$motivo}»: {$stmt}");
        $this->assertSame($motivo, $r['motivo']);
    }

    public static function enunciadosInvalidos(): array
    {
        return [
            '(a) arrastra un código de objetivo' => [
                'arrastra un código curricular',
                'Observar y explicar la fuerza de gravedad y sus efectos OG.CN.10. Apreciar la importancia.',
            ],
            '(a) arrastra otra destreza' => [
                'arrastra un código curricular',
                'Indagar sobre los animales y su hábitat natural CN.2.1.5. Indagar sobre los animales.',
            ],
            '(b) palabra partida con guion suelto' => [
                'palabra partida o pegada',
                'Observar y describir las partes de la planta, expli- car sus funciones en la nutrición.',
            ],
            '(b) palabra pegada a mayúscula' => [
                'palabra partida o pegada',
                'Observar y describir el ciclo vital de las plantas y la semillaCN de los animales domésticos.',
            ],
            '(c) demasiado corto' => [
                'demasiado corto',
                'Observar el ciclo.',
            ],
            '(d) no termina en puntuación' => [
                'no termina en puntuación',
                'Indagar e identificar las diferentes clases de amenazas de los hábitats locales y',
            ],
        ];
    }

    public function test_un_codigo_invasor_corta_el_enunciado(): void
    {
        $limpio = ImportMineduc::cortarInvasores(
            'Observar y explicar la fuerza de gravedad y expeOG.CN.10. Apreciar la importancia',
        );

        // Se corta aunque el código venga PEGADO a la palabra anterior.
        $this->assertStringNotContainsString('OG.CN', $limpio);
        $this->assertStringNotContainsString('Apreciar', $limpio);
    }
}
