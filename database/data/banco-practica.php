<?php

/**
 * BANCO INICIAL DE ÍTEMS DE PRÁCTICA.
 *
 * Cada entrada se ancla a un BLOQUE del currículo mediante el prefijo del
 * código: `LL.4.3` es Lengua y Literatura · subnivel 4 (Básica Superior) ·
 * bloque 3 (Lectura). Esa correspondencia no es una suposición — es la
 * estructura del propio código MINEDEC, y está en curriculo-semilla.json.
 *
 * QUÉ GARANTIZA ESTE ANCLAJE Y QUÉ NO. Garantiza que el ítem cae dentro del
 * bloque y del nivel que le tocan. NO garantiza que corresponda uno a uno con
 * el enunciado oficial de la destreza concreta a la que acabe pegado: los PDF
 * del Ministerio no están en este entorno (storage/curriculo/README.md), así
 * que no se pudo cotejar destreza por destreza. Por eso cada ítem nace con
 * `attrs.revision.alineado_a = 'bloque'` — la marca de que un docente tiene
 * que darle el visto bueno, igual que el crosswalk no entra a producción sin
 * `reviewed_at`. Lo que NO se hizo fue inventar cobertura y callarlo.
 *
 * COBERTURA: el banco cubre TODOS los bloques de cada asignatura × subnivel
 * que declara cubrir. Física, Química y Biología no son asignaturas: son las
 * ramas CN.F.*, CN.Q.* y CN.B.* de Ciencias Naturales en BGU — por eso el
 * código que tumbaba al seeder viejo era CN.F.5.1.9.
 *
 * DÓNDE VA LA RESPUESTA: aquí la correcta se escribe la PRIMERA por convención
 * de autoría (así el fichero se revisa de un vistazo), pero al sembrar va a la
 * columna `practice_items.answer_key`, nunca a `params` ni a `attrs` — que sí
 * se serializan al cliente. Las opciones viajan con su clave inmutable; la
 * semilla solo baraja el orden de pintado.
 *
 * Reglas de contenido:
 *  - El enunciado es del nivel del grado y del tema del bloque.
 *  - Los distractores de opción múltiple son PLAUSIBLES: errores que un alumno
 *    comete de verdad, no relleno. Nada de «todas las anteriores».
 *  - Los numéricos son PARAMETRIZADOS: los números cambian con la semilla, así
 *    que repetir el ejercicio no es repetir la respuesta.
 *  - Las expresiones solo usan lo que admite MathExpression (+ - * / ^,
 *    sqrt abs sin cos tan asin acos atan deg2rad rad2deg, pi).
 *
 * Formato de una entrada:
 *   numérica: [bloque, 'numeric', enunciado, params, expresión, tolerancia, tipo, unidad]
 *   opción:   [bloque, 'choice',  enunciado, opciones, clave correcta]
 *
 * En las de opción, `opciones` es clave => texto y la clave es INMUTABLE: es lo
 * que el alumno envía de vuelta y contra lo que se corrige.
 */

return [
    // ================= MATEMÁTICA =================

    // — Básica Elemental (2.º-4.º EGB) —
    ['M.2.1', 'numeric', 'En una granja hay {a} gallinas y llegan {b} más. ¿Cuántas gallinas hay ahora?',
        ['a' => ['min' => 12, 'max' => 40, 'step' => 1], 'b' => ['min' => 5, 'max' => 25, 'step' => 1]],
        'a + b', 0.001, 'abs', null],
    ['M.2.2', 'numeric', 'Un patio rectangular mide {l} metros de largo y {w} metros de ancho. ¿Cuántos metros mide su contorno?',
        ['l' => ['min' => 3, 'max' => 12, 'step' => 1], 'w' => ['min' => 2, 'max' => 9, 'step' => 1]],
        '2 * (l + w)', 0.001, 'abs', 'm'],
    // Bloque 3 = Estadística y probabilidad: leer un pictograma, no sumar.
    ['M.2.3', 'numeric', 'En un pictograma, cada dibujo representa a 1 niño. Fútbol tiene {r} dibujos y básquet {v}. ¿Cuántos niños respondieron en total?',
        ['r' => ['min' => 4, 'max' => 15, 'step' => 1], 'v' => ['min' => 3, 'max' => 14, 'step' => 1]],
        'r + v', 0.001, 'abs', 'niños'],

    // — Básica Media (5.º-7.º EGB) —
    ['M.3.1', 'numeric', 'Un paquete trae {u} cuadernos y se compran {p} paquetes. ¿Cuántos cuadernos son en total?',
        ['u' => ['min' => 4, 'max' => 12, 'step' => 1], 'p' => ['min' => 3, 'max' => 15, 'step' => 1]],
        'u * p', 0.001, 'abs', null],
    ['M.3.2', 'numeric', 'Un terreno rectangular mide {l} m de largo y {w} m de ancho. ¿Cuál es su área en metros cuadrados?',
        ['l' => ['min' => 6, 'max' => 25, 'step' => 1], 'w' => ['min' => 4, 'max' => 18, 'step' => 1]],
        'l * w', 0.001, 'abs', 'm²'],
    ['M.3.3', 'numeric', 'En cuatro pruebas, Ana sacó {a}, {b}, {c} y {d} puntos. ¿Cuál es su promedio?',
        [
            'a' => ['min' => 4, 'max' => 10, 'step' => 1], 'b' => ['min' => 4, 'max' => 10, 'step' => 1],
            'c' => ['min' => 4, 'max' => 10, 'step' => 1], 'd' => ['min' => 4, 'max' => 10, 'step' => 1],
        ],
        '(a + b + c + d) / 4', 0.01, 'abs', 'puntos'],

    // — Básica Superior (8.º-10.º EGB) —
    // Entera POR CONSTRUCCIÓN. Con `{a}x + {b} = {c}` y rangos independientes,
    // el 77 % de los casos pedía decimales (9x+20=25 → x=0.5556) en el primer
    // bloque de álgebra de 8.º EGB, y con tolerancia absoluta de 0.01 había que
    // dar cuatro cifras. Aquí la solución es siempre a·k.
    ['M.4.1', 'numeric', 'Resuelve la ecuación x/{a} = {k}. ¿Cuánto vale x?',
        ['a' => ['min' => 2, 'max' => 9, 'step' => 1], 'k' => ['min' => 2, 'max' => 12, 'step' => 1]],
        'a * k', 0.001, 'abs', null],
    ['M.4.2', 'numeric', 'Un triángulo rectángulo tiene catetos de {a} cm y {b} cm. ¿Cuánto mide la hipotenusa?',
        ['a' => ['min' => 3, 'max' => 20, 'step' => 1], 'b' => ['min' => 4, 'max' => 20, 'step' => 1]],
        'sqrt(a^2 + b^2)', 0.02, 'rel', 'cm'],
    ['M.4.3', 'numeric', 'En una urna hay {f} bolas favorables de un total de {t}. ¿Cuál es la probabilidad de sacar una favorable? Responde en tanto por ciento.',
        ['f' => ['min' => 1, 'max' => 9, 'step' => 1], 't' => ['min' => 10, 'max' => 40, 'step' => 1]],
        'f / t * 100', 0.02, 'rel', '%'],

    // — BGU (1.º-3.º) —
    ['M.5.1', 'numeric', 'En una progresión aritmética el primer término es {a} y la diferencia común es {d}. ¿Cuál es el término número {n}?',
        [
            'a' => ['min' => 2, 'max' => 15, 'step' => 1], 'd' => ['min' => 2, 'max' => 9, 'step' => 1],
            'n' => ['min' => 6, 'max' => 20, 'step' => 1],
        ],
        'a + (n - 1) * d', 0.001, 'abs', null],
    ['M.5.2', 'numeric', 'Calcula la distancia entre los puntos A({x1}, {y1}) y B({x2}, {y2}) del plano cartesiano.',
        [
            'x1' => ['min' => -6, 'max' => 2, 'step' => 1], 'y1' => ['min' => -6, 'max' => 2, 'step' => 1],
            'x2' => ['min' => 3, 'max' => 10, 'step' => 1], 'y2' => ['min' => 3, 'max' => 10, 'step' => 1],
        ],
        'sqrt((x2 - x1)^2 + (y2 - y1)^2)', 0.02, 'rel', 'unidades'],
    ['M.5.3', 'numeric', 'De {t} estudiantes encuestados, {f} practican fútbol. ¿Qué porcentaje del total representan?',
        ['t' => ['min' => 20, 'max' => 60, 'step' => 1], 'f' => ['min' => 5, 'max' => 19, 'step' => 1]],
        'f / t * 100', 0.02, 'rel', '%'],

    // ================= LENGUA Y LITERATURA =================

    // — Básica Elemental —
    ['LL.2.1', 'choice', 'En el Ecuador se hablan varias lenguas además del castellano. ¿Cuál de estas es una lengua ancestral ecuatoriana?',
        ['a' => 'El kichwa', 'b' => 'El portugués', 'c' => 'El italiano', 'd' => 'El alemán'], 'a'],
    ['LL.2.3', 'choice', 'Lee: «Martina guardó el paraguas porque el cielo estaba muy oscuro.» ¿Por qué guardó el paraguas Martina?',
        [
            'a' => 'Porque parecía que iba a llover',
            'b' => 'Porque el paraguas estaba roto',
            'c' => 'Porque hacía mucho calor',
            'd' => 'Porque se iba a dormir',
        ], 'a'],
    ['LL.2.4', 'choice', '¿Cuál de estas oraciones está escrita correctamente?',
        [
            'a' => 'Mi hermana vive en Quito.',
            'b' => 'mi hermana vive en quito',
            'c' => 'Mi Hermana Vive En Quito',
            'd' => 'mi hermana Vive en Quito',
        ], 'a'],

    // — Básica Media —
    ['LL.3.2', 'choice', 'Cuando alguien expone un tema ante la clase, ¿cuál de estas actitudes corresponde a escuchar de forma activa?',
        [
            'a' => 'Mirar a quien habla y esperar el turno para preguntar',
            'b' => 'Interrumpir cada vez que se te ocurre algo',
            'c' => 'Conversar en voz baja con el compañero de al lado',
            'd' => 'Levantarse a mitad de la exposición',
        ], 'a'],
    ['LL.3.3', 'choice', 'Lee: «El colibrí bate sus alas hasta 80 veces por segundo, lo que le permite quedarse quieto en el aire.» ¿Cuál es la idea principal del texto?',
        [
            'a' => 'El colibrí puede mantenerse suspendido gracias a la velocidad de sus alas',
            'b' => 'El colibrí es un ave muy pequeña',
            'c' => 'El colibrí vive en el Ecuador',
            'd' => 'Las aves tienen alas',
        ], 'a'],
    ['LL.3.5', 'choice', 'Un texto que narra hechos maravillosos protagonizados por héroes y dioses, y que explica el origen de algo, es:',
        ['a' => 'Un mito', 'b' => 'Una noticia', 'c' => 'Una receta', 'd' => 'Un anuncio'], 'a'],

    // — Básica Superior —
    ['LL.4.1', 'choice', 'Decimos que una lengua tiene VARIEDADES cuando cambia según la región o el grupo que la habla. ¿Cuál de estos casos es un ejemplo de variedad lingüística?',
        [
            'a' => 'En la Costa se dice «guagua» y en la Sierra también, pero con distinta entonación',
            'b' => 'Una persona escribe con faltas de ortografía',
            'c' => 'Un niño todavía no aprende a leer',
            'd' => 'Un texto tiene muchas páginas',
        ], 'a'],
    ['LL.4.3', 'choice', 'Lee: «Aunque el informe presenta datos abundantes, sus conclusiones no se sostienen.» ¿Qué expresa la palabra «aunque»?',
        [
            'a' => 'Una oposición entre las dos partes de la oración',
            'b' => 'Una causa',
            'c' => 'Una consecuencia',
            'd' => 'Una condición',
        ], 'a'],
    ['LL.4.4', 'choice', 'En un texto argumentativo, ¿cuál es la función de la TESIS?',
        [
            'a' => 'Enunciar la postura que el texto va a defender',
            'b' => 'Resumir lo que otros autores dijeron',
            'c' => 'Describir el lugar donde ocurre la acción',
            'd' => 'Enumerar las fuentes consultadas',
        ], 'a'],

    // — BGU —
    ['LL.5.3', 'choice', 'Lee: «La empresa reconoce que hubo demoras, si bien atribuye la responsabilidad a sus proveedores.» ¿Qué hace el autor con la segunda parte de la oración?',
        [
            'a' => 'Admite el hecho pero traslada la responsabilidad',
            'b' => 'Niega que hubiera demoras',
            'c' => 'Se disculpa sin matices',
            'd' => 'Aporta una prueba documental',
        ], 'a'],
    // Bloque 4 = Escritura: distinguir hecho de opinión es lectura (bloque 3).
    ['LL.5.4', 'choice', 'Al redactar un texto argumentativo, ¿qué función cumple el párrafo de cierre?',
        [
            'a' => 'Recoger la tesis y las razones expuestas para dejarlas asentadas',
            'b' => 'Introducir un argumento nuevo que no se ha desarrollado',
            'c' => 'Enumerar las fuentes consultadas',
            'd' => 'Repetir literalmente el primer párrafo',
        ], 'a'],
    ['LL.5.5', 'choice', 'En un texto literario, ¿qué es una METÁFORA?',
        [
            'a' => 'Identificar una cosa con otra por una semejanza, sin usar «como»',
            'b' => 'Repetir un sonido al inicio de varias palabras',
            'c' => 'Exagerar deliberadamente una cualidad',
            'd' => 'Atribuir cualidades humanas a un objeto',
        ], 'a'],

    // ================= CIENCIAS NATURALES =================

    // — Básica Elemental —
    ['CN.2.1', 'choice', '¿Qué necesitan las plantas para fabricar su propio alimento?',
        [
            'a' => 'Luz del sol, agua y aire',
            'b' => 'Solo tierra',
            'c' => 'Únicamente oscuridad',
            'd' => 'Comida que les dan los animales',
        ], 'a'],
    ['CN.2.2', 'choice', '¿Cuál de estos hábitos ayuda a cuidar la salud de los dientes?',
        [
            'a' => 'Cepillarlos después de cada comida',
            'b' => 'Comer dulces antes de dormir',
            'c' => 'Usarlos para abrir envases',
            'd' => 'Cepillarlos una vez por semana',
        ], 'a'],
    ['CN.2.3', 'choice', 'El agua de un charco desaparece con el sol. ¿Qué le ocurrió?',
        [
            'a' => 'Se evaporó y pasó al aire',
            'b' => 'Se convirtió en tierra',
            'c' => 'Desapareció para siempre',
            'd' => 'Se volvió más pesada',
        ], 'a'],

    // — Básica Media —
    ['CN.3.1', 'choice', 'En una cadena alimenticia, ¿qué papel cumplen las plantas?',
        [
            'a' => 'Son productoras: fabrican su alimento',
            'b' => 'Son consumidoras primarias',
            'c' => 'Son descomponedoras',
            'd' => 'Son depredadoras',
        ], 'a'],
    ['CN.3.3', 'choice', 'Si un cuerpo cambia de estado sólido a líquido, el proceso se llama:',
        ['a' => 'Fusión', 'b' => 'Evaporación', 'c' => 'Condensación', 'd' => 'Sublimación'], 'a'],
    ['CN.3.4', 'choice', '¿Qué causa el día y la noche en la Tierra?',
        [
            'a' => 'La rotación de la Tierra sobre su propio eje',
            'b' => 'La traslación de la Tierra alrededor del Sol',
            'c' => 'Que el Sol se apaga por la noche',
            'd' => 'Las fases de la Luna',
        ], 'a'],

    // — Básica Superior (mezcla: conceptual + numérico) —
    ['CN.4.1', 'choice', 'Dos especies distintas viven juntas y AMBAS salen beneficiadas. Esa relación se llama:',
        ['a' => 'Mutualismo', 'b' => 'Parasitismo', 'c' => 'Depredación', 'd' => 'Competencia'], 'a'],
    ['CN.4.2', 'choice', '¿Cuál es la función principal de los glóbulos rojos?',
        [
            'a' => 'Transportar oxígeno por la sangre',
            'b' => 'Defender al cuerpo de infecciones',
            'c' => 'Coagular las heridas',
            'd' => 'Digerir los alimentos',
        ], 'a'],
    ['CN.4.3', 'numeric', 'Un cuerpo tiene una masa de {m} g y ocupa un volumen de {v} cm³. ¿Cuál es su densidad en g/cm³?',
        // Hasta 12.5 g/cm³: plomo (11.3) sí, tres veces el osmio no.
        ['m' => ['min' => 20, 'max' => 250, 'step' => 5], 'v' => ['min' => 20, 'max' => 120, 'step' => 5]],
        'm / v', 0.02, 'rel', 'g/cm³'],

    // ================= ESTUDIOS SOCIALES =================

    // — Básica Elemental —
    ['CS.2.1', 'choice', '¿Para qué sirven las fotografías antiguas de una familia?',
        [
            'a' => 'Para conocer cómo vivían antes las personas de esa familia',
            'b' => 'Para saber qué tiempo hará mañana',
            'c' => 'Para medir distancias',
            'd' => 'Para aprender matemática',
        ], 'a'],
    ['CS.2.2', 'choice', '¿Cuál de estos es un elemento NATURAL del paisaje?',
        ['a' => 'Un río', 'b' => 'Un puente', 'c' => 'Una carretera', 'd' => 'Un edificio'], 'a'],
    ['CS.2.3', 'choice', 'En una escuela conviven estudiantes de distintas provincias. ¿Cuál es la mejor forma de convivir?',
        [
            'a' => 'Respetar las costumbres de cada quien',
            'b' => 'Pedir que todos hablen igual',
            'c' => 'Jugar solo con los de la propia provincia',
            'd' => 'Burlarse de quien habla distinto',
        ], 'a'],

    // — Básica Media —
    ['CS.3.1', 'choice', 'Antes de la llegada de los españoles, ¿qué pueblo había extendido su dominio sobre el actual territorio ecuatoriano?',
        ['a' => 'Los incas', 'b' => 'Los mayas', 'c' => 'Los aztecas', 'd' => 'Los guaraníes'], 'a'],
    // Sin «e insular» en el enunciado: era una pista —la correcta es la única
    // que dice «Insular»— y además hacía defendible la opción de tres regiones
    // para quien leyera «Ecuador continental».
    ['CS.3.2', 'choice', '¿Cuántas regiones naturales tiene el Ecuador?',
        [
            'a' => 'Cuatro: Costa, Sierra, Amazonía e Insular',
            'b' => 'Dos: Costa y Sierra',
            'c' => 'Tres: Costa, Sierra y Amazonía',
            'd' => 'Cinco',
        ], 'a'],
    ['CS.3.3', 'choice', '¿Qué es un derecho?',
        [
            'a' => 'Algo que toda persona puede exigir por el hecho de serlo',
            'b' => 'Un premio que se gana portándose bien',
            'c' => 'Una orden que da la autoridad',
            'd' => 'Un permiso que se compra',
        ], 'a'],

    // — Básica Superior —
    ['CS.4.1', 'choice', 'La Revolución Juliana (1925) en el Ecuador se caracterizó por:',
        [
            'a' => 'El intento de los militares de limitar el poder de la banca guayaquileña',
            'b' => 'La independencia de Guayaquil',
            'c' => 'La firma del Protocolo de Río de Janeiro',
            'd' => 'La fundación de Quito',
        ], 'a'],
    ['CS.4.2', 'choice', '¿Qué explica que el Ecuador tenga tanta diversidad de climas en un territorio pequeño?',
        [
            'a' => 'La cordillera de los Andes y las corrientes marinas',
            'b' => 'Su gran extensión territorial',
            'c' => 'Estar lejos de la línea ecuatorial',
            'd' => 'La ausencia de montañas',
        ], 'a'],
    ['CS.4.3', 'choice', 'En una democracia, ¿qué significa que el poder sea «de la ciudadanía»?',
        [
            'a' => 'Que las autoridades se eligen y responden ante quienes las eligieron',
            'b' => 'Que manda quien tiene más dinero',
            'c' => 'Que las decisiones las toma siempre una sola persona',
            'd' => 'Que nadie tiene que cumplir las leyes',
        ], 'a'],

    // ========== CIENCIAS NATURALES EN BGU (ramas CN.F, CN.Q, CN.B) ==========

    ['CN.F.5.2', 'numeric', 'Un cuerpo de {m} kg se levanta a {h} m de altura. ¿Cuál es su energía potencial gravitatoria? (g = {g} m/s²)',
        [
            'm' => ['min' => 2, 'max' => 30, 'step' => 1], 'h' => ['min' => 1, 'max' => 20, 'step' => 0.5],
            'g' => ['const' => 9.8],
        ],
        'm * g * h', 0.02, 'rel', 'J'],
    ['CN.F.5.4', 'numeric', 'Por una resistencia de {r} Ω circula una corriente de {i} A. ¿Cuál es la diferencia de potencial entre sus extremos?',
        ['r' => ['min' => 10, 'max' => 200, 'step' => 5], 'i' => ['min' => 0.1, 'max' => 2, 'step' => 0.1]],
        'r * i', 0.02, 'rel', 'V'],
    ['CN.F.5.3', 'numeric', 'Una onda tiene una frecuencia de {f} Hz y una longitud de onda de {l} m. ¿Cuál es su rapidez de propagación?',
        ['f' => ['min' => 20, 'max' => 500, 'step' => 10], 'l' => ['min' => 0.5, 'max' => 8, 'step' => 0.5]],
        'f * l', 0.02, 'rel', 'm/s'],

    ['CN.Q.5.1', 'choice', '¿Cuál de estos es un cambio QUÍMICO y no físico?',
        [
            'a' => 'Un clavo de hierro que se oxida',
            'b' => 'El hielo que se derrite',
            'c' => 'El azúcar que se disuelve en agua',
            'd' => 'El agua que hierve',
        ], 'a'],
    ['CN.Q.5.2', 'numeric', 'Un átomo neutro tiene {p} protones y {n} neutrones. ¿Cuál es su número másico?',
        // Rangos solapados: se evitan los núclidos disparatados tipo p=30, n=3.
        ['p' => ['min' => 6, 'max' => 20, 'step' => 1], 'n' => ['min' => 6, 'max' => 24, 'step' => 1]],
        'p + n', 0.001, 'abs', null],
    ['CN.Q.5.3', 'numeric', 'Se disuelven {m} g de soluto en {v} mL de solución. ¿Cuál es la concentración en g/L?',
        // Hasta 150 g/L: por debajo de la solubilidad del NaCl (~360 g/L).
        ['m' => ['min' => 2, 'max' => 30, 'step' => 1], 'v' => ['min' => 200, 'max' => 1000, 'step' => 50]],
        'm / (v / 1000)', 0.02, 'rel', 'g/L'],

    ['CN.B.5.1', 'choice', '¿Qué estructura de la célula vegetal NO está presente en la célula animal?',
        ['a' => 'La pared celular', 'b' => 'El núcleo', 'c' => 'La membrana celular', 'd' => 'El citoplasma'], 'a'],
    ['CN.B.5.3', 'choice', 'Según la selección natural, ¿por qué se vuelven más frecuentes ciertos rasgos en una población?',
        [
            'a' => 'Porque quienes los tienen dejan más descendencia en ese ambiente',
            'b' => 'Porque los individuos deciden cambiar para adaptarse',
            'c' => 'Porque el ambiente modifica directamente los genes',
            'd' => 'Porque los rasgos aparecen cuando hacen falta',
        ], 'a'],
    ['CN.B.5.2', 'choice', 'En un ecosistema, ¿qué ocurre si desaparece el depredador principal?',
        [
            'a' => 'La población de sus presas tiende a crecer sin control',
            'b' => 'Todas las especies desaparecen de inmediato',
            'c' => 'No ocurre ningún cambio',
            'd' => 'Las plantas dejan de hacer fotosíntesis',
        ], 'a'],

    // ===== ESTUDIOS SOCIALES EN BGU (ramas CS.H, CS.EC) =====

    ['CS.H.5.1', 'choice', '¿Qué caracterizó a la sociedad feudal europea?',
        [
            'a' => 'Una relación de vasallaje en la que la tierra se cedía a cambio de servicios',
            'b' => 'La producción industrial en fábricas',
            'c' => 'El sufragio universal',
            'd' => 'La economía basada en la banca internacional',
        ], 'a'],
    ['CS.H.5.2', 'choice', 'La Ilustración del siglo XVIII defendió sobre todo:',
        [
            'a' => 'El uso de la razón y la crítica a la autoridad heredada',
            'b' => 'El regreso al poder absoluto de los reyes',
            'c' => 'La expansión del feudalismo',
            'd' => 'El abandono de la ciencia',
        ], 'a'],
    ['CS.H.5.3', 'choice', 'El Ecuador se separó de la Gran Colombia en:',
        ['a' => '1830', 'b' => '1809', 'c' => '1822', 'd' => '1895'], 'a'],

    ['CS.EC.5.1', 'choice', '¿Qué distingue a un DERECHO de un privilegio?',
        [
            'a' => 'El derecho corresponde a todas las personas; el privilegio, solo a algunas',
            'b' => 'El derecho se compra y el privilegio se hereda',
            'c' => 'No hay ninguna diferencia',
            'd' => 'El privilegio está en la Constitución y el derecho no',
        ], 'a'],
    ['CS.EC.5.2', 'choice', 'En la democracia moderna, ¿para qué sirve la división de poderes?',
        [
            // Sin doble negación: «ningún poder … sin contrapeso» costaba de leer
            // más que de entender, que es lo contrario de lo que debe pedir un ítem.
            'a' => 'Para que cada poder limite y controle a los otros',
            'b' => 'Para que las decisiones se tomen más rápido',
            'c' => 'Para que el presidente pueda gobernar sin oposición',
            'd' => 'Para reducir el número de funcionarios',
        ], 'a'],
    ['CS.EC.5.3', 'choice', '¿Qué es el Estado laico?',
        [
            'a' => 'Aquel que no adopta una religión oficial y garantiza la libertad de culto',
            'b' => 'Aquel que prohíbe todas las religiones',
            'c' => 'Aquel que obliga a profesar una religión',
            'd' => 'Aquel que no tiene Constitución',
        ], 'a'],

    // ===== Bloques que completan cada asignatura × subnivel cubierto =====
    // (el suelo es que NINGÚN bloque de un ámbito cubierto se quede sin ítem)

    // Lengua y Literatura
    ['LL.2.2', 'choice', 'Cuando conversamos con alguien, ¿qué debemos hacer para que la otra persona nos entienda?',
        [
            'a' => 'Hablar con claridad y esperar nuestro turno',
            'b' => 'Hablar muy rápido para terminar antes',
            'c' => 'Hablar todos a la vez',
            'd' => 'Hablar en voz muy bajita',
        ], 'a'],
    ['LL.2.5', 'choice', 'En el cuento «La tortuga y la liebre», la liebre pierde la carrera porque:',
        [
            'a' => 'Se confió y se detuvo a descansar',
            'b' => 'Corría más despacio que la tortuga',
            'c' => 'Se perdió en el camino',
            'd' => 'No quiso participar',
        ], 'a'],
    ['LL.3.1', 'choice', '¿Por qué decimos que el Ecuador es un país plurinacional?',
        [
            'a' => 'Porque en él conviven varios pueblos y nacionalidades con culturas propias',
            'b' => 'Porque tiene fronteras con varios países',
            'c' => 'Porque su bandera tiene tres colores',
            'd' => 'Porque está dividido en provincias',
        ], 'a'],
    ['LL.3.4', 'choice', 'Antes de escribir un texto, ¿cuál es el primer paso de la planificación?',
        [
            'a' => 'Decidir para quién se escribe y con qué propósito',
            'b' => 'Revisar la ortografía',
            'c' => 'Pasarlo en limpio',
            'd' => 'Contar las palabras',
        ], 'a'],
    ['LL.4.2', 'choice', 'En un debate, ¿qué distingue a un buen argumento de una simple opinión?',
        [
            'a' => 'Que se apoya en razones o datos que se pueden comprobar',
            'b' => 'Que se dice con voz más fuerte',
            'c' => 'Que lo repite más gente',
            'd' => 'Que es más largo',
        ], 'a'],
    ['LL.4.5', 'choice', '¿Qué caracteriza al género LÍRICO frente al narrativo?',
        [
            'a' => 'Expresa sentimientos y emociones del hablante, más que contar una historia',
            'b' => 'Siempre está escrito en prosa',
            'c' => 'Se representa sobre un escenario',
            'd' => 'Solo trata temas históricos',
        ], 'a'],
    ['LL.5.1', 'choice', 'Un préstamo lingüístico es:',
        [
            'a' => 'Una palabra que una lengua toma de otra y adapta',
            'b' => 'Un libro que se pide en la biblioteca',
            'c' => 'Una palabra que cambia de significado con el tiempo',
            'd' => 'Un error de ortografía frecuente',
        ], 'a'],
    ['LL.5.2', 'choice', 'En una exposición oral académica, ¿qué recurso da MÁS credibilidad a lo que se afirma?',
        [
            'a' => 'Citar la fuente de los datos que se presentan',
            'b' => 'Hablar durante más tiempo',
            'c' => 'Usar palabras difíciles',
            'd' => 'Repetir la idea varias veces',
        ], 'a'],

    // Ciencias Naturales
    ['CN.2.4', 'choice', '¿Qué vemos en el cielo durante el día?',
        ['a' => 'El Sol', 'b' => 'Las estrellas brillantes', 'c' => 'La Vía Láctea', 'd' => 'Solo la Luna'], 'a'],
    ['CN.2.5', 'choice', 'Para saber si una planta crece más con luz o sin luz, lo correcto es:',
        [
            'a' => 'Poner dos plantas iguales, una con luz y otra sin luz, y comparar',
            'b' => 'Mirar una sola planta y adivinar',
            'c' => 'Preguntar a un compañero',
            'd' => 'Regar mucho una de las dos',
        ], 'a'],
    ['CN.3.2', 'choice', '¿Cuál es la función del sistema respiratorio?',
        [
            'a' => 'Tomar oxígeno del aire y expulsar dióxido de carbono',
            'b' => 'Digerir los alimentos',
            'c' => 'Bombear la sangre',
            'd' => 'Sostener el cuerpo',
        ], 'a'],
    ['CN.3.5', 'choice', 'En un experimento escolar, ¿para qué sirve repetir la medición varias veces?',
        [
            'a' => 'Para reducir el efecto de los errores de medida',
            'b' => 'Para gastar más tiempo',
            'c' => 'Para cambiar el resultado',
            'd' => 'Para no tener que anotar nada',
        ], 'a'],
    ['CN.4.4', 'choice', '¿Qué provoca las estaciones del año?',
        [
            'a' => 'La inclinación del eje terrestre durante la traslación',
            'b' => 'La distancia variable al Sol únicamente',
            'c' => 'La rotación de la Tierra',
            'd' => 'Las fases de la Luna',
        ], 'a'],
    ['CN.4.5', 'choice', 'En una investigación, una HIPÓTESIS es:',
        [
            'a' => 'Una explicación provisional que se puede poner a prueba',
            'b' => 'La conclusión final del trabajo',
            'c' => 'Un dato medido en el laboratorio',
            'd' => 'La opinión del profesor',
        ], 'a'],

    // Ciencias Naturales en BGU
    ['CN.F.5.1', 'numeric', 'Sobre un cuerpo de {m} kg actúa una fuerza neta de {f} N. ¿Cuál es su aceleración?',
        ['m' => ['min' => 2, 'max' => 40, 'step' => 1], 'f' => ['min' => 5, 'max' => 200, 'step' => 5]],
        'f / m', 0.02, 'rel', 'm/s²'],
    // Bloque 5 = «La física de nuestro entorno»: la cinemática es del bloque 1,
    // y «un vehículo» a 800 km/h (400 km en 0.5 h) no es de este mundo.
    ['CN.F.5.5', 'numeric', 'Un foco de {p} W permanece encendido {t} horas. ¿Cuánta energía consume, en kWh?',
        ['p' => ['min' => 20, 'max' => 200, 'step' => 10], 't' => ['min' => 1, 'max' => 12, 'step' => 1]],
        'p * t / 1000', 0.02, 'rel', 'kWh'],
    ['CN.Q.5.4', 'choice', '¿Qué caracteriza a los compuestos orgánicos?',
        [
            'a' => 'Están formados principalmente por cadenas de carbono',
            'b' => 'Siempre contienen metales',
            'c' => 'Nunca contienen hidrógeno',
            'd' => 'Solo existen en el laboratorio',
        ], 'a'],
    ['CN.Q.5.5', 'choice', 'En la fórmula H₂O, ¿qué indica el subíndice 2?',
        [
            'a' => 'Que hay dos átomos de hidrógeno por molécula',
            'b' => 'Que hay dos moléculas de agua',
            'c' => 'Que el hidrógeno pesa el doble',
            'd' => 'Que la reacción ocurre dos veces',
        ], 'a'],
    ['CN.B.5.4', 'choice', '¿Cuál es la función principal del sistema inmunológico?',
        [
            'a' => 'Defender al organismo de agentes que lo dañan',
            'b' => 'Transportar nutrientes',
            'c' => 'Producir energía',
            'd' => 'Regular la temperatura',
        ], 'a'],
    ['CN.B.5.5', 'choice', 'Un cultivo transgénico es aquel al que:',
        [
            'a' => 'Se le ha introducido un gen de otra especie',
            'b' => 'Se le ha aplicado más fertilizante',
            'c' => 'Se ha cultivado sin agua',
            'd' => 'Se ha cosechado antes de tiempo',
        ], 'a'],

    // Filosofía (rama CS.F de Estudios Sociales en BGU — CS.FL era la errata)
    ['CS.F.5.1', 'choice', '¿Qué distingue a una pregunta filosófica de una pregunta científica?',
        [
            'a' => 'La filosófica interroga los supuestos; la científica se responde con datos',
            'b' => 'La filosófica siempre tiene una única respuesta',
            'c' => 'La científica no usa razonamiento',
            'd' => 'No hay ninguna diferencia',
        ], 'a'],
    ['CS.F.5.2', 'choice', 'Un razonamiento es VÁLIDO cuando:',
        [
            'a' => 'La conclusión se sigue necesariamente de las premisas',
            'b' => 'La conclusión es verdadera',
            'c' => 'Las premisas son creíbles',
            'd' => 'Lo defiende alguien con autoridad',
        ], 'a'],
    ['CS.F.5.3', 'choice', 'Cuando alguien descalifica a quien argumenta en vez de responder a su argumento, comete la falacia:',
        [
            'a' => 'Ad hominem',
            'b' => 'De falsa causa',
            'c' => 'De generalización apresurada',
            'd' => 'De falso dilema',
        ], 'a'],
];
