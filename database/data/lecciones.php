<?php

/**
 * BANCO INICIAL DE LECCIONES.
 *
 * Cada entrada se ancla a un BLOQUE por el prefijo del código, igual que el
 * banco de práctica: `LL.4.3` es Lengua · Básica Superior · bloque 3 (Lectura).
 * Y aterriza en la misma destreza que el ejercicio de ese bloque, porque las dos
 * siembras usan `DestinosDeBloque`: leer y practicar tienen que caer en el mismo
 * sitio o el alumno acabaría con el texto en una destreza y el ejercicio en otra.
 *
 * ALCANCE DECLARADO: BÁSICA SUPERIOR (8.º-10.º EGB) completa en las cuatro áreas
 * importadas — todos los bloques de M.4, LL.4, CN.4 y CS.4. El resto del
 * currículo NO tiene lección todavía, y el informe del comando lo dice destreza
 * a destreza en vez de redondear. Un subnivel entero y bien hecho enseña más que
 * cuatro párrafos sueltos repartidos por todo el grafo.
 *
 * QUÉ GARANTIZA EL ANCLAJE Y QUÉ NO. Garantiza bloque y nivel. NO garantiza
 * correspondencia uno a uno con el enunciado oficial de la destreza concreta:
 * los PDF del Ministerio no están en este entorno. Por eso las lecciones nacen
 * SIN FIRMAR y no las ve ningún alumno hasta que un docente las publica con
 * `lecciones:firmar`.
 *
 * Reglas de contenido:
 *  - Del nivel del grado y del tema del bloque.
 *  - Ejemplo resuelto PASO A PASO, que es lo que distingue enseñar de enunciar.
 *  - Un bloque de ERROR TÍPICO en cada lección: el error que un profesor ve
 *    todos los años. Sin eso, un texto informa pero no enseña.
 *  - Las fórmulas en el subconjunto de LaTeX de App\Services\Lesson\MathML.
 *    Lo que no entienda revienta al sembrar, no en el navegador del alumno.
 */

return [

    // ================= MATEMÁTICA · Básica Superior =================

    [
        'bloque' => 'M.4.1',
        'slug' => 'ecuaciones-primer-grado',
        'titulo' => 'Ecuaciones de primer grado',
        'resumen' => 'Qué es una ecuación, qué significa resolverla y cómo despejar la incógnita sin perderse.',
        'minutos' => 8,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Una ecuación es una igualdad en la que hay un número que no conocemos. A ese número lo llamamos incógnita y solemos escribirlo como x. Resolver la ecuación es averiguar qué valor tiene que tener x para que la igualdad sea cierta.']],
            ['tipo' => 'formula', 'latex' => '2x + 3 = 11', 'etiqueta' => ['es' => 'Una ecuación de primer grado']],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Se llama «de primer grado» porque la incógnita está elevada a 1: no hay x al cuadrado ni x al cubo. Eso hace que siempre tenga, como mucho, una solución.']],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'La idea para resolverla es sencilla: una igualdad es como una balanza. Si haces lo mismo en los dos lados, la balanza sigue equilibrada. Así vas quitando lo que acompaña a la x hasta dejarla sola.']],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Resolver 2x + 3 = 11'], 'pasos' => [
                ['texto' => ['es' => 'Restamos 3 en los dos lados para quitar el +3 de la izquierda.'], 'latex' => '2x + 3 - 3 = 11 - 3'],
                ['texto' => ['es' => 'Queda la x multiplicada por 2.'], 'latex' => '2x = 8'],
                ['texto' => ['es' => 'Dividimos entre 2 en los dos lados.'], 'latex' => 'x = 4'],
                ['texto' => ['es' => 'Comprobamos: si x vale 4, entonces 2 por 4 más 3 son 11. La igualdad se cumple.']],
            ]],
            ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                ['es' => 'Quita los paréntesis, si los hay.'],
                ['es' => 'Deja todos los términos con x en un lado y los números en el otro.'],
                ['es' => 'Junta los términos semejantes.'],
                ['es' => 'Divide entre el número que multiplica a la x.'],
                ['es' => 'Comprueba tu resultado sustituyéndolo en la ecuación original.'],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Cambiar de signo un número al pasarlo al otro lado, pero olvidarse de que el signo cambia porque estás restando en LOS DOS lados. Si en un lado restas 3, en el otro también. La balanza no se inclina.']],
            ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Comprueba siempre la solución sustituyéndola en la ecuación de partida. Tarda diez segundos y caza casi todos los errores de signo.']],
        ],
    ],

    [
        'bloque' => 'M.4.2',
        'slug' => 'teorema-de-pitagoras',
        'titulo' => 'El teorema de Pitágoras',
        'resumen' => 'Cómo relacionar los tres lados de un triángulo rectángulo, y cuándo se puede usar.',
        'minutos' => 7,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Un triángulo rectángulo es el que tiene un ángulo de 90 grados. El lado más largo, el que está enfrente de ese ángulo, se llama hipotenusa. Los otros dos son los catetos.']],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'El teorema de Pitágoras dice que si elevas al cuadrado los dos catetos y los sumas, obtienes el cuadrado de la hipotenusa.']],
            ['tipo' => 'formula', 'latex' => 'a^{2} + b^{2} = c^{2}', 'etiqueta' => ['es' => 'a y b son los catetos; c es la hipotenusa']],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una escalera apoyada en la pared'], 'pasos' => [
                ['texto' => ['es' => 'Una escalera se apoya en una pared. El pie está a 3 m de la pared y la escalera llega a 4 m de altura. ¿Cuánto mide la escalera?']],
                ['texto' => ['es' => 'La pared y el suelo forman el ángulo recto, así que la escalera es la hipotenusa.'], 'latex' => '3^{2} + 4^{2} = c^{2}'],
                ['texto' => ['es' => 'Calculamos los cuadrados y sumamos.'], 'latex' => '9 + 16 = 25'],
                ['texto' => ['es' => 'La hipotenusa es la raíz de 25.'], 'latex' => 'c = \sqrt{25} = 5'],
                ['texto' => ['es' => 'La escalera mide 5 metros.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Usar el teorema en un triángulo que no es rectángulo. Si no hay un ángulo de 90 grados, la fórmula no vale: no es una propiedad de todos los triángulos.']],
            ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Otro despiste frecuente: sumar los catetos y luego elevar al cuadrado. Primero se elevan al cuadrado y DESPUÉS se suman. No es lo mismo.']],
        ],
    ],

    [
        'bloque' => 'M.4.3',
        'slug' => 'probabilidad-basica',
        'titulo' => 'Probabilidad de un suceso',
        'resumen' => 'Cómo se mide la probabilidad, qué significa el resultado y por qué nunca pasa de 1.',
        'minutos' => 7,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'La probabilidad mide cuán posible es que ocurra algo. Se calcula comparando los casos que nos interesan con todos los casos que pueden salir, siempre que todos sean igual de posibles.']],
            ['tipo' => 'formula', 'latex' => 'P = \frac{casos favorables}{casos posibles}'],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'El resultado siempre está entre 0 y 1. Un 0 significa que es imposible; un 1, que es seguro. Para expresarlo en porcentaje se multiplica por 100.']],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Sacar una bola roja'], 'pasos' => [
                ['texto' => ['es' => 'En una urna hay 3 bolas rojas y 7 azules. ¿Qué probabilidad hay de sacar una roja?']],
                ['texto' => ['es' => 'Los casos favorables son las 3 rojas. Los posibles, las 10 bolas.'], 'latex' => 'P = \frac{3}{10}'],
                ['texto' => ['es' => 'En porcentaje.'], 'latex' => 'P = 0.3 \times 100 = 30 %'],
                ['texto' => ['es' => 'Hay un 30 % de probabilidad de sacar una bola roja.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Poner en el denominador solo los casos que NO nos interesan. El denominador son TODOS los casos posibles, incluidos los favorables: en el ejemplo son 10, no 7.']],
            ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Si te sale una probabilidad mayor que 1, o negativa, algo está mal: revisa el denominador. Es la comprobación más rápida que existe.']],
        ],
    ],

    // ================= LENGUA Y LITERATURA · Básica Superior =================

    [
        'bloque' => 'LL.4.1',
        'slug' => 'variedades-linguisticas',
        'titulo' => 'La lengua cambia según quién la habla',
        'resumen' => 'Qué son las variedades lingüísticas y por qué ninguna es «mejor» que otra.',
        'minutos' => 6,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Una misma lengua no se habla igual en todas partes. Cambia según la región, la edad, el oficio y hasta la situación: no hablas igual con tus amigos que en una entrevista. A esas formas distintas de una misma lengua las llamamos variedades lingüísticas.']],
            ['tipo' => 'lista', 'items' => [
                ['es' => 'Variedad geográfica: cambia según el lugar. En la Costa, la Sierra y la Amazonía se usan palabras y entonaciones distintas.'],
                ['es' => 'Variedad social: cambia según el grupo. Los jóvenes usan expresiones que sus abuelos no usan.'],
                ['es' => 'Variedad de situación: cambia según el contexto. Es el registro formal o informal.'],
            ]],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'El Ecuador es además un país plurinacional: junto al castellano se hablan lenguas ancestrales como el kichwa y el shuar. No son «dialectos» del castellano, son lenguas propias con su gramática.']],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'La misma idea, tres registros'], 'pasos' => [
                ['texto' => ['es' => 'Con un amigo: «Oye, ¿me pasas el cuaderno?»']],
                ['texto' => ['es' => 'Con un profesor: «Profesor, ¿me presta el cuaderno, por favor?»']],
                ['texto' => ['es' => 'En un escrito formal: «Solicito el préstamo del cuaderno de registro.»']],
                ['texto' => ['es' => 'Las tres son correctas. Lo que cambia es la situación, no la calidad de la lengua.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Creer que hay una forma «correcta» de hablar y que las demás están mal. Una variedad no es un error: es una forma distinta. Lo que sí existe es usar el registro equivocado para la situación.']],
        ],
    ],

    [
        'bloque' => 'LL.4.2',
        'slug' => 'argumentar-en-un-debate',
        'titulo' => 'Argumentar: sostener lo que dices',
        'resumen' => 'Qué distingue un argumento de una opinión y cómo se construye uno que se sostenga.',
        'minutos' => 7,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Opinar es decir lo que piensas. Argumentar es decir lo que piensas Y dar razones que se puedan comprobar o discutir. La diferencia no está en el tono ni en el volumen: está en si hay razones detrás.']],
            ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                ['es' => 'La TESIS: lo que defiendes, en una frase.'],
                ['es' => 'Los ARGUMENTOS: las razones que la sostienen.'],
                ['es' => 'Las PRUEBAS: datos, ejemplos o citas que apoyan cada razón.'],
                ['es' => 'La CONCLUSIÓN: recoge lo dicho y cierra.'],
            ]],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'De la opinión al argumento'], 'pasos' => [
                ['texto' => ['es' => 'Opinión: «El recreo debería durar más.»']],
                ['texto' => ['es' => 'Añadimos una razón: «…porque veinte minutos no alcanzan para almorzar y descansar.»']],
                ['texto' => ['es' => 'Añadimos una prueba: «En una encuesta a 60 estudiantes del colegio, 45 dijeron que terminan de almorzar cuando ya suena el timbre.»']],
                ['texto' => ['es' => 'Ahora es un argumento: se puede comprobar, y se puede rebatir con otros datos.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Atacar a quien argumenta en vez de responder a su argumento («eso lo dices porque eres del otro curso»). Eso no rebate nada: es cambiar de tema.']],
            ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Repetir la tesis con otras palabras no es un argumento. «Debería durar más porque es muy corto» está diciendo dos veces lo mismo.']],
        ],
    ],

    [
        'bloque' => 'LL.4.3',
        'slug' => 'leer-entre-lineas',
        'titulo' => 'Leer entre líneas: lo que el texto no dice',
        'resumen' => 'Cómo distinguir la idea principal, deducir lo implícito y reconocer la intención del autor.',
        'minutos' => 8,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Leer no es solo entender cada palabra. Un texto tiene una idea principal —de qué trata en el fondo— y muchas ideas secundarias que la apoyan. Y tiene cosas que no dice pero que se pueden deducir.']],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'La idea principal no siempre está en la primera frase. A veces hay que buscarla: pregúntate «si tuviera que resumir esto en una frase, ¿cuál sería?».']],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Deducir lo que no está escrito'], 'pasos' => [
                ['texto' => ['es' => 'Lee: «La empresa reconoce que hubo demoras, si bien atribuye la responsabilidad a sus proveedores.»']],
                ['texto' => ['es' => '¿Qué admite la empresa? Que hubo demoras. Eso está dicho.']],
                ['texto' => ['es' => '¿Qué hace con «si bien»? Enlaza una concesión: admite algo para acto seguido matizarlo.']],
                ['texto' => ['es' => 'Lo que NO dice pero se deduce: la empresa no quiere asumir la culpa. Eso es leer entre líneas.']],
            ]],
            ['tipo' => 'lista', 'items' => [
                ['es' => '«Aunque», «si bien», «sin embargo» → el autor va a matizar u oponerse a lo anterior.'],
                ['es' => '«Porque», «ya que», «puesto que» → viene una causa.'],
                ['es' => '«Por lo tanto», «así que» → viene una consecuencia.'],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Confundir la idea principal con el detalle más llamativo. Que un dato sea curioso no lo convierte en la idea central del texto.']],
        ],
    ],

    [
        'bloque' => 'LL.4.4',
        'slug' => 'escribir-un-texto-argumentativo',
        'titulo' => 'Escribir un texto argumentativo',
        'resumen' => 'Del borrador al texto final: planificar, escribir y revisar.',
        'minutos' => 8,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Escribir no es sentarse y empezar. Antes de la primera frase hay que decidir dos cosas: para quién escribes y qué quieres conseguir. Un texto para tus compañeros no se escribe igual que uno para el rector.']],
            ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                ['es' => 'PLANIFICAR: decide destinatario, propósito y tesis. Haz un esquema con tus razones.'],
                ['es' => 'ESCRIBIR: un párrafo por idea. Empieza cada uno con la idea y desarróllala después.'],
                ['es' => 'REVISAR: léelo en voz alta. Lo que no puedas leer de un tirón, reescríbelo.'],
                ['es' => 'CORREGIR: ortografía y puntuación, al final. Corregir mientras escribes te corta el hilo.'],
            ]],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Un esquema antes de escribir'], 'pasos' => [
                ['texto' => ['es' => 'Destinatario: el consejo estudiantil. Propósito: convencerlos.']],
                ['texto' => ['es' => 'Tesis: «El colegio debería tener un espacio de estudio abierto en los recreos.»']],
                ['texto' => ['es' => 'Razón 1: muchos estudiantes no tienen dónde estudiar en casa. Prueba: la encuesta del año pasado.']],
                ['texto' => ['es' => 'Razón 2: la biblioteca está cerrada justo a esa hora. Prueba: el horario publicado.']],
                ['texto' => ['es' => 'Cierre: recoger las dos razones y pedir algo concreto.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Meter un argumento nuevo en el párrafo de cierre. El cierre recoge lo ya dicho; si aparece una razón nueva ahí, el lector se queda con la sensación de que el texto se cortó a medias.']],
            ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Escribe el título al final. Cuando el texto está terminado sabes de qué trata de verdad, que casi nunca es lo que creías al empezar.']],
        ],
    ],

    [
        'bloque' => 'LL.4.5',
        'slug' => 'recursos-literarios',
        'titulo' => 'Cómo dice lo que dice: los recursos literarios',
        'resumen' => 'Metáfora, personificación e hipérbole, y para qué las usa un autor.',
        'minutos' => 6,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'En un texto literario importa tanto QUÉ se dice como CÓMO se dice. Los recursos literarios son las formas que usa un autor para que el lenguaje diga más de lo que dicen las palabras por separado.']],
            ['tipo' => 'lista', 'items' => [
                ['es' => 'METÁFORA: identificar una cosa con otra por un parecido, sin usar «como». «Sus ojos son dos luceros».'],
                ['es' => 'COMPARACIÓN: el mismo parecido, pero con «como». «Sus ojos brillan como luceros».'],
                ['es' => 'PERSONIFICACIÓN: dar cualidades humanas a algo que no lo es. «El viento susurraba».'],
                ['es' => 'HIPÉRBOLE: exagerar a propósito. «Te lo he dicho un millón de veces».'],
            ]],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Reconocer el recurso'], 'pasos' => [
                ['texto' => ['es' => '«La ciudad dormía bajo una manta de niebla.»']],
                ['texto' => ['es' => '«La ciudad dormía»: una ciudad no duerme. Es una personificación.']],
                ['texto' => ['es' => '«Una manta de niebla»: la niebla no es una manta, pero la cubre igual. Es una metáfora.']],
                ['texto' => ['es' => 'Los dos recursos juntos crean la sensación de quietud, que es lo que el autor buscaba.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Confundir metáfora con comparación. Si aparece «como», es comparación. La metáfora afirma directamente que una cosa ES la otra.']],
        ],
    ],

    // ================= CIENCIAS NATURALES · Básica Superior =================

    [
        'bloque' => 'CN.4.1',
        'slug' => 'relaciones-entre-especies',
        'titulo' => 'Cómo se relacionan los seres vivos',
        'resumen' => 'Mutualismo, comensalismo, parasitismo, depredación y competencia.',
        'minutos' => 7,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'En un ecosistema ninguna especie vive aislada. Las relaciones entre especies distintas se clasifican según a quién beneficia y a quién perjudica la convivencia.']],
            ['tipo' => 'lista', 'items' => [
                ['es' => 'MUTUALISMO: las dos salen beneficiadas. La abeja se lleva néctar y la flor, polinización.'],
                ['es' => 'COMENSALISMO: una gana y la otra ni gana ni pierde. Las orquídeas sobre los árboles.'],
                ['es' => 'PARASITISMO: una gana a costa de la otra, sin matarla enseguida. Las garrapatas.'],
                ['es' => 'DEPREDACIÓN: una caza y se come a la otra.'],
                ['es' => 'COMPETENCIA: las dos pierden, porque quieren lo mismo y no alcanza para las dos.'],
            ]],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Clasificar una relación'], 'pasos' => [
                ['texto' => ['es' => 'El pez payaso vive entre los tentáculos de la anémona.']],
                ['texto' => ['es' => '¿Qué gana el pez? Protección: los depredadores no se acercan a la anémona.']],
                ['texto' => ['es' => '¿Qué gana la anémona? El pez la limpia y ahuyenta a quienes la comerían.']],
                ['texto' => ['es' => 'Ganan las dos: es mutualismo.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Llamar parasitismo a cualquier relación que parezca injusta. Para que sea parasitismo, el parásito tiene que VIVIR a costa del otro durante un tiempo. Un depredador no es un parásito: mata y se va.']],
        ],
    ],

    [
        'bloque' => 'CN.4.2',
        'slug' => 'la-sangre-y-su-transporte',
        'titulo' => 'La sangre: qué transporta y cómo',
        'resumen' => 'Glóbulos rojos, glóbulos blancos y plaquetas, y qué hace cada uno.',
        'minutos' => 6,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'La sangre es un tejido líquido. Su parte líquida se llama plasma, y en ella flotan tres tipos de células, cada una con un trabajo distinto.']],
            ['tipo' => 'lista', 'items' => [
                ['es' => 'GLÓBULOS ROJOS: transportan el oxígeno desde los pulmones hasta cada célula. Son los más numerosos.'],
                ['es' => 'GLÓBULOS BLANCOS: defienden al cuerpo de virus y bacterias.'],
                ['es' => 'PLAQUETAS: cierran las heridas formando el coágulo.'],
            ]],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'El oxígeno viaja pegado a la hemoglobina, una proteína que está dentro del glóbulo rojo y que además le da el color.']],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Por qué cuesta respirar en la altura'], 'pasos' => [
                ['texto' => ['es' => 'En Quito, a 2.850 m, el aire tiene menos oxígeno por cada litro que a nivel del mar.']],
                ['texto' => ['es' => 'Los glóbulos rojos recogen menos oxígeno en cada vuelta por los pulmones.']],
                ['texto' => ['es' => 'El cuerpo responde fabricando MÁS glóbulos rojos, para compensar con cantidad lo que falta de concentración.']],
                ['texto' => ['es' => 'Por eso quien vive en la Sierra tiene, de media, más glóbulos rojos que quien vive en la Costa.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir que la sangre «lleva aire». Lleva oxígeno disuelto y unido a la hemoglobina, no burbujas de aire. Una burbuja de aire en la sangre es, de hecho, peligrosa.']],
        ],
    ],

    [
        'bloque' => 'CN.4.3',
        'slug' => 'densidad',
        'titulo' => 'La densidad: por qué flota lo que flota',
        'resumen' => 'Qué es la densidad, cómo se calcula y por qué un barco de acero no se hunde.',
        'minutos' => 7,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'La densidad dice cuánta masa hay en cada trocito de volumen. Es lo que hace que un kilo de plomo ocupe mucho menos espacio que un kilo de plumas: no pesan distinto, ocupan distinto.']],
            ['tipo' => 'formula', 'latex' => 'd = \frac{m}{V}', 'etiqueta' => ['es' => 'Densidad: masa dividida entre volumen']],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'El agua tiene una densidad de 1 gramo por centímetro cúbico. Todo lo que sea menos denso que el agua flota; lo que sea más denso, se hunde.']],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Calcular una densidad'], 'pasos' => [
                ['texto' => ['es' => 'Un objeto tiene 120 g de masa y ocupa 40 cm³.']],
                ['texto' => ['es' => 'Aplicamos la fórmula.'], 'latex' => 'd = \frac{120}{40}'],
                ['texto' => ['es' => 'El resultado es 3 g/cm³.'], 'latex' => 'd = 3'],
                ['texto' => ['es' => 'Como 3 es mayor que 1, este objeto se hunde en el agua.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Un barco de acero flota aunque el acero sea ocho veces más denso que el agua. La clave es que el barco no es macizo: por dentro hay aire, y lo que cuenta es la densidad del CONJUNTO.']],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir que «lo pesado se hunde». Un tronco enorme pesa muchísimo y flota; una moneda pesa poco y se hunde. Lo que decide es la densidad, no el peso.']],
        ],
    ],

    [
        'bloque' => 'CN.4.4',
        'slug' => 'las-estaciones',
        'titulo' => 'Por qué hay estaciones (y por qué en el Ecuador casi no)',
        'resumen' => 'La inclinación del eje terrestre y sus consecuencias.',
        'minutos' => 6,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'La Tierra da una vuelta alrededor del Sol cada año. Pero no gira «derecha»: su eje está inclinado unos 23 grados y medio, y esa inclinación se mantiene siempre apuntando en la misma dirección.']],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Por eso, durante medio año el hemisferio norte recibe los rayos del Sol más de frente y el sur más de lado. Seis meses después, al revés. Eso son las estaciones.']],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Por qué en la línea equinoccial apenas cambia'], 'pasos' => [
                ['texto' => ['es' => 'En el Ecuador, el Sol cae casi perpendicular todo el año.']],
                ['texto' => ['es' => 'La inclinación del eje inclina más al norte o más al sur, pero la franja del centro queda casi igual siempre.']],
                ['texto' => ['es' => 'Por eso aquí no hay verano e invierno como en Europa: hay estación seca y estación lluviosa, que dependen de los vientos y las lluvias, no de la inclinación.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Creer que hace más calor en verano porque la Tierra está más cerca del Sol. No es eso: la distancia cambia muy poco, y de hecho la Tierra está MÁS cerca del Sol en enero. Lo que cambia es el ángulo con que llegan los rayos.']],
        ],
    ],

    [
        'bloque' => 'CN.4.5',
        'slug' => 'como-se-investiga',
        'titulo' => 'Cómo se investiga en ciencias',
        'resumen' => 'Hipótesis, variables y por qué se repite una medición.',
        'minutos' => 7,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Una investigación empieza con una pregunta y una HIPÓTESIS: una explicación provisional que se puede poner a prueba. Si una idea no se puede comprobar de ninguna forma, no es una hipótesis científica.']],
            ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                ['es' => 'Formula la pregunta.'],
                ['es' => 'Propón una hipótesis que se pueda comprobar.'],
                ['es' => 'Diseña el experimento: cambia UNA sola cosa y deja el resto igual.'],
                ['es' => 'Mide, y repite la medición varias veces.'],
                ['es' => 'Compara los resultados con la hipótesis y saca conclusiones.'],
            ]],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => '¿La planta crece más con luz?'], 'pasos' => [
                ['texto' => ['es' => 'Hipótesis: una planta con luz crece más que una sin luz.']],
                ['texto' => ['es' => 'Dos plantas iguales, misma tierra, misma agua, mismo tiempo. Lo único distinto: una recibe luz y la otra no.']],
                ['texto' => ['es' => 'Lo que cambiamos a propósito (la luz) es la variable independiente. Lo que medimos (la altura) es la dependiente.']],
                ['texto' => ['es' => 'Medimos la altura cada semana, varias veces, y anotamos.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Cambiar dos cosas a la vez. Si a una planta le das más luz Y más agua, y crece más, no sabes cuál de las dos fue. Una variable cada vez.']],
            ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Se repite la medición para que un error puntual no arrastre la conclusión. Una medida suelta puede estar mal; tres que coinciden, difícilmente.']],
        ],
    ],

    // ================= ESTUDIOS SOCIALES · Básica Superior =================

    [
        'bloque' => 'CS.4.1',
        'slug' => 'ecuador-siglo-xx',
        'titulo' => 'El Ecuador del siglo XX: tres momentos',
        'resumen' => 'La Revolución Juliana, el auge bananero y el retorno a la democracia.',
        'minutos' => 8,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'El siglo XX ecuatoriano se entiende mejor por momentos de cambio que por una lista de presidentes. Tres marcan el rumbo del país.']],
            ['tipo' => 'lista', 'items' => [
                ['es' => 'REVOLUCIÓN JULIANA (1925): un grupo de militares jóvenes derroca al gobierno para limitar el poder de la banca guayaquileña sobre el Estado. De ahí salen el Banco Central y las primeras leyes sociales.'],
                ['es' => 'AUGE BANANERO (años 1950): el Ecuador se convierte en el mayor exportador de banano del mundo. Llegan carreteras, migración a la Costa y una clase media urbana.'],
                ['es' => 'RETORNO A LA DEMOCRACIA (1979): tras casi una década de dictaduras militares, se aprueba una nueva Constitución y se vuelve a votar.'],
            ]],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Leer una causa y una consecuencia'], 'pasos' => [
                ['texto' => ['es' => 'Causa: la banca privada prestaba al Estado y decidía, de hecho, su política económica.']],
                ['texto' => ['es' => 'Hecho: en 1925 los militares julianos toman el poder.']],
                ['texto' => ['es' => 'Consecuencia: se crea el Banco Central en 1927, y la emisión de dinero pasa a manos del Estado.']],
                ['texto' => ['es' => 'Fíjate en el orden: la causa explica el hecho, y el hecho explica la consecuencia. Eso es analizar un proceso histórico y no solo recordar una fecha.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Confundir la Revolución Juliana (1925) con la Revolución Liberal de Eloy Alfaro (1895). Son treinta años de diferencia y proyectos distintos.']],
        ],
    ],

    [
        'bloque' => 'CS.4.2',
        'slug' => 'diversidad-de-climas',
        'titulo' => 'Por qué un país pequeño tiene tantos climas',
        'resumen' => 'La cordillera, las corrientes marinas y la latitud cero.',
        'minutos' => 7,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'El Ecuador continental cabe muchas veces dentro de países como Brasil, y sin embargo tiene más variedad de climas que casi cualquiera de ellos. La razón no es una sola: son tres factores que actúan a la vez.']],
            ['tipo' => 'lista', 'items' => [
                ['es' => 'LA ALTITUD: la cordillera de los Andes atraviesa el país de norte a sur. Cada 1.000 metros que subes, la temperatura baja unos 6 grados.'],
                ['es' => 'LAS CORRIENTES MARINAS: la corriente fría de Humboldt enfría la costa sur; la cálida de El Niño la calienta en ciertos meses.'],
                ['es' => 'LA LATITUD: al estar sobre la línea equinoccial, la duración del día apenas cambia en todo el año.'],
            ]],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Tres ciudades, tres climas'], 'pasos' => [
                ['texto' => ['es' => 'Guayaquil está casi a nivel del mar: cálida y húmeda todo el año.']],
                ['texto' => ['es' => 'Quito está a 2.850 m: templada, con una diferencia grande entre el día y la noche.']],
                ['texto' => ['es' => 'El Cotopaxi supera los 5.000 m: nieve permanente, a la misma latitud que las otras dos.']],
                ['texto' => ['es' => 'Las tres están casi en la misma línea del mapa. Lo que las diferencia es la altura.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pensar que en la línea equinoccial siempre hace calor. Sobre el Ecuador hay nieves perpetuas: la latitud manda en la luz, pero la altitud manda en la temperatura.']],
        ],
    ],

    [
        'bloque' => 'CS.4.3',
        'slug' => 'derechos-y-democracia',
        'titulo' => 'Derechos, deberes y democracia',
        'resumen' => 'Qué es un derecho, en qué se diferencia de un privilegio y para qué sirve dividir el poder.',
        'minutos' => 7,
        'bloques' => [
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Un derecho es algo que toda persona puede exigir por el simple hecho de ser persona. No se compra, no se gana portándose bien y no se pierde por caer mal. Un privilegio, en cambio, lo tienen solo algunos.']],
            ['tipo' => 'parrafo', 'texto' => ['es' => 'Todo derecho tiene un deber al otro lado: si tienes derecho a que te escuchen, tienes el deber de escuchar. Los derechos de cada uno terminan donde empiezan los del resto.']],
            ['tipo' => 'lista', 'items' => [
                ['es' => 'FUNCIÓN EJECUTIVA: gobierna y hace cumplir las leyes.'],
                ['es' => 'FUNCIÓN LEGISLATIVA: hace las leyes y fiscaliza al Ejecutivo.'],
                ['es' => 'FUNCIÓN JUDICIAL: juzga y aplica la ley a los casos concretos.'],
            ]],
            ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Para qué sirve que estén separadas'], 'pasos' => [
                ['texto' => ['es' => 'Imagina que quien hace las leyes fuera el mismo que las aplica y juzga si se cumplen.']],
                ['texto' => ['es' => 'Podría escribir una ley a su medida, aplicarla como quisiera y declararse inocente.']],
                ['texto' => ['es' => 'Separarlas hace que cada poder limite y controle a los otros. A eso se le llama contrapeso.']],
            ]],
            ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Creer que democracia es solo votar. Votar es una parte. Sin derechos garantizados, sin poderes que se controlen entre sí y sin prensa que pueda criticar, unas elecciones solas no hacen una democracia.']],
        ],
    ],
];
