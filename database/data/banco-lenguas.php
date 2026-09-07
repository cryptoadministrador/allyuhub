<?php

/**
 * BANCO DE LENGUAS — contenido de los cursos de idiomas, anclado al MCER.
 *
 * SEPARADO de banco-practica.php a propósito: otro marco (CEFR, no
 * EC-MINEDEC), otro anclaje (descriptor exacto, no prefijo de bloque), otros
 * tipos de ítem, y enunciados que no siempre van en español.
 *
 * FORMATO: entradas POR CLAVE. Cada tipo declara cómo se lee la suya en
 * `App\Services\Practice\Tipos\*::desdeBanco()`.
 *
 * ALCANCE ACTUAL: los CURSOS ENTEROS de ITALIANO, FRANCÉS, ALEMÁN y CHINO A1, nueve unidades cada uno.
 * Las unidades comparten esqueleto (los mismos «Puedo…» del MCER); lo que cambia
 * es el relleno lingüístico y el punto de oído de cada una.
 *
 * ITALIANO:
 *   U1 «Ciao! Come ti chiami?» — saludar, decir tu nombre y de dónde eres.
 *   U2 «La mia famiglia» — la familia, la edad y las consonantes dobles.
 *   U3 «La mia giornata» — la rutina, la hora, los verbos en -are y el acento.
 *   U4 «Mi piace!» — gustos, adjetivos, y los sonidos gl y gn.
 *   U5 «In città» — orientarse, c'è / ci sono, a / in, y la s sonora.
 *   U6 «Al bar» — pedir, el partitivo, leer un menú, y la z.
 *   U7 «Quanto costa?» — ropa, precios, el tiempo, la concordancia y la entonación.
 *   U8 «Ieri» — el passato prossimo con avere, y el enlace entre palabras.
 *   U9 «Non ho capito» — repaso, reparar la conversación, y el proyecto final.
 *
 * FRANCÉS:
 *   U1 «Bonjour !» — saludar, tu / vous, être, el pronombre obligatorio, las finales mudas.
 *   U2 «Ma famille» — la familia, avoir, el artículo, las nasales.
 *   U3 «Ma journée» — los verbos en -er, la hora, la liaison.
 *   U4 «J'aime !» — aimer + artículo, ne… pas, u frente a ou.
 *   U5 «En ville» — il y a, à / en, la e muda.
 *   U6 «Au café» — je voudrais, los partitivos, la r.
 *   U7 «Ça coûte combien ?» — el adjetivo (lugar y concordancia), el tiempo, é / è.
 *   U8 «Hier» — el passé composé con avoir, el enchaînement.
 *   U9 «Je n'ai pas compris» — repaso, reparar la conversación, el proyecto final.
 *
 * ALEMÁN (25 palabras por unidad, no 30: más estructura, menos léxico):
 *   U1 «Hallo!» — saludar, du / Sie, sein, EL VERBO EN SEGUNDA POSICIÓN, ei / ie.
 *   U2 «Meine Familie» — la familia, haben, los tres géneros, la edad con sein, las dos ch.
 *   U3 «Mein Tag» — el presente regular, la hora (halb neun), vocales largas y cortas.
 *   U4 «Ich mag» — mögen / gern, los Umlaute.
 *   U5 «In der Stadt» — es gibt, EL ACUSATIVO (solo cambia el masculino), z / s / v / w.
 *   U6 «Im Café» — möchten + acusativo, la r final.
 *   U7 «Was kostet das?» — el adjetivo predicativo (no cambia), el tiempo, st- / sp-.
 *   U8 «Gestern» — el Perfekt con haben, el participio al final, el acento de compuestas.
 *   U9 «Ich verstehe nicht» — repaso, reparar la conversación, el proyecto final.
 *
 * Cada una de las cuatro lenguas cubre 10 de los 13 descriptores A1 con dos o más ítems cada uno. Los tres que
 * faltan —A1.CO.1, A1.CO.2 y A1.CO.3, comprensión oral— NO pueden tener ítems sin audio:
 * sus ejercicios de `escucha` y `dictado` están escritos en U1-audio-pendiente.md
 * y entran en cuanto el equipo grabe los clips. Declarado, no disimulado.
 *
 * CHINO (17 palabras por unidad, HSK 1; pinyin y tonos desde el primer día, los
 * caracteres como pista aparte —cinco por unidad—; los `pares` a TRES columnas):
 *   U1 «你好 Nǐ hǎo» — saludar, 是 y 叫, LOS CUATRO TONOS, leer el pinyin (x q zh).
 *   U2 «我的家» — la familia, 有 / 没有, el medidor 个 y 口, la edad sin verbo, el sandhi del tercer tono.
 *   U3 «我的一天» — la rutina, QUIÉN + CUÁNDO + 在 DÓNDE + VERBO, 在 frente a 是, la hora, 不 / 一.
 *   U4 «我喜欢» — 喜欢, el adjetivo como verbo con 很, 也, zh ch sh frente a z c s.
 *   U5 «在哪儿» — 在 + lugar, 哪儿, referencia + posición (学校旁边), 有 = hay, -n / -ng.
 *   U6 «在饭馆» — 要, medidores 碗 / 杯, 多少钱 y 块, la ü tras j q x; letreros 出口 入口 男 女.
 *   U7 «这个多少钱» — 这 / 那 + medidor, 太…了, el tiempo, tono neutro y erhua.
 *   U8 «昨天» — 了 (acción terminada) y 没, la entonación de la frase.
 *   U9 «我听不懂» — repaso, reparar la conversación, el proyecto final.
 * El chino es la lengua que MÁS necesita audio (docs/mision-lenguas/zh-audio-pendiente.md).
 *
 * DOS REGLAS DE CONTENIDO que no son cosméticas y que conviene no perder:
 *
 *  1. DOS ÍTEMS POR DESCRIPTOR COMO MÍNIMO. `MasteryTracker::ITEMS_TO_MASTER`
 *     exige aciertos en dos ítems DISTINTOS para sellar dominio y para que la
 *     nota viaje al aula. Un descriptor con un solo ítem se practica, se
 *     corrige… y no cuenta nunca. En MINEDEC eso le pasa a 372 destrezas de
 *     380: aquí no se repite.
 *
 *  2. NI LA PUNTUACIÓN NI LAS MAYÚSCULAS VIAJAN EN LAS FICHAS DE `orden`. Una
 *     ficha que lleve «?» pegado se coloca al final sin saber una palabra de
 *     italiano, y una que empiece por mayúscula se coloca la primera. Es la
 *     misma clase de fuga que la del #22 —la respuesta viajando en algo que no
 *     debería llevarla—, solo que en el dato en vez de en el código. La
 *     consigna avisa de que el signo y la mayúscula ya están puestos.
 *
 * AUDIO: los ítems de `escucha` y `dictado` de esta unidad, y los bloques de
 * audio de sus lecciones, están en U1-audio-pendiente.md — entran aquí cuando
 * el equipo de contenido grabe los clips. Hasta entonces A1.CO.2 tiene lección
 * pero no ítems, y eso está declarado, no disimulado.
 */

return [

    'lecciones' => [

        // ============ A1.CO.2 · saludos y fórmulas de cortesía ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.CO.2', 'slug' => 'saluti',
            'titulo' => ['es' => 'Saludar en italiano (y saber cuál toca)'],
            'resumen' => ['es' => 'No hay un solo «hola»: hay uno para tus amigos y otro para tu profesora.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En italiano no hay un solo «hola». Hay uno que sirve para tus amigos y otro que sirve para tu profesora, y usar el que no toca se nota igual que se nota en español tutear a alguien a quien no se tutea.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ciao — hola Y adiós, pero solo con gente de confianza: amigos, compañeros, familia.'],
                    ['es' => 'buongiorno — buenos días, desde la mañana hasta media tarde.'],
                    ['es' => 'buonasera — buenas tardes o buenas noches, a partir de ahí.'],
                    ['es' => 'salve — la salida cuando no sabes cuál de las dos toca: sirve con cualquiera y no queda mal con nadie.'],
                    ['es' => 'arrivederci — la despedida formal. A tu profesora le dices arrivederci, no ciao.'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y las cuatro palabras que vas a usar todos los días sin pensar: per favore (por favor), grazie (gracias), prego (de nada) y scusa (perdona).']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => '«Prego» tiene una segunda vida: también significa «adelante, pasa» o «dime». Si alguien te abre una puerta y dice prego, no te está dando las gracias: te está invitando a entrar.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decirle «ciao» a un profesor, a un camarero o a alguien mayor que acabas de conocer. En español eso sería tutear a un desconocido: se entiende, pero suena mal. Si dudas, di «salve».']],
            ],
        ],

        // ============ A1.IO.3 · presentarse y presentar a otro ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.IO.3', 'slug' => 'presentarsi',
            'titulo' => ['es' => 'Ciao! Come ti chiami?'],
            'resumen' => ['es' => 'Decir cómo te llamas, de dónde eres y presentar a otra persona. Y la primera trampa del italiano: la c y la g.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Lo primero que vas a decir en tu vida en italiano es tu nombre. Se dice con una fórmula que por ahora aprendes entera, sin desmontarla.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Presentarse y preguntar el nombre'], 'pasos' => [
                    ['texto' => ['es' => '— Come ti chiami?  (¿Cómo te llamas?)']],
                    ['texto' => ['es' => '— Mi chiamo Sofía.  (Me llamo Sofía.)']],
                    ['texto' => ['es' => 'Y si hablas de otra persona: «Si chiama Marco» (se llama Marco).']],
                    ['texto' => ['es' => 'Fíjate en que solo cambia el final: chiamo, chiami, chiama. Por ahora apréndete las tres frases enteras; en la unidad 3 verás por qué cambian.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Leer «chiamo» como si fuera español y decir «CHAmo». En italiano la h no suena, y su único trabajo es endurecer la c: «chiamo» se dice KIAmo, con k. Y el mismo error al revés: leer «ciao» como KIAO. Se dice CHAO.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Ese es el punto de esta unidad, y no es una curiosidad: sin él no puedes decir ni «ciao» ni «mi chiamo», que es literalmente lo primero que vas a decir. La c y la g cambian de sonido según la vocal que viene detrás.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ca, co, cu → suena k: come, scusa, amico'],
                    ['es' => 'ce, ci → suena como la ch española: ciao, piacere, arrivederci'],
                    ['es' => 'che, chi → vuelve a sonar k: mi chiamo, chi'],
                    ['es' => 'ga, go, gu → g de gato: grazie, prego'],
                    ['es' => 'ge, gi → suena como la y argentina: buongiorno'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'La regla entera en una frase: la i y la e ablandan la c y la g; la h las vuelve a endurecer. La h italiana no suena nunca sola — ese es todo su trabajo.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Lo tercero: de dónde eres. Para eso hace falta «essere», que es el «ser» italiano. En singular tiene tres formas y ninguna se parece a la otra, así que hay que aprendérselas.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'io sono — yo soy'],
                    ['es' => 'tu sei — tú eres'],
                    ['es' => 'lui / lei è — él / ella es'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Buena noticia: igual que en español, el pronombre se calla. No dices «io sono ecuadoriana», dices «sono ecuadoriana». Io, tu, lui y lei solo se ponen cuando quieres contrastar: «IO sono italiano, TU sei ecuadoriano».']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir de dónde eres'], 'pasos' => [
                    ['texto' => ['es' => '— Di dove sei?  (¿De dónde eres?)']],
                    ['texto' => ['es' => '— Sono ecuadoriana, di Quito.']],
                    ['texto' => ['es' => 'Con la ciudad se usa «di»: sono di Quito, sono di Torino. Con el país es más natural la nacionalidad: sono ecuadoriano, sono italiana.']],
                    ['texto' => ['es' => 'Lo que cambia según seas chico o chica es la nacionalidad —italiano / italiana, ecuadoriano / ecuadoriana—, no el verbo.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«è» lleva acento y «e» no, y son dos palabras distintas: «è» es «es», «e» es «y». «Marco e Sofía» son dos personas; «Marco è Sofía» diría que Marco es Sofía. El acento aquí no es adorno: es la palabra.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Con esto ya puedes tener tu primera conversación entera. Esta es Sofía, de Quito, en su primer día en un colegio de Torino.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Il primo giorno'], 'pasos' => [
                    ['texto' => ['es' => 'Marco — Buongiorno. Io sono Marco. E tu, come ti chiami?']],
                    ['texto' => ['es' => 'Sofía — Ciao, Marco. Mi chiamo Sofía.']],
                    ['texto' => ['es' => 'Marco — Piacere, Sofía. Di dove sei?']],
                    ['texto' => ['es' => 'Sofía — Sono ecuadoriana, di Quito. E tu?']],
                    ['texto' => ['es' => 'Marco — Io sono italiano, di Torino. Sei studentessa qui?']],
                    ['texto' => ['es' => 'Sofía — Sì. Scusa… e lei chi è?']],
                    ['texto' => ['es' => 'Marco — È la professoressa Rossi.']],
                    ['texto' => ['es' => 'Sofía — Grazie, Marco. Arrivederci!']],
                    ['texto' => ['es' => 'Marco — Ciao!']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Cuenta las palabras de ese diálogo: no hay ni una que no esté en esta unidad. Si te has aprendido las treinta, ya puedes sostener esa conversación entera.']],
            ],
        ],

        // ============ U2 · A1.PO.1 · la familia y avere ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.PO.1', 'slug' => 'la-famiglia',
            'titulo' => ['es' => 'La mia famiglia'],
            'resumen' => ['es' => 'Hablar de tu familia y decir cuántos años tiene cada uno. Y las consonantes dobles, que no son un detalle.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Ya sabes presentarte. Ahora vas a hablar de los tuyos, que es de lo primero que habla cualquiera cuando conoce a alguien. Para eso hacen falta dos cosas: los nombres de la familia y un verbo nuevo.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'padre / madre — y en el día a día: papà / mamma'],
                    ['es' => 'fratello / sorella — hermano / hermana'],
                    ['es' => 'nonno / nonna — abuelo / abuela'],
                    ['es' => 'zio / zia — tío / tía'],
                    ['es' => 'cugino / cugina — primo / prima'],
                    ['es' => 'figlio / figlia — hijo / hija'],
                    ['es' => 'genitori — los padres (los dos juntos)'],
                    ['es' => 'marito / moglie — marido / mujer'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El verbo nuevo es «avere», que es «tener». En singular también son tres formas, y esta vez sí se parecen entre sí.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'io ho — yo tengo'],
                    ['es' => 'tu hai — tú tienes'],
                    ['es' => 'lui / lei ha — él / ella tiene'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Esa h no suena nunca. «Ho» se dice O, «hai» se dice AI, «ha» se dice A. Está ahí solo para distinguirlas por escrito de otras palabras que suenan igual. Es la misma h muda de «chiamo», haciendo otro trabajo.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir la edad'], 'pasos' => [
                    ['texto' => ['es' => '— Quanti anni hai?  (¿Cuántos años tienes?)']],
                    ['texto' => ['es' => '— Ho quindici anni.  (Tengo quince años.)']],
                    ['texto' => ['es' => 'Igual que en español, la edad se TIENE, no se es. «Sono quindici anni» no existe.']],
                    ['texto' => ['es' => 'Y de otra persona: «Mia sorella ha dodici anni». Fíjate en que «anni» no se cae nunca — en italiano no se dice «ho quindici» a secas.']],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Los números del 1 al 20, que vas a necesitar para todas las edades de tu clase: uno, due, tre, quattro, cinque, sei, sette, otto, nove, dieci, undici, dodici, tredici, quattordici, quindici, sedici, diciassette, diciotto, diciannove, venti.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Del 11 al 16 el italiano pone primero la unidad (undici, dodici, tredici…) y del 17 al 19 le da la vuelta y pone primero el diez: diciassette, diciotto, diciannove. Es exactamente lo mismo que hace el español con dieciséis y diecisiete.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y ahora el punto de esta unidad, que es de oído. En italiano una consonante doble SE OYE, y cambiar una por otra cambia la palabra.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'sono (yo soy) — sonno (sueño)'],
                    ['es' => 'nono (noveno) — nonno (abuelo)'],
                    ['es' => 'cane (perro) — canne (cañas)'],
                    ['es' => 'papa (el Papa) — papà (papá)'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Un hispanohablante no distingue esas parejas porque en español la doble no existe: «carro» y «caro» se diferencian por la erre, no por su duración. En italiano la doble se sostiene el doble de tiempo. Decir «mia nona» en vez de «mia nonna» es decir «mi novena» en vez de «mi abuela».']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Con «mio» y «mia» hay una regla curiosa que conviene aprender ya: delante de UN familiar en singular NO se pone artículo. Se dice «mia sorella», no «la mia sorella». Con todo lo demás sí: «il mio amico», «la mia città». Es de las pocas excepciones que el italiano se toma en serio.']],
            ],
        ],

        // ============ U2 · A1.CE.2 · leer un mensaje breve ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.CE.2', 'slug' => 'un-messaggio',
            'titulo' => ['es' => 'Leer un mensaje corto'],
            'resumen' => ['es' => 'Una postal de cuatro líneas trae más información de la que parece. Cómo sacarla sin entenderlo todo.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Leer en una lengua que acabas de empezar no es entender cada palabra: es sacar lo que necesitas. Un mensaje corto casi siempre trae quién escribe, dónde está, con quién y qué quiere. Búscalo en ese orden y no te bloquees en lo que no reconozcas.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una postal desde Roma'], 'pasos' => [
                    ['texto' => ['es' => 'Ciao Marco!']],
                    ['texto' => ['es' => 'Sono a Roma con mia sorella e mia zia.']],
                    ['texto' => ['es' => 'Mia nonna è di Roma.']],
                    ['texto' => ['es' => 'Ciao, Anna.']],
                    ['texto' => ['es' => 'Quién escribe: Anna. Dónde está: en Roma. Con quién: su hermana y su tía. Y un dato de más: su abuela es de Roma, que probablemente es la razón del viaje.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Cuando leas, subraya primero los nombres propios y las palabras que se parecen al español. Con eso solo ya tienes el esqueleto del mensaje, y lo que quede en medio suele deducirse.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Traducir palabra por palabra y parar en la primera que no conoces. En A1 vas a encontrarte palabras desconocidas en todos los textos: eso es normal y no significa que no puedas leerlo. Salta y sigue.']],
            ],
        ],

        // ============ U3 · A1.PO.2 · mi día a día y los verbos en -are ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.PO.2', 'slug' => 'la-mia-giornata',
            'titulo' => ['es' => 'La mia giornata'],
            'resumen' => ['es' => 'Contar lo que haces cada día. Con eso aparece la primera conjugación entera, y el acento que el italiano solo escribe cuando cae al final.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Hasta ahora has usado verbos sueltos y aprendidos enteros. Hoy aprendes el primer grupo de verbos de verdad: los que terminan en -are. Son la mayoría de los verbos del italiano, y una vez que sabes uno, sabes todos.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'parlare — hablar · studiare — estudiar · mangiare — comer'],
                    ['es' => 'lavorare — trabajar · abitare — vivir (en un sitio) · giocare — jugar'],
                    ['es' => 'guardare — mirar (¡no «guardar»!) · ascoltare — escuchar'],
                    ['es' => 'arrivare — llegar · tornare — volver'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Conjugar parlare en singular'], 'pasos' => [
                    ['texto' => ['es' => 'Se quita -are y queda la raíz: parl-.']],
                    ['texto' => ['es' => 'io parl-o → parlo (yo hablo)']],
                    ['texto' => ['es' => 'tu parl-i → parli (tú hablas)']],
                    ['texto' => ['es' => 'lui / lei parl-a → parla (él / ella habla)']],
                    ['texto' => ['es' => 'Y ya está. Studiare: studio, studi, studia. Mangiare: mangio, mangi, mangia. La misma máquina para los diez.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Poner -as en la segunda persona como en español: «tu parlas». En italiano la segunda persona termina en -i: tu parli, tu studi, tu mangi. Es el error más repetido del año y se arregla en una semana si lo cazas ahora.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Guardare» es mirar, no guardar. Y «abitare» es vivir en un lugar (abito a Quito), no habitar en el sentido de existir. Dos falsos amigos más para la colección.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para contar un día hacen falta las partes del día y los días de la semana. Las partes: la mattina (la mañana), il pomeriggio (la tarde), la sera (la noche temprana), la notte (la noche). Y oggi (hoy), domani (mañana), sempre (siempre), mai (nunca).']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'lunedì · martedì · mercoledì · giovedì · venerdì — de lunes a viernes'],
                    ['es' => 'sabato · domenica — sábado y domingo'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Fíjate en esos cinco acentos. Ahí está el punto de esta unidad. En italiano el acento gráfico solo se escribe cuando la sílaba fuerte es LA ÚLTIMA. Lunedì se dice lune-DÌ, y por eso lleva tilde. Sabato se dice SA-bato y domenica do-ME-nica, y por eso no llevan nada.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'La regla al revés te sirve para leer: si una palabra italiana no lleva tilde, la sílaba fuerte NO es la última. Casi siempre es la penúltima (parlo, studio, mattina), y a veces la antepenúltima (SAbato, DOmenica, TAvola). Cuando dudes, la penúltima acierta ocho de cada diez.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Leer «domenica» como «domeNIca», con la fuerza donde la pondría el español. Es do-ME-nica. Y «sabato» no es «saBAto»: es SA-bato. Estas dos se fosilizan porque parecen fáciles.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Un día de Sofía'], 'pasos' => [
                    ['texto' => ['es' => 'La mattina studio a scuola.  (Por la mañana estudio en el colegio.)']],
                    ['texto' => ['es' => 'Il pomeriggio gioco con mio fratello.  (Por la tarde juego con mi hermano.)']],
                    ['texto' => ['es' => 'La sera guardo la TV e ascolto musica.  (Por la noche veo la tele y escucho música.)']],
                    ['texto' => ['es' => 'Il sabato non studio mai.  (Los sábados no estudio nunca.)']],
                    ['texto' => ['es' => 'Fíjate en «non… mai»: en italiano el «nunca» va con «non» delante del verbo, igual que en español «no estudio nunca».']],
                ]],
            ],
        ],

        // ============ U3 · A1.IO.2 · la hora ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.IO.2', 'slug' => 'che-ore-sono',
            'titulo' => ['es' => 'Che ore sono?'],
            'resumen' => ['es' => 'Preguntar y decir la hora, y quedar a una hora. Con lo que ya sabes de números, esto es corto.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'La hora en italiano se dice en plural, porque «las horas» son varias: Che ore sono? — ¿Qué hora es? Y la respuesta empieza casi siempre con «Sono le…».']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Sono le otto. — Son las ocho.'],
                    ['es' => 'Sono le dieci e mezza. — Son las diez y media.'],
                    ['es' => 'È l\'una. — Es la una. (La única en singular, igual que en español.)'],
                    ['es' => 'È mezzogiorno. — Es mediodía.'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Quedar a una hora'], 'pasos' => [
                    ['texto' => ['es' => '— A che ora mangi?  (¿A qué hora comes?)']],
                    ['texto' => ['es' => '— Mangio all\'una.  (Como a la una.)']],
                    ['texto' => ['es' => '— A che ora torni a casa?  (¿A qué hora vuelves a casa?)']],
                    ['texto' => ['es' => '— Torno alle cinque.  (Vuelvo a las cinco.)']],
                    ['texto' => ['es' => '«Alle» es «a + le», o sea «a las». Con la una es «all\'una». Es la misma contracción que el español hace con «a + el = al».']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «è le otto» en singular. Las horas son plural: SONO le otto. Solo la una, el mediodía y la medianoche van en singular con «è».']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Si te preguntan «Che ore sono?» y no sabes la hora exacta, «Non lo so» (no lo sé) es una respuesta perfectamente italiana y te saca del apuro.']],
            ],
        ],

        // ============ U4 · A1.PO.2 · lo que me gusta ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.PO.2', 'slug' => 'mi-piace',
            'titulo' => ['es' => 'Mi piace!'],
            'resumen' => ['es' => 'Decir qué te gusta y qué no, y por qué. Y dos sonidos que el español no tiene: gl y gn.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Buena noticia: «gustar» en italiano funciona exactamente como en español. No dices «yo gusto la pizza», dices «me gusta la pizza» — la pizza es la que gusta, y tú eres a quien le gusta. El italiano hace lo mismo: mi piace la pizza.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'mi piace + UNA cosa → Mi piace la pizza. Mi piace il calcio.'],
                    ['es' => 'mi piacciono + VARIAS cosas → Mi piacciono i gelati. Mi piacciono i cani.'],
                    ['es' => 'mi piace + un verbo → Mi piace cantare. Mi piace leggere.'],
                    ['es' => 'para preguntar: Ti piace…? / Ti piacciono…?'],
                    ['es' => 'para negar: Non mi piace. Non mi piacciono.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Usar «piace» para todo: «mi piace i gelati». Si lo que gusta es plural, el verbo es plural: mi piacCIONO i gelati. Es el mismo error que «me gusta los helados» en español, y suena igual de raro.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir por qué'], 'pasos' => [
                    ['texto' => ['es' => '— Ti piace il calcio?  (¿Te gusta el fútbol?)']],
                    ['texto' => ['es' => '— Sì, mi piace molto, perché è bello.  (Sí, me gusta mucho, porque es bonito.)']],
                    ['texto' => ['es' => '— E la scuola?']],
                    ['texto' => ['es' => '— Mi piace poco. È interessante ma è difficile.  (Me gusta poco. Es interesante pero es difícil.)']],
                    ['texto' => ['es' => 'Con seis adjetivos ya puedes opinar de casi todo: bello / brutto (bonito / feo), buono (bueno, de comida), interessante / noioso (interesante / aburrido), facile / difficile (fácil / difícil).']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Perché» sirve para preguntar Y para responder: es «por qué» y «porque» a la vez. Perché ti piace? — Perché è bello. Y lleva tilde siempre, sobre la última e, porque la sílaba fuerte es la última — la regla de la U3.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y ahora dos sonidos que no existen en español y que ya llevas dos unidades diciendo mal sin saberlo. Uno está en «famiglia».']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'gl + i → suena como la «ll» de un argentino o como «li» muy pegado: famiglia, gli, meglio (mejor). NUNCA con g dura.'],
                    ['es' => 'gn → suena como la ñ: spagnolo (español), gnocchi, bagno (baño), Bologna.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «fami-GLIA» con una g de gato. En italiano esa g no suena: famiglia es «fa-MI-lia» con la ll bien pegada. Y «spagnolo» no es «spag-NO-lo»: es «spa-ÑO-lo», con ñ. El español tiene la ñ y no la usa aquí porque no la ve escrita.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Si te cuesta el gl, piensa en cómo dice «llave» un argentino, o en la palabra española «familia» dicha muy rápido. Ese es el sonido. El gn es fácil: es tu ñ de siempre, solo que escrita con dos letras.']],
            ],
        ],

        // ============ U4 · A1.EE.1 · escribir una nota corta ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.EE.1', 'slug' => 'una-nota',
            'titulo' => ['es' => 'Escribir una nota de tres líneas'],
            'resumen' => ['es' => 'Un mensaje corto tiene una forma fija. Aprendértela te ahorra pensar y te evita los errores de siempre.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Con lo que sabes ya puedes escribir un mensaje real: presentarte, decir qué te gusta y despedirte. Un mensaje corto en italiano tiene tres piezas, y siempre las mismas.']],

                ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                    ['es' => 'Saludo con nombre: «Ciao Marco!» (si es un amigo) o «Buongiorno, professoressa» (si no lo es).'],
                    ['es' => 'Dos o tres frases cortas. Una idea por frase. Con punto.'],
                    ['es' => 'Despedida y tu nombre: «Ciao, Sofía.» o «A presto, Sofía.» (hasta pronto).'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una nota completa'], 'pasos' => [
                    ['texto' => ['es' => 'Ciao Anna!']],
                    ['texto' => ['es' => 'Mi chiamo Sofía e sono ecuadoriana. Mi piace molto la musica e mi piacciono i cani. Non mi piace il calcio.']],
                    ['texto' => ['es' => 'A presto, Sofía.']],
                    ['texto' => ['es' => 'Tres frases. Ninguna tiene más de nueve palabras. En A1 la frase corta no es una limitación: es la que sale bien.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Intentar escribir la frase larga que escribirías en español, con «que», «aunque» y «cuando». Todavía no tienes esas herramientas y la frase se rompe a la mitad. Dos frases cortas dicen lo mismo y las dos salen bien.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Antes de mandarla, lee tu nota buscando solo dos cosas: ¿cada «mi piacciono» va con un plural? ¿cada «è» lleva su acento? Con esas dos revisiones cazas la mitad de los errores de la unidad.']],
            ],
        ],

        // ============ U5 · A1.CE.3 · orientarse en la ciudad ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.CE.3', 'slug' => 'in-citta',
            'titulo' => ['es' => 'Scusi, dov\'è la stazione?'],
            'resumen' => ['es' => 'Preguntar dónde está algo, entender la respuesta, y decir qué hay en tu barrio. Y la s que suena de dos maneras.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En una ciudad italiana vas a necesitar dos cosas: preguntar dónde está algo y entender lo que te contestan. Para lo primero hay una fórmula; para lo segundo, seis palabras.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Preguntar por un sitio'], 'pasos' => [
                    ['texto' => ['es' => '— Scusi, dov\'è la stazione?  (Perdone, ¿dónde está la estación?)']],
                    ['texto' => ['es' => '— È lì, a destra.  (Está allí, a la derecha.)']],
                    ['texto' => ['es' => '— C\'è una farmacia qui vicino?  (¿Hay una farmacia aquí cerca?)']],
                    ['texto' => ['es' => '— Sì, in via Roma. Vai dritto e poi a sinistra.  (Sí, en la calle Roma. Sigue recto y luego a la izquierda.)']],
                    ['texto' => ['es' => 'Fíjate en «scusi» con i: es la forma de usted. A un desconocido en la calle se le dice scusi, no scusa. Es la primera vez que la diferencia formal / informal cambia una palabra, y no será la última.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'a destra — a la derecha · a sinistra — a la izquierda · dritto — recto'],
                    ['es' => 'qui — aquí · lì — allí · vicino — cerca · lontano — lejos'],
                    ['es' => 'poi — luego, después'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El punto de gramática es «hay». En italiano tiene dos formas, según lo que haya sea una cosa o varias: c\'è (hay una) y ci sono (hay varias).']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'C\'è una farmacia in via Roma. — Hay una farmacia en la calle Roma.'],
                    ['es' => 'Ci sono due bar in piazza. — Hay dos bares en la plaza.'],
                    ['es' => 'Non c\'è un ospedale qui vicino. — No hay un hospital aquí cerca.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Usar «c\'è» para todo, porque en español «hay» no cambia nunca. En italiano sí: c\'è UN parco, ci sono DUE parchi. Es exactamente la misma lógica de mi piace / mi piacciono de la unidad pasada.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Las preposiciones de lugar se reparten así: «a» con ciudades (a Quito, a Roma), «in» con países y con calles (in Italia, in via Roma). Y «in» también con la mayoría de sitios cerrados: in banca, in farmacia, in piazza. Con «al bar» y «al ristorante» se usa «a» — apréndetelos como excepciones, que lo son.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y el sonido de esta unidad, que llevas oyendo sin notarlo desde «casa» y «scusa»: la s italiana suena de dos maneras.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 's SORDA (la s del español): al empezar palabra y junto a consonante sorda — sole, scuola, stazione, sinistra.'],
                    ['es' => 's SONORA (como un zumbido, la z del inglés «zoo»): entre dos vocales — casa, chiesa, rosa, museo.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pronunciar «casa» y «chiesa» con la s española. En italiano esa s entre vocales vibra: es «caza» con z inglesa, «chie-za». No es grave para que te entiendan, pero es la marca más clara de acento extranjero, y se corrige con cinco minutos de práctica delante de un espejo.']],
            ],
        ],

        // ============ U5 · A1.PO.1 · mi ciudad ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.PO.1', 'slug' => 'la-mia-citta',
            'titulo' => ['es' => 'La mia città'],
            'resumen' => ['es' => 'Describir tu barrio con lo que hay y lo que no hay. Es la primera descripción de un lugar que puedes hacer entera.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Con c\'è, ci sono, diez lugares y cuatro adjetivos ya describes tu barrio. No hace falta más para que un italiano se lo imagine.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'la città — la ciudad · la via / la strada — la calle · la piazza — la plaza'],
                    ['es' => 'la stazione · l\'ospedale · la farmacia · il supermercato · la banca'],
                    ['es' => 'la chiesa — la iglesia · il museo · il parco · il bar · il ristorante'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El barrio de Sofía'], 'pasos' => [
                    ['texto' => ['es' => 'Abito a Quito, in via Amazonas.']],
                    ['texto' => ['es' => 'Qui vicino c\'è un parco molto bello e ci sono due supermercati.']],
                    ['texto' => ['es' => 'Non c\'è un museo, ma c\'è una piazza grande.']],
                    ['texto' => ['es' => 'La scuola è lontano: è in un\'altra via.']],
                    ['texto' => ['es' => 'Cuatro frases, y ya sabes dónde vive, qué tiene cerca, qué le falta y que el colegio le queda lejos. Es una descripción completa.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Para describir un lugar, alterna: una cosa que hay, una que no hay, una que hay. «Non c\'è… ma c\'è…» (no hay… pero hay…) es la estructura que más natural suena y la que menos errores produce.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Il bar» en Italia no es un bar de copas: es donde se desayuna, se toma el café de pie y se compra un bocadillo. Abre a las seis de la mañana. Cuando un italiano dice «andiamo al bar» a las ocho de la mañana, no está proponiendo nada raro.']],
            ],
        ],

        // ============ U6 · A1.IO.2 · pedir en un bar ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.IO.2', 'slug' => 'al-bar',
            'titulo' => ['es' => 'Al bar: vorrei un caffè'],
            'resumen' => ['es' => 'Pedir algo de comer o beber, decir cuánto, y pagar. Y la z italiana, que no se parece a la tuya.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Pedir en Italia se hace con una palabra mágica que no tienes que conjugar ni entender todavía: «vorrei», que significa «quisiera». Vorrei + lo que quieras + per favore, y ya estás pidiendo como un italiano.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'En el bar'], 'pasos' => [
                    ['texto' => ['es' => '— Buongiorno! Vorrei un caffè e un cornetto, per favore.  (Quisiera un café y un cruasán, por favor.)']],
                    ['texto' => ['es' => '— Subito. Altro?  (Enseguida. ¿Algo más?)']],
                    ['texto' => ['es' => '— No, grazie. Quanto costa?  (No, gracias. ¿Cuánto cuesta?)']],
                    ['texto' => ['es' => '— Due euro e cinquanta.']],
                    ['texto' => ['es' => 'Con vorrei, quanto costa, y los números que ya sabes, sobrevives en cualquier bar de Italia. Lo demás es vocabulario.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'il pane — el pan · l\'acqua — el agua · il latte — la leche · il formaggio — el queso'],
                    ['es' => 'la carne · il pesce — el pescado · la frutta · la verdura · le uova — los huevos'],
                    ['es' => 'la colazione — el desayuno · il pranzo — el almuerzo · la cena — la cena'],
                    ['es' => 'lo zucchero — el azúcar · il sale — la sal · il conto — la cuenta'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El punto de gramática es cómo decir «un poco de»: el partitivo. Se forma con di + el artículo, y suena como una sola palabra.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'del (di + il) → del pane, del formaggio'],
                    ['es' => 'della (di + la) → della frutta, della carne'],
                    ['es' => 'dei (di + i) → dei biscotti · delle (di + le) → delle uova'],
                    ['es' => 'Vorrei del pane e della frutta. — Quisiera (un poco de) pan y fruta.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pedir sin partitivo, como en español: «vorrei pane». En italiano suena a que quieres TODO el pan del local. Con «del pane» pides una cantidad razonable. Y al revés: con cosas contables y una sola, va «un / una»: un caffè, una pizza, no «del caffè».']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: la z. Está en pizza, en grazie, en zucchero, y en ninguna de las tres suena como la z española.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'z SORDA = «ts»: pizza (pit-tsa), grazie (grat-tsie), zucchero (tsuc-chero), stazione (sta-tsio-ne).'],
                    ['es' => 'z SONORA = «dz»: zero (dze-ro), mezzo (med-dzo). Menos frecuente; si dudas, «ts» acierta más veces.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «pisa» o «piza» con s. En italiano la z siempre lleva una t escondida delante: pit-tsa. Y «grazie» no es «grasie»: es «grat-tsie». Es de las pocas cosas que un italiano corrige a un extranjero en la primera conversación.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Buon appetito» se dice siempre antes de empezar a comer, a todos los de la mesa, y se contesta «grazie, altrettanto» (gracias, igualmente). No decirlo no es un error de gramática, pero se nota.']],
            ],
        ],

        // ============ U6 · A1.CE.1 · leer un menú ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.CE.1', 'slug' => 'il-menu',
            'titulo' => ['es' => 'Leer un menú y un cartel'],
            'resumen' => ['es' => 'Un menú italiano se lee en dos minutos si sabes qué buscar. Y cuatro carteles que verás en cualquier puerta.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Un menú italiano tiene un orden fijo: antipasti (entrantes), primi (pasta y arroz), secondi (carne y pescado), contorni (guarniciones), dolci (postres). Si sabes eso, ya no te pierdes aunque no entiendas cada plato.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Un trozo de menú'], 'pasos' => [
                    ['texto' => ['es' => 'PRIMI · Spaghetti al pomodoro — 9 € · Risotto ai funghi — 11 €']],
                    ['texto' => ['es' => 'SECONDI · Pollo arrosto — 12 € · Pesce del giorno — 15 €']],
                    ['texto' => ['es' => 'BEVANDE · Acqua — 2 € · Caffè — 1,20 €']],
                    ['texto' => ['es' => 'Lo que necesitas de aquí: qué es cada bloque, y el precio. «Pomodoro» es tomate, «funghi» son setas, «pollo» es pollo. Lo demás se deduce o se pregunta: «Cos\'è?» (¿qué es?).']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'APERTO — abierto · CHIUSO — cerrado'],
                    ['es' => 'ENTRATA — entrada · USCITA — salida (esta ya la conoces)'],
                    ['es' => 'SPINGERE — empujar · TIRARE — tirar (de la puerta)'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Leer «TIRARE» en una puerta y empujar, porque en español «tirar» algo es lanzarlo. En italiano tirare es tirar HACIA TI. Y «CHIUSO» no tiene nada que ver con «chulo» ni con nada: es cerrado, y si está en la puerta, no insistas.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Los precios en Italia se escriben con coma decimal, como en Ecuador: 1,20 € es un euro con veinte. Y el café de pie en la barra cuesta menos que sentado en la mesa — a veces la mitad. No es una estafa: está en la ley y en el cartel.']],
            ],
        ],

        // ============ U7 · A1.IO.2 · comprar y el tiempo ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.IO.2', 'slug' => 'quanto-costa',
            'titulo' => ['es' => 'Quanto costa? Che tempo fa?'],
            'resumen' => ['es' => 'Comprar ropa, preguntar precios y hablar del tiempo. Con eso aparece la concordancia del adjetivo entera — y la entonación de la pregunta, que en italiano es lo único que la marca.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Comprar ropa es la excusa perfecta para el punto de gramática de esta unidad, porque la ropa tiene colores y los colores son adjetivos, y los adjetivos italianos concuerdan igual que los españoles… casi.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'il vestito — el vestido · la maglietta — la camiseta · i pantaloni — los pantalones'],
                    ['es' => 'le scarpe — los zapatos · la giacca — la chaqueta · il cappello — el sombrero · la borsa — el bolso'],
                    ['es' => 'rosso, giallo, nero, bianco — rojo, amarillo, negro, blanco'],
                    ['es' => 'verde, blu — verde, azul · caro / economico — caro / barato · la taglia — la talla'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Cómo concuerda un adjetivo'], 'pasos' => [
                    ['texto' => ['es' => 'Los adjetivos en -o tienen CUATRO formas: rosso (il vestito rosso), rossa (la giacca rossa), rossi (i pantaloni rossi), rosse (le scarpe rosse).']],
                    ['texto' => ['es' => 'Los adjetivos en -e tienen solo DOS: verde para singular (il vestito verde, la giacca verde) y verdi para plural (i pantaloni verdi, le scarpe verdi).']],
                    ['texto' => ['es' => 'Y «blu» no cambia nunca: la giacca blu, le scarpe blu. Es extranjera y el italiano la deja en paz.']],
                    ['texto' => ['es' => 'La diferencia con el español está en el plural: el italiano NO añade -s. Cambia la vocal: rosso → rossi, rossa → rosse, verde → verdi.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Hacer el plural con -s: «le scarpe rossas», «i pantaloni verdes». En italiano no existe la -s de plural. Nunca. Ni en sustantivos ni en adjetivos. Si escribes una palabra italiana terminada en -s, casi seguro está mal.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'En la tienda'], 'pasos' => [
                    ['texto' => ['es' => '— Quanto costa questa maglietta?  (¿Cuánto cuesta esta camiseta?)']],
                    ['texto' => ['es' => '— Quindici euro.']],
                    ['texto' => ['es' => '— È un po\' cara. C\'è la taglia M?  (Es un poco cara. ¿Hay talla M?)']],
                    ['texto' => ['es' => '— Sì, in nero e in bianco.']],
                    ['texto' => ['es' => 'Fíjate en la última frase de la clienta: «È un po\' cara» y «C\'è la taglia M?» tienen la misma estructura, y solo la entonación dice cuál es pregunta.']],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Ese es el punto de pronunciación. En italiano una pregunta se hace SOLO con la voz: no se cambia el orden de las palabras, y no hay signo de apertura. «Costa dieci euro.» y «Costa dieci euro?» son las mismas cuatro palabras. La segunda sube al final.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Subir el tono demasiado pronto, en la primera palabra, como hace el español con «¿cuánto…?». En italiano la subida va al FINAL, en la última sílaba. Si subes al principio, el italiano oye una afirmación rara y espera a que termines.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y para hablar del tiempo, que es lo segundo de lo que habla cualquiera: en italiano el tiempo «hace», con el verbo fare.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Che tempo fa? — ¿Qué tiempo hace?'],
                    ['es' => 'Fa caldo. / Fa freddo. — Hace calor. / Hace frío.'],
                    ['es' => 'C\'è il sole. / Piove. / È nuvoloso. — Hay sol. / Llueve. / Está nublado.'],
                    ['es' => 'l\'estate, l\'inverno, la primavera, l\'autunno — verano, invierno, primavera, otoño'],
                ]],
            ],
        ],

        // ============ U8 · A1.PO.2 · contar lo que hice ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.PO.2', 'slug' => 'ieri',
            'titulo' => ['es' => 'Ieri ho mangiato una pizza'],
            'resumen' => ['es' => 'Contar lo que hiciste ayer o el fin de semana. Un solo tiempo del pasado, y se construye con dos piezas que ya tienes.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Hasta ahora todo ha sido presente. Para contar algo que pasó, el italiano usa un tiempo que se llama passato prossimo, y la buena noticia es que se monta con dos cosas que ya sabes: el verbo avere (U2) y una forma nueva del verbo que es facilísima.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'La máquina del pasado'], 'pasos' => [
                    ['texto' => ['es' => 'Pieza 1: avere en presente. ho, hai, ha.']],
                    ['texto' => ['es' => 'Pieza 2: el participio. Para los verbos en -are se quita -are y se pone -ato: mangiare → mangiato, studiare → studiato, parlare → parlato.']],
                    ['texto' => ['es' => 'Se juntan: Ho mangiato. (He comido / comí.) Hai studiato? (¿Estudiaste?) Ha parlato. (Habló.)']],
                    ['texto' => ['es' => 'Y el participio NO cambia con la persona: ho mangiato, hai mangiato, ha mangiato. Es la misma palabra las tres veces.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Ese tiempo sirve para las dos cosas que el español separa: «he comido» Y «comí». Ieri ho mangiato = ayer comí. Oggi ho mangiato = hoy he comido. Un solo tiempo, menos que aprender.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ieri — ayer · ieri sera — anoche · la settimana scorsa — la semana pasada'],
                    ['es' => 'prima — antes · poi / dopo — luego / después · già — ya · ancora — todavía'],
                    ['es' => 'con — con · insieme — juntos · gli amici — los amigos · la festa — la fiesta'],
                    ['es' => 'il mare — el mar · la montagna — la montaña · il viaggio — el viaje'],
                    ['es' => 'comprare — comprar · visitare — visitar · telefonare — llamar por teléfono'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El fin de semana de Marco'], 'pasos' => [
                    ['texto' => ['es' => 'Sabato ho studiato la mattina.  (El sábado estudié por la mañana.)']],
                    ['texto' => ['es' => 'Poi ho giocato a calcio con gli amici.  (Luego jugué al fútbol con los amigos.)']],
                    ['texto' => ['es' => 'Domenica ho visitato mia nonna e ho mangiato molto bene.  (El domingo visité a mi abuela y comí muy bien.)']],
                    ['texto' => ['es' => 'Cuatro verbos en pasado y todos con la misma pieza: ho + -ato. Con eso ya cuentas un fin de semana entero.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Usar «sono» con estos verbos: «sono mangiato», «sono studiato». Con los verbos de esta unidad el pasado va SIEMPRE con avere: ho mangiato, ho studiato. Hay otros verbos que van con essere —«sono andato» (fui)— y tienen su propia regla, pero esos los verás el año que viene. Por ahora: avere + -ato.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y el punto de pronunciación, que ya no es un sonido sino la forma de hilar las palabras: el italiano NO corta entre una palabra que termina en vocal y otra que empieza en vocal. Se enlazan.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ho ascoltato → se oye «oascoltato», de un tirón'],
                    ['es' => 'ha visitato una amica → «avisitatounamica»'],
                    ['es' => 'ieri ho mangiato → «ierio-mangiato»'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'El español hace lo mismo —«mi amigo» se dice «miamigo»—, así que esto te sale solo si no te frenas. El error viene de LEER: al leer palabra por palabra metes un corte entre «ho» y «ascoltato» que al hablar no existe. Lee en voz alta como si hablaras.']],
            ],
        ],

        // ============ U9 · A1.IO.1 · sostener una conversación (repaso y proyecto) ============
        [
            'lengua' => 'it', 'descriptor' => 'A1.IO.1', 'slug' => 'non-ho-capito',
            'titulo' => ['es' => 'Non ho capito: cómo no perder una conversación'],
            'resumen' => ['es' => 'La unidad 9 no trae gramática nueva. Trae lo único que te falta para hablar dos minutos seguidos con un italiano: saber qué decir cuando no entiendes.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En A1 vas a entender la mitad de lo que te digan. Eso es normal y está previsto: el descriptor del MCER dice literalmente «siempre que la otra persona repita o hable más despacio». Lo que distingue a un alumno que conversa de uno que se bloquea no es cuánto sabe, sino si sabe pedir ayuda sin salirse del italiano.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Come? — ¿Cómo? (la más corta, sirve siempre)'],
                    ['es' => 'Scusi, non ho capito. — Perdone, no he entendido.'],
                    ['es' => 'Può ripetere, per favore? — ¿Puede repetir, por favor?'],
                    ['es' => 'Più lentamente, per favore. — Más despacio, por favor.'],
                    ['es' => 'Cosa significa «…»? — ¿Qué significa «…»?'],
                    ['es' => 'Come si dice «…» in italiano? — ¿Cómo se dice «…» en italiano?'],
                    ['es' => 'Va bene. / Certo. / Un momento. — Vale. / Claro. / Un momento.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Apréndete estas siete frases como si fueran una sola palabra cada una. No se analizan, se disparan. «Non ho capito» es un passato prossimo (U8), pero no hace falta que lo sepas para usarlo: es lo que dices cuando te quedas en blanco, y te devuelve al italiano en vez de al español.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una conversación con reparación'], 'pasos' => [
                    ['texto' => ['es' => '— Ciao! Di dove sei?']],
                    ['texto' => ['es' => '— Sono di Quito. E tu?']],
                    ['texto' => ['es' => '— Di Bologna. Che cosa studi a scuola?']],
                    ['texto' => ['es' => '— Come? Più lentamente, per favore.']],
                    ['texto' => ['es' => '— Che… cosa… studi… a… scuola?']],
                    ['texto' => ['es' => '— Ah! Studio italiano e matematica. Mi piace molto l\'italiano.']],
                    ['texto' => ['es' => 'La alumna no entendió una pregunta entera, lo dijo en italiano, la otra persona repitió, y la conversación siguió. Eso es exactamente lo que evalúa el nivel A1. No que lo entiendas todo: que no te caigas.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pasarse al español —o al inglés— en cuanto algo no se entiende. «¿Qué?», «What?», «eh…». Cada vez que lo haces, la conversación en italiano se acaba. «Come?» cuesta lo mismo y la mantiene viva.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El proyecto final de esta unidad es una conversación de dos minutos con el interlocutor del curso, sobre lo que quieras de las ocho unidades: quién eres, tu familia, tu día, lo que te gusta, tu barrio, qué comes, qué ropa llevas, qué hiciste ayer. Vas a usar las siete frases de arriba al menos una vez. Si no te hacen falta, es que la conversación fue demasiado fácil.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Antes del proyecto, repasa: las cinco parejas de la c y la g (U1), las dobles (U2), la segunda persona en -i (U3), piace / piacciono (U4), c\'è / ci sono (U5), el partitivo (U6), el plural sin -s (U7) y ho + -ato (U8). Ocho cosas. Si las tienes, tienes el A1.']],
            ],
        ],

        // ================================================================
        // ======================= FRANCÉS · A1 ============================
        // ================================================================

        // ============ FR U1 · A1.CO.2 · saludar, y tu / vous ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.CO.2', 'slug' => 'bonjour',
            'titulo' => ['es' => 'Bonjour! Saludar en francés (y a quién tratar de vous)'],
            'resumen' => ['es' => 'Los saludos, y la decisión que un francés toma antes de abrir la boca: tú o usted.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En francés el saludo lleva dentro una decisión: si a la otra persona le hablas de tú (tu) o de usted (vous). Y a diferencia del español de Ecuador, donde el usted es cosa de respeto pero no de distancia, en Francia el vous es lo NORMAL con cualquier adulto que no sea de tu círculo. Equivocarse de lado se nota mucho.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'bonjour — buenos días (y también «hola» formal, todo el día hasta la noche)'],
                    ['es' => 'bonsoir — buenas tardes / noches, a partir de las seis o siete'],
                    ['es' => 'salut — hola / adiós, solo con amigos y gente de tu edad'],
                    ['es' => 'au revoir — adiós, con cualquiera'],
                    ['es' => 'merci — gracias · s\'il vous plaît — por favor (de usted) · pardon — perdón'],
                    ['es' => 'enchanté / enchantée — encantado / encantada'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Bonjour» es la palabra más importante del francés. Se dice al entrar en cualquier tienda, al camarero, al conductor del autobús, ANTES de pedir nada. No decirla y empezar directamente con lo que quieres se considera maleducado. Bonjour primero, siempre.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Usar «salut» con un adulto desconocido o con un profesor. Salut es tuteo puro: solo compañeros, amigos, familia. Si dudas, bonjour. Nunca queda mal.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y ahora el punto de pronunciación de esta unidad, que es la razón por la que el francés escrito y el francés hablado parecen dos idiomas: LAS LETRAS FINALES NO SUENAN.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'salut → se dice «salú». La t final es muda.'],
                    ['es' => 'Paris → «parí». La s final es muda.'],
                    ['es' => 'comment → «comán». La t final es muda.'],
                    ['es' => 'français → «fransé». La s final es muda.'],
                    ['es' => 'Excepciones que sí suenan: la r (bonjour → «bonyúr»), la l, la c y la f, y casi todas las vocales.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pronunciar todo lo que ves escrito, como haces en español. «Salut» dicho «salut» con t, o «Paris» con s, delata al hispanohablante en la primera palabra. La regla es cruda pero funciona: consonante final → muda, salvo r, l, c, f (piensa en «careful»: c-r-f-l).']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Cuando veas una palabra francesa nueva, léela en voz alta quitándole la última consonante. Acertarás nueve de cada diez veces. Las que fallen serán r, l, c o f.']],
            ],
        ],

        // ============ FR U1 · A1.IO.3 · presentarse: être y el pronombre obligatorio ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.IO.3', 'slug' => 'je-m-appelle',
            'titulo' => ['es' => 'Je m\'appelle Sofía'],
            'resumen' => ['es' => 'Decir tu nombre, de dónde eres y presentar a alguien. Y la regla que el español no tiene: el pronombre NUNCA se calla.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para presentarte hacen falta tres frases fijas, que aprendes enteras, y un verbo: être, que es «ser». Pero antes, la regla que lo cambia todo respecto al español.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'En español dices «soy ecuatoriana» y el «yo» sobra. En francés NO: es «JE suis équatorienne», con el je siempre. El pronombre es obligatorio en todas las frases, con todos los verbos, sin excepción. La razón: muchas formas del verbo suenan igual (je suis, tu es… pero il est y ils sont), y sin el pronombre no se sabe quién habla. Esta es la regla que más veces vas a olvidar este año.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Presentarse y preguntar el nombre'], 'pasos' => [
                    ['texto' => ['es' => '— Comment tu t\'appelles ?  (¿Cómo te llamas?)']],
                    ['texto' => ['es' => '— Je m\'appelle Sofía.  (Me llamo Sofía.)']],
                    ['texto' => ['es' => 'De otra persona: «Il s\'appelle Marc» / «Elle s\'appelle Anna».']],
                    ['texto' => ['es' => 'Y a un adulto, de usted: «Comment vous appelez-vous ?». Por ahora apréndete las dos preguntas enteras: la de tu y la de vous.']],
                    ['texto' => ['es' => 'Fíjate en el espacio antes del signo de interrogación: en francés se escribe así, con un espacio. No es un error de tecleo.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'je suis — yo soy'],
                    ['es' => 'tu es — tú eres'],
                    ['es' => 'il est / elle est — él es / ella es'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Tu es» y «il est» se pronuncian casi igual: «tü e» e «il e». La s de es y la t de est son mudas (la regla de la lección anterior). Por eso el pronombre no se puede callar: es lo único que distingue a uno de otro al oído.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir de dónde eres'], 'pasos' => [
                    ['texto' => ['es' => '— Tu es d\'où ?  (¿De dónde eres?)']],
                    ['texto' => ['es' => '— Je suis équatorienne, de Quito.  (Soy ecuatoriana, de Quito.)']],
                    ['texto' => ['es' => 'La nacionalidad cambia con el género, y en francés el cambio se OYE: français (fransé) / française (fransés); équatorien (ecuatoriã) / équatorienne (ecuatorién). El femenino añade una e que hace sonar la consonante de antes.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «suis équatorienne» sin el je, o «m\'appelle Sofía» sin el je. Cada vez que empieces una frase en francés, pregúntate: ¿dónde está mi pronombre? Si no está, la frase está mal, aunque se entienda.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Le premier jour'], 'pasos' => [
                    ['texto' => ['es' => 'Marc — Bonjour ! Je m\'appelle Marc. Et toi, comment tu t\'appelles ?']],
                    ['texto' => ['es' => 'Sofía — Salut, Marc. Je m\'appelle Sofía.']],
                    ['texto' => ['es' => 'Marc — Enchanté. Tu es d\'où ?']],
                    ['texto' => ['es' => 'Sofía — Je suis équatorienne, de Quito. Et toi ?']],
                    ['texto' => ['es' => 'Marc — Je suis français, de Lyon. Tu es élève ici ?']],
                    ['texto' => ['es' => 'Sofía — Oui. Pardon… et elle, qui est-ce ?']],
                    ['texto' => ['es' => 'Marc — C\'est madame Durand, la professeure.']],
                    ['texto' => ['es' => 'Sofía — Merci, Marc. Au revoir !']],
                    ['texto' => ['es' => 'Marc — Salut !']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Cuenta los pronombres del diálogo: je, tu, elle, aparecen en todas las frases con verbo menos en una («C\'est madame Durand», donde el «ce» es el pronombre). Ese es el ritmo del francés. Cuando te suene raro repetir tanto «je», es que lo estás haciendo bien.']],
            ],
        ],

        // ============ FR U2 · A1.PO.1 · la familia, avoir, y las nasales ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.PO.1', 'slug' => 'ma-famille',
            'titulo' => ['es' => 'Ma famille'],
            'resumen' => ['es' => 'Hablar de tu familia y decir la edad. Con eso llega «avoir», el artículo, y los tres sonidos que salen por la nariz.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para hablar de los tuyos necesitas los nombres de la familia, el verbo «tener» (avoir), y los artículos, que en francés van delante de casi todo.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'le père / la mère — el padre / la madre (y en casa: papa / maman)'],
                    ['es' => 'le frère / la sœur — el hermano / la hermana'],
                    ['es' => 'le grand-père / la grand-mère — el abuelo / la abuela'],
                    ['es' => 'l\'oncle / la tante — el tío / la tía · le cousin / la cousine — el primo / la prima'],
                    ['es' => 'le fils / la fille — el hijo / la hija · les parents — los padres'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El artículo: le, la, les, l\''], 'pasos' => [
                    ['texto' => ['es' => 'le → masculino singular: le père, le frère']],
                    ['texto' => ['es' => 'la → femenino singular: la mère, la sœur']],
                    ['texto' => ['es' => 'les → plural, los dos géneros: les parents, les sœurs']],
                    ['texto' => ['es' => 'l\' → delante de vocal, los dos géneros: l\'oncle, l\'élève. La vocal se come el artículo.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'j\'ai — yo tengo (je + ai → j\'ai, la vocal se come la e)'],
                    ['es' => 'tu as — tú tienes'],
                    ['es' => 'il a / elle a — él tiene / ella tiene'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir la edad'], 'pasos' => [
                    ['texto' => ['es' => '— Tu as quel âge ?  (¿Qué edad tienes?)']],
                    ['texto' => ['es' => '— J\'ai quinze ans.  (Tengo quince años.)']],
                    ['texto' => ['es' => 'Igual que en español y en italiano: la edad se TIENE. Y «ans» no se cae nunca: no se dice «j\'ai quinze» a secas.']],
                    ['texto' => ['es' => 'Del 1 al 20: un, deux, trois, quatre, cinq, six, sept, huit, neuf, dix, onze, douze, treize, quatorze, quinze, seize, dix-sept, dix-huit, dix-neuf, vingt.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Quinze ans» se dice «kanzán» de un tirón: la z de quinze se enlaza con la a de ans. Esto se llama liaison y es el punto de la unidad 3; por ahora, oye que la edad suena como una sola palabra.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y el sonido de esta unidad, que es el que más separa al francés del español: las vocales nasales. El aire sale por la nariz. Hay tres, y las tres están en palabras de esta lección.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'AN / EN → «ã»: grand-père, parents, enfant, quinze ans. Es una a con la boca abierta y aire por la nariz.'],
                    ['es' => 'ON → «õ»: oncle, bonjour, mon, non. Una o cerrada y nasal.'],
                    ['es' => 'IN / AIN / UN → «ɛ̃»: cousin, cinq, vingt, un. Una e abierta y nasal.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pronunciar la n: decir «bon» como «bon» español, con la n al final, o «cousin» como «cusín». En francés esa n NO SE DICE: es una marca de que la vocal de antes es nasal. «Bon» es «bõ», sin cerrar la boca al final. Si al terminar la palabra tu lengua toca el paladar, la has dicho en español.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Para encontrar la nasal: di «bon» y, justo antes de cerrar para la n, para. Lo que tienes en ese momento —la boca abierta, el aire saliendo por la nariz— es la vocal nasal. Practica con «mon oncle»: «mõ nõkl».']],
            ],
        ],

        // ============ FR U2 · A1.CE.2 · leer un mensaje breve ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.CE.2', 'slug' => 'un-message',
            'titulo' => ['es' => 'Leer un mensaje corto'],
            'resumen' => ['es' => 'Cómo sacar lo que importa de una postal de cuatro líneas sin entenderlo todo.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Leer en francés tiene una ventaja que hablar no tiene: escrito, el francés se parece muchísimo al español. Muchas palabras que no reconocerías al oído las reconoces a la primera en el papel. Aprovéchalo.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una postal desde Lyon'], 'pasos' => [
                    ['texto' => ['es' => 'Salut Marc !']],
                    ['texto' => ['es' => 'Je suis à Lyon avec ma sœur et ma tante.']],
                    ['texto' => ['es' => 'Ma grand-mère est de Lyon.']],
                    ['texto' => ['es' => 'À bientôt, Anna.']],
                    ['texto' => ['es' => 'Quién escribe: Anna. Dónde está: en Lyon. Con quién: su hermana y su tía. Y el dato extra: su abuela es de Lyon. «À bientôt» es «hasta pronto».']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Subraya primero lo que se parece al español: «sœur» no, pero «tante», «grand-mère», «Lyon» sí. Con eso ya tienes el esqueleto. Lo que no reconozcas, dedúcelo de lo que hay alrededor.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Parar en la primera palabra desconocida. En A1 vas a encontrar dos o tres por mensaje: eso es normal y no te impide entenderlo. Salta y sigue.']],
            ],
        ],

        // ============ FR U3 · A1.PO.2 · mi día, los verbos en -er, la liaison ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.PO.2', 'slug' => 'ma-journee',
            'titulo' => ['es' => 'Ma journée'],
            'resumen' => ['es' => 'Contar lo que haces cada día. Llega el primer grupo de verbos —y la sorpresa de que tres formas distintas suenan igual—, y la liaison.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Los verbos en -er son casi todos los verbos del francés. Una vez que sabes conjugar uno, sabes conjugar cientos. Y traen una sorpresa que explica por qué el francés escribe tanto y dice tan poco.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'parler — hablar · étudier — estudiar · manger — comer · travailler — trabajar'],
                    ['es' => 'habiter — vivir (en un sitio) · jouer — jugar · regarder — mirar · écouter — escuchar'],
                    ['es' => 'arriver — llegar · rentrer — volver (a casa)'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Conjugar parler en singular'], 'pasos' => [
                    ['texto' => ['es' => 'Se quita -er y queda la raíz: parl-.']],
                    ['texto' => ['es' => 'je parle · tu parles · il / elle parle']],
                    ['texto' => ['es' => 'Y ahora dilo en voz alta: «parl», «parl», «parl». LAS TRES SUENAN IGUAL. La e y la s finales son mudas.']],
                    ['texto' => ['es' => 'Por eso el pronombre es obligatorio (U1): al oído, «je parle» y «il parle» solo se distinguen por el je y el il. Escrito, la -s de «tu parles» está ahí; hablado, no existe.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Olvidar la -s de «tu» al escribir: «tu parle». Como no se oye, se olvida. Al escribir, tu SIEMPRE lleva -s: tu parles, tu manges, tu habites. Es el error de ortografía número uno de todo el curso.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Delante de vocal o h muda, je se convierte en j\': j\'habite, j\'écoute, j\'étudie. Es la misma elisión de «j\'ai» (U2). Y «regarder» es mirar, no guardar; «rentrer» es volver a casa, no entrar. Dos falsos amigos para la colección.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'le matin — la mañana · l\'après-midi — la tarde · le soir — la noche (temprana) · la nuit — la noche'],
                    ['es' => 'aujourd\'hui — hoy · demain — mañana · toujours — siempre · jamais — nunca'],
                    ['es' => 'lundi, mardi, mercredi, jeudi, vendredi, samedi, dimanche — de lunes a domingo, sin mayúscula'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Un día de Sofía'], 'pasos' => [
                    ['texto' => ['es' => 'Le matin, j\'étudie à l\'école.  (Por la mañana estudio en el colegio.)']],
                    ['texto' => ['es' => 'L\'après-midi, je joue avec mon frère.  (Por la tarde juego con mi hermano.)']],
                    ['texto' => ['es' => 'Le soir, je regarde la télé et j\'écoute de la musique.  (Por la noche veo la tele y escucho música.)']],
                    ['texto' => ['es' => 'Le samedi, je ne travaille jamais.  (Los sábados no trabajo nunca.)']],
                    ['texto' => ['es' => 'Fíjate en «ne… jamais»: la negación francesa va en dos piezas, una delante del verbo y otra detrás. «Ne… pas» es «no»; «ne… jamais» es «nunca».']],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y el sonido de esta unidad: la liaison. Una consonante final que normalmente es muda VUELVE A SONAR cuando la palabra siguiente empieza por vocal. Es lo que hace que el francés hablado no tenga cortes entre palabras.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'les amis → «lezamí» (la s de les, muda sola, suena como z delante de amis)'],
                    ['es' => 'deux heures → «deuzeur»'],
                    ['es' => 'vous avez → «vuzavé»'],
                    ['es' => 'un ami → «ãnamí» (la n de un, nasal sola, suena delante de ami)'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «les amis» como «le amí», con un corte en medio. Sin la liaison se entiende, pero suena a leer palabra por palabra. Y al revés, la trampa: NO hay liaison delante de h aspirada ni entre un sustantivo singular y lo que sigue — pero eso es de A2. Por ahora: artículo o pronombre + vocal → enlaza.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'La liaison casi siempre suena como Z (les amis, deux heures, vous avez) o como N (un ami, mon oncle). Si dudas de si hay liaison, pregúntate: ¿la palabra de antes es un artículo, un número o un pronombre? Si sí, enlaza.']],
            ],
        ],

        // ============ FR U3 · A1.IO.2 · la hora ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.IO.2', 'slug' => 'quelle-heure',
            'titulo' => ['es' => 'Quelle heure est-il ?'],
            'resumen' => ['es' => 'Preguntar y decir la hora, y quedar a una hora. Corto, porque los números ya los tienes.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'La hora en francés se dice siempre con la misma fórmula, en singular, y con la palabra «heures» que nunca se cae.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Quelle heure est-il ? — ¿Qué hora es?'],
                    ['es' => 'Il est huit heures. — Son las ocho.'],
                    ['es' => 'Il est une heure. — Es la una. (la única con «une» y «heure» en singular)'],
                    ['es' => 'Il est midi. / Il est minuit. — Es mediodía. / Es medianoche.'],
                    ['es' => 'Il est dix heures et demie. — Son las diez y media.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Al revés que en español e italiano: la hora en francés es SIEMPRE «il est», en singular, aunque sean las ocho. «Il est huit heures», no «ils sont». Ese «il» no es «él»: es un sujeto vacío, como el «it» del inglés.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Quedar a una hora'], 'pasos' => [
                    ['texto' => ['es' => '— À quelle heure tu manges ?  (¿A qué hora comes?)']],
                    ['texto' => ['es' => '— Je mange à midi.  (Como a mediodía.)']],
                    ['texto' => ['es' => '— À quelle heure tu rentres ?  (¿A qué hora vuelves?)']],
                    ['texto' => ['es' => '— Je rentre à cinq heures.  (Vuelvo a las cinco.)']],
                    ['texto' => ['es' => 'Y oye la liaison de la unidad: «cinq heures» → «sãkeur», «deux heures» → «deuzeur», «huit heures» → «uiteur».']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «huit heures» como dos palabras separadas, «uí eur». La t de huit, muda sola, suena delante de heures. Es la liaison en su hábitat natural: casi todas las horas la llevan.']],
            ],
        ],

        // ============ FR U4 · A1.PO.2 · lo que me gusta: aimer + le/la/les ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.PO.2', 'slug' => 'j-aime',
            'titulo' => ['es' => 'J\'aime !'],
            'resumen' => ['es' => 'Decir qué te gusta y por qué. En francés «gustar» funciona al revés que en español, y eso lo simplifica. Y dos vocales que el español confunde: u y ou.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En español «me gusta la pizza» es la pizza la que gusta. En francés no: «j\'aime la pizza» es YO amo/quiero la pizza. Aimer se conjuga como cualquier verbo en -er, con el sujeto normal, y eso te ahorra el lío de piace / piacciono del italiano.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'j\'aime — me gusta · tu aimes — te gusta · il / elle aime — le gusta'],
                    ['es' => 'j\'aime beaucoup — me gusta mucho · je n\'aime pas — no me gusta'],
                    ['es' => 'tu aimes… ? — ¿te gusta…?'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Lo que sí cambia respecto al español: después de aimer va SIEMPRE el artículo definido, aunque hables en general. «J\'aime le football», «j\'aime les chiens», «j\'aime la musique». Nunca «j\'aime football». El español también lo hace (me gusta EL fútbol), así que no te costará — pero no te lo saltes.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir por qué'], 'pasos' => [
                    ['texto' => ['es' => '— Tu aimes le football ?  (¿Te gusta el fútbol?)']],
                    ['texto' => ['es' => '— Oui, j\'aime beaucoup, parce que c\'est génial.  (Sí, me gusta mucho, porque es genial.)']],
                    ['texto' => ['es' => '— Et l\'école ?']],
                    ['texto' => ['es' => '— Je n\'aime pas beaucoup. C\'est intéressant mais c\'est difficile.']],
                    ['texto' => ['es' => 'Seis adjetivos para opinar de todo: génial / nul (genial / malísimo), bon (bueno, de comida), intéressant / ennuyeux (interesante / aburrido), facile / difficile (fácil / difícil).']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Poner el «ne» sin el «pas», o el «pas» sin el «ne». La negación francesa son DOS palabras que abrazan el verbo: je N\'aime PAS. En la calle los franceses se comen el ne al hablar («j\'aime pas»), pero escrito y en clase van los dos.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'la musique · le sport · le football · le livre — el libro · le film · le cinéma'],
                    ['es' => 'la pizza · la glace — el helado · le café · le chien — el perro · le chat — el gato'],
                    ['es' => 'chanter — cantar · danser — bailar · lire — leer (solo en infinitivo por ahora: j\'aime lire)'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: dos vocales que en español son una sola. La u francesa y la ou francesa son dos letras distintas y dos sonidos distintos, y confundirlas cambia la palabra.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'OU → la u española de siempre: nous, vous, tout, bonjour, écouter.'],
                    ['es' => 'U → un sonido que el español no tiene: pon la boca como para decir «u» y di «i». Sale «ü»: tu, musique, salut, une, étudier.'],
                    ['es' => 'La pareja que lo demuestra: «tu» (tú) y «tout» (todo). Si dices las dos igual, has dicho «todo» dos veces.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «tu», «musique» y «salut» con la u española. Es el acento hispanohablante más reconocible en francés, más que las nasales. Cada vez que veas una u sola (sin o delante), boca de u, lengua de i.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Practica con «tu» y «tout» diez veces seguidas, alternando: tü-tu-tü-tu. Si la boca cambia de forma entre una y otra, lo estás haciendo bien. Si no cambia, las dos son «tout».']],
            ],
        ],

        // ============ FR U4 · A1.EE.1 · escribir una nota corta ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.EE.1', 'slug' => 'un-petit-mot',
            'titulo' => ['es' => 'Escribir una nota de tres líneas'],
            'resumen' => ['es' => 'Un mensaje corto en francés tiene una forma fija. Aprendértela te evita los errores de siempre.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Con lo que sabes ya puedes escribir una nota real: saludar, decir quién eres y qué te gusta, y despedirte. Tres piezas, siempre las mismas.']],

                ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                    ['es' => 'Saludo con nombre: «Salut Marc !» (amigo) o «Bonjour madame» (adulto, de vous).'],
                    ['es' => 'Dos o tres frases cortas. Una idea por frase. Con punto. Y CON el pronombre en cada una.'],
                    ['es' => 'Despedida y tu nombre: «À bientôt, Sofía.» (hasta pronto) o «Bises, Sofía.» (besos, solo con amigos).'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una nota completa'], 'pasos' => [
                    ['texto' => ['es' => 'Salut Anna !']],
                    ['texto' => ['es' => 'Je m\'appelle Sofía et je suis équatorienne. J\'aime beaucoup la musique et j\'aime les chiens. Je n\'aime pas le football.']],
                    ['texto' => ['es' => 'À bientôt, Sofía.']],
                    ['texto' => ['es' => 'Cuatro frases, ninguna de más de nueve palabras, y en todas hay un je. Así se escribe en A1.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Escribir la frase larga que escribirías en español, con «que» y «cuando». Todavía no tienes esas piezas y la frase se rompe. Dos frases cortas dicen lo mismo y salen bien.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Antes de mandar la nota, tres revisiones de diez segundos: ¿cada frase tiene su pronombre? ¿cada «tu» lleva su -s? ¿cada negación tiene ne Y pas? Con eso cazas la mitad de los errores del curso.']],
            ],
        ],

        // ============ FR U5 · A1.CE.3 · orientarse: il y a, à / en, la e muda ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.CE.3', 'slug' => 'en-ville',
            'titulo' => ['es' => 'Pardon, où est la gare ?'],
            'resumen' => ['es' => 'Preguntar dónde está algo, entender la respuesta, decir qué hay en tu barrio. Y la e que se escribe y no se dice.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En una ciudad francesa necesitas dos cosas: preguntar dónde está algo (de vous, porque es a un desconocido) y entender seis palabras de respuesta.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Preguntar por un sitio'], 'pasos' => [
                    ['texto' => ['es' => '— Pardon, madame, où est la gare ?  (Perdone, señora, ¿dónde está la estación?)']],
                    ['texto' => ['es' => '— C\'est là, à droite.  (Está allí, a la derecha.)']],
                    ['texto' => ['es' => '— Il y a une pharmacie près d\'ici ?  (¿Hay una farmacia cerca de aquí?)']],
                    ['texto' => ['es' => '— Oui, rue de la Paix. Tout droit, puis à gauche.  (Sí, en la calle de la Paz. Recto, luego a la izquierda.)']],
                    ['texto' => ['es' => 'Fíjate en «Pardon, madame»: a un desconocido se le pone el tratamiento. Pardon monsieur, pardon madame. Sin eso, la pregunta suena brusca.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'à droite — a la derecha · à gauche — a la izquierda · tout droit — recto'],
                    ['es' => 'ici — aquí · là — allí · près — cerca · loin — lejos · puis — luego'],
                    ['es' => 'la gare — la estación · la rue — la calle · la place — la plaza · l\'hôpital · la pharmacie'],
                    ['es' => 'le supermarché · la banque · l\'église — la iglesia · le musée · le parc · le café · le restaurant'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El punto de gramática es «hay»: il y a. Y aquí el francés es más fácil que el italiano: il y a NO CAMBIA. Una cosa o cien, siempre il y a.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Il y a une pharmacie rue de la Paix. — Hay una farmacia en la calle de la Paz.'],
                    ['es' => 'Il y a deux cafés sur la place. — Hay dos cafés en la plaza.'],
                    ['es' => 'Il n\'y a pas d\'hôpital près d\'ici. — No hay hospital cerca de aquí.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Las preposiciones de lugar: «à» con ciudades (à Quito, à Paris), «en» con países femeninos (en France, en Équateur) y «au» con países masculinos (au Pérou, au Canada). Con calles, nada: «rue de la Paix», sin preposición. Y «il y a» se pronuncia «iliá», de un tirón.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «en Quito» o «en Paris» como en español. Con ciudades es «à»: à Quito, à Paris, à Lyon. «En» es para países. Es de los errores que más se repiten porque el español dice «en» para todo.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y el sonido de esta unidad, que en realidad es una ausencia: la e muda. Una e sin acento al final de palabra NO SE PRONUNCIA. Y muchas en medio de palabra, tampoco.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'rue → «rü». place → «plas». gauche → «gosh». pharmacie → «farmasí».'],
                    ['es' => 'La e final solo hace una cosa: hacer sonar la consonante de antes. «grand» → «grã» (d muda) pero «grande» → «grãd» (la e hace sonar la d, y luego se calla).'],
                    ['es' => 'Con acento SÍ suena: musée → «müzé», église → «egliz», café → «kafé».'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pronunciar «place» como «plase» o «rue» como «rue», con la e sonando como en español. La e final francesa es un signo de escritura, no un sonido. Si la dices, has añadido una sílaba que no existe.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Junta las dos reglas de pronunciación que ya tienes: la consonante final es muda (U1) SALVO que le siga una e muda, que la hace sonar y luego se calla ella. «petit» → «petí»; «petite» → «petít». Con eso lees bien el 90 % del francés.']],
            ],
        ],

        // ============ FR U5 · A1.PO.1 · mi ciudad ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.PO.1', 'slug' => 'ma-ville',
            'titulo' => ['es' => 'Ma ville'],
            'resumen' => ['es' => 'Describir tu barrio con lo que hay y lo que no hay. Tu primera descripción de un lugar.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Con il y a, diez lugares y cuatro adjetivos ya describes tu barrio entero.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El barrio de Sofía'], 'pasos' => [
                    ['texto' => ['es' => 'J\'habite à Quito, rue Amazonas.']],
                    ['texto' => ['es' => 'Près d\'ici, il y a un parc très joli et il y a deux supermarchés.']],
                    ['texto' => ['es' => 'Il n\'y a pas de musée, mais il y a une grande place.']],
                    ['texto' => ['es' => 'L\'école est loin : elle est dans une autre rue.']],
                    ['texto' => ['es' => 'Cuatro frases, y ya sabes dónde vive, qué tiene cerca, qué le falta y que el colegio le queda lejos.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Después de una negación, «un / une / des» se convierten en «de»: il y a UN musée → il n\'y a pas DE musée. Es una regla pequeña que un francés nota al instante cuando falta.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Alterna: una cosa que hay, una que no hay, una que hay. «Il n\'y a pas de… mais il y a…» es la estructura que más natural suena y menos errores produce.']],
            ],
        ],

        // ============ FR U6 · A1.IO.2 · pedir en un café: je voudrais, partitivos, la r ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.IO.2', 'slug' => 'au-cafe',
            'titulo' => ['es' => 'Au café : je voudrais un croissant'],
            'resumen' => ['es' => 'Pedir algo de comer o beber, decir cuánto, y pagar. Y la r francesa, que sale de la garganta.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Pedir en Francia se hace con una fórmula que no tienes que conjugar todavía: «je voudrais», que significa «quisiera». Je voudrais + lo que quieras + s\'il vous plaît. Y antes de todo eso, bonjour.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'En el café'], 'pasos' => [
                    ['texto' => ['es' => '— Bonjour ! Je voudrais un café et un croissant, s\'il vous plaît.']],
                    ['texto' => ['es' => '— Tout de suite. Et avec ça ?  (Enseguida. ¿Algo más?)']],
                    ['texto' => ['es' => '— Non, merci. Ça fait combien ?  (No, gracias. ¿Cuánto es?)']],
                    ['texto' => ['es' => '— Trois euros cinquante.']],
                    ['texto' => ['es' => 'Con je voudrais, ça fait combien y los números, sobrevives en cualquier café de Francia. Lo demás es vocabulario.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'le pain — el pan · l\'eau — el agua · le lait — la leche · le fromage — el queso'],
                    ['es' => 'la viande — la carne · le poisson — el pescado · les fruits · les légumes — las verduras · les œufs — los huevos'],
                    ['es' => 'le petit-déjeuner — el desayuno · le déjeuner — el almuerzo · le dîner — la cena'],
                    ['es' => 'le sucre — el azúcar · le sel — la sal · l\'addition — la cuenta'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El punto de gramática: cómo decir «un poco de». El francés usa el partitivo, que es de + artículo, y suena como una sola palabra.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'du (de + le) → du pain, du fromage'],
                    ['es' => 'de la → de la viande, de la salade'],
                    ['es' => 'de l\' (delante de vocal) → de l\'eau'],
                    ['es' => 'des (de + les) → des fruits, des œufs'],
                    ['es' => 'Je voudrais du pain et de la viande. — Quisiera pan y carne.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pedir sin partitivo, como en español: «je voudrais pain». En francés eso no es una frase. Con cosas que no se cuentan (pan, agua, carne) va du / de la / des; con cosas que se cuentan de una en una va un / une: un café, une pizza. Y tras negación, todo se vuelve «de»: je ne veux pas DE pain.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad es el más famoso del francés: la r. No se hace con la punta de la lengua como en español, sino en la garganta, casi donde haces gárgaras.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Está en: croissant, fromage, restaurant, merci, bonjour, au revoir, voudrais.'],
                    ['es' => 'Cómo encontrarla: di la j española de «jamón» y suavízala hasta que vibre un poco. Eso es la r francesa.'],
                    ['es' => 'Al final de palabra es suave, casi un soplo: «bonjour» termina en un roce, no en una vibración.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Usar la r española, vibrante, en «merci» o «croissant». Se entiende perfectamente, pero es el segundo acento más reconocible después de la u. Una r española en «au revoir» suena a turista en la primera sílaba.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'No te obsesiones con la r: un francés te entiende igual con la española. Trabaja primero la u y las nasales, que sí cambian el significado. La r es cosmética; llega sola con los meses.']],
            ],
        ],

        // ============ FR U6 · A1.CE.1 · leer una carta y un cartel ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.CE.1', 'slug' => 'le-menu',
            'titulo' => ['es' => 'Leer un menú y un cartel'],
            'resumen' => ['es' => 'Un menú francés se lee en dos minutos si sabes su orden. Y cuatro carteles que verás en cualquier puerta.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Un menú francés tiene un orden fijo: entrées (entrantes), plats (platos principales), desserts (postres), boissons (bebidas). Y muchos restaurantes ofrecen un «menu» o «formule» a precio cerrado: entrada + plato, o plato + postre.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Un trozo de carta'], 'pasos' => [
                    ['texto' => ['es' => 'PLATS · Poulet rôti — 12 € · Poisson du jour — 15 €']],
                    ['texto' => ['es' => 'DESSERTS · Crème brûlée — 6 € · Glace — 4 €']],
                    ['texto' => ['es' => 'BOISSONS · Eau — 2 € · Café — 1,50 €']],
                    ['texto' => ['es' => '«Poulet» es pollo, «rôti» es asado, «poisson» es pescado (¡no poison!). Lo que no entiendas: «Qu\'est-ce que c\'est ?» (¿qué es?).']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'OUVERT — abierto · FERMÉ — cerrado'],
                    ['es' => 'ENTRÉE — entrada · SORTIE — salida (esta ya la conoces)'],
                    ['es' => 'POUSSEZ — empujar · TIREZ — tirar (de la puerta)'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Leer «TIREZ» y empujar, porque en español tirar algo es lanzarlo. Tirez es tirar HACIA TI. Y «poisson» (pescado) se parece a «poison» (veneno) pero tiene dos s: la doble s suena sorda, la s sola entre vocales suena como z. Poisson / poison: la diferencia es una letra y una comida.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'En Francia el agua del grifo es gratis en cualquier restaurante: pide «une carafe d\'eau» y no te cobran. Y el pan viene solo, sin pedirlo. Dos cosas que un turista paga y un francés no.']],
            ],
        ],

        // ============ FR U7 · A1.IO.2 · comprar y el tiempo: el adjetivo, é / è ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.IO.2', 'slug' => 'combien-ca-coute',
            'titulo' => ['es' => 'Ça coûte combien ? Quel temps fait-il ?'],
            'resumen' => ['es' => 'Comprar ropa, preguntar precios, hablar del tiempo. Con eso llega el adjetivo francés —dónde va y cómo concuerda— y dos acentos que suenan distinto.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'La ropa tiene colores, los colores son adjetivos, y el adjetivo francés tiene dos reglas que el español solo tiene a medias: concuerda (como en español) y va casi siempre DETRÁS del nombre (como en español), pero unos pocos van delante, y esos son justo los más usados.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'la robe — el vestido · le t-shirt — la camiseta · le pantalon — el pantalón (¡singular!)'],
                    ['es' => 'les chaussures — los zapatos · la veste — la chaqueta · le chapeau — el sombrero · le sac — el bolso'],
                    ['es' => 'rouge, jaune, noir, blanc, vert, bleu — rojo, amarillo, negro, blanco, verde, azul'],
                    ['es' => 'cher / pas cher — caro / barato · la taille — la talla · grand / petit — grande / pequeño'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Cómo concuerda un adjetivo'], 'pasos' => [
                    ['texto' => ['es' => 'Femenino: se añade -e. vert → verte, grand → grande, petit → petite, noir → noire.']],
                    ['texto' => ['es' => 'Plural: se añade -s. vert → verts, verte → vertes.']],
                    ['texto' => ['es' => 'Si ya termina en -e, el femenino no cambia: rouge → rouge, jaune → jaune.']],
                    ['texto' => ['es' => 'Y lo importante al oído: la -e del femenino HACE SONAR la consonante final. «vert» → «ver»; «verte» → «vert». «petit» → «petí»; «petite» → «petít». El plural -s no se oye nunca.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Dónde va: los colores y casi todos los adjetivos, DETRÁS: une robe rouge, un sac noir. Pero grand, petit, bon, joli y beau van DELANTE: une grande place, un petit café, un bon restaurant. Son pocos y son los que más usas.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Poner el color delante («une rouge robe») o poner «grand» detrás («une place grande»). El español pone casi todo detrás y por eso el «grand» delante despista. Apréndete los cinco de delante como excepciones: grand, petit, bon, joli, beau.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'En la tienda'], 'pasos' => [
                    ['texto' => ['es' => '— Ça coûte combien, ce t-shirt ?  (¿Cuánto cuesta esta camiseta?)']],
                    ['texto' => ['es' => '— Quinze euros.']],
                    ['texto' => ['es' => '— C\'est un peu cher. Vous avez la taille M ?  (Es un poco caro. ¿Tiene la talla M?)']],
                    ['texto' => ['es' => '— Oui, en noir et en blanc.']],
                    ['texto' => ['es' => 'En una tienda se habla de vous, siempre: «vous avez», no «tu as».']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Quel temps fait-il ? — ¿Qué tiempo hace?'],
                    ['es' => 'Il fait chaud. / Il fait froid. — Hace calor. / Hace frío.'],
                    ['es' => 'Il y a du soleil. / Il pleut. / Il fait gris. — Hay sol. / Llueve. / Está nublado.'],
                    ['es' => 'l\'été, l\'hiver, le printemps, l\'automne — verano, invierno, primavera, otoño'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: los dos acentos de la e. En español el acento marca dónde cae la fuerza; en francés marca CÓMO SUENA la vocal. é y è son dos vocales distintas.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'é (agudo) → e cerrada, como la e española pero con la boca más cerrada: été, café, marché, équatorien.'],
                    ['es' => 'è (grave) y ê → e abierta, con la boca más abierta, tirando a la «e» de «perro»: père, mère, très, frère, fête.'],
                    ['es' => 'La pareja: «été» (verano) y «était» (era). Cerrada y abierta.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pronunciar «père» y «été» con la misma e. Un francés lo oye al instante. La regla práctica: é → sonríe un poco al decirla; è → abre la boca. «Mon père» con la boca abierta; «l\'été» con sonrisa.']],
            ],
        ],

        // ============ FR U8 · A1.PO.2 · contar lo que hice: passé composé, enchaînement ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.PO.2', 'slug' => 'hier',
            'titulo' => ['es' => 'Hier, j\'ai mangé une pizza'],
            'resumen' => ['es' => 'Contar lo que hiciste ayer o el fin de semana. Un solo tiempo del pasado, con dos piezas que ya tienes.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para contar algo que pasó, el francés usa el passé composé. Se monta con avoir (U2) y una forma nueva del verbo que, para los verbos en -er, es la más fácil del idioma.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'La máquina del pasado'], 'pasos' => [
                    ['texto' => ['es' => 'Pieza 1: avoir en presente. j\'ai, tu as, il a.']],
                    ['texto' => ['es' => 'Pieza 2: el participio. Para los verbos en -er se quita -er y se pone -é: manger → mangé, étudier → étudié, parler → parlé.']],
                    ['texto' => ['es' => 'Se juntan: J\'ai mangé. (He comido / comí.) Tu as étudié ? (¿Estudiaste?) Il a parlé. (Habló.)']],
                    ['texto' => ['es' => 'Y aquí viene lo bonito: «mangé» y «manger» SE PRONUNCIAN IGUAL («manyé»). La diferencia es solo escrita. Al hablar, lo que marca el pasado es el «ai», «as», «a» de delante.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Escribir «j\'ai manger» con -er, porque suena igual que «mangé». Regla para no fallar: detrás de avoir va SIEMPRE -é. «J\'ai mangé», «tu as parlé». Si puedes sustituir el verbo por «fait» (hecho) y la frase sigue teniendo sentido, es participio y va con -é.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Ese tiempo sirve para las dos cosas que el español separa: «he comido» Y «comí». Hier, j\'ai mangé = ayer comí. Aujourd\'hui, j\'ai mangé = hoy he comido. Un solo tiempo, menos que aprender.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'hier — ayer · hier soir — anoche · la semaine dernière — la semana pasada · le week-end — el fin de semana'],
                    ['es' => 'd\'abord — primero · puis / après — luego / después · déjà — ya · encore — todavía'],
                    ['es' => 'avec — con · ensemble — juntos · les amis — los amigos · la fête — la fiesta'],
                    ['es' => 'la mer — el mar · la montagne — la montaña · le voyage — el viaje'],
                    ['es' => 'acheter — comprar · visiter — visitar · téléphoner — llamar por teléfono'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El fin de semana de Marc'], 'pasos' => [
                    ['texto' => ['es' => 'Samedi, j\'ai étudié le matin.  (El sábado estudié por la mañana.)']],
                    ['texto' => ['es' => 'Puis j\'ai joué au football avec les amis.  (Luego jugué al fútbol con los amigos.)']],
                    ['texto' => ['es' => 'Dimanche, j\'ai visité ma grand-mère et j\'ai très bien mangé.  (El domingo visité a mi abuela y comí muy bien.)']],
                    ['texto' => ['es' => 'Cuatro verbos en pasado, todos con la misma pieza: ai + -é. Con eso cuentas un fin de semana entero.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Usar «je suis» con estos verbos: «je suis mangé». Con los verbos de esta unidad el pasado va SIEMPRE con avoir. Hay otros —«je suis allé» (fui)— que van con être y tienen su regla, pero los verás el año que viene. Por ahora: avoir + -é.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y el sonido de esta unidad, que ya no es un sonido sino la forma de hilar: el enchaînement. Es primo de la liaison (U3), pero con consonantes que YA sonaban. La consonante final de una palabra se pega a la vocal de la siguiente, sin corte.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'il a → «i-la» (la l de il se va con la a)'],
                    ['es' => 'elle a mangé → «e-la-mangé»'],
                    ['es' => 'avec un ami → «ave-kun-ami»'],
                    ['es' => 'quatre heures → «ka-treur»'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'El español hace lo mismo —«el amigo» se dice «e-lamigo»—, así que esto te sale solo si no te frenas. El error viene de LEER: al ver «il a» como dos palabras metes un corte que al hablar no existe. Lee en voz alta como si hablaras, y deja que las sílabas se recoloquen solas.']],
            ],
        ],

        // ============ FR U9 · A1.IO.1 · reparar la conversación (repaso y proyecto) ============
        [
            'lengua' => 'fr', 'descriptor' => 'A1.IO.1', 'slug' => 'je-n-ai-pas-compris',
            'titulo' => ['es' => 'Je n\'ai pas compris : cómo no perder una conversación'],
            'resumen' => ['es' => 'La unidad 9 no trae gramática nueva. Trae lo único que te falta para hablar dos minutos seguidos con un francés: saber qué decir cuando no entiendes. Y en francés, que se habla rápido, esto es más importante que en cualquier otra lengua del curso.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En A1 vas a entender la mitad de lo que te digan, y en francés puede que menos: se habla rápido y con las palabras enlazadas (U3, U8). Eso está previsto: el descriptor del MCER dice literalmente «siempre que la otra persona repita o hable más despacio». Lo que separa a un alumno que conversa de uno que se bloquea es si sabe pedir ayuda sin salirse del francés.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Pardon ? — ¿Cómo? (la más corta; sube el tono al final)'],
                    ['es' => 'Je n\'ai pas compris. — No he entendido.'],
                    ['es' => 'Vous pouvez répéter, s\'il vous plaît ? — ¿Puede repetir, por favor?'],
                    ['es' => 'Plus lentement, s\'il vous plaît. — Más despacio, por favor.'],
                    ['es' => 'Qu\'est-ce que ça veut dire, « … » ? — ¿Qué significa «…»?'],
                    ['es' => 'Comment on dit « … » en français ? — ¿Cómo se dice «…» en francés?'],
                    ['es' => 'D\'accord. / Bien sûr. / Un moment. — Vale. / Claro. / Un momento.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Apréndete estas siete frases como si fueran una sola palabra cada una. No se analizan, se disparan. «Je n\'ai pas compris» es un passé composé con negación (U8 + U4), pero no hace falta que lo sepas para usarlo: es lo que dices cuando te quedas en blanco, y te devuelve al francés en vez de al español.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una conversación con reparación'], 'pasos' => [
                    ['texto' => ['es' => '— Salut ! Tu es d\'où ?']],
                    ['texto' => ['es' => '— Je suis de Quito. Et toi ?']],
                    ['texto' => ['es' => '— De Lyon. Qu\'est-ce que tu étudies à l\'école ?']],
                    ['texto' => ['es' => '— Pardon ? Plus lentement, s\'il te plaît.']],
                    ['texto' => ['es' => '— Qu\'est-ce… que… tu… étudies… à… l\'école ?']],
                    ['texto' => ['es' => '— Ah ! J\'étudie le français et les maths. J\'aime beaucoup le français.']],
                    ['texto' => ['es' => 'Fíjate en «s\'il te plaît»: con un amigo, de tu. Con un adulto, «s\'il vous plaît». La alumna no entendió una pregunta entera, lo dijo en francés, la otra persona repitió, y la conversación siguió. Eso es exactamente el A1.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pasarse al español —o al inglés— en cuanto algo no se entiende. Cada vez que lo haces, la conversación en francés se acaba. «Pardon ?» cuesta lo mismo y la mantiene viva.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El proyecto final de esta unidad es una conversación de dos minutos con el interlocutor del curso, sobre lo que quieras de las ocho unidades: quién eres, tu familia, tu día, lo que te gusta, tu barrio, qué comes, qué ropa llevas, qué hiciste ayer. Vas a usar las siete frases de arriba al menos una vez. Si no te hacen falta, es que la conversación fue demasiado fácil.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Antes del proyecto, repasa las ocho cosas: las finales mudas (U1), las nasales sin n (U2), la -s de tu y la liaison (U3), ne… pas y la u (U4), il y a y la e muda (U5), du / de la / des (U6), el adjetivo delante o detrás (U7) y j\'ai + -é (U8). Si las tienes, tienes el A1.']],
            ],
        ],

        // ================================================================
        // ======================== ALEMÁN · A1 ============================
        // ================================================================

        // ============ DE U1 · A1.CO.2 · saludar, du / Sie ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.CO.2', 'slug' => 'hallo',
            'titulo' => ['es' => 'Hallo! Saludar en alemán (y a quién tratar de Sie)'],
            'resumen' => ['es' => 'Los saludos, la decisión du / Sie, y la primera regla de lectura: ei y ie se leen al revés de lo que parece.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'El alemán tiene dos «tú»: du para amigos, familia y gente de tu edad, y Sie para cualquier adulto que no conozcas — con mayúscula siempre, para distinguirlo de sie (ella). Un alumno de quince años trata de Sie a todos sus profesores, sin excepción.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Hallo — hola (sirve casi siempre) · Guten Morgen — buenos días (hasta las 10 o 11)'],
                    ['es' => 'Guten Tag — buenos días / buenas tardes (formal, todo el día) · Guten Abend — buenas tardes / noches'],
                    ['es' => 'Tschüss — adiós (informal) · Auf Wiedersehen — adiós (formal)'],
                    ['es' => 'Danke — gracias · Bitte — por favor Y de nada · Entschuldigung — perdón'],
                    ['es' => 'ja / nein — sí / no · Herr / Frau — señor / señora'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Bitte» hace tres trabajos: por favor, de nada, y «¿cómo?» cuando no has entendido. Si alguien te dice danke y tú dices bitte, has dicho «de nada». Es la palabra más rentable del alemán.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «Tschüss» o «Hallo» a un profesor. Con Sie van «Guten Tag» y «Auf Wiedersehen». Y «Entschuldigung» se dice entero aunque sea largo: «ent-SHUL-di-gung». Si te sale, ya has dicho la palabra más difícil de la unidad.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'La regla de lectura de esta unidad, que es la más fácil de aprender y la que más se falla: EI y IE se pronuncian al revés de como se escriben para un hispanohablante.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'EI → suena «ai»: nein (nain), mein (main), heißen (jaisen), Wiedersehen — no, esa es ie.'],
                    ['es' => 'IE → suena «i» larga: Wiedersehen (VI-der-sehen), sie (si), Sie (si), wie (vi).'],
                    ['es' => 'Truco: se pronuncia la SEGUNDA letra, larga. ei → i…no, ¡«ai»! Mejor: ei = «ai» como en «aire»; ie = «ii».'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Leer «nein» como «nein» a la española (ne-in) o «sie» como «sie» (si-e). Nein es «nain» y sie es «si». Es de los pocos casos en que el alemán engaña al leer, y aparece en las primeras diez palabras del curso.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Fuera de ei / ie, el alemán se lee casi como se escribe, más que el francés y casi tanto como el italiano. Una vez pasas esta trampa, leer alemán es honesto: lo que ves es lo que suena.']],
            ],
        ],

        // ============ DE U1 · A1.IO.3 · presentarse: sein y el verbo en segunda posición ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.IO.3', 'slug' => 'ich-heisse',
            'titulo' => ['es' => 'Ich heiße Sofía'],
            'resumen' => ['es' => 'Decir tu nombre, de dónde eres y presentar a alguien. Y la regla que gobierna TODO el alemán: el verbo va en segunda posición.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para presentarte necesitas tres frases fijas y el verbo sein (ser/estar). Pero antes, la única regla de gramática del alemán que vas a usar en cada frase del año.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'EL VERBO VA EN SEGUNDA POSICIÓN. Siempre. Sea lo que sea lo primero —el sujeto, un «hoy», un «en Quito»—, el verbo conjugado es la segunda pieza de la frase. «Ich bin Sofía» (yo-soy-Sofía). «Heute bin ich müde» (hoy-soy-yo-cansada): al poner «heute» primero, el sujeto salta detrás del verbo para que el verbo siga siendo el segundo. Esto no es una excepción: es EL alemán.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Presentarse y preguntar el nombre'], 'pasos' => [
                    ['texto' => ['es' => '— Wie heißt du?  (¿Cómo te llamas?)']],
                    ['texto' => ['es' => '— Ich heiße Sofía.  (Me llamo Sofía.)']],
                    ['texto' => ['es' => 'De otra persona: «Er heißt Marco» / «Sie heißt Anna».']],
                    ['texto' => ['es' => 'A un adulto, de Sie: «Wie heißen Sie?». Apréndete las dos preguntas enteras.']],
                    ['texto' => ['es' => 'La ß se lee como una s fuerte: «haise». Y ojo: el «Sie» mayúscula (usted) y el «sie» minúscula (ella) suenan igual — la mayúscula es lo único que los separa por escrito.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ich bin — yo soy'],
                    ['es' => 'du bist — tú eres'],
                    ['es' => 'er ist / sie ist — él es / ella es'],
                    ['es' => 'Sie sind — usted es'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Como en francés, el pronombre NO se calla nunca: «ich bin», no «bin». Y «ich» se pronuncia con un sonido que no tienes: una j muy suave, como un gato que bufa bajito. Lo trabajamos en la U2; por ahora, que no suene como «ich» con ch española.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir de dónde eres'], 'pasos' => [
                    ['texto' => ['es' => '— Woher kommst du?  (¿De dónde vienes?)']],
                    ['texto' => ['es' => '— Ich komme aus Ecuador, aus Quito.  (Vengo de Ecuador, de Quito.)']],
                    ['texto' => ['es' => 'En alemán el origen se dice con «venir de» (kommen aus), no con «ser de». «Ich bin aus Quito» se entiende, pero «ich komme aus Quito» es lo natural.']],
                    ['texto' => ['es' => 'Y la nacionalidad: Ich bin Ecuadorianer (chico) / Ecuadorianerin (chica). Deutscher / Deutsche (alemán / alemana). El femenino añade -in casi siempre.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Poner el verbo donde lo pondría el español cuando la frase no empieza por el sujeto: «Heute ich bin müde». En alemán: «Heute BIN ich müde». Cada vez que arranques una frase con algo que no sea ich / du / er, para y comprueba: ¿el verbo es lo segundo? Si no, mueve el sujeto detrás.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Der erste Tag'], 'pasos' => [
                    ['texto' => ['es' => 'Marco — Hallo! Ich heiße Marco. Und du, wie heißt du?']],
                    ['texto' => ['es' => 'Sofía — Hallo, Marco. Ich heiße Sofía.']],
                    ['texto' => ['es' => 'Marco — Woher kommst du?']],
                    ['texto' => ['es' => 'Sofía — Ich komme aus Ecuador, aus Quito. Und du?']],
                    ['texto' => ['es' => 'Marco — Ich komme aus Berlin. Bist du Schülerin hier?']],
                    ['texto' => ['es' => 'Sofía — Ja. Entschuldigung… und wer ist das?']],
                    ['texto' => ['es' => 'Marco — Das ist Frau Müller, die Lehrerin.']],
                    ['texto' => ['es' => 'Sofía — Danke, Marco. Tschüss!']],
                    ['texto' => ['es' => 'Marco — Tschüss!']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Mira «Bist du Schülerin hier?»: en una pregunta de sí / no, el verbo va PRIMERO y el sujeto detrás. Es la misma regla vista desde otro lado: el verbo manda, y el sujeto se coloca donde el verbo le deja.']],
            ],
        ],

        // ============ DE U2 · A1.PO.1 · la familia, haben, los tres géneros, ch ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.PO.1', 'slug' => 'meine-familie',
            'titulo' => ['es' => 'Meine Familie'],
            'resumen' => ['es' => 'Hablar de tu familia y decir la edad. Con eso llegan haben, los TRES géneros del alemán, y los dos sonidos de la ch.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Antes de la familia, la noticia que hay que dar el primer día y no el último: el alemán tiene TRES géneros —masculino (der), femenino (die) y neutro (das)— y NO hay una regla fiable para saber cuál es cuál. Se aprende cada palabra con su artículo, como si fueran una sola palabra. Nunca «Tisch»: siempre «der Tisch».']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'der Vater / die Mutter — el padre / la madre (y en casa: Papa / Mama)'],
                    ['es' => 'der Bruder / die Schwester — el hermano / la hermana'],
                    ['es' => 'der Opa / die Oma — el abuelo / la abuela (Großvater / Großmutter si es formal)'],
                    ['es' => 'der Onkel / die Tante — el tío / la tía · der Sohn / die Tochter — el hijo / la hija'],
                    ['es' => 'das Kind — el niño, la niña (¡neutro!) · die Eltern — los padres (siempre plural)'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Das Kind» (el niño) y «das Mädchen» (la chica) son NEUTROS. No tiene lógica y no la busques: el género alemán es gramatical, no biológico. Por eso en este curso todos los ejercicios te piden la palabra CON su artículo, y el motor no perdona que falte.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ich habe — yo tengo'],
                    ['es' => 'du hast — tú tienes'],
                    ['es' => 'er hat / sie hat — él tiene / ella tiene'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir la edad'], 'pasos' => [
                    ['texto' => ['es' => '— Wie alt bist du?  (¿Cuántos años tienes? — literalmente «¿cómo de viejo eres?»)']],
                    ['texto' => ['es' => '— Ich bin fünfzehn Jahre alt.  (Tengo quince años.) O corto: Ich bin fünfzehn.']],
                    ['texto' => ['es' => 'AQUÍ el alemán se separa del español: la edad se ES, no se tiene. «Ich habe fünfzehn Jahre» es el error clásico del hispanohablante. Ich BIN fünfzehn.']],
                    ['texto' => ['es' => 'Del 1 al 20: eins, zwei, drei, vier, fünf, sechs, sieben, acht, neun, zehn, elf, zwölf, dreizehn, vierzehn, fünfzehn, sechzehn, siebzehn, achtzehn, neunzehn, zwanzig.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => '«Ich habe fünfzehn Jahre.» No. En alemán la edad va con sein: «Ich bin fünfzehn». Es el único de los cuatro idiomas del curso donde la edad no se tiene, y por eso es el error más repetido de la unidad.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: la CH, que en alemán tiene dos versiones según la vocal que va delante, y ninguna es la ch española.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ch después de a, o, u → «ach-Laut»: la j española de «jamón», fuerte: acht, Tochter, auch, Buch.'],
                    ['es' => 'ch después de e, i, ä, ö, ü, y de consonante → «ich-Laut»: una j suavísima, como un soplo con la lengua en posición de «i»: ich, nicht, Mädchen, München.'],
                    ['es' => 'La pareja: «acht» (ocho, con j fuerte) y «ich» (yo, con soplo suave).'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «ich» como «ich» con ch de «chico», o como «ik». Es «ij» con una j de gato que bufa, sin voz. Si te sale la j de jamón en «ich», estás usando la versión de «acht» donde va la de «ich». Suena a acento fuerte pero se entiende; lo que no se entiende es «ik».']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Para el ich-Laut: di «i» larga en un susurro y, sin mover la lengua, sopla. Ese roce es el sonido. Practícalo con «ich nicht» diez veces y lo tienes.']],
            ],
        ],

        // ============ DE U2 · A1.CE.2 · leer un mensaje breve ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.CE.2', 'slug' => 'eine-nachricht',
            'titulo' => ['es' => 'Leer un mensaje corto'],
            'resumen' => ['es' => 'Sacar lo que importa de una postal de cuatro líneas. Y una ventaja del alemán: los sustantivos llevan mayúscula, y eso te dice dónde mirar.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'El alemán te regala una pista de lectura que ningún otro idioma tiene: TODOS los sustantivos van con mayúscula. En un mensaje, las palabras con mayúscula en medio de la frase son las cosas y las personas. Con eso solo ya tienes el esqueleto.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una postal desde Berlín'], 'pasos' => [
                    ['texto' => ['es' => 'Hallo Marco!']],
                    ['texto' => ['es' => 'Ich bin in Berlin mit meiner Schwester und meiner Tante.']],
                    ['texto' => ['es' => 'Meine Oma kommt aus Berlin.']],
                    ['texto' => ['es' => 'Bis bald, Anna.']],
                    ['texto' => ['es' => 'Las mayúsculas: Berlin, Schwester, Tante, Oma. Ya sabes dónde está, con quién y quién es de allí. «Bis bald» es «hasta pronto».']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Subraya las mayúsculas de en medio de la frase, luego busca el verbo (siempre en segunda posición, U1). Con nombres y verbo tienes el 80 % del mensaje; lo demás son piezas pequeñas que se deducen.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Bloquearse con las palabras largas. «Entschuldigung», «Wiedersehen», «Großmutter» son palabras normales pegadas: Groß + Mutter = grande + madre = abuela. Cuando veas una palabra larga, busca la costura y córtala.']],
            ],
        ],

        // ============ DE U3 · A1.PO.2 · mi día: el presente, el verbo segundo, vocales largas y cortas ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.PO.2', 'slug' => 'mein-tag',
            'titulo' => ['es' => 'Mein Tag'],
            'resumen' => ['es' => 'Contar lo que haces cada día. Llega la conjugación regular, la regla del verbo segundo con un complemento delante, y la diferencia entre vocal larga y corta.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Los verbos regulares alemanes se conjugan con tres terminaciones en singular, y son las mismas para todos. Una vez que sabes una, sabes todas.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'lernen — aprender / estudiar · spielen — jugar · wohnen — vivir (en un sitio) · arbeiten — trabajar'],
                    ['es' => 'hören — oír / escuchar · kommen — venir · machen — hacer · kochen — cocinar'],
                    ['es' => 'gehen — ir (a pie) · trinken — beber'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Conjugar lernen en singular'], 'pasos' => [
                    ['texto' => ['es' => 'Se quita -en y queda la raíz: lern-.']],
                    ['texto' => ['es' => 'ich lern-e → lerne · du lern-st → lernst · er / sie lern-t → lernt']],
                    ['texto' => ['es' => 'Spielen: spiele, spielst, spielt. Wohnen: wohne, wohnst, wohnt. Sin sorpresas.']],
                    ['texto' => ['es' => 'La única trampa: si la raíz termina en -t o -d (arbeit-), se mete una e para poder pronunciarlo: du arbeitEst, er arbeitEt.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Olvidar la -st de «du»: «du lern». Se oye y se ve; no hay excusa como en francés. Du SIEMPRE -st: du lernst, du spielst, du wohnst.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'der Morgen — la mañana · der Nachmittag — la tarde · der Abend — la noche temprana · die Nacht — la noche'],
                    ['es' => 'heute — hoy · morgen — mañana (minúscula: es adverbio) · immer — siempre · nie — nunca'],
                    ['es' => 'Montag, Dienstag, Mittwoch, Donnerstag, Freitag, Samstag, Sonntag — de lunes a domingo, con mayúscula'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Un día de Sofía — y el verbo segundo en acción'], 'pasos' => [
                    ['texto' => ['es' => 'Am Morgen lerne ich in der Schule.  (Por la mañana estudio en el colegio.)']],
                    ['texto' => ['es' => 'Am Nachmittag spiele ich mit meinem Bruder.  (Por la tarde juego con mi hermano.)']],
                    ['texto' => ['es' => 'Am Abend höre ich Musik.  (Por la noche escucho música.)']],
                    ['texto' => ['es' => 'Am Samstag lerne ich nie.  (Los sábados no estudio nunca.)']],
                    ['texto' => ['es' => 'Mira las cuatro: empiezan con «Am…», y por eso el verbo va inmediatamente después y el «ich» SALTA detrás. «Am Morgen lerne ich», no «am Morgen ich lerne». Y las cuatro podrían decirse al revés: «Ich lerne am Morgen…». Las dos son correctas; solo cambia qué se pone primero.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Esa libertad es real y el motor la respeta: en los ejercicios de ordenar de alemán, casi siempre hay DOS o TRES órdenes correctos. Lo que nunca es correcto es el verbo en tercera posición.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: las vocales alemanas son largas o cortas, y cambia el significado. El español tiene una sola duración; el alemán tiene dos.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'LARGA cuando va seguida de h, de una sola consonante, o doblada: wohnen (woonen), Sohn (soon), Tee (tee).'],
                    ['es' => 'CORTA cuando va seguida de dos consonantes: kommen (kom-men), Mutter (mut-ter), bitte (bit-te).'],
                    ['es' => 'La pareja: «Stadt» (ciudad, a corta) y «Staat» (estado, a larga). Y «wohnen» (vivir, larga) frente a «kommen» (venir, corta).'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir todas las vocales cortas, a la española. «Wohnen» con o corta suena a otra palabra. Regla rápida: si ves una h detrás de la vocal, alárgala y NO pronuncies la h — está ahí solo para alargar.']],
            ],
        ],

        // ============ DE U3 · A1.IO.2 · la hora ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.IO.2', 'slug' => 'wie-spaet',
            'titulo' => ['es' => 'Wie spät ist es?'],
            'resumen' => ['es' => 'Preguntar y decir la hora, y quedar a una hora. Los números ya los tienes; esto es corto.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'La hora en alemán se dice con «es ist» (es) y la palabra «Uhr», que nunca se cae.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Wie spät ist es? / Wie viel Uhr ist es? — ¿Qué hora es?'],
                    ['es' => 'Es ist acht Uhr. — Son las ocho.'],
                    ['es' => 'Es ist ein Uhr. — Es la una. (¡ein, no eins!)'],
                    ['es' => 'Es ist halb neun. — Son las ocho y media. (¡«media hacia las nueve»!)'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Halb neun» NO son las nueve y media: son las OCHO y media. El alemán cuenta la media hora hacia la hora siguiente: halb neun = «a medio camino de las nueve». Es la trampa horaria más famosa del idioma y hace perder trenes de verdad. Si dudas, di «acht Uhr dreißig».']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Quedar a una hora'], 'pasos' => [
                    ['texto' => ['es' => '— Um wie viel Uhr isst du?  (¿A qué hora comes?)']],
                    ['texto' => ['es' => '— Ich esse um ein Uhr.  (Como a la una.)']],
                    ['texto' => ['es' => '— Um wie viel Uhr kommst du nach Hause?  (¿A qué hora vuelves a casa?)']],
                    ['texto' => ['es' => '— Ich komme um fünf Uhr.  (Vuelvo a las cinco.)']],
                    ['texto' => ['es' => '«Um» es la preposición de la hora: um acht Uhr, um ein Uhr. Y «nach Hause» es «a casa» (dirección); «zu Hause» es «en casa» (lugar). Dos que se confunden todo el año.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «es ist eins Uhr». Con «Uhr» detrás, el uno es «ein»: ein Uhr. Sin Uhr, es «eins»: Es ist eins. Y no confundas «Uhr» (la hora / el reloj) con «Stunde» (una hora de duración).']],
            ],
        ],

        // ============ DE U4 · A1.PO.2 · lo que me gusta: gern, mögen, y las diéresis ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.PO.2', 'slug' => 'ich-mag',
            'titulo' => ['es' => 'Ich mag Musik. Ich spiele gern Fußball.'],
            'resumen' => ['es' => 'Decir qué te gusta de dos maneras: una para cosas y otra para actividades. Y los tres sonidos con dos puntitos: ä, ö, ü.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'El alemán tiene dos formas de decir «me gusta», y se reparten el trabajo: «mögen» para cosas y personas, «gern» para actividades. Las dos son fáciles; lo difícil es no mezclarlas.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ich mag — me gusta (una cosa) · du magst · er / sie mag'],
                    ['es' => 'Ich mag Musik. Ich mag Pizza. Ich mag meinen Bruder.'],
                    ['es' => 'gern = «con gusto», y va DETRÁS del verbo de la actividad: Ich spiele gern Fußball. Ich lerne gern Deutsch.'],
                    ['es' => 'Para negar: Ich mag keine Pizza. Ich spiele nicht gern Fußball.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «ich mag spielen Fußball» para «me gusta jugar al fútbol». En alemán eso no es natural: para actividades va el verbo normal + gern: «Ich spiele gern Fußball». Regla: si lo que te gusta es un VERBO, usa gern; si es un NOMBRE, usa mögen.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir por qué'], 'pasos' => [
                    ['texto' => ['es' => '— Magst du Fußball?  (¿Te gusta el fútbol?)']],
                    ['texto' => ['es' => '— Ja, ich spiele sehr gern Fußball. Es ist toll.  (Sí, me gusta mucho jugar. Es genial.)']],
                    ['texto' => ['es' => '— Und die Schule?']],
                    ['texto' => ['es' => '— Die Schule ist interessant, aber schwer.  (El colegio es interesante, pero difícil.)']],
                    ['texto' => ['es' => 'Seis adjetivos para opinar: toll / schlecht (genial / malo), gut (bueno), interessant / langweilig (interesante / aburrido), leicht / schwer (fácil / difícil).']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'die Musik · der Sport · der Fußball · das Buch — el libro · der Film · das Kino'],
                    ['es' => 'die Pizza · das Eis — el helado · der Kaffee · der Hund — el perro · die Katze — el gato'],
                    ['es' => 'singen — cantar · tanzen — bailar · lesen — leer'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: los Umlaute, las vocales con dos puntos. No son adornos: son tres vocales distintas de a, o, u, y cambian la palabra.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Ä → como una e abierta: Mädchen (medjen), spät (shpet), Käse (keze).'],
                    ['es' => 'Ö → boca de o, lengua de e: schön (bonito), hören, Söhne. Como el «eu» francés.'],
                    ['es' => 'Ü → boca de u, lengua de i: über, Tschüss, müde, fünf. Igual que la u francesa (si hiciste francés, ya la tienes).'],
                    ['es' => 'La pareja: «schon» (ya) y «schön» (bonito). Dos puntitos, dos palabras.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Ignorar los puntitos y decir «Tschuss», «funf», «schon» por «schön». Cada Umlaut es otra vocal. Si no tienes el teclado alemán, se escriben como ae, oe, ue (schoen, fuenf, Tschuess) — eso es correcto y cualquier alemán lo entiende; lo que no es correcto es quitar los puntos y no poner la e.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Ü y ö se hacen con la boca en forma de u y o pero la lengua en posición de i y e. Di «i» y, sin mover la lengua, pon los labios en «u»: sale «ü». Di «e» y pon los labios en «o»: sale «ö». Diez veces con «fünf» y «schön» y lo tienes.']],
            ],
        ],

        // ============ DE U4 · A1.EE.1 · escribir una nota corta ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.EE.1', 'slug' => 'eine-kurze-nachricht',
            'titulo' => ['es' => 'Escribir una nota de tres líneas'],
            'resumen' => ['es' => 'La forma fija de un mensaje corto en alemán, y las tres revisiones que cazan la mitad de los errores.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Con lo que sabes ya escribes una nota real: saludo, quién eres y qué te gusta, despedida. Tres piezas, siempre las mismas.']],

                ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                    ['es' => 'Saludo con nombre: «Hallo Marco!» (amigo) o «Guten Tag, Frau Müller» (adulto, de Sie). Después del saludo en alemán va coma o signo de exclamación, y la frase siguiente empieza con MAYÚSCULA.'],
                    ['es' => 'Dos o tres frases cortas. Una idea por frase. Verbo en segunda posición en todas.'],
                    ['es' => 'Despedida y tu nombre: «Bis bald, Sofía.» (hasta pronto) o «Liebe Grüße, Sofía.» (saludos cariñosos, con amigos).'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una nota completa'], 'pasos' => [
                    ['texto' => ['es' => 'Hallo Anna!']],
                    ['texto' => ['es' => 'Ich heiße Sofía und ich komme aus Ecuador. Ich mag Musik sehr und ich spiele gern Fußball. Ich mag keine Katzen.']],
                    ['texto' => ['es' => 'Liebe Grüße, Sofía.']],
                    ['texto' => ['es' => 'Cuatro frases cortas, todas con el verbo segundo, todos los sustantivos con mayúscula. Así se escribe en A1.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Olvidar las mayúsculas de los sustantivos: «ich mag musik». En alemán es Musik, Fußball, Katzen — siempre. Es un error de ortografía que un alemán ve antes que cualquier otro.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Tres revisiones de diez segundos antes de mandar: ¿cada sustantivo lleva mayúscula? ¿cada verbo está en segunda posición? ¿cada «du» lleva -st? Con eso cazas la mitad de los errores del curso.']],
            ],
        ],

        // ============ DE U5 · A1.CE.3 · orientarse: es gibt, el acusativo, z y s ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.CE.3', 'slug' => 'in-der-stadt',
            'titulo' => ['es' => 'Entschuldigung, wo ist der Bahnhof?'],
            'resumen' => ['es' => 'Preguntar dónde está algo, entender la respuesta, decir qué hay en tu barrio. Con eso llega el primer caso del alemán: el acusativo.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En una ciudad alemana necesitas preguntar de Sie (a un desconocido) y entender seis palabras de respuesta.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Preguntar por un sitio'], 'pasos' => [
                    ['texto' => ['es' => '— Entschuldigung, wo ist der Bahnhof?  (Perdone, ¿dónde está la estación?)']],
                    ['texto' => ['es' => '— Da, rechts.  (Ahí, a la derecha.)']],
                    ['texto' => ['es' => '— Gibt es eine Apotheke hier in der Nähe?  (¿Hay una farmacia aquí cerca?)']],
                    ['texto' => ['es' => '— Ja, in der Goethestraße. Geradeaus und dann links.  (Sí, en la Goethestraße. Recto y luego a la izquierda.)']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'rechts — a la derecha · links — a la izquierda · geradeaus — recto'],
                    ['es' => 'hier — aquí · da / dort — ahí / allí · in der Nähe — cerca · weit — lejos · dann — luego'],
                    ['es' => 'der Bahnhof — la estación · die Straße — la calle · der Platz — la plaza · das Krankenhaus — el hospital · die Apotheke — la farmacia'],
                    ['es' => 'der Supermarkt · die Bank · die Kirche — la iglesia · das Museum · der Park · das Café · das Restaurant'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El punto de gramática es «hay»: es gibt. No cambia nunca (como el francés). Pero trae consigo el primer CASO del alemán, y hay que verlo de frente: lo que va detrás de «es gibt» está en acusativo.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El acusativo: solo cambia el masculino'], 'pasos' => [
                    ['texto' => ['es' => 'El acusativo es el caso del complemento directo: lo que se tiene, se ve, se come, o lo que «hay».']],
                    ['texto' => ['es' => 'La buena noticia: femenino y neutro NO cambian. die Bank → die Bank. das Museum → das Museum. eine Apotheke → eine Apotheke.']],
                    ['texto' => ['es' => 'Solo cambia el masculino: der → DEN, ein → EINEN. Es gibt einen Park. Ich sehe den Bahnhof.']],
                    ['texto' => ['es' => 'Es gibt eine Apotheke. (femenino, igual) · Es gibt ein Museum. (neutro, igual) · Es gibt einen Park. (masculino: einen)']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => '«Es gibt ein Park.» No: «es gibt EINEN Park». El masculino en acusativo lleva -en, y es la única marca de caso que necesitas en todo el A1. Si te la aprendes con «es gibt» y con «ich habe» («ich habe einen Bruder»), tienes el acusativo resuelto para el año.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Por eso importaba tanto aprender cada palabra con su artículo (U2): sin saber que Park es «der», no puedes saber que en acusativo es «den / einen». El género no es un capricho; es lo que te dice cómo cambia la palabra.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: dos letras que el alemán pronuncia al revés de lo que espera un hispanohablante.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Z → siempre «ts»: zwei (tsvai), Zeit (tsait), Platz (plats), zehn (tseen). Nunca como la z española.'],
                    ['es' => 'S al empezar palabra, delante de vocal → suena como un zumbido, la z inglesa: sie (zii), Sohn (zoon), sehr (zeer), Sonntag (zontag).'],
                    ['es' => 'Y de propina: W suena como v de vaca, y V suena como f. Wo (vo), wie (vi), Vater (fater), vier (fir).'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «zwei» con z española, «Sonntag» con s sorda, o «Vater» con v. Las tres son las letras más traicioneras del alemán para un hispanohablante, y las tres están en las primeras cincuenta palabras del curso. Z = ts. S inicial = zumbido. V = f. W = v.']],
            ],
        ],

        // ============ DE U5 · A1.PO.1 · mi ciudad ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.PO.1', 'slug' => 'meine-stadt',
            'titulo' => ['es' => 'Meine Stadt'],
            'resumen' => ['es' => 'Describir tu barrio con lo que hay y lo que no hay. Y practicar el acusativo sin que se note.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Con es gibt, diez lugares y cuatro adjetivos ya describes tu barrio. Y cada «es gibt einen…» es un acusativo que practicas sin pensar.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El barrio de Sofía'], 'pasos' => [
                    ['texto' => ['es' => 'Ich wohne in Quito, in der Amazonasstraße.']],
                    ['texto' => ['es' => 'In der Nähe gibt es einen schönen Park und zwei Supermärkte.']],
                    ['texto' => ['es' => 'Es gibt kein Museum, aber es gibt einen großen Platz.']],
                    ['texto' => ['es' => 'Die Schule ist weit: sie ist in einer anderen Straße.']],
                    ['texto' => ['es' => 'Cuatro frases: dónde vive, qué hay cerca, qué falta, y que el colegio queda lejos. Fíjate en «einen Park», «einen Platz»: masculinos, acusativo, -en.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Para negar «hay» se usa «kein»: es gibt KEIN Museum, es gibt KEINEN Park (masculino acusativo, -en otra vez). Kein es «ningún» y funciona exactamente como ein, con las mismas terminaciones.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Alterna: una cosa que hay, una que no hay, una que hay. «Es gibt kein… aber es gibt…» suena natural y te obliga a practicar kein y ein en la misma frase.']],
            ],
        ],

        // ============ DE U6 · A1.IO.2 · pedir: möchten, y la r ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.IO.2', 'slug' => 'im-cafe',
            'titulo' => ['es' => 'Im Café: ich möchte einen Kaffee'],
            'resumen' => ['es' => 'Pedir algo de comer o beber, decir cuánto, pagar. Con «möchten», que ya trae el acusativo dentro. Y la r alemana, que no es la tuya.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Pedir en Alemania se hace con «ich möchte» (quisiera), que es la fórmula educada y la que usa todo el mundo. Ich möchte + lo que quieras (en acusativo) + bitte.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'En el café'], 'pasos' => [
                    ['texto' => ['es' => '— Guten Tag! Ich möchte einen Kaffee und ein Brötchen, bitte.  (Quisiera un café y un panecillo, por favor.)']],
                    ['texto' => ['es' => '— Gern. Sonst noch etwas?  (Con gusto. ¿Algo más?)']],
                    ['texto' => ['es' => '— Nein, danke. Was kostet das?  (No, gracias. ¿Cuánto cuesta?)']],
                    ['texto' => ['es' => '— Drei Euro fünfzig.']],
                    ['texto' => ['es' => '«Einen Kaffee»: der Kaffee es masculino, y detrás de möchten va acusativo → einen. «Ein Brötchen»: das Brötchen es neutro, no cambia. El acusativo de la U5, otra vez, y ya casi sin pensar.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ich möchte · du möchtest · er / sie möchte — quisiera / quisieras / quisiera'],
                    ['es' => 'das Brot — el pan · das Wasser — el agua · die Milch — la leche · der Käse — el queso'],
                    ['es' => 'das Fleisch — la carne · der Fisch — el pescado · das Obst — la fruta · das Gemüse — la verdura · das Ei / die Eier — el huevo / los huevos'],
                    ['es' => 'das Frühstück — el desayuno · das Mittagessen — el almuerzo · das Abendessen — la cena'],
                    ['es' => 'der Zucker — el azúcar · das Salz — la sal · die Rechnung — la cuenta'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => '«Ich möchte ein Kaffee.» Der Kaffee, masculino, acusativo → EINEN Kaffee. Y «ich will» (quiero) existe pero suena a niño exigiendo; en un café se dice möchte. Y siempre, siempre, el «bitte» al final.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'En Alemania se paga en la mesa, no en la barra, y se dice «Zusammen oder getrennt?» (¿juntos o separados?). Si sois varios, lo normal es que cada uno pague lo suyo — «getrennt» — y nadie se ofende. Y la propina se dice al pagar: si son 3,50, dices «vier» (cuatro) y te devuelven el cambio de cuatro.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: la r alemana. Se parece a la francesa —en la garganta— pero tiene una particularidad al final de palabra que el hispanohablante no espera.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'R al principio o en medio: en la garganta, suave: Rechnung, Brot, drei, Frühstück.'],
                    ['es' => 'R al FINAL de palabra, y en -er: casi desaparece y se vuelve una «a» corta: Vater (fata), Mutter (muta), Wasser (vasa), Bier (bia).'],
                    ['es' => 'La pareja: «Bier» (cerveza) suena «bia»; «Bitte» suena «bite». La r final no vibra: se abre.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pronunciar «Vater» con la r vibrante española al final: «fáter». En alemán es «fata», con la r convertida en una a floja. Es lo que hace que «Mutter» y «Vater» suenen tan distintos de lo que parecen escritos.']],
            ],
        ],

        // ============ DE U6 · A1.CE.1 · leer una carta y un cartel ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.CE.1', 'slug' => 'die-speisekarte',
            'titulo' => ['es' => 'Leer una carta y un cartel'],
            'resumen' => ['es' => 'El orden de una carta alemana, y los cuatro carteles que verás en cualquier puerta.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'La carta (die Speisekarte) tiene un orden fijo: Vorspeisen (entrantes), Hauptgerichte (platos principales), Nachspeisen (postres), Getränke (bebidas). Y las palabras largas se cortan por la costura: Haupt + Gericht = principal + plato.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Un trozo de carta'], 'pasos' => [
                    ['texto' => ['es' => 'HAUPTGERICHTE · Schnitzel mit Pommes — 12 € · Fisch des Tages — 15 €']],
                    ['texto' => ['es' => 'NACHSPEISEN · Apfelstrudel — 5 € · Eis — 4 €']],
                    ['texto' => ['es' => 'GETRÄNKE · Wasser — 2 € · Kaffee — 2,50 €']],
                    ['texto' => ['es' => '«Pommes» son patatas fritas (se dice «pomes»), «Apfelstrudel» es Apfel + Strudel, tarta de manzana. Lo que no entiendas: «Was ist das?» (¿qué es esto?).']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'GEÖFFNET / OFFEN — abierto · GESCHLOSSEN — cerrado'],
                    ['es' => 'EINGANG — entrada · AUSGANG — salida (esta ya la conoces)'],
                    ['es' => 'DRÜCKEN — empujar · ZIEHEN — tirar (de la puerta)'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Confundir «Eingang» (entrada) con «Ausgang» (salida) porque las dos terminan en -gang. Ein = dentro, aus = fuera. Y «Ausfahrt» en la autopista es también salida — para coches.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'En Alemania el agua del restaurante NO es gratis: si pides «Wasser» te traen una botella y la cobran. Y «stilles Wasser» es sin gas; si no lo dices, viene con gas. Dos cosas que un turista aprende pagando.']],
            ],
        ],

        // ============ DE U7 · A1.IO.2 · comprar y el tiempo: adjetivo predicativo, st- / sp- ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.IO.2', 'slug' => 'was-kostet-das',
            'titulo' => ['es' => 'Was kostet das? Wie ist das Wetter?'],
            'resumen' => ['es' => 'Comprar ropa, preguntar precios, hablar del tiempo. Y una buena noticia: el adjetivo detrás de «ist» NO cambia nunca.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'El adjetivo alemán tiene fama de difícil, y la tiene merecida —cuando va delante del nombre. Pero en A1 lo vas a usar casi siempre DETRÁS de «ist», y ahí es la cosa más fácil del idioma: no cambia. Ni género, ni número, ni caso. Nada.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'das Kleid — el vestido · das T-Shirt — la camiseta · die Hose — el pantalón (¡singular y femenino!)'],
                    ['es' => 'die Schuhe — los zapatos · die Jacke — la chaqueta · der Hut — el sombrero · die Tasche — el bolso'],
                    ['es' => 'rot, gelb, schwarz, weiß, grün, blau — rojo, amarillo, negro, blanco, verde, azul'],
                    ['es' => 'teuer / billig — caro / barato · die Größe — la talla · groß / klein — grande / pequeño'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El adjetivo detrás de «ist»'], 'pasos' => [
                    ['texto' => ['es' => 'Das Kleid ist rot. Die Jacke ist rot. Die Schuhe sind rot.']],
                    ['texto' => ['es' => 'Neutro, femenino, plural: «rot» las tres veces. Sin -e, sin -en, sin nada.']],
                    ['texto' => ['es' => 'Y la comparación básica: «Das Kleid ist teurer» (más caro), «die Hose ist billiger» (más barato). Se añade -er, como el inglés.']],
                    ['texto' => ['es' => 'Cuando el adjetivo vaya DELANTE del nombre («ein rotes Kleid») sí cambia — pero eso es A2. En este curso, adjetivo detrás de ist, y a dormir tranquilo.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Concordar el adjetivo como en español: «die Schuhe sind rote». No: «sind rot». Detrás de sein el adjetivo es una piedra. El hispanohablante añade la -e por instinto; quítasela.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'En la tienda'], 'pasos' => [
                    ['texto' => ['es' => '— Was kostet das T-Shirt?  (¿Cuánto cuesta la camiseta?)']],
                    ['texto' => ['es' => '— Fünfzehn Euro.']],
                    ['texto' => ['es' => '— Das ist ein bisschen teuer. Haben Sie Größe M?  (Es un poco caro. ¿Tiene la talla M?)']],
                    ['texto' => ['es' => '— Ja, in Schwarz und in Weiß.']],
                    ['texto' => ['es' => 'En una tienda, de Sie: «Haben Sie», no «hast du». Y «das ist» sirve para «esto es / eso es» sin preocuparse del género: das ist teuer, das ist schön.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Wie ist das Wetter? — ¿Qué tiempo hace? (literalmente «¿cómo es el tiempo?»)'],
                    ['es' => 'Es ist warm. / Es ist kalt. — Hace calor. / Hace frío. (¡con «ist», no con «hace»!)'],
                    ['es' => 'Die Sonne scheint. / Es regnet. / Es ist bewölkt. — Hay sol. / Llueve. / Está nublado.'],
                    ['es' => 'der Sommer, der Winter, der Frühling, der Herbst — verano, invierno, primavera, otoño'],
                ]],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: dos grupos de letras al principio de palabra que se leen distinto de como se escriben.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'ST- al principio → «sht»: Straße (shtrase), Stadt (shtat), Stunde (shtunde), studieren.'],
                    ['es' => 'SP- al principio → «shp»: spielen (shpilen), Sport (shport), spät (shpet), Sprache.'],
                    ['es' => 'En medio o al final de palabra NO: ist (ist), Post (post), Wurst (vurst).'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «Straße» como «strase» o «spielen» como «spilen», a la española. Al principio de palabra, st y sp llevan una sh escondida: SHtraße, SHpielen. Es una de las marcas más claras de que alguien lleva poco alemán.']],
            ],
        ],

        // ============ DE U8 · A1.PO.2 · contar lo que hice: Perfekt con haben, acento de compuestas ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.PO.2', 'slug' => 'gestern',
            'titulo' => ['es' => 'Gestern habe ich Pizza gegessen'],
            'resumen' => ['es' => 'Contar lo que hiciste ayer. Un solo tiempo del pasado, con dos piezas que ya tienes — y una que se va al final de la frase.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para contar algo que pasó, el alemán hablado usa el Perfekt. Se monta con haben (U2) y el participio, que para los verbos regulares tiene una forma muy reconocible: ge- delante y -t detrás.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'La máquina del pasado'], 'pasos' => [
                    ['texto' => ['es' => 'Pieza 1: haben en presente. ich habe, du hast, er hat.']],
                    ['texto' => ['es' => 'Pieza 2: el participio. Se quita -en, se pone ge- delante y -t detrás: lernen → gelernt, spielen → gespielt, machen → gemacht, kochen → gekocht.']],
                    ['texto' => ['es' => 'Se juntan… pero NO juntos: Ich habe Deutsch gelernt. El haben va en segunda posición (como siempre) y el participio se va AL FINAL de la frase.']],
                    ['texto' => ['es' => 'Ich habe gestern Fußball gespielt. — Ayer jugué al fútbol. Todo lo demás queda en medio, como en un bocadillo: habe … gespielt.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Ese «bocadillo» es la estructura más alemana que hay: el verbo conjugado segundo, el participio último, y todo lo demás en medio. Va a parecerte raro tres semanas y natural el resto de tu vida. «Ich habe gestern mit Marco Pizza gegessen» — el «gegessen» espera al final aunque la frase sea larga.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Poner el participio justo detrás de haben, como en español: «Ich habe gespielt Fußball». En alemán el participio cierra la frase: «Ich habe Fußball gespielt». Si sientes que te sobra algo al final, es que lo has puesto demasiado pronto.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'gestern — ayer · gestern Abend — anoche · letzte Woche — la semana pasada · das Wochenende — el fin de semana'],
                    ['es' => 'zuerst — primero · dann / danach — luego / después · schon — ya · noch — todavía'],
                    ['es' => 'mit — con · zusammen — juntos · die Freunde — los amigos · die Party — la fiesta'],
                    ['es' => 'das Meer — el mar · die Berge — las montañas · die Reise — el viaje'],
                    ['es' => 'kaufen — comprar (gekauft) · besuchen — visitar (besucht, ¡sin ge-!) · telefonieren — llamar (telefoniert, ¡sin ge-!)'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Dos excepciones que conviene ver ya: los verbos que empiezan por be- (besuchen) y los que terminan en -ieren (telefonieren, studieren) NO llevan ge-: besucht, telefoniert, studiert. Y «essen» (comer) es irregular: gegessen. Son tres, y son las que más vas a usar.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El fin de semana de Marco'], 'pasos' => [
                    ['texto' => ['es' => 'Am Samstag habe ich am Morgen gelernt.  (El sábado estudié por la mañana.)']],
                    ['texto' => ['es' => 'Dann habe ich mit Freunden Fußball gespielt.  (Luego jugué al fútbol con amigos.)']],
                    ['texto' => ['es' => 'Am Sonntag habe ich meine Oma besucht und sehr gut gegessen.  (El domingo visité a mi abuela y comí muy bien.)']],
                    ['texto' => ['es' => 'Cuatro participios, todos al final de su trozo de frase. Y fíjate: «Am Samstag HABE ich» — el verbo segundo también cuando la frase empieza con un complemento.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Usar «bin» con estos verbos: «ich bin gespielt». Con los verbos de esta unidad el pasado va con haben. Hay otros —«ich bin gegangen» (fui)— que van con sein, y tienen su regla (movimiento), pero los verás el año que viene. Por ahora: haben + ge-…-t al final.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El sonido de esta unidad: el acento en las palabras compuestas, que el alemán fabrica a montones. La regla es simple y el español la tiene al revés.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'En una palabra compuesta, la fuerza va en la PRIMERA parte: WOchenende (Wochen + Ende), BAHNhof (Bahn + Hof), KRANkenhaus (Kranken + Haus).'],
                    ['es' => 'Y en las palabras simples, casi siempre en la primera sílaba: LERnen, SPIElen, GEStern, MUtter.'],
                    ['es' => 'Excepción: los prefijos ge-, be-, ver- no llevan acento nunca: geSPIELT, beSUCHT, verSTEHen.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Acentuar la última parte, a la española: «wochenENde», «bahnHOF». El alemán acentúa el principio. Si dudas, carga la fuerza en la primera sílaba y acertarás casi siempre — salvo con ge-, be-, ver-, que se saltan.']],
            ],
        ],

        // ============ DE U9 · A1.IO.1 · reparar la conversación (repaso y proyecto) ============
        [
            'lengua' => 'de', 'descriptor' => 'A1.IO.1', 'slug' => 'ich-verstehe-nicht',
            'titulo' => ['es' => 'Ich verstehe nicht: cómo no perder una conversación'],
            'resumen' => ['es' => 'La unidad 9 no trae gramática nueva. Trae lo único que te falta para hablar dos minutos seguidos con un alemán: saber qué decir cuando no entiendes.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En A1 vas a entender la mitad de lo que te digan. Eso está previsto: el descriptor del MCER dice «siempre que la otra persona repita o hable más despacio». Lo que separa a un alumno que conversa de uno que se bloquea es si sabe pedir ayuda sin salirse del alemán. Y el alemán tiene una ventaja aquí: los alemanes están acostumbrados a que se les pida repetir, y lo hacen sin drama.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Wie bitte? — ¿Cómo? (la más corta; educada y universal)'],
                    ['es' => 'Ich verstehe nicht. — No entiendo.'],
                    ['es' => 'Können Sie das bitte wiederholen? — ¿Puede repetirlo, por favor?'],
                    ['es' => 'Langsamer, bitte. — Más despacio, por favor.'],
                    ['es' => 'Was bedeutet «…»? — ¿Qué significa «…»?'],
                    ['es' => 'Wie sagt man «…» auf Deutsch? — ¿Cómo se dice «…» en alemán?'],
                    ['es' => 'Okay. / Natürlich. / Einen Moment. — Vale. / Claro. / Un momento.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Apréndete estas siete frases como si fueran una sola palabra cada una. No se analizan, se disparan. «Wie bitte?» es la que más vas a usar: son dos palabras, es educada, y funciona con du y con Sie.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una conversación con reparación'], 'pasos' => [
                    ['texto' => ['es' => '— Hallo! Woher kommst du?']],
                    ['texto' => ['es' => '— Ich komme aus Quito. Und du?']],
                    ['texto' => ['es' => '— Aus München. Was lernst du in der Schule?']],
                    ['texto' => ['es' => '— Wie bitte? Langsamer, bitte.']],
                    ['texto' => ['es' => '— Was… lernst… du… in… der… Schule?']],
                    ['texto' => ['es' => '— Ah! Ich lerne Deutsch und Mathe. Ich lerne sehr gern Deutsch.']],
                    ['texto' => ['es' => 'No entendió una pregunta entera, lo dijo en alemán, la otra persona repitió, y la conversación siguió. Eso es exactamente el A1.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Pasarse al español —o al inglés, que en Alemania es muy tentador porque casi todos lo hablan— en cuanto algo no se entiende. Cada vez que lo haces, la conversación en alemán se acaba. «Wie bitte?» cuesta lo mismo y la mantiene viva.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'El proyecto final es una conversación de dos minutos con el interlocutor del curso, sobre lo que quieras de las ocho unidades. Vas a usar las siete frases de arriba al menos una vez. Si no te hacen falta, es que la conversación fue demasiado fácil.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Antes del proyecto, repasa las ocho cosas: ei / ie y el verbo segundo (U1), los tres géneros y las dos ch (U2), la -st de du y las vocales largas (U3), mögen / gern y los Umlaute (U4), es gibt einen (U5), möchte einen y la r final (U6), el adjetivo que no cambia y st- / sp- (U7), y habe … ge-…-t al final (U8). Si las tienes, tienes el A1.']],
            ],
        ],


        // ================================================================
        // ========================= CHINO · A1 ============================
        // ================================================================
        //
        // El chino rompe la simetría de las otras tres lenguas y conviene
        // decirlo aquí, donde vive el contenido:
        //
        //  - NO HAY ALFABETO. El alumno arranca con PINYIN (la escritura
        //    latina oficial del chino) y los CUATRO TONOS desde el primer
        //    día; los caracteres avanzan como una pista aparte y más lenta
        //    (unos cinco por unidad, los del cuadro de ESQUELETO-9-UNIDADES).
        //  - LO QUE SE ESCRIBE EN LOS `hueco` ES PINYIN CON TONOS. Se acepta
        //    también el pinyin con números (ni3 hao3) —lo que escribe quien
        //    no tiene el teclado configurado— y el carácter, para quien ya
        //    lo sepa. El tono que falta es un error de «acento» para el
        //    motor (`detalle: 'acento'`): en chino ese detalle se lee «tono».
        //  - LOS `pares` LLEVAN TRES COLUMNAS: carácter (a), pinyin (b) y
        //    significado (c). Emparejar carácter↔significado saltándose el
        //    pinyin enseña a leer sin poder hablar.
        //  - ~17 PALABRAS POR UNIDAD (HSK 1), no 30: cada palabra cuesta el
        //    triple —sonido, tono y carácter—.
        //  - SIN AUDIO NO SE PUEDE ENSEÑAR CHINO: mā má mǎ mà son cuatro
        //    palabras. Las lecciones se escriben para que se puedan leer y
        //    practicar SIN clip, pero los clips de cada unidad están en
        //    docs/mision-lenguas/zh-audio-pendiente.md y son la primera
        //    prioridad de grabación de las cuatro lenguas.
        //  - Chino estándar (pǔtōnghuà), caracteres SIMPLIFICADOS, pinyin
        //    según la norma oficial. Los nombres: Sofía (la alumna de
        //    Quito, como en los otros cursos) y Lǐ Míng (李明), su compañero
        //    de Pekín; la profesora es Wáng lǎoshī (王老师).

        // ============ ZH U1 · A1.CO.2 · nǐ hǎo: saludar, los cuatro tonos y leer el pinyin ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.CO.2', 'slug' => 'ni-hao',
            'titulo' => ['es' => '你好 Nǐ hǎo: saludar en chino (y por qué mā no es mà)'],
            'resumen' => ['es' => 'Los saludos, y las dos cosas sin las que no hay chino: el pinyin, que es cómo se escribe el sonido, y los cuatro tonos, que son parte de la palabra.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'El chino no tiene alfabeto: cada palabra se escribe con uno o dos caracteres (你好). Para poder leer y escribir desde el primer día vas a usar el PINYIN, la escritura latina oficial del chino: 你好 se escribe nǐ hǎo. Los caracteres los irás aprendiendo aparte, unos pocos por unidad. El pinyin no es «chino fácil»: es la herramienta con la que los propios niños chinos aprenden a leer.']],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'LOS TONOS SON PARTE DE LA PALABRA. La sílaba «ma» dicha con cuatro melodías distintas es cuatro palabras: mā (妈, mamá), má (麻, cáñamo), mǎ (马, caballo), mà (骂, regañar). Si dices «ma» sin tono no has dicho una palabra a medias: no has dicho ninguna. Por eso el tono se escribe siempre: la rayita sobre la vocal no es un adorno.']],

                ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                    ['es' => 'Primer tono ā — alto y plano, como si cantaras una nota sostenida: mā, tā (él/ella), bā (ocho).'],
                    ['es' => 'Segundo tono á — sube, como una pregunta en español («¿qué?»): má, rén (persona), shí (diez).'],
                    ['es' => 'Tercer tono ǎ — baja y vuelve a subir, como un «hmm…» dubitativo: mǎ, nǐ (tú), hǎo (bien), wǒ (yo).'],
                    ['es' => 'Cuarto tono à — cae de golpe, como una orden («¡no!»): mà, shì (ser), jiào (llamarse), sì (cuatro).'],
                    ['es' => 'Y el tono neutro, sin marca: corto y suave, se apoya en la sílaba anterior: ma (吗, la partícula de pregunta), de (的).'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Con la mano: primer tono, la mano va recta; segundo, sube; tercero, baja y sube; cuarto, cae. Dilo moviendo la mano hasta que no haga falta. Y un descanso: cuando dos terceros tonos van seguidos, el primero se dice como segundo —nǐ hǎo suena «ní hǎo»—, pero se escribe nǐ hǎo. Lo verás con calma en la unidad 2.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Los saludos y las fórmulas de la unidad. Fíjate en que cada sílaba lleva su tono.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '你好 nǐ hǎo — hola (sirve con todos, a cualquier hora) · 您好 nín hǎo — hola (a un adulto: nín es el «usted»)'],
                    ['es' => '老师好 lǎoshī hǎo — hola, profesor/a (así se saluda en clase) · 再见 zàijiàn — adiós'],
                    ['es' => '谢谢 xièxie — gracias · 不客气 bú kèqi — de nada'],
                    ['es' => '对不起 duìbuqǐ — perdón · 没关系 méi guānxi — no pasa nada'],
                    ['es' => '请 qǐng — por favor (delante de lo que pides: 请坐 qǐng zuò, siéntate por favor)'],
                    ['es' => '是 shì — sí (literalmente «es») · 不是 bú shì — no'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Contestar «谢谢» con «请» porque se parece a decir «por favor / de nada» en otras lenguas. En chino a «gracias» se contesta «不客气 bú kèqi». Y a «对不起 duìbuqǐ» (perdón) se contesta «没关系 méi guānxi». Son parejas fijas: apréndelas de dos en dos.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Y lo segundo sin lo que no hay chino: LEER EL PINYIN. Es latino pero no se lee a la española. Las letras que engañan, y solo esas:']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'x → como una «sh» muy suave, con la lengua plana: xièxie (sie-sie, con sonrisa). NUNCA «ks».'],
                    ['es' => 'q → «ch» suave, de la misma familia que la x: qǐng (ching). NUNCA «k».'],
                    ['es' => 'zh → «ch» con la lengua hacia atrás, y sin aire: Zhōngguó (China). ch → la misma, pero con aire (como si soplaras). sh → «sh» con la lengua atrás: shì.'],
                    ['es' => 'z → «ts» sin aire: zàijiàn (tsai-chien). c → «ts» con aire: cèsuǒ (baño). NUNCA «z» ni «c» a la española.'],
                    ['es' => 'r → entre la r y la «y», con la lengua atrás y sin vibrar: rén (persona). Nada que ver con la rr.'],
                    ['es' => 'i después de zh, ch, sh, r, z, c, s → casi no suena, es un zumbido: shì suena «shr», sì suena «sz». Ojo: en nǐ y en xièxie la i sí es una i.'],
                    ['es' => 'e sola → una «e» oscura, hacia la «o»: rén, hěn (muy). ü → como la u francesa o alemana, con los labios en «u» y diciendo «i»: nǚ (mujer), lǜ (verde).'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Las tres letras que un hispanohablante siempre lee mal son x, q y zh. Si consigues decir bien «xièxie», «qǐng» y «Zhōngguó», tienes resuelta la mitad del pinyin de todo el año. Las demás casi se leen solas.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Escribir el pinyin sin tonos porque «se entiende». En este curso, un hueco escrito sin tonos está mal: el motor te dirá que es un error de acento —que en chino significa «te falta el tono»—, y no que la palabra no es. Si tu teclado no saca las rayitas, escribe el número del tono al final de la sílaba: ni3 hao3. Vale igual.']],
            ],
        ],

        // ============ ZH U1 · A1.IO.3 · presentarse: 我叫…, 是, y de dónde eres ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.IO.3', 'slug' => 'wo-jiao',
            'titulo' => ['es' => '我叫 Sofía · Wǒ jiào Sofía'],
            'resumen' => ['es' => 'Decir tu nombre, de dónde eres y presentar a alguien, con los dos verbos de la unidad: 叫 jiào (llamarse) y 是 shì (ser). Y la primera buena noticia: los verbos chinos no se conjugan.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para presentarte necesitas dos verbos y cinco pronombres. Y la noticia que te va a ahorrar la mitad del trabajo del año: EL VERBO CHINO NO CAMBIA NUNCA. 是 shì es «soy», «eres», «es», «somos»… todo. Lo que dice quién es es el pronombre, y el pronombre no se puede callar.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '我 wǒ — yo · 你 nǐ — tú · 您 nín — usted'],
                    ['es' => '他 tā — él · 她 tā — ella (suenan igual: solo el carácter los distingue) · 我们 wǒmen — nosotros'],
                    ['es' => '是 shì — ser · 叫 jiào — llamarse · 不 bù — no (delante del verbo)'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Presentarse y preguntar el nombre'], 'pasos' => [
                    ['texto' => ['es' => '— 你叫什么名字？ Nǐ jiào shénme míngzi?  (¿Cómo te llamas? — literalmente «tú llamarse qué nombre»)']],
                    ['texto' => ['es' => '— 我叫 Sofía。 Wǒ jiào Sofía.  (Me llamo Sofía.)']],
                    ['texto' => ['es' => 'De otra persona: 他叫李明。 Tā jiào Lǐ Míng. (Él se llama Li Ming.) Fíjate: el verbo es el mismo, 叫. Solo cambia el pronombre.']],
                    ['texto' => ['es' => 'A un adulto: 您叫什么名字？ Nín jiào shénme míngzi? — y con 您 se contesta igual, pero ya has sido educado.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'LA PREGUNTA NO CAMBIA EL ORDEN. En español giramos («¿cómo te llamas?»); en chino la pregunta tiene el mismo orden que la respuesta y la palabra interrogativa ocupa el sitio de lo que preguntas: 你叫【什么】名字 → 我叫【Sofía】. La palabra 什么 shénme (qué) está exactamente donde va a estar tu nombre. Eso vale para todas las preguntas del curso.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Decir de dónde eres'], 'pasos' => [
                    ['texto' => ['es' => '— 你是哪国人？ Nǐ shì nǎ guó rén?  (¿De qué país eres? — «tú ser qué país persona»)']],
                    ['texto' => ['es' => '— 我是厄瓜多尔人。 Wǒ shì Èguāduō’ěr rén.  (Soy ecuatoriana.)']],
                    ['texto' => ['es' => '— 他是中国人。 Tā shì Zhōngguó rén.  (Él es chino.)']],
                    ['texto' => ['es' => 'La receta es siempre la misma: PAÍS + 人 rén (persona). 中国 Zhōngguó (China) + 人 = chino. 厄瓜多尔 Èguāduō’ěr (Ecuador) + 人 = ecuatoriano. No hay masculino ni femenino ni plural: 人 sirve para todos.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Para preguntar «¿y tú?» basta con 你呢？ Nǐ ne? — el pronombre y la partícula 呢. Y para convertir cualquier frase en pregunta de sí / no, añade 吗 ma al final: 你是中国人吗？ Nǐ shì Zhōngguó rén ma? (¿Eres chino?). Se contesta 是 shì (sí) o 不是 bú shì (no).']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Poner 是 shì delante de un adjetivo: «我是好» para decir «estoy bien». En chino el adjetivo YA es el verbo: 我很好 wǒ hěn hǎo (estoy bien). 是 solo une dos nombres: 我是学生 (soy estudiante). Lo trabajamos en la U4; por ahora, 是 solo con nombres.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Los cinco caracteres de la unidad. Míralos, cuenta sus trazos, y escríbelos de arriba abajo y de izquierda a derecha; son los que más vas a ver en el año.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '我 wǒ — yo (7 trazos) · 你 nǐ — tú (7)'],
                    ['es' => '好 hǎo — bien (6): una mujer 女 y un niño 子 juntos, «lo bueno»'],
                    ['es' => '是 shì — ser (9) · 叫 jiào — llamarse (5): a la izquierda, la boca 口'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => '第一天 · Dì yī tiān · El primer día'], 'pasos' => [
                    ['texto' => ['es' => '李明 — 你好！我叫李明。你叫什么名字？ Nǐ hǎo! Wǒ jiào Lǐ Míng. Nǐ jiào shénme míngzi?']],
                    ['texto' => ['es' => 'Sofía — 你好，李明。我叫 Sofía。 Nǐ hǎo, Lǐ Míng. Wǒ jiào Sofía.']],
                    ['texto' => ['es' => '李明 — 你是哪国人？ Nǐ shì nǎ guó rén?']],
                    ['texto' => ['es' => 'Sofía — 我是厄瓜多尔人。你呢？ Wǒ shì Èguāduō’ěr rén. Nǐ ne?']],
                    ['texto' => ['es' => '李明 — 我是中国人，北京人。你是学生吗？ Wǒ shì Zhōngguó rén, Běijīng rén. Nǐ shì xuésheng ma?']],
                    ['texto' => ['es' => 'Sofía — 是。对不起……她是谁？ Shì. Duìbuqǐ… tā shì shéi?']],
                    ['texto' => ['es' => '李明 — 她是王老师。 Tā shì Wáng lǎoshī.']],
                    ['texto' => ['es' => 'Sofía — 谢谢，李明。再见！ Xièxie, Lǐ Míng. Zàijiàn!']],
                    ['texto' => ['es' => '李明 — 再见！ Zàijiàn!']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'En chino el APELLIDO va primero: 李明 es «Li» (apellido) + «Ming» (nombre), y 王老师 es «profesora Wang»: el título va DETRÁS del apellido, al revés que en español. A un profesor nunca se le llama por el nombre de pila: 王老师, siempre.']],
            ],
        ],

        // ============ ZH U2 · A1.PO.1 · la familia, 有, el medidor 个 y el sandhi del tercer tono ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.PO.1', 'slug' => 'wo-de-jia',
            'titulo' => ['es' => '我的家 · Wǒ de jiā'],
            'resumen' => ['es' => 'Hablar de tu familia, contar cuántos sois y decir la edad. Con eso llegan 有 yǒu (tener), los números, el medidor 个 y la regla del tercer tono.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para hablar de tu familia necesitas los números del uno al diez, el verbo 有 yǒu (tener) y una pieza que el español no tiene: el MEDIDOR. En chino no se dice «tres hermanos» sino «tres [unidades de] hermanos»: 三个哥哥 sān gè gēge. Por ahora un solo medidor, 个 gè, que sirve para personas y para casi todo.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '一 yī 1 · 二 èr 2 · 三 sān 3 · 四 sì 4 · 五 wǔ 5 · 六 liù 6 · 七 qī 7 · 八 bā 8 · 九 jiǔ 9 · 十 shí 10'],
                    ['es' => '十一 shíyī 11 (diez-uno) · 十五 shíwǔ 15 · 二十 èrshí 20 (dos-diez) · 二十三 èrshísān 23. No hay nada que memorizar del 11 al 99: se componen.'],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '家 jiā — familia, casa · 爸爸 bàba — papá · 妈妈 māma — mamá'],
                    ['es' => '哥哥 gēge — hermano mayor · 弟弟 dìdi — hermano menor · 姐姐 jiějie — hermana mayor · 妹妹 mèimei — hermana menor'],
                    ['es' => '有 yǒu — tener · 没有 méiyǒu — no tener (有 NUNCA se niega con 不) · 个 gè — el medidor · 口 kǒu — el medidor de miembros de la familia'],
                    ['es' => '几 jǐ — cuántos (hasta diez) · 岁 suì — años de edad · 的 de — «de» (posesión): 我的 wǒ de, mi · 朋友 péngyou — amigo · 狗 gǒu — perro'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'En chino no existe «hermano» a secas: hay que decir si es MAYOR o MENOR que tú. 哥哥 gēge (mayor) o 弟弟 dìdi (menor); 姐姐 jiějie o 妹妹 mèimei. Cuando presentes a tu familia, la primera decisión no es el nombre: es quién nació antes.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Contar la familia'], 'pasos' => [
                    ['texto' => ['es' => '— 你家有几口人？ Nǐ jiā yǒu jǐ kǒu rén?  (¿Cuántos sois en tu familia? — «tu familia tener cuántas bocas persona»)']],
                    ['texto' => ['es' => '— 我家有四口人：爸爸、妈妈、姐姐和我。 Wǒ jiā yǒu sì kǒu rén: bàba, māma, jiějie hé wǒ.  (Somos cuatro: papá, mamá, mi hermana mayor y yo.)']],
                    ['texto' => ['es' => '— 你有哥哥吗？ Nǐ yǒu gēge ma?  (¿Tienes hermano mayor?)']],
                    ['texto' => ['es' => '— 没有。我有一个姐姐。 Méiyǒu. Wǒ yǒu yí gè jiějie.  (No. Tengo una hermana mayor.)']],
                    ['texto' => ['es' => 'Fíjate: los miembros de la familia se cuentan con 口 kǒu («bocas»), y todo lo demás con 个 gè. Y para negar 有 se usa 没 méi, no 不.']],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'La edad'], 'pasos' => [
                    ['texto' => ['es' => '— 你多大？ Nǐ duō dà?  (¿Cuántos años tienes? — «tú cuánto grande»)']],
                    ['texto' => ['es' => '— 我十五岁。 Wǒ shíwǔ suì.  (Tengo quince años.)']],
                    ['texto' => ['es' => 'SIN VERBO. Ni 是 ni 有: en chino la edad se dice pronombre + número + 岁. «Yo quince años.» Poner 是 (我是十五岁) es el error más repetido de la unidad.']],
                    ['texto' => ['es' => '— 你妹妹几岁？ Nǐ mèimei jǐ suì? — a un niño pequeño se le pregunta con 几 (cuántos, hasta diez); a alguien de tu edad, con 多大.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Olvidar el medidor: «我有一姐姐». Entre el número y la cosa SIEMPRE va 个 (o 口 con la familia): 我有一个姐姐. Piensa que el número en chino no puede tocar el nombre directamente; necesita esa pieza en medio.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'La regla de pronunciación de la unidad: EL SANDHI DEL TERCER TONO. Cuando dos sílabas de tercer tono van seguidas, la primera se pronuncia como segundo tono. Se ESCRIBE igual —nǐ hǎo—, pero se DICE «ní hǎo». Es automático en la boca de un chino y hay que hacerlo automático en la tuya.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '你好 nǐ hǎo → se dice «ní hǎo» · 我有 wǒ yǒu → «wó yǒu» · 很好 hěn hǎo → «hén hǎo» · 五个 wǔ gè → no cambia (个 es cuarto tono / neutro).'],
                    ['es' => 'Y dos cambios más que sí se escriben: 不 bù pasa a bú delante de un cuarto tono (不是 bú shì, 不客气 bú kèqi), y 一 yī pasa a yí delante de cuarto tono (一个 yí gè) y a yì delante de los demás (一天 yì tiān). Contando, sigue siendo yī.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Un tercer tono entero (baja y sube) casi solo se oye cuando la palabra va al final. En medio de la frase se queda en la mitad de abajo, corto. Si dices los terceros tonos «a medias», sonarás más natural que si los cantas enteros.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Caracteres de la unidad: 有 yǒu (tener) · 个 gè (medidor) · 人 rén (persona: dos piernas andando) · 大 dà (grande: una persona con los brazos abiertos) · 小 xiǎo (pequeño)'],
                ]],
            ],
        ],

        // ============ ZH U2 · A1.CE.2 · leer un mensaje breve ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.CE.2', 'slug' => 'yi-tiao-xinxi',
            'titulo' => ['es' => '一条信息 · Leer un mensaje corto'],
            'resumen' => ['es' => 'Entender un mensaje de WeChat sin conocer todos los caracteres: apoyarse en el pinyin, en los nombres y en las palabras que ya tienes.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Un mensaje real llega en caracteres, sin pinyin. Con quince caracteres no puedes leerlo entero, y no hace falta: se lee buscando lo que SÍ conoces —los números, 我 / 你 / 他, 是, 有, los nombres— y dejando que el resto se apoye en eso. En este curso los mensajes vienen con el pinyin debajo para que puedas comprobar; en la vida real, primero los caracteres.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El mensaje de Lǐ Míng'], 'pasos' => [
                    ['texto' => ['es' => 'Sofía，你好！我是李明。我家有五口人：爸爸、妈妈、一个哥哥、一个妹妹和我。我哥哥十八岁，我妹妹八岁。我有一个狗，叫小白。你家有几口人？再见！']],
                    ['texto' => ['es' => 'Sofía, nǐ hǎo! Wǒ shì Lǐ Míng. Wǒ jiā yǒu wǔ kǒu rén: bàba, māma, yí gè gēge, yí gè mèimei hé wǒ. Wǒ gēge shíbā suì, wǒ mèimei bā suì. Wǒ yǒu yí gè gǒu, jiào Xiǎo Bái. Nǐ jiā yǒu jǐ kǒu rén? Zàijiàn!']],
                    ['texto' => ['es' => 'Qué sabes seguro: 五口人 (cinco personas), 一个哥哥 (un hermano mayor), 十八岁 (dieciocho años), 八岁 (ocho años), 叫小白 (se llama Xiǎo Bái —«Blanquito»—). Con eso el mensaje ya está entendido.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Las tres señales que más te ayudan a leer: la coma china 、, que separa los elementos de una lista (爸爸、妈妈、…); los NÚMEROS, que casi siempre llevan detrás un medidor y una cosa (五口人, 一个狗); y 吗 / 呢 / ？ al final, que te dicen que eso era una pregunta y hay que contestarla.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Leer 我哥哥 como «yo hermano» y perderse. Cuando un pronombre va pegado a un miembro de la familia, es posesivo sin 的: 我哥哥 = mi hermano mayor, 你妈妈 = tu madre. El 的 se puede callar con la familia y con la gente cercana.']],
            ],
        ],

        // ============ ZH U3 · A1.PO.2 · mi día: la rutina, 在 frente a 是, y los cambios de 不 y 一 ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.PO.2', 'slug' => 'wo-de-yi-tian',
            'titulo' => ['es' => '我的一天 · Wǒ de yì tiān'],
            'resumen' => ['es' => 'Contar tu día a día: a qué hora te levantas, qué haces, dónde estás. La rutina trae el orden de la frase china —cuándo y dónde van ANTES del verbo— y el verbo 在 zài (estar en).'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'El verbo no se conjuga, así que contar tu rutina es cuestión de orden. Y el orden del chino tiene una regla que el español no tiene: EL TIEMPO Y EL LUGAR VAN ANTES DEL VERBO. «Yo a-las-siete en-casa como» — 我七点在家吃饭. Nunca «como en casa a las siete».']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '起床 qǐchuáng — levantarse · 吃饭 chī fàn — comer (comer-arroz) · 早饭 zǎofàn — desayuno · 午饭 wǔfàn — almuerzo · 晚饭 wǎnfàn — cena'],
                    ['es' => '上课 shàngkè — tener clase · 学习 xuéxí — estudiar · 看书 kàn shū — leer (mirar-libro) · 看电视 kàn diànshì — ver la tele · 睡觉 shuìjiào — dormir'],
                    ['es' => '在 zài — estar en · 家 jiā — casa · 学校 xuéxiào — escuela · 现在 xiànzài — ahora · 每天 měitiān — cada día · 回家 huí jiā — volver a casa'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El día de Sofía'], 'pasos' => [
                    ['texto' => ['es' => '我每天六点起床。 Wǒ měitiān liù diǎn qǐchuáng.  (Me levanto cada día a las seis.)']],
                    ['texto' => ['es' => '我七点吃早饭。 Wǒ qī diǎn chī zǎofàn.  (Desayuno a las siete.)']],
                    ['texto' => ['es' => '我上午在学校上课。 Wǒ shàngwǔ zài xuéxiào shàngkè.  (Por la mañana tengo clase en la escuela.)']],
                    ['texto' => ['es' => '我下午三点回家。晚上我看书，十点睡觉。 Wǒ xiàwǔ sān diǎn huí jiā. Wǎnshang wǒ kàn shū, shí diǎn shuìjiào.']],
                    ['texto' => ['es' => 'Mira el esqueleto de cada frase: QUIÉN + CUÁNDO + (在 DÓNDE) + VERBO. Si respetas ese orden, cualquier frase de rutina te sale bien.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '在 zài y 是 shì son dos verbos distintos y el español los junta en «estar / ser». 是 dice QUÉ es algo (我是学生, soy estudiante). 在 dice DÓNDE está (我在学校, estoy en la escuela). Nunca 我是在学校. Si la respuesta a la pregunta es un lugar, el verbo es 在.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Poner la hora al final, como en español: «我起床六点». La hora va ANTES del verbo: 我六点起床. Cuando escribas una frase con hora, colócala justo después del sujeto y no la muevas.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'La regla de pronunciación de la unidad son los dos cambios de tono que SÍ se escriben, los de 不 bù y 一 yī. En la U2 los viste de pasada; aquí los vas a usar en cada frase.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '不 bù → bú delante de cuarto tono: 不是 bú shì, 不去 bú qù, 不看 bú kàn. Delante de los otros tonos sigue bù: 不吃 bù chī, 不学 bù xué, 不好 bù hǎo.'],
                    ['es' => '一 yī → yí delante de cuarto tono: 一个 yí gè, 一半 yí bàn. → yì delante de primero, segundo y tercero: 一天 yì tiān, 一年 yì nián, 一点 yì diǎn. Solo, o contando (一、二、三), sigue siendo yī.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Ambos cambian por la misma razón: dos cuartos tonos seguidos (bù shì) son difíciles de decir, y la lengua pone el primero como segundo. No es una regla arbitraria: es la boca ahorrando esfuerzo. Dilo rápido diez veces y notarás que sale solo.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Caracteres de la unidad: 在 zài (estar en) · 家 jiā (casa: un cerdo 豕 bajo un techo 宀) · 上 shàng (arriba, subir) · 下 xià (abajo, bajar) · 中 zhōng (centro: el de 中国, el «país del centro»)'],
                ]],
            ],
        ],

        // ============ ZH U3 · A1.IO.2 · la hora ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.IO.2', 'slug' => 'ji-dian',
            'titulo' => ['es' => '几点？ · Jǐ diǎn? · ¿Qué hora es?'],
            'resumen' => ['es' => 'Preguntar y decir la hora. Es la parte más regular del chino: número + 点 diǎn, y ya está.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'La hora en chino es la recompensa por haber aprendido los números: se dice el número y 点 diǎn («punto»). Sin artículo, sin «son las», sin plural. 三点 sān diǎn: las tres.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Preguntar y decir la hora'], 'pasos' => [
                    ['texto' => ['es' => '— 现在几点？ Xiànzài jǐ diǎn?  (¿Qué hora es? — «ahora cuántos puntos»)']],
                    ['texto' => ['es' => '— 现在三点。 Xiànzài sān diǎn.  (Son las tres.)']],
                    ['texto' => ['es' => '— 三点半。 Sān diǎn bàn.  (Las tres y media: 半 bàn = mitad.)']],
                    ['texto' => ['es' => '— 三点十分。 Sān diǎn shí fēn.  (Las tres y diez: 分 fēn = minuto.)']],
                    ['texto' => ['es' => '— 两点。 Liǎng diǎn.  (Las dos.) ← la única trampa: con 点 y con 个 el «dos» es 两 liǎng, no 二 èr.']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '点 diǎn — hora (en punto) · 半 bàn — y media · 分 fēn — minuto · 两 liǎng — dos (delante de medidor)'],
                    ['es' => '早上 zǎoshang — por la mañana (temprano) · 上午 shàngwǔ — por la mañana · 中午 zhōngwǔ — mediodía · 下午 xiàwǔ — por la tarde · 晚上 wǎnshang — por la noche'],
                    ['es' => '几点 jǐ diǎn — a qué hora / qué hora · 什么时候 shénme shíhou — cuándo'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'El chino no usa las 13, las 14, las 20 horas. Dice la parte del día y después la hora de 1 a 12: 下午三点 xiàwǔ sān diǎn (las tres de la tarde), 晚上八点 wǎnshang bā diǎn (las ocho de la noche). Y el orden es siempre de lo grande a lo pequeño: parte del día → hora → minutos. Igual que la fecha, que verás más adelante: año → mes → día.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => '«二点» para decir las dos. Es 两点 liǎng diǎn. Cuando cuentas (一、二、三) es 二; cuando dices «dos cosas» o «las dos» es 两. Es la única excepción de todo el sistema numérico, y aparece a diario.']],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Para preguntar a qué hora hace alguien algo, pon 几点 donde iría la hora: 你几点起床？ Nǐ jǐ diǎn qǐchuáng? (¿A qué hora te levantas?) → 我六点起床. La misma regla de la U1: la palabra interrogativa ocupa el sitio de la respuesta.']],
            ],
        ],


        // ============ ZH U4 · A1.PO.2 · lo que me gusta: 喜欢, el adjetivo con 很, y zh ch sh frente a z c s ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.PO.2', 'slug' => 'wo-xihuan',
            'titulo' => ['es' => '我喜欢 · Wǒ xǐhuan'],
            'resumen' => ['es' => 'Decir lo que te gusta y lo que no, y describir con adjetivos. Descubres que en chino el adjetivo es un verbo, y afinas el oído con las consonantes que más se confunden.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para hablar de gustos hay un verbo, 喜欢 xǐhuan, y funciona como cualquier otro: 我喜欢音乐 wǒ xǐhuan yīnyuè (me gusta la música). Sin «me», sin «a mí»: el que gusta es el sujeto. Y detrás puede ir una cosa o un verbo: 我喜欢看书 (me gusta leer).']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '喜欢 xǐhuan — gustar · 不喜欢 bù xǐhuan — no gustar · 也 yě — también · 都 dōu — todos, ambos'],
                    ['es' => '音乐 yīnyuè — música · 电影 diànyǐng — película, cine · 足球 zúqiú — fútbol · 中文 Zhōngwén — chino (la lengua) · 水果 shuǐguǒ — fruta · 苹果 píngguǒ — manzana · 茶 chá — té · 咖啡 kāfēi — café · 猫 māo — gato'],
                    ['es' => '很 hěn — muy (y la pieza que une sujeto y adjetivo) · 好 hǎo — bueno · 好吃 hǎochī — rico (de comer) · 好看 hǎokàn — bonito · 大 dà — grande · 小 xiǎo — pequeño · 太…了 tài… le — demasiado'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Gustos'], 'pasos' => [
                    ['texto' => ['es' => '— 你喜欢什么？ Nǐ xǐhuan shénme?  (¿Qué te gusta?)']],
                    ['texto' => ['es' => '— 我喜欢音乐和足球。 Wǒ xǐhuan yīnyuè hé zúqiú.  (Me gustan la música y el fútbol.)']],
                    ['texto' => ['es' => '— 你喜欢咖啡吗？ Nǐ xǐhuan kāfēi ma?  — 不喜欢，我喜欢茶。 Bù xǐhuan, wǒ xǐhuan chá.']],
                    ['texto' => ['es' => '— 我也喜欢茶。 Wǒ yě xǐhuan chá.  (A mí también me gusta el té.) ← 也 va SIEMPRE entre el sujeto y el verbo, nunca al final.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'EL ADJETIVO CHINO ES UN VERBO. Para decir «el té está rico» no hace falta 是: 茶很好喝 chá hěn hǎohē. El adjetivo lleva el trabajo del verbo, y delante casi siempre va 很 hěn, que aquí no significa «muy»: solo rellena el hueco. Sin 很, «茶好喝» suena a comparación («el té sí, lo otro no»). Regla práctica de A1: sujeto + 很 + adjetivo, siempre.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => '«我是很高兴» para decir «estoy contento». Sobra el 是: 我很高兴 wǒ hěn gāoxìng. 是 solo va entre dos nombres (我是学生). Delante de un adjetivo, 是 está mal en el 99 % de las frases de A1.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'La regla de pronunciación de la unidad es la que más separa a quien suena a chino de quien no: dos familias de consonantes que un hispanohablante oye iguales.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'z c s — con la lengua PLANA, detrás de los dientes: zài (en), cài (plato), sān (tres). z suena «ts», c suena «ts» con aire, s es s.'],
                    ['es' => 'zh ch sh — con la punta de la lengua CURVADA HACIA ATRÁS, contra el paladar: Zhōngguó (China), chī (comer), shì (ser). Si pones la lengua atrás y dices «ts», te sale zh.'],
                    ['es' => 'Parejas para practicar: zài / zhài · cài / chài · sān / shān (montaña) · sì (cuatro) / shì (ser) · zì (carácter) / zhì.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Un truco físico: para zh ch sh, empieza por poner la lengua como si fueras a decir la «r» inglesa de «red» y sin moverla di «ts», «ch», «sh». Para z c s, apoya la punta de la lengua en los dientes de abajo. Diez repeticiones de sì / shì y el oído empieza a separarlas.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Caracteres de la unidad: 喜 xǐ · 欢 huān (juntos, 喜欢) · 不 bù (no) · 很 hěn (muy) · 天 tiān (día, cielo: una persona 大 con el cielo encima)'],
                ]],
            ],
        ],

        // ============ ZH U4 · A1.EE.1 · escribir una nota corta ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.EE.1', 'slug' => 'xie-yi-zhang-tiaozi',
            'titulo' => ['es' => '写一张条子 · Escribir una nota corta'],
            'resumen' => ['es' => 'Una nota de tres frases: a quién, qué, quién la escribe. En pinyin si hace falta; en caracteres los que ya tienes.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Una nota corta en chino tiene la misma forma que en español: a quién va, qué dices, quién la firma. La diferencia es que puedes escribirla en pinyin. En este curso una nota en pinyin con los tonos bien puestos vale; una nota en caracteres con los tonos mal aprendidos, no.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'La nota de Sofía'], 'pasos' => [
                    ['texto' => ['es' => '李明：你好！我今天下午三点在家。你来吗？ Sofía']],
                    ['texto' => ['es' => 'Lǐ Míng: Nǐ hǎo! Wǒ jīntiān xiàwǔ sān diǎn zài jiā. Nǐ lái ma? Sofía']],
                    ['texto' => ['es' => 'Tres piezas: el nombre con dos puntos (：) para dirigirte a alguien; la información con el orden de siempre (quién + cuándo + 在 dónde); la pregunta con 吗; y la firma.']],
                    ['texto' => ['es' => 'Otra: 妈妈：我在学校。我六点回家。谢谢！ Māma: Wǒ zài xuéxiào. Wǒ liù diǎn huí jiā. Xièxie!']],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Signos chinos: el punto es un circulito 。; la coma es ，; los dos puntos ：; la coma de lista es 、. Ocupan el ancho de un carácter y no llevan espacio detrás.'],
                    ['es' => '今天 jīntiān — hoy · 明天 míngtiān — mañana · 来 lái — venir · 去 qù — ir · 好的 hǎo de — vale, de acuerdo'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Si escribes en pinyin, escribe cada PALABRA junta y separa las palabras con espacios: «Wǒ jīntiān zài jiā», no «Wo jin tian zai jia». Y las mayúsculas van donde en español: al empezar la frase y en los nombres propios (Zhōngguó, Lǐ Míng).']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Olvidar el tono en la nota: «Wo jintian zai jia» es una nota que un chino lee a duras penas. Y poner un punto español (.) en un texto en caracteres: en chino, 。. Son detalles, pero son los que hacen que la nota parezca escrita por alguien que aprende chino y no por alguien que aprende «a escribir chino en latino».']],
            ],
        ],

        // ============ ZH U5 · A1.CE.3 · orientarse: 在 + lugar, 哪儿, y -n frente a -ng ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.CE.3', 'slug' => 'zai-nar',
            'titulo' => ['es' => '在哪儿？ · Zài nǎr? · ¿Dónde está?'],
            'resumen' => ['es' => 'Preguntar dónde está algo y seguir indicaciones sencillas. Con eso llegan los lugares de la ciudad, 前面 / 后面 / 旁边, y la diferencia entre -n y -ng.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para orientarte necesitas el verbo 在 zài (estar en), la palabra 哪儿 nǎr (dónde) y unos pocos lugares. La pregunta sigue la regla de siempre: 哪儿 ocupa el sitio de la respuesta. 学校在哪儿？ Xuéxiào zài nǎr? → 学校在那儿。 Xuéxiào zài nàr. (La escuela está allí.)']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '哪儿 nǎr — dónde · 这儿 zhèr — aquí · 那儿 nàr — allí · 在 zài — estar en'],
                    ['es' => '商店 shāngdiàn — tienda · 医院 yīyuàn — hospital · 饭馆 fànguǎn — restaurante · 火车站 huǒchēzhàn — estación de tren · 学校 xuéxiào — escuela · 银行 yínháng — banco'],
                    ['es' => '前面 qiánmiàn — delante · 后面 hòumiàn — detrás · 旁边 pángbiān — al lado · 左边 zuǒbian — a la izquierda · 右边 yòubian — a la derecha'],
                    ['es' => '走 zǒu — andar, ir · 远 yuǎn — lejos · 近 jìn — cerca · 请问 qǐngwèn — disculpe (para preguntar)'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Preguntar por un sitio'], 'pasos' => [
                    ['texto' => ['es' => '— 请问，医院在哪儿？ Qǐngwèn, yīyuàn zài nǎr?  (Disculpe, ¿dónde está el hospital?)']],
                    ['texto' => ['es' => '— 医院在学校旁边。 Yīyuàn zài xuéxiào pángbiān.  (El hospital está al lado de la escuela.)']],
                    ['texto' => ['es' => '— 远吗？ Yuǎn ma?  — 不远，很近。 Bù yuǎn, hěn jìn.']],
                    ['texto' => ['es' => 'El orden de «al lado de»: PRIMERO el punto de referencia, DESPUÉS la posición: 学校旁边 = «escuela-al lado» = al lado de la escuela. 商店前面 = delante de la tienda. Al revés que en español.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '在 zài hace dos trabajos y los dos con lugares: es «estar en» (我在家) y es «en» delante del verbo (我在家吃饭, como en casa). En las dos frases va ANTES del verbo o ES el verbo. Nunca va detrás del verbo: «我吃饭在家» está mal.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Decir «旁边学校» (al lado escuela) copiando el orden español. El punto de referencia va primero: 学校旁边. Piensa en «de la escuela, al lado». Todas las posiciones (前面, 后面, 旁边, 左边, 右边) funcionan igual.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'La regla de pronunciación de la unidad: -n frente a -ng al final de sílaba. Un hispanohablante los oye iguales y son sílabas distintas: 前 qián (delante) no es 墙 qiáng (pared); 银行 yínháng (banco) termina la primera sílaba en -n y la segunda en -ng.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '-n → la lengua TOCA los dientes de arriba, como en «pan»: qián, yuǎn (lejos), jìn (cerca), sān (tres).'],
                    ['es' => '-ng → la lengua NO toca nada, la boca se queda abierta y el sonido va por la nariz, como en «tango» sin llegar a decir la g: shàng (arriba), zhōng (centro), háng (el de 银行). Ojo con 饭馆 fànguǎn: fàn termina en -n, y la g que ves es el principio de guǎn. Léelo despacio.'],
                    ['es' => 'Parejas para practicar: qián / qiáng · yīn / yīng · shēn / shēng · fàn / fàng · bàn (medio) / bàng (genial).'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Si dudas, mira la palabra siguiente en el ESQUELETO del pinyin: 中 zhōng, 上 shàng, 行 háng son -ng; 天 tiān, 三 sān, 半 bàn son -n. Y en pinyin un apóstrofo separa sílabas que se confundirían: 天安门 Tiān’ānmén, 西安 Xī’ān (que no es 先 xiān).']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Caracteres de la unidad: 儿 ér (el de 哪儿 / 这儿 / 那儿) · 里 lǐ (dentro) · 去 qù (ir) · 来 lái (venir) · 学 xué (estudiar: el de 学校 y 学生)'],
                ]],
            ],
        ],

        // ============ ZH U5 · A1.PO.1 · mi ciudad ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.PO.1', 'slug' => 'wo-de-chengshi',
            'titulo' => ['es' => '我的城市 · Wǒ de chéngshì'],
            'resumen' => ['es' => 'Describir tu ciudad en cuatro frases: cómo es, qué hay, dónde está. Con 有 en su segundo trabajo —«hay»— y los adjetivos de la U4.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Describir tu ciudad junta tres cosas que ya tienes: el adjetivo con 很 (U4), 在 con lugares (U5) y 有 yǒu, que además de «tener» significa «hay»: 基多有很多山 Jīduō yǒu hěn duō shān (en Quito hay muchas montañas). El lugar va primero, como sujeto: LUGAR + 有 + cosa.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '城市 chéngshì — ciudad · 基多 Jīduō — Quito · 北京 Běijīng — Pekín · 山 shān — montaña · 公园 gōngyuán — parque · 车 chē — coche, vehículo'],
                    ['es' => '大 dà — grande · 小 xiǎo — pequeño · 漂亮 piàoliang — bonito · 多 duō — mucho · 很多 hěn duō — muchos · 高 gāo — alto · 冷 lěng — frío · 热 rè — calor, caliente'],
                    ['es' => '有 yǒu — hay · 没有 méiyǒu — no hay · 但是 dànshì — pero'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Quito, por Sofía'], 'pasos' => [
                    ['texto' => ['es' => '我的城市叫基多。 Wǒ de chéngshì jiào Jīduō.  (Mi ciudad se llama Quito.)']],
                    ['texto' => ['es' => '基多不大，但是很漂亮。 Jīduō bú dà, dànshì hěn piàoliang.  (Quito no es grande, pero es muy bonita.)']],
                    ['texto' => ['es' => '基多有很多山，有很多公园。 Jīduō yǒu hěn duō shān, yǒu hěn duō gōngyuán.  (En Quito hay muchas montañas y muchos parques.)']],
                    ['texto' => ['es' => '我家在学校旁边。 Wǒ jiā zài xuéxiào pángbiān.  (Mi casa está al lado de la escuela.)']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '«Mucho» se dice con 很多 hěn duō delante del nombre: 很多山, 很多人. Y con 有 no se usa 是: «hay» es 有. 基多有很多人 (en Quito hay mucha gente). Si empiezas por el lugar y sigues con 有, la frase sale sola.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Negar un adjetivo con 没: «基多没大». Los adjetivos se niegan con 不: 不大, 不冷, 不漂亮. 没 solo niega 有 (没有) y, más adelante, el pasado. Regla: 没 va con 有; todo lo demás, 不.']],
            ],
        ],

        // ============ ZH U6 · A1.IO.2 · en el restaurante: 要, 多少钱, y la ü con j q x ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.IO.2', 'slug' => 'zai-fanguan',
            'titulo' => ['es' => '在饭馆 · Zài fànguǎn · En el restaurante'],
            'resumen' => ['es' => 'Pedir algo de comer y de beber, y preguntar cuánto cuesta. El verbo 要 yào (querer), el dinero en 块 kuài, y la ü que se esconde detrás de j, q, x.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Para pedir en chino basta con 要 yào (querer) y lo que quieres, con su medidor: 我要一碗米饭 wǒ yào yì wǎn mǐfàn (quiero un cuenco de arroz). No hace falta condicional ni «por favor»: el 请 va en otra frase o no va. Ser directo no es ser maleducado; lo maleducado es no decir 谢谢 al recibirlo.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '要 yào — querer (pedir) · 想 xiǎng — querer, tener ganas de · 吃 chī — comer · 喝 hē — beber · 服务员 fúwùyuán — camarero/a · 菜单 càidān — carta, menú'],
                    ['es' => '米饭 mǐfàn — arroz · 面条 miàntiáo — fideos · 鸡 jī — pollo · 鱼 yú — pescado · 菜 cài — plato, verdura · 水 shuǐ — agua · 茶 chá — té · 果汁 guǒzhī — zumo'],
                    ['es' => '碗 wǎn — cuenco (medidor) · 杯 bēi — vaso, taza (medidor) · 多少钱 duōshao qián — cuánto cuesta · 块 kuài — yuan (la unidad de dinero, hablando) · 买单 mǎidān — la cuenta'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Pedir'], 'pasos' => [
                    ['texto' => ['es' => '— 服务员！ Fúwùyuán!  (¡Camarero!) — se le llama así, sin «perdone».']],
                    ['texto' => ['es' => '— 你要什么？ Nǐ yào shénme?  (¿Qué quiere?)']],
                    ['texto' => ['es' => '— 我要一碗面条和一杯茶。 Wǒ yào yì wǎn miàntiáo hé yì bēi chá.  (Quiero un cuenco de fideos y una taza de té.)']],
                    ['texto' => ['es' => '— 好的。 Hǎo de.  (Vale.)']],
                    ['texto' => ['es' => 'Cada cosa lleva SU medidor: 碗 para lo que va en cuenco, 杯 para lo que va en vaso. 个 sirve para casi todo… menos para comida y bebida. Y para pedir la cuenta: 买单！ Mǎidān!']],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El precio'], 'pasos' => [
                    ['texto' => ['es' => '— 多少钱？ Duōshao qián?  (¿Cuánto es?)']],
                    ['texto' => ['es' => '— 二十五块。 Èrshíwǔ kuài.  (Veinticinco yuanes.)']],
                    ['texto' => ['es' => '— 一杯茶多少钱？ Yì bēi chá duōshao qián?  (¿Cuánto cuesta un té?)  — 五块。 Wǔ kuài.']],
                    ['texto' => ['es' => 'En la carta pone 元 yuán; hablando todo el mundo dice 块 kuài. Y 多少 duōshao es el «cuántos» sin límite: 几 jǐ solo llega hasta diez.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => '«我要面条» sin medidor está bien si pides de forma general, pero en cuanto digas un número el medidor vuelve: 两碗面条 (dos cuencos de fideos), y recuerda que ese «dos» es 两 liǎng. «二碗» no existe.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'La regla de pronunciación de la unidad: la ü. Es la vocal francesa / alemana —labios en «u», lengua en «i»— y en pinyin juega al escondite: se escribe ü solo después de n y l (女 nǚ mujer, 绿 lǜ verde), y se escribe u, sin puntos, después de j, q, x, y… pero SIGUE SONANDO ü.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'j q x + u → siempre ü: 去 qù (ir) suena «chü», 鱼 yú suena «ü», 学 xué suena «süe». No existe «ju», «qu», «xu» con u española.'],
                    ['es' => 'n l + ü → se escriben los puntos porque nu / lu con u normal también existen: 女 nǚ (mujer) frente a 努 nǔ; 绿 lǜ (verde) frente a 路 lù (camino).'],
                    ['es' => 'Y una consecuencia: 服务员 fúwùyuán lleva -yuan al final, que suena «üen», no «yuan» a la española.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Para sacar la ü: di una «i» larga y, sin mover la lengua, cierra los labios como para silbar. Lo que sale es ü. Para escribirla en pinyin con números, se usa la v: nv3 (nǚ), lv4 (lǜ). El curso lo acepta.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Caracteres de la unidad: 要 yào (querer) · 钱 qián (dinero) · 多 duō (mucho) · 少 shǎo (poco) · 吃 chī (comer: a la izquierda, otra vez la boca 口)'],
                ]],
            ],
        ],

        // ============ ZH U6 · A1.CE.1 · leer una carta y los letreros ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.CE.1', 'slug' => 'caidan',
            'titulo' => ['es' => '菜单和牌子 · Leer una carta y los letreros'],
            'resumen' => ['es' => 'Los ocho caracteres que hay que reconocer de un vistazo en cualquier ciudad china —salida, entrada, hombres, mujeres, abierto, cerrado, empujar, tirar— y cómo leer una carta sin pinyin.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'Hay caracteres que no hace falta saber escribir ni pronunciar: hay que RECONOCERLOS, porque están en cada puerta y equivocarse tiene consecuencias. Estos ocho se aprenden con la vista, como se aprende un logo.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '出口 chūkǒu — SALIDA · 入口 rùkǒu — ENTRADA (出 «salir» tiene dos montañas; 入 «entrar» es una persona 人 con el trazo cruzado)'],
                    ['es' => '男 nán — HOMBRES · 女 nǚ — MUJERES (en la puerta del baño: 男 es un campo 田 sobre fuerza 力; 女 es la figura de una mujer)'],
                    ['es' => '开 kāi — ABIERTO · 关 guān — CERRADO (en la puerta de una tienda; 开 también es «encender», 关 «apagar»)'],
                    ['es' => '推 tuī — EMPUJAR · 拉 lā — TIRAR (en la puerta misma; los dos llevan la mano 扌 a la izquierda)'],
                    ['es' => '厕所 cèsuǒ / 洗手间 xǐshǒujiān — baño · 请勿 qǐng wù — prohibido (请勿吸烟: prohibido fumar) · 小心 xiǎoxīn — cuidado'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '出 y 入 se parecen a 山 (montaña) y a 人 (persona), y los dos pares se confunden. 出 son DOS 山 apilados (salir de las montañas). 入 tiene el trazo de la izquierda POR ENCIMA del de la derecha; en 人 es al revés. Es un detalle de un milímetro, y es la diferencia entre entrar y salir.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Una carta de verdad'], 'pasos' => [
                    ['texto' => ['es' => '菜单 · 米饭 5元 · 面条 12元 · 鸡 28元 · 鱼 35元 · 茶 5元 · 水 3元 · 果汁 10元']],
                    ['texto' => ['es' => 'No sabes leer «鸡» todavía; sí sabes que 米饭 es arroz, 面条 fideos, 茶 té, 水 agua, y que 元 yuán marca el precio. Con eso ya puedes pedir y saber qué vas a pagar.']],
                    ['texto' => ['es' => 'Truco de lectura: en las cartas chinas el nombre del plato suele terminar en el ingrediente principal: …鸡 lleva pollo, …鱼 pescado, …饭 arroz, …面 fideos. Lee el ÚLTIMO carácter de cada línea.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Quedarse parado delante de un texto porque no se conoce el 80 % de los caracteres. Leer en A1 es cazar el 20 % que conoces —números, 元, 人, 口, 大 / 小, 出 / 入— y deducir el resto. Un letrero con un número y 元 es un precio; uno con 口 es una puerta; uno rojo con 请勿 es una prohibición.']],
            ],
        ],


        // ============ ZH U7 · A1.IO.2 · comprar y el tiempo: 这个 / 那个, 太…了, el tono neutro y la erhua ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.IO.2', 'slug' => 'duoshao-qian',
            'titulo' => ['es' => '这个多少钱？ · Zhège duōshao qián?'],
            'resumen' => ['es' => 'Comprar ropa, decir que algo es caro o barato, y hablar del tiempo que hace. Llegan 这个 / 那个, «demasiado» con 太…了, y las dos cosas que hacen que el chino de Pekín suene a Pekín: el tono neutro y la erhua.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'En una tienda señalas: 这个 zhège (este) y 那个 nàge (ese, aquel). Son 这 / 那 más el medidor 个, y por eso pueden ir solos («este») o delante de un nombre (这个衣服, esta ropa). Y para decir que algo es demasiado: 太 tài + adjetivo + 了 le. 太贵了 tài guì le, demasiado caro.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '这个 zhège — este · 那个 nàge — ese, aquel · 衣服 yīfu — ropa · 件 jiàn — medidor de prendas · 买 mǎi — comprar · 卖 mài — vender'],
                    ['es' => '贵 guì — caro · 便宜 piányi — barato · 太…了 tài… le — demasiado · 一点儿 yìdiǎnr — un poco · 红 hóng — rojo · 白 bái — blanco'],
                    ['es' => '天气 tiānqì — el tiempo (meteorológico) · 冷 lěng — frío · 热 rè — calor · 下雨 xià yǔ — llover · 怎么样 zěnmeyàng — cómo es, qué tal · 今天 jīntiān — hoy · 明天 míngtiān — mañana'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'En la tienda'], 'pasos' => [
                    ['texto' => ['es' => '— 这件衣服多少钱？ Zhè jiàn yīfu duōshao qián?  (¿Cuánto cuesta esta prenda?) ← con nombre y medidor propio, 这 + 件 sustituye a 这个.']],
                    ['texto' => ['es' => '— 一百二十块。 Yìbǎi èrshí kuài.  (Ciento veinte yuanes.)']],
                    ['texto' => ['es' => '— 太贵了！便宜一点儿吧。 Tài guì le! Piányi yìdiǎnr ba.  (¡Demasiado caro! Un poco más barato, anda.)']],
                    ['texto' => ['es' => '— 那个呢？ Nàge ne?  — 那个八十块。 Nàge bāshí kuài.']],
                    ['texto' => ['es' => '— 好，我买那个。 Hǎo, wǒ mǎi nàge.  (Vale, compro ese.)']],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El tiempo'], 'pasos' => [
                    ['texto' => ['es' => '— 今天天气怎么样？ Jīntiān tiānqì zěnmeyàng?  (¿Qué tiempo hace hoy?)']],
                    ['texto' => ['es' => '— 今天很冷。 Jīntiān hěn lěng.  (Hoy hace frío.) ← el adjetivo es el verbo, con 很, como en la U4. Sin «hace».']],
                    ['texto' => ['es' => '— 明天下雨吗？ Míngtiān xià yǔ ma?  — 不下雨，明天很热。 Bú xià yǔ, míngtiān hěn rè.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '太…了 va con sus dos piezas: el 了 del final no es el 了 del pasado (que verás en la U8); aquí solo cierra la exclamación. 太贵了, 太热了, 太好了 (¡genial!). Sin el 了 suena a frase a medias.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => '«这衣服» sin medidor, o «这个件衣服» con dos. Es 这 + medidor + nombre: 这件衣服 (prendas), 这个人 (personas), 这杯茶 (vasos). Cuando el nombre tiene medidor propio, 个 se retira.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'Las dos reglas de pronunciación de la unidad son las que un oído chino nota más: el TONO NEUTRO y la ERHUA.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Tono neutro: la sílaba final de muchas palabras pierde su tono y se dice corta y floja, apoyada en la anterior: 衣服 yīfu (no yīfú), 便宜 piányi, 怎么样 zěnmeyàng (么 neutro), 爸爸 bàba, 谢谢 xièxie, 什么 shénme. En pinyin va sin marca. Si la cantas con tono, suena a extranjero.'],
                    ['es' => 'Erhua: en el norte (y en el chino estándar) muchas palabras cortas añaden un -r al final que se funde con la sílaba: 一点儿 yìdiǎnr («un poco»), 哪儿 nǎr, 这儿 zhèr, 那儿 nàr, 玩儿 wánr (jugar). El 儿 no es una sílaba aparte: es la lengua curvándose al final de la anterior.'],
                    ['es' => 'Cuando -r se pega a una sílaba en -n, la n desaparece: 一点儿 se dice «yìdiǎr», no «yìdiǎn-r». 玩儿 se dice «wár».'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Las palabras con tono neutro se aprenden con él: no memorices 衣服 como «yī-fú» y lo corrijas después. Escríbelo sin marca en la segunda sílaba desde el principio y dilo como si la segunda sílaba fuera un eco de la primera.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Caracteres de la unidad: 这 zhè (este) · 那 nà (ese) · 些 xiē (algunos: 这些, estos) · 衣 yī · 服 fu (juntos, 衣服)'],
                ]],
            ],
        ],

        // ============ ZH U8 · A1.PO.2 · contar lo que hice: 了 y 没 ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.PO.2', 'slug' => 'zuotian',
            'titulo' => ['es' => '昨天 · Zuótiān · Ayer'],
            'resumen' => ['es' => 'Contar lo que hiciste ayer. El chino no tiene pasado: tiene 了 le, que dice «acción terminada», y 没 méi, que dice «no llegó a pasar».'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'La segunda buena noticia del año: el chino no conjuga el pasado. «Fui», «voy» e «iré» son el mismo 去 qù; lo que cambia es la palabra de tiempo (昨天 ayer, 今天 hoy, 明天 mañana) y, para una acción hecha y terminada, la partícula 了 le detrás del verbo: 我去了商店 wǒ qù le shāngdiàn (fui a la tienda).']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '昨天 zuótiān — ayer · 今天 jīntiān — hoy · 明天 míngtiān — mañana · 上午 / 下午 / 晚上 — mañana / tarde / noche'],
                    ['es' => '了 le — (acción terminada) · 没 méi — no (para el pasado) · 去 qù — ir · 看 kàn — ver, mirar · 买 mǎi — comprar · 做 zuò — hacer · 吃 chī — comer · 玩儿 wánr — jugar, pasarlo bien'],
                    ['es' => '电影 diànyǐng — película · 朋友 péngyou — amigo · 商店 shāngdiàn — tienda · 累 lèi — cansado · 高兴 gāoxìng — contento · 作业 zuòyè — deberes'],
                ]],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'El fin de semana de Sofía'], 'pasos' => [
                    ['texto' => ['es' => '昨天我去了商店，买了一件衣服。 Zuótiān wǒ qù le shāngdiàn, mǎi le yí jiàn yīfu.  (Ayer fui a la tienda y compré una prenda.)']],
                    ['texto' => ['es' => '下午我和朋友看了一个电影。 Xiàwǔ wǒ hé péngyou kàn le yí gè diànyǐng.  (Por la tarde vi una película con un amigo.)']],
                    ['texto' => ['es' => '晚上我没做作业，太累了。 Wǎnshang wǒ méi zuò zuòyè, tài lèi le.  (Por la noche no hice los deberes: demasiado cansada.)']],
                    ['texto' => ['es' => 'Mira la negación: 没做, y SIN 了. Cuando dices que algo NO pasó, 没 sustituye al 了; no van juntos nunca.']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => '了 NO ES «EL PASADO». Es «hecho, terminado». Por eso 昨天我很累 (ayer estaba cansada) no lleva 了: estar cansado no es una acción que se termina. Y por eso 我去了商店 puede ser ayer o hace cinco minutos. La palabra de tiempo pone la fecha; 了 solo dice que se completó.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Preguntar por el pasado'], 'pasos' => [
                    ['texto' => ['es' => '— 你昨天做了什么？ Nǐ zuótiān zuò le shénme?  (¿Qué hiciste ayer?)']],
                    ['texto' => ['es' => '— 你去了学校吗？ Nǐ qù le xuéxiào ma?  — 去了。 Qù le.  / 没去。 Méi qù.']],
                    ['texto' => ['es' => 'La respuesta corta repite el verbo: 去了 (sí, fui) o 没去 (no fui). Sin «sí» ni «no».']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => '«我不去了学校» para decir «no fui a la escuela». Dos errores: el pasado se niega con 没, y con 没 desaparece 了. Es 我没去学校. Y el contrario: «昨天我了去…» — 了 va DETRÁS del verbo, pegado a él, nunca delante.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'La regla de pronunciación de la unidad ya no es una letra sino la frase entera: la ENTONACIÓN. En chino no puedes subir la voz al final para preguntar, porque subir es el segundo tono y cambiarías la última palabra. La pregunta la hace 吗 (o la palabra interrogativa), no la melodía.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Cada sílaba conserva su tono; la frase entera puede subir o bajar un poco de nivel, pero los contornos no se tocan. 你去了吗？ termina en el neutro de 吗, bajito, aunque sea pregunta.'],
                    ['es' => 'Lo que sí hace la frase: acorta las sílabas en medio y alarga la última. Y en una lista (爸爸、妈妈、姐姐), cada elemento se dice con sus tonos completos.'],
                    ['es' => 'El énfasis no se hace con volumen sino con palabras: 太…了, 很, 也, 都. «¡Qué bueno!» es 太好了, no «hǎo» gritado.'],
                ]],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => 'Caracteres de la unidad: 了 le · 昨 zuó · 今 jīn · 明 míng (el sol 日 y la luna 月: «brillante», y «mañana») · 日 rì (sol, día)'],
                ]],
            ],
        ],

        // ============ ZH U9 · A1.IO.1 · reparar la conversación (repaso y proyecto) ============
        [
            'lengua' => 'zh', 'descriptor' => 'A1.IO.1', 'slug' => 'wo-ting-bu-dong',
            'titulo' => ['es' => '我听不懂 · Wǒ tīng bu dǒng · No entiendo'],
            'resumen' => ['es' => 'Las seis frases que mantienen viva una conversación cuando no entiendes: pedir que repitan, que hablen despacio, preguntar cómo se dice algo. Y el proyecto final del curso.'],
            'bloques' => [
                ['tipo' => 'parrafo', 'texto' => ['es' => 'El descriptor A1.IO.1 del MCER no pide que entiendas todo: pide que puedas interactuar «siempre que la otra persona hable despacio, repita o reformule». Esta unidad es cómo CONSEGUIR que lo haga — en chino, sin cambiar de idioma.']],

                ['tipo' => 'lista', 'ordenada' => false, 'items' => [
                    ['es' => '我听不懂。 Wǒ tīng bu dǒng. — No entiendo (lo que oigo). Literalmente «escucho-no-comprendo». El 不 en medio va neutro.'],
                    ['es' => '请再说一遍。 Qǐng zài shuō yí biàn. — Repita, por favor (otra vez decir una vez).'],
                    ['es' => '请说慢一点儿。 Qǐng shuō màn yìdiǎnr. — Hable más despacio, por favor.'],
                    ['es' => '«mochila» 用中文怎么说？ … yòng Zhōngwén zěnme shuō? — ¿Cómo se dice «mochila» en chino?'],
                    ['es' => '这是什么意思？ Zhè shì shénme yìsi? — ¿Qué significa esto?'],
                    ['es' => '我知道 / 我不知道。 Wǒ zhīdào / wǒ bù zhīdào. — Lo sé / no lo sé. · 对 duì — correcto, sí. · 没问题 méi wèntí — sin problema.'],
                ]],

                ['tipo' => 'aviso', 'variante' => 'ojo', 'texto' => ['es' => 'Hay dos «no entiendo» y no son iguales: 听不懂 tīng bu dǒng es «no entiendo lo que OIGO» (habla muy rápido); 看不懂 kàn bu dǒng es «no entiendo lo que LEO» (un carácter que no conoces). Elegir el verbo correcto ya le dice al otro qué tiene que hacer: repetir, o escribirlo en pinyin.']],

                ['tipo' => 'ejemplo', 'titulo' => ['es' => 'Reparar la conversación'], 'pasos' => [
                    ['texto' => ['es' => '王老师 — Sofía，你周末做了什么？ (Sofía, ¿qué hiciste el fin de semana?) ← 周末 zhōumò no lo has visto.']],
                    ['texto' => ['es' => 'Sofía — 对不起，老师，我听不懂。请再说一遍。 Duìbuqǐ, lǎoshī, wǒ tīng bu dǒng. Qǐng zài shuō yí biàn.']],
                    ['texto' => ['es' => '王老师 — 周—末。星期六和星期天。 Zhōu-mò. Xīngqīliù hé xīngqītiān.  (Fin de semana: sábado y domingo.)']],
                    ['texto' => ['es' => 'Sofía — 啊，周末！我去了公园，也看了一个电影。 À, zhōumò! Wǒ qù le gōngyuán, yě kàn le yí gè diànyǐng.']],
                    ['texto' => ['es' => '王老师 — 很好！ Hěn hǎo!']],
                    ['texto' => ['es' => 'Sofía — 老师，«fin de semana» 用中文怎么说？周末？ Lǎoshī, «fin de semana» yòng Zhōngwén zěnme shuō? Zhōumò?  — 对！ Duì!']],
                ]],

                ['tipo' => 'aviso', 'variante' => 'truco', 'texto' => ['es' => 'Repetir la palabra nueva con tono de pregunta —«周末？»— es la herramienta más rentable de esta unidad: confirmas que la has oído bien, la practicas, y le das al otro la oportunidad de corregirte el tono. Y como la pregunta se hace con la entonación de 吗 y no subiendo la voz, di «Zhōumò ma?» o simplemente «Zhōumò?» con los tonos intactos.']],

                ['tipo' => 'aviso', 'variante' => 'error-tipico', 'texto' => ['es' => 'Cambiar a español o a inglés en cuanto una palabra se escapa. Con 听不懂, 请再说一遍 y …怎么说 puedes seguir en chino aunque no entiendas la mitad. Eso es exactamente el A1: no es saber, es saber seguir.']],

                ['tipo' => 'parrafo', 'texto' => ['es' => 'EL PROYECTO FINAL. Una presentación de un minuto y una nota escrita, en chino, con todo lo que sabes hacer. Pinyin con tonos; caracteres, los que tengas (y los cuarenta y cinco del curso deberían salir). Se graba y la escucha tu profesor/a —solo él o ella y tú—; no se comparte.']],

                ['tipo' => 'lista', 'ordenada' => true, 'items' => [
                    ['es' => 'Quién eres: nombre, de dónde, edad, qué eres (U1–U2). 我叫…，我是厄瓜多尔人，我十五岁。'],
                    ['es' => 'Tu familia: cuántos sois y quiénes, con 口 y 个 (U2).'],
                    ['es' => 'Tu día: tres frases con hora y lugar, en el orden QUIÉN + CUÁNDO + 在 DÓNDE + VERBO (U3).'],
                    ['es' => 'Lo que te gusta y lo que no, con 喜欢 y con un adjetivo + 很 (U4).'],
                    ['es' => 'Tu ciudad: cómo es y qué hay, con 有 (U5).'],
                    ['es' => 'Qué hiciste ayer: dos frases con 了 y una con 没 (U8).'],
                    ['es' => 'Y una frase de reparación de las de esta unidad, colocada donde te haga falta.'],
                ]],
            ],
        ],
    ],

    'items' => [

        // ============ A1.IO.3 — cuatro ítems: el dominio se alcanza ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 1,
            // Este ítem es el que justifica que el motor distinga acento de
            // palabra: quien escribe «e» no ha escrito otra palabra, ha escrito
            // LA palabra sin su acento — y «e» («y») existe, así que su error
            // tiene sentido y hay que nombrárselo.
            'consigna' => ['es' => 'Completa: « Lei ___ la professoressa Rossi. »  (Ella ES la profesora Rossi.)'],
            'aceptadas' => ['è'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 2,
            'consigna' => ['es' => 'Ordena las palabras para devolver la pregunta: «¿Y tú cómo te llamas?». La mayúscula inicial y el signo de interrogación ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['it' => 'e']],
                ['clave' => 'w2', 'texto' => ['it' => 'tu']],
                ['clave' => 'w3', 'texto' => ['it' => 'come']],
                ['clave' => 'w4', 'texto' => ['it' => 'ti']],
                ['clave' => 'w5', 'texto' => ['it' => 'chiami']],
            ],
            'secuencias' => [['w1', 'w2', 'w3', 'w4', 'w5']],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 3,
            'consigna' => ['es' => 'Empareja cada fórmula con lo que significa.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['it' => 'buongiorno']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['it' => 'buonasera']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['it' => 'arrivederci']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['it' => 'prego']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['it' => 'piacere']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'buenos días']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'buenas tardes']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'hasta la vista']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'de nada']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'encantado']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 4,
            // Los tres distractores son los tres errores reales: come y scusa
            // son la c dura que el alumno confunde por analogía, y chiamo es el
            // error estrella — el que lleva la h y que todo hispanohablante
            // ablanda.
            'consigna' => ['es' => '¿En cuál de estas palabras la «c» suena como la «ch» del español?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'ciao']],
                ['clave' => 'b', 'texto' => ['it' => 'come']],
                ['clave' => 'c', 'texto' => ['it' => 'scusa']],
                ['clave' => 'd', 'texto' => ['it' => 'mi chiamo']],
            ],
            'correcta' => 'a',
        ],

        // ============ A1.CE.1 — dos ítems: letreros de verdad ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Lees «USCITA» sobre una puerta del aeropuerto. ¿Qué señala?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'La salida']],
                ['clave' => 'b', 'texto' => ['es' => 'La entrada']],
                ['clave' => 'c', 'texto' => ['es' => 'La aduana']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'it', 'seq' => 2,
            // «Vietato» no se parece a nada en español: el ítem comprueba que
            // el alumno no está adivinando solo por los cognados.
            'consigna' => ['es' => 'En la pared de un bar hay un cartel: «VIETATO FUMARE». ¿Qué dice?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Prohibido fumar']],
                ['clave' => 'b', 'texto' => ['es' => 'Zona de fumadores']],
                ['clave' => 'c', 'texto' => ['es' => 'Salida de emergencia']],
            ],
            'correcta' => 'a',
        ],

        // ============ A1.EE.2 — dos ítems: el formulario y el género ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.2', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Rellena la ficha de Sofía, que es de Quito.  Nome: Sofía · Nazionalità: ______'],
            'aceptadas' => ['ecuadoriana'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.2', 'lengua' => 'it', 'seq' => 2,
            'consigna' => ['es' => 'Ahora la de Marco, que es de Torino.  Nome: Marco · Nazionalità: ______'],
            'aceptadas' => ['italiano'],
        ],

        // ============ U2 · A1.PO.1 — cinco ítems ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Completa: « Mia sorella ___ dodici anni. »  (Mi hermana TIENE doce años.)'],
            'aceptadas' => ['ha'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'it', 'seq' => 2,
            'consigna' => ['es' => 'Completa: « Io ___ due fratelli. »  (Yo TENGO dos hermanos.)'],
            'aceptadas' => ['ho'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.1', 'lengua' => 'it', 'seq' => 3,
            'consigna' => ['es' => 'Empareja cada palabra con el parentesco que nombra.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['it' => 'nonna']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['it' => 'sorella']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['it' => 'zio']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['it' => 'figlio']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['it' => 'moglie']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'abuela']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'hermana']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'tío']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'hijo']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'mujer, esposa']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.1', 'lengua' => 'it', 'seq' => 4,
            // Sin puntuación ni mayúsculas en las fichas: ver la nota 2 de la
            // cabecera. Aquí además importa porque «mio fratello» va junto y
            // sin artículo, y esa es la regla que el ejercicio comprueba.
            'consigna' => ['es' => 'Ordena las palabras para decir «Mi hermano tiene dieciséis años». La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['it' => 'mio']],
                ['clave' => 'w2', 'texto' => ['it' => 'fratello']],
                ['clave' => 'w3', 'texto' => ['it' => 'ha']],
                ['clave' => 'w4', 'texto' => ['it' => 'sedici']],
                ['clave' => 'w5', 'texto' => ['it' => 'anni']],
            ],
            'secuencias' => [['w1', 'w2', 'w3', 'w4', 'w5']],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.PO.1', 'lengua' => 'it', 'seq' => 5,
            // La doble no es ortografía decorativa: «sonno» es otra palabra.
            // El distractor no es relleno — es lo que el alumno escribe de
            // verdad cuando no ha interiorizado la doble.
            'consigna' => ['es' => 'Quieres escribir «yo soy». ¿Cuál de estas dos es?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'sono']],
                ['clave' => 'b', 'texto' => ['it' => 'sonno']],
                ['clave' => 'c', 'texto' => ['it' => 'son']],
            ],
            'correcta' => 'a',
        ],

        // ============ U2 · A1.IO.3 — dos ítems más (siguen a los de la U1) ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 5,
            'consigna' => ['es' => 'Te preguntan «Quanti anni hai?». ¿Qué contestas si tienes quince?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'Ho quindici anni.']],
                ['clave' => 'b', 'texto' => ['it' => 'Sono quindici anni.']],
                ['clave' => 'c', 'texto' => ['it' => 'Ho quindici.']],
                ['clave' => 'd', 'texto' => ['it' => 'Sono quindici.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'it', 'seq' => 6,
            'consigna' => ['es' => 'Completa tu respuesta: « — Quanti anni hai?  — ___ sedici anni. »'],
            'aceptadas' => ['ho'],
        ],

        // ============ U2 · A1.CE.2 — dos ítems sobre la misma postal ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Lee la postal: «Ciao Marco! Sono a Roma con mia sorella e mia zia. Mia nonna è di Roma. Ciao, Anna.» ¿Con quién está Anna en Roma?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Con su hermana y su tía']],
                ['clave' => 'b', 'texto' => ['es' => 'Con su abuela']],
                ['clave' => 'c', 'texto' => ['es' => 'Con Marco']],
                ['clave' => 'd', 'texto' => ['es' => 'Sola']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'it', 'seq' => 2,
            // El dato está en la tercera línea, no en la que habla del viaje:
            // obliga a leer entero en vez de quedarse con lo primero que suena.
            'consigna' => ['es' => 'En la misma postal, ¿quién es de Roma?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Su abuela']],
                ['clave' => 'b', 'texto' => ['es' => 'Su tía']],
                ['clave' => 'c', 'texto' => ['es' => 'Anna']],
                ['clave' => 'd', 'texto' => ['es' => 'Marco']],
            ],
            'correcta' => 'a',
        ],

        // ============ U3 · A1.PO.2 — cinco ítems ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Completa con «parlare»: « Tu ___ italiano? »  (¿Tú HABLAS italiano?)'],
            'aceptadas' => ['parli'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 2,
            'consigna' => ['es' => 'Completa con «abitare»: « Io ___ a Quito. »  (Yo VIVO en Quito.)'],
            'aceptadas' => ['abito'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 3,
            // El tercero de la serie: la tercera persona. Con los tres huecos
            // el alumno ha conjugado el singular entero, no reconocido una forma.
            'consigna' => ['es' => 'Completa con «mangiare»: « Mia sorella ___ a scuola. »  (Mi hermana COME en el colegio.)'],
            'aceptadas' => ['mangia'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 4,
            // Todo en minúscula y sin puntuación (nota 2 de la cabecera). Aquí
            // hay UN solo orden natural: el complemento de tiempo va delante.
            'consigna' => ['es' => 'Ordena las palabras para decir «Por la mañana estudio italiano». La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['it' => 'la']],
                ['clave' => 'w2', 'texto' => ['it' => 'mattina']],
                ['clave' => 'w3', 'texto' => ['it' => 'studio']],
                ['clave' => 'w4', 'texto' => ['it' => 'italiano']],
            ],
            'secuencias' => [['w1', 'w2', 'w3', 'w4']],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 5,
            // Dos falsos amigos dentro de las cinco parejas (guardare, abitare):
            // el ítem castiga al que empareja por parecido en vez de por
            // significado.
            'consigna' => ['es' => 'Empareja cada verbo con lo que significa. Cuidado: dos de ellos no significan lo que parecen.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['it' => 'guardare']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['it' => 'abitare']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['it' => 'lavorare']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['it' => 'ascoltare']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['it' => 'tornare']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'mirar']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'vivir en un lugar']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'trabajar']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'escuchar']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'volver']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        // ============ U3 · A1.IO.2 — tres ítems: la hora ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Son las ocho. Te preguntan «Che ore sono?». ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'Sono le otto.']],
                ['clave' => 'b', 'texto' => ['it' => 'È le otto.']],
                ['clave' => 'c', 'texto' => ['it' => 'Sono otto.']],
                ['clave' => 'd', 'texto' => ['it' => 'Ho le otto.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 2,
            'consigna' => ['es' => 'Completa la pregunta: « A che ___ mangi? »  (¿A qué HORA comes?)'],
            'aceptadas' => ['ora'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 3,
            // Distingue «è l'una» (singular) de «sono le…» (plural): la
            // excepción de la unidad, comprobada directamente.
            'consigna' => ['es' => 'Es la una de la tarde. ¿Cuál es la forma correcta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'È l\'una.']],
                ['clave' => 'b', 'texto' => ['it' => 'Sono l\'una.']],
                ['clave' => 'c', 'texto' => ['it' => 'Sono le una.']],
            ],
            'correcta' => 'a',
        ],

        // ============ U3 · A1.CE.2 — un ítem más sobre la rutina escrita ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'it', 'seq' => 3,
            // «non… mai» es lo que hay que leer bien: quien traduce palabra por
            // palabra entiende lo contrario.
            'consigna' => ['es' => 'Lees en el mensaje de Marco: «Il sabato non studio mai, gioco con mio fratello». ¿Qué hace Marco los sábados?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Juega con su hermano y no estudia']],
                ['clave' => 'b', 'texto' => ['es' => 'Estudia siempre']],
                ['clave' => 'c', 'texto' => ['es' => 'Estudia con su hermano']],
            ],
            'correcta' => 'a',
        ],

        // ============ U4 · A1.IO.2 — tres ítems más: gustos ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 4,
            'consigna' => ['es' => 'Completa: « Mi ___ i gelati. »  (Me GUSTAN los helados.)'],
            'aceptadas' => ['piacciono'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 5,
            'consigna' => ['es' => 'Completa la pregunta: « ___ piace la pizza? »  (¿TE gusta la pizza?)'],
            'aceptadas' => ['ti'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 6,
            'consigna' => ['es' => 'Te preguntan «Ti piacciono i cani?» y te encantan. ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'Sì, mi piacciono molto.']],
                ['clave' => 'b', 'texto' => ['it' => 'Sì, mi piace molto.']],
                ['clave' => 'c', 'texto' => ['it' => 'Sì, io piaccio molto.']],
                ['clave' => 'd', 'texto' => ['it' => 'Sì, ti piacciono molto.']],
            ],
            'correcta' => 'a',
        ],

        // ============ U4 · A1.PO.2 — tres ítems más ============

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 6,
            'consigna' => ['es' => 'Ordena las palabras para decir «Me gusta escuchar música». La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['it' => 'mi']],
                ['clave' => 'w2', 'texto' => ['it' => 'piace']],
                ['clave' => 'w3', 'texto' => ['it' => 'ascoltare']],
                ['clave' => 'w4', 'texto' => ['it' => 'musica']],
            ],
            'secuencias' => [['w1', 'w2', 'w3', 'w4']],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 7,
            'consigna' => ['es' => 'Empareja cada adjetivo con su contrario en español.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['it' => 'bello']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['it' => 'noioso']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['it' => 'difficile']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['it' => 'buono']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['it' => 'brutto']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'bonito']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'aburrido']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'difícil']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'bueno']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'feo']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 8,
            // El punto de pronunciación, comprobado sobre una palabra que el
            // alumno lleva usando desde la U2 y probablemente diciendo mal.
            'consigna' => ['es' => '¿Cómo suena el «gl» de «famiglia»?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Como la «ll» argentina, sin g: fa-MI-lia']],
                ['clave' => 'b', 'texto' => ['es' => 'Con g de gato: fa-MI-glia']],
                ['clave' => 'c', 'texto' => ['es' => 'Como una «y»: fa-MI-ya']],
            ],
            'correcta' => 'a',
        ],

        // ============ U4 · A1.EE.1 — dos ítems: la nota corta ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.1', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Estás escribiendo una nota a Anna. Completa: « Ciao Anna! Mi ___ molto il calcio. »  (Me GUSTA mucho el fútbol.)'],
            'aceptadas' => ['piace'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.EE.1', 'lengua' => 'it', 'seq' => 2,
            // La despedida es la parte de la nota que más se olvida o se
            // españoliza («Chao», «Saludos»).
            'consigna' => ['es' => 'Vas a cerrar tu nota a un amigo. ¿Cuál es la despedida correcta en italiano?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'A presto, Sofía.']],
                ['clave' => 'b', 'texto' => ['it' => 'Saludos, Sofía.']],
                ['clave' => 'c', 'texto' => ['it' => 'Arrivederci, Sofía.']],
                ['clave' => 'd', 'texto' => ['it' => 'Piacere, Sofía.']],
            ],
            'correcta' => 'a',
        ],

        // ============ U5 · A1.CE.3 — tres ítems: seguir indicaciones ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.3', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Lees esta indicación: «Vai dritto, poi a sinistra. La farmacia è a destra, vicino alla banca.» ¿Dónde está la farmacia al final del recorrido?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'A la derecha, cerca del banco']],
                ['clave' => 'b', 'texto' => ['es' => 'A la izquierda, cerca del banco']],
                ['clave' => 'c', 'texto' => ['es' => 'Recto, lejos del banco']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.CE.3', 'lengua' => 'it', 'seq' => 2,
            'consigna' => ['es' => 'Completa la indicación: « Vai ___ e poi a destra. »  (Sigue RECTO y luego a la derecha.)'],
            'aceptadas' => ['dritto'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.3', 'lengua' => 'it', 'seq' => 3,
            // «Lontano» contra «vicino»: quien lee por encima se queda con el
            // nombre del sitio y se pierde la distancia, que es el dato.
            'consigna' => ['es' => 'En un cartel del hotel lees: «La stazione è lontano. Il museo è qui vicino, a sinistra.» ¿A dónde puedes ir andando en dos minutos?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Al museo']],
                ['clave' => 'b', 'texto' => ['es' => 'A la estación']],
                ['clave' => 'c', 'texto' => ['es' => 'A los dos']],
            ],
            'correcta' => 'a',
        ],

        // ============ U5 · A1.IO.2 — dos ítems más: preguntar por un sitio ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 7,
            // «è» con acento, otra vez: la palabra más fallada del curso vuelve
            // dentro de una contracción.
            'consigna' => ['es' => 'Completa la pregunta a un desconocido: « Scusi, dov\'___ la stazione? »'],
            'aceptadas' => ['è'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 8,
            'consigna' => ['es' => 'Quieres preguntarle a un señor mayor en la calle si hay una farmacia cerca. ¿Cuál es la forma correcta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'Scusi, c\'è una farmacia qui vicino?']],
                ['clave' => 'b', 'texto' => ['it' => 'Scusa, c\'è una farmacia qui vicino?']],
                ['clave' => 'c', 'texto' => ['it' => 'Scusi, ci sono una farmacia qui vicino?']],
            ],
            'correcta' => 'a',
        ],

        // ============ U5 · A1.PO.1 — dos ítems más: describir mi barrio ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'it', 'seq' => 6,
            'consigna' => ['es' => 'Completa: « A Quito ___ molti parchi. »  (En Quito HAY muchos parques.)'],
            'aceptadas' => ['ci sono'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.1', 'lengua' => 'it', 'seq' => 7,
            'consigna' => ['es' => 'Empareja cada lugar con su nombre en español.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['it' => 'la chiesa']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['it' => 'la stazione']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['it' => 'la via']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['it' => 'l\'ospedale']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['it' => 'la piazza']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'la iglesia']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'la estación']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'la calle']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'el hospital']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'la plaza']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        // ============ U6 · A1.IO.2 — dos ítems más: pedir ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 9,
            'consigna' => ['es' => 'Pide un helado con educación: « ___ un gelato, per favore. »  (QUISIERA un helado, por favor.)'],
            'aceptadas' => ['vorrei'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 10,
            // El partitivo bien puesto contra el sustantivo desnudo y contra el
            // artículo sin «di»: los dos errores reales del hispanohablante.
            'consigna' => ['es' => 'Quieres pedir un poco de pan y un poco de queso. ¿Cuál es la forma correcta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'Vorrei del pane e del formaggio.']],
                ['clave' => 'b', 'texto' => ['it' => 'Vorrei pane e formaggio.']],
                ['clave' => 'c', 'texto' => ['it' => 'Vorrei il pane e il formaggio.']],
            ],
            'correcta' => 'a',
        ],

        // ============ U6 · A1.CE.1 — dos ítems más: menú y carteles ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'it', 'seq' => 3,
            'consigna' => ['es' => 'En el menú lees: «Spaghetti al pomodoro — 9 € · Risotto ai funghi — 11 € · Acqua — 2 €». Pides spaghetti y agua. ¿Cuánto pagas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => '11 €']],
                ['clave' => 'b', 'texto' => ['es' => '13 €']],
                ['clave' => 'c', 'texto' => ['es' => '9 €']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'it', 'seq' => 4,
            'consigna' => ['es' => 'Llegas a un restaurante y en la puerta pone «CHIUSO». ¿Qué haces?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Buscar otro: está cerrado']],
                ['clave' => 'b', 'texto' => ['es' => 'Entrar: está abierto']],
                ['clave' => 'c', 'texto' => ['es' => 'Empujar la puerta: es una indicación']],
            ],
            'correcta' => 'a',
        ],

        // ============ U6 · A1.PO.2 — dos ítems más: qué como ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 9,
            'consigna' => ['es' => 'Completa con el partitivo: « A colazione mangio ___ pane. »  (En el desayuno como [un poco de] pan.)'],
            'aceptadas' => ['del'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 10,
            'consigna' => ['es' => 'Empareja cada comida con su nombre en español.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['it' => 'il formaggio']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['it' => 'il pesce']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['it' => 'le uova']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['it' => 'lo zucchero']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['it' => 'la cena']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'el queso']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'el pescado']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'los huevos']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'el azúcar']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'la cena']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        // ============ U7 · A1.IO.2 — dos ítems más: precios y tiempo ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 11,
            'consigna' => ['es' => 'Quieres saber el precio de una chaqueta. ¿Qué preguntas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'Quanto costa questa giacca?']],
                ['clave' => 'b', 'texto' => ['it' => 'Quanto è questa giacca?']],
                ['clave' => 'c', 'texto' => ['it' => 'Che costa questa giacca?']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 12,
            'consigna' => ['es' => 'Completa la pregunta: « Che tempo ___ oggi? »  (¿Qué tiempo HACE hoy?)'],
            'aceptadas' => ['fa'],
        ],

        // ============ U7 · A1.PO.1 — dos ítems más: concordancia y clima ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'it', 'seq' => 8,
            // Femenino plural de un adjetivo en -o: la forma que el español
            // no tiene (haría «rojas» con -s).
            'consigna' => ['es' => 'Completa con «rosso» en la forma correcta: « Le scarpe sono ___. »  (Los zapatos son ROJOS — y «scarpe» es femenino plural.)'],
            'aceptadas' => ['rosse'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.1', 'lengua' => 'it', 'seq' => 9,
            // TRES órdenes válidos: el complemento de tiempo y el de lugar se
            // mueven libremente. Es el primer ítem del curso donde el conjunto
            // de secuencias tiene más de un elemento — la razón de que #27 lo
            // diseñara como conjunto. Lo que NO vale: separar «la» de «sera», o
            // «a» de «Quito».
            'consigna' => ['es' => 'Ordena las palabras para decir «En Quito hace frío por la noche». Hay más de un orden correcto; cualquiera vale. La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['it' => 'a']],
                ['clave' => 'w2', 'texto' => ['it' => 'quito']],
                ['clave' => 'w3', 'texto' => ['it' => 'fa']],
                ['clave' => 'w4', 'texto' => ['it' => 'freddo']],
                ['clave' => 'w5', 'texto' => ['it' => 'la']],
                ['clave' => 'w6', 'texto' => ['it' => 'sera']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4', 'w5', 'w6'],
                ['w5', 'w6', 'w1', 'w2', 'w3', 'w4'],
                ['w1', 'w2', 'w5', 'w6', 'w3', 'w4'],
            ],
        ],

        // ============ U7 · A1.CE.1 y A1.CE.2 — una etiqueta y un mensaje ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'it', 'seq' => 5,
            'consigna' => ['es' => 'En la etiqueta de una camiseta lees: «Taglia M · 100% cotone · 25 €». ¿Qué sabes de la camiseta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Es talla M, de algodón, y cuesta 25 €']],
                ['clave' => 'b', 'texto' => ['es' => 'Es talla M, de lana, y cuesta 25 €']],
                ['clave' => 'c', 'texto' => ['es' => 'Es talla 25 y cuesta 100 €']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'it', 'seq' => 4,
            'consigna' => ['es' => 'Anna te escribe desde Roma: «Qui fa molto caldo e c\'è il sole. A Quito piove?». ¿Qué tiempo hace en Roma?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Calor y sol']],
                ['clave' => 'b', 'texto' => ['es' => 'Frío y lluvia']],
                ['clave' => 'c', 'texto' => ['es' => 'Está nublado']],
            ],
            'correcta' => 'a',
        ],

        // ============ U8 · A1.PO.2 — tres ítems más: el pasado ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 11,
            'consigna' => ['es' => 'Completa con «avere»: « Ieri ___ mangiato una pizza. »  (Ayer comí una pizza.)'],
            'aceptadas' => ['ho'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 12,
            // Formar el participio, no reconocerlo.
            'consigna' => ['es' => 'Completa con el participio de «guardare»: « Ieri sera Marco ha ___ la TV. »  (Anoche Marco vio la tele.)'],
            'aceptadas' => ['guardato'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'it', 'seq' => 13,
            // Dos órdenes válidos: «ieri» va al principio o al final. Lo que
            // no puede pasar es que «ho» y «studiato» se separen.
            'consigna' => ['es' => 'Ordena las palabras para decir «Ayer estudié italiano». Hay dos órdenes correctos. La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['it' => 'ieri']],
                ['clave' => 'w2', 'texto' => ['it' => 'ho']],
                ['clave' => 'w3', 'texto' => ['it' => 'studiato']],
                ['clave' => 'w4', 'texto' => ['it' => 'italiano']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4'],
                ['w2', 'w3', 'w4', 'w1'],
            ],
        ],

        // ============ U8 · A1.IO.2 — un ítem más: preguntar por el pasado ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'it', 'seq' => 13,
            'consigna' => ['es' => 'Te preguntan «Cosa hai mangiato ieri?» (¿Qué comiste ayer?). Comiste pasta. ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'Ho mangiato la pasta.']],
                ['clave' => 'b', 'texto' => ['it' => 'Sono mangiato la pasta.']],
                ['clave' => 'c', 'texto' => ['it' => 'Ho mangiare la pasta.']],
                ['clave' => 'd', 'texto' => ['it' => 'Mangio la pasta.']],
            ],
            'correcta' => 'a',
        ],

        // ============ U8 · A1.EE.1 y A1.CE.2 — escribir y leer el pasado ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.1', 'lengua' => 'it', 'seq' => 3,
            'consigna' => ['es' => 'Escribes una postal desde Roma. Completa con «visitare»: « Ciao Marco! Ho ___ Roma con mia zia. È bellissima! »'],
            'aceptadas' => ['visitato'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'it', 'seq' => 5,
            // «Prima» y «poi» ordenan los hechos: leer el mensaje bien es
            // reconstruir la secuencia, no cazar un nombre.
            'consigna' => ['es' => 'Lees el mensaje de Anna: «Ieri prima ho studiato, poi ho telefonato a mia nonna e dopo ho guardato un film». ¿Qué hizo Anna en segundo lugar?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Llamó a su abuela']],
                ['clave' => 'b', 'texto' => ['es' => 'Estudió']],
                ['clave' => 'c', 'texto' => ['es' => 'Vio una película']],
            ],
            'correcta' => 'a',
        ],

        // ============ U9 · A1.IO.1 — tres ítems: reparar la conversación ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.1', 'lengua' => 'it', 'seq' => 1,
            'consigna' => ['es' => 'Un italiano te dice algo muy rápido y no entiendes nada. ¿Qué dices para que la conversación siga en italiano?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'Scusi, non ho capito. Più lentamente, per favore.']],
                ['clave' => 'b', 'texto' => ['it' => 'No entiendo, ¿puede repetir?']],
                ['clave' => 'c', 'texto' => ['it' => 'Sì, sì, va bene.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.1', 'lengua' => 'it', 'seq' => 2,
            'consigna' => ['es' => 'Completa: « Più ___, per favore. »  (Más DESPACIO, por favor.)'],
            'aceptadas' => ['lentamente'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.1', 'lengua' => 'it', 'seq' => 3,
            // Contestar «sì, va bene» a algo que no entendiste es el error que
            // hace que la conversación se hunda dos frases más tarde.
            'consigna' => ['es' => 'No sabes cómo se dice «mochila» en italiano y la necesitas en la frase. ¿Qué preguntas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['it' => 'Come si dice «mochila» in italiano?']],
                ['clave' => 'b', 'texto' => ['it' => 'Cosa significa «mochila»?']],
                ['clave' => 'c', 'texto' => ['it' => 'Può ripetere «mochila»?']],
            ],
            'correcta' => 'a',
        ],

        // ================================================================
        // ======================= FRANCÉS · A1 ============================
        // ================================================================

        // ============ FR U1 · A1.IO.3 — cuatro ítems ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'fr', 'seq' => 1,
            'consigna' => ['es' => 'Completa con «être»: « Je ___ Sofía. »  (Yo SOY Sofía.)'],
            'aceptadas' => ['suis'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.IO.3', 'lengua' => 'fr', 'seq' => 2,
            // DOS órdenes válidos: en francés hablado la pregunta se hace con
            // «comment» delante o detrás. Sin puntuación en las fichas.
            'consigna' => ['es' => 'Ordena las palabras para preguntar «¿Cómo te llamas?» (de tú). Hay dos órdenes correctos. La mayúscula y el signo ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['fr' => 'comment']],
                ['clave' => 'w2', 'texto' => ['fr' => 'tu']],
                ['clave' => 'w3', 'texto' => ['fr' => 't\'appelles']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3'],
                ['w2', 'w3', 'w1'],
            ],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.IO.3', 'lengua' => 'fr', 'seq' => 3,
            'consigna' => ['es' => 'Empareja cada fórmula con lo que significa.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['fr' => 'bonsoir']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['fr' => 'au revoir']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['fr' => 'enchanté']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['fr' => 's\'il vous plaît']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['fr' => 'pardon']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'buenas tardes']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'adiós']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'encantado']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'por favor']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'perdón']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.3', 'lengua' => 'fr', 'seq' => 4,
            // El punto de pronunciación: tres finales mudas y una que suena.
            'consigna' => ['es' => '¿En cuál de estas palabras SÍ se pronuncia la última letra?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'bonjour']],
                ['clave' => 'b', 'texto' => ['fr' => 'salut']],
                ['clave' => 'c', 'texto' => ['fr' => 'Paris']],
                ['clave' => 'd', 'texto' => ['fr' => 'comment']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U1 · A1.CE.1 — dos ítems: letreros ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'fr', 'seq' => 1,
            'consigna' => ['es' => 'Lees «SORTIE» sobre una puerta del aeropuerto. ¿Qué señala?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'La salida']],
                ['clave' => 'b', 'texto' => ['es' => 'La entrada']],
                ['clave' => 'c', 'texto' => ['es' => 'La aduana']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'fr', 'seq' => 2,
            'consigna' => ['es' => 'En la puerta de una tienda pone «FERMÉ». ¿Qué haces?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Buscar otra: está cerrada']],
                ['clave' => 'b', 'texto' => ['es' => 'Entrar: está abierta']],
                ['clave' => 'c', 'texto' => ['es' => 'Llamar al timbre: está en obras']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U1 · A1.EE.2 — dos ítems: la nacionalidad y el género ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.2', 'lengua' => 'fr', 'seq' => 1,
            // «équatorienne» lleva acento en la é: el normalizador distingue
            // «te falta el acento» de «esa palabra no es».
            'consigna' => ['es' => 'Rellena la ficha de Sofía, que es de Quito.  Nom : Sofía · Nationalité : ______'],
            'aceptadas' => ['équatorienne'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.2', 'lengua' => 'fr', 'seq' => 2,
            'consigna' => ['es' => 'Ahora la de Marc, que es de Lyon.  Nom : Marc · Nationalité : ______'],
            'aceptadas' => ['français'],
        ],

        // ============ FR U2 · A1.PO.1 — cinco ítems ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'fr', 'seq' => 1,
            'consigna' => ['es' => 'Completa con «avoir»: « Ma sœur ___ douze ans. »  (Mi hermana TIENE doce años.)'],
            'aceptadas' => ['a'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'fr', 'seq' => 2,
            // «j'ai» con apóstrofo: el normalizador iguala ’ y '. El alumno que
            // escribe «je ai» ha fallado la elisión, que es el contenido.
            'consigna' => ['es' => 'Completa: « ___ deux frères. »  (TENGO dos hermanos — pronombre y verbo juntos.)'],
            'aceptadas' => ['j\'ai'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.1', 'lengua' => 'fr', 'seq' => 3,
            'consigna' => ['es' => 'Empareja cada palabra con el parentesco que nombra.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['fr' => 'la grand-mère']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['fr' => 'la sœur']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['fr' => 'l\'oncle']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['fr' => 'le fils']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['fr' => 'la fille']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'la abuela']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'la hermana']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'el tío']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'el hijo']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'la hija']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.1', 'lengua' => 'fr', 'seq' => 4,
            'consigna' => ['es' => 'Ordena las palabras para decir «Mi hermano tiene dieciséis años». La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['fr' => 'mon']],
                ['clave' => 'w2', 'texto' => ['fr' => 'frère']],
                ['clave' => 'w3', 'texto' => ['fr' => 'a']],
                ['clave' => 'w4', 'texto' => ['fr' => 'seize']],
                ['clave' => 'w5', 'texto' => ['fr' => 'ans']],
            ],
            'secuencias' => [['w1', 'w2', 'w3', 'w4', 'w5']],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.PO.1', 'lengua' => 'fr', 'seq' => 5,
            // La nasal: la n no se pronuncia.
            'consigna' => ['es' => '¿Cómo se pronuncia «bon» en francés?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => '«bõ»: vocal nasal, sin cerrar la boca para la n']],
                ['clave' => 'b', 'texto' => ['es' => '«bon», como en español, con la n al final']],
                ['clave' => 'c', 'texto' => ['es' => '«bo», sin nada al final']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U2 · A1.IO.3 — dos ítems más ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.3', 'lengua' => 'fr', 'seq' => 5,
            'consigna' => ['es' => 'Te preguntan «Tu as quel âge ?». Tienes quince. ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'J\'ai quinze ans.']],
                ['clave' => 'b', 'texto' => ['fr' => 'Je suis quinze ans.']],
                ['clave' => 'c', 'texto' => ['fr' => 'Ai quinze ans.']],
                ['clave' => 'd', 'texto' => ['fr' => 'J\'ai quinze.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'fr', 'seq' => 6,
            // El artículo elidido delante de vocal.
            'consigna' => ['es' => 'Completa con el artículo: « C\'est ___ oncle de Marc. »  (Es EL tío de Marc — la palabra empieza por vocal.)'],
            'aceptadas' => ['l\''],
        ],

        // ============ FR U2 · A1.CE.2 — dos ítems sobre la postal ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'fr', 'seq' => 1,
            'consigna' => ['es' => 'Lee la postal: «Salut Marc ! Je suis à Lyon avec ma sœur et ma tante. Ma grand-mère est de Lyon. À bientôt, Anna.» ¿Con quién está Anna en Lyon?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Con su hermana y su tía']],
                ['clave' => 'b', 'texto' => ['es' => 'Con su abuela']],
                ['clave' => 'c', 'texto' => ['es' => 'Con Marc']],
                ['clave' => 'd', 'texto' => ['es' => 'Sola']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'fr', 'seq' => 2,
            'consigna' => ['es' => 'En la misma postal, ¿quién es de Lyon?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Su abuela']],
                ['clave' => 'b', 'texto' => ['es' => 'Su tía']],
                ['clave' => 'c', 'texto' => ['es' => 'Anna']],
                ['clave' => 'd', 'texto' => ['es' => 'Marc']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U3 · A1.PO.2 — cinco ítems ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 1,
            // La -s de «tu», que no se oye y por eso se olvida.
            'consigna' => ['es' => 'Completa con «parler»: « Tu ___ français ? »  (¿Tú HABLAS francés?)'],
            'aceptadas' => ['parles'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 2,
            // Elisión obligatoria: «je habite» es error, «j'habite» es la forma.
            'consigna' => ['es' => 'Completa con «habiter», pronombre incluido: « ___ à Quito. »  (VIVO en Quito.)'],
            'aceptadas' => ['j\'habite'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 3,
            'consigna' => ['es' => 'Completa con «manger»: « Ma sœur ___ à l\'école. »  (Mi hermana COME en el colegio.)'],
            'aceptadas' => ['mange'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 4,
            // Dos órdenes: el complemento de tiempo delante o detrás.
            'consigna' => ['es' => 'Ordena las palabras para decir «Por la mañana estudio francés». Hay dos órdenes correctos. La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['fr' => 'le']],
                ['clave' => 'w2', 'texto' => ['fr' => 'matin']],
                ['clave' => 'w3', 'texto' => ['fr' => 'j\'étudie']],
                ['clave' => 'w4', 'texto' => ['fr' => 'le']],
                ['clave' => 'w5', 'texto' => ['fr' => 'français']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4', 'w5'],
                ['w3', 'w4', 'w5', 'w1', 'w2'],
            ],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 5,
            'consigna' => ['es' => 'Empareja cada verbo con lo que significa. Cuidado: dos no significan lo que parecen.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['fr' => 'regarder']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['fr' => 'rentrer']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['fr' => 'travailler']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['fr' => 'écouter']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['fr' => 'habiter']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'mirar']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'volver a casa']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'trabajar']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'escuchar']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'vivir en un lugar']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        // ============ FR U3 · A1.IO.2 — tres ítems: la hora ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 1,
            'consigna' => ['es' => 'Son las ocho. Te preguntan «Quelle heure est-il ?». ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'Il est huit heures.']],
                ['clave' => 'b', 'texto' => ['fr' => 'Ils sont huit heures.']],
                ['clave' => 'c', 'texto' => ['fr' => 'Il est huit.']],
                ['clave' => 'd', 'texto' => ['fr' => 'C\'est huit heures.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 2,
            'consigna' => ['es' => 'Completa la pregunta: « À quelle ___ tu manges ? »  (¿A qué HORA comes?)'],
            'aceptadas' => ['heure'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 3,
            // La liaison, comprobada sobre la hora.
            'consigna' => ['es' => '¿Cómo suena «deux heures» en francés?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => '«deuzeur», enlazado, con la x sonando como z']],
                ['clave' => 'b', 'texto' => ['es' => '«deu eur», con un corte entre las dos palabras']],
                ['clave' => 'c', 'texto' => ['es' => '«deux eures», pronunciando la x como en español']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U3 · A1.CE.2 — un ítem más: ne… jamais ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'fr', 'seq' => 3,
            'consigna' => ['es' => 'Lees en el mensaje de Marc: «Le samedi je ne travaille jamais, je joue avec mon frère». ¿Qué hace Marc los sábados?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Juega con su hermano y no trabaja']],
                ['clave' => 'b', 'texto' => ['es' => 'Trabaja siempre']],
                ['clave' => 'c', 'texto' => ['es' => 'Trabaja con su hermano']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U4 · A1.IO.2 — tres ítems: gustos ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 4,
            'consigna' => ['es' => 'Completa con «aimer», pronombre incluido: « ___ les chiens. »  (ME GUSTAN los perros.)'],
            'aceptadas' => ['j\'aime'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 5,
            // Las dos piezas de la negación.
            'consigna' => ['es' => 'Completa la negación: « Je n\'aime ___ le football. »  (NO me gusta el fútbol.)'],
            'aceptadas' => ['pas'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 6,
            'consigna' => ['es' => 'Te preguntan «Tu aimes la pizza ?» y te encanta. ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'Oui, j\'aime beaucoup.']],
                ['clave' => 'b', 'texto' => ['fr' => 'Oui, aime beaucoup.']],
                ['clave' => 'c', 'texto' => ['fr' => 'Oui, me plaît beaucoup.']],
                ['clave' => 'd', 'texto' => ['fr' => 'Oui, j\'aime pas.']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U4 · A1.PO.2 — tres ítems más ============

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 6,
            'consigna' => ['es' => 'Ordena las palabras para decir «Me gusta escuchar música». La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['fr' => 'j\'aime']],
                ['clave' => 'w2', 'texto' => ['fr' => 'écouter']],
                ['clave' => 'w3', 'texto' => ['fr' => 'de']],
                ['clave' => 'w4', 'texto' => ['fr' => 'la']],
                ['clave' => 'w5', 'texto' => ['fr' => 'musique']],
            ],
            'secuencias' => [['w1', 'w2', 'w3', 'w4', 'w5']],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 7,
            'consigna' => ['es' => 'Empareja cada adjetivo con su significado.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['fr' => 'génial']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['fr' => 'ennuyeux']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['fr' => 'difficile']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['fr' => 'nul']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['fr' => 'facile']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'genial']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'aburrido']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'difícil']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'malísimo']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'fácil']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 8,
            // u frente a ou, sobre la pareja mínima de la lección.
            'consigna' => ['es' => '«Tu» (tú) y «tout» (todo). ¿Cuál es la diferencia al pronunciarlas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => '«Tu» lleva la u francesa (boca de u, lengua de i); «tout» lleva la u española']],
                ['clave' => 'b', 'texto' => ['es' => 'Se pronuncian igual; solo cambia la escritura']],
                ['clave' => 'c', 'texto' => ['es' => '«Tout» se pronuncia con la t final; «tu» no']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U4 · A1.EE.1 — dos ítems: la nota ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.1', 'lengua' => 'fr', 'seq' => 1,
            'consigna' => ['es' => 'Estás escribiendo una nota a Anna. Completa: « Salut Anna ! J\'aime beaucoup ___ football. »  (el artículo)'],
            'aceptadas' => ['le'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.EE.1', 'lengua' => 'fr', 'seq' => 2,
            'consigna' => ['es' => 'Vas a cerrar tu nota a un amigo. ¿Cuál es la despedida correcta en francés?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'À bientôt, Sofía.']],
                ['clave' => 'b', 'texto' => ['fr' => 'Saludos, Sofía.']],
                ['clave' => 'c', 'texto' => ['fr' => 'Enchantée, Sofía.']],
                ['clave' => 'd', 'texto' => ['fr' => 'Bonjour, Sofía.']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U5 · A1.CE.3 — tres ítems: seguir indicaciones ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.3', 'lengua' => 'fr', 'seq' => 1,
            'consigna' => ['es' => 'Lees esta indicación: «Tout droit, puis à gauche. La pharmacie est à droite, près de la banque.» ¿Dónde está la farmacia al final del recorrido?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'A la derecha, cerca del banco']],
                ['clave' => 'b', 'texto' => ['es' => 'A la izquierda, cerca del banco']],
                ['clave' => 'c', 'texto' => ['es' => 'Recto, lejos del banco']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.CE.3', 'lengua' => 'fr', 'seq' => 2,
            'consigna' => ['es' => 'Completa la indicación: « Tout ___, puis à droite. »  (RECTO, luego a la derecha.)'],
            'aceptadas' => ['droit'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.3', 'lengua' => 'fr', 'seq' => 3,
            'consigna' => ['es' => 'En un cartel del hotel lees: «La gare est loin. Le musée est près d\'ici, à gauche.» ¿A dónde puedes ir andando en dos minutos?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Al museo']],
                ['clave' => 'b', 'texto' => ['es' => 'A la estación']],
                ['clave' => 'c', 'texto' => ['es' => 'A los dos']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U5 · A1.IO.2 — dos ítems más ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 7,
            // «où» con acento grave: la palabra que motivó la regla del
            // normalizador. Sin acento, «ou» es «o».
            'consigna' => ['es' => 'Completa la pregunta a una desconocida: « Pardon madame, ___ est la gare ? »  (¿DÓNDE está la estación?)'],
            'aceptadas' => ['où'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 8,
            // «à» con ciudad, no «en».
            'consigna' => ['es' => 'Quieres decir «Vivo en Quito». ¿Cuál es la forma correcta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'J\'habite à Quito.']],
                ['clave' => 'b', 'texto' => ['fr' => 'J\'habite en Quito.']],
                ['clave' => 'c', 'texto' => ['fr' => 'J\'habite au Quito.']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U5 · A1.PO.1 — dos ítems más ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'fr', 'seq' => 6,
            // «de» tras negación.
            'consigna' => ['es' => 'Completa: « Il n\'y a pas ___ musée près d\'ici. »  (No hay museo cerca de aquí — el artículo cambia tras la negación.)'],
            'aceptadas' => ['de'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.1', 'lengua' => 'fr', 'seq' => 7,
            'consigna' => ['es' => 'Empareja cada lugar con su nombre en español.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['fr' => 'l\'église']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['fr' => 'la gare']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['fr' => 'la rue']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['fr' => 'l\'hôpital']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['fr' => 'la place']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'la iglesia']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'la estación']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'la calle']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'el hospital']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'la plaza']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        // ============ FR U6 · A1.IO.2 — dos ítems más: pedir ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 9,
            'consigna' => ['es' => 'Pide un helado con educación: « Je ___ une glace, s\'il vous plaît. »  (QUISIERA un helado, por favor.)'],
            'aceptadas' => ['voudrais'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 10,
            'consigna' => ['es' => 'Quieres pedir un poco de pan y un poco de queso. ¿Cuál es la forma correcta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'Je voudrais du pain et du fromage.']],
                ['clave' => 'b', 'texto' => ['fr' => 'Je voudrais pain et fromage.']],
                ['clave' => 'c', 'texto' => ['fr' => 'Je voudrais le pain et le fromage.']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U6 · A1.CE.1 — dos ítems más ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'fr', 'seq' => 3,
            'consigna' => ['es' => 'En la carta lees: «Poulet rôti — 12 € · Poisson du jour — 15 € · Eau — 2 €». Pides pollo y agua. ¿Cuánto pagas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => '14 €']],
                ['clave' => 'b', 'texto' => ['es' => '17 €']],
                ['clave' => 'c', 'texto' => ['es' => '12 €']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'fr', 'seq' => 4,
            'consigna' => ['es' => 'En la puerta de un restaurante pone «TIREZ». ¿Qué haces?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Tirar de la puerta hacia mí']],
                ['clave' => 'b', 'texto' => ['es' => 'Empujar la puerta']],
                ['clave' => 'c', 'texto' => ['es' => 'Buscar otra entrada: está cerrado']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U6 · A1.PO.2 — dos ítems más: qué como ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 9,
            'consigna' => ['es' => 'Completa con el partitivo: « Au petit-déjeuner, je mange ___ pain. »  (En el desayuno como [un poco de] pan.)'],
            'aceptadas' => ['du'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 10,
            'consigna' => ['es' => 'Empareja cada comida con su nombre en español.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['fr' => 'le fromage']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['fr' => 'le poisson']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['fr' => 'les œufs']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['fr' => 'le sucre']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['fr' => 'le dîner']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'el queso']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'el pescado']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'los huevos']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'el azúcar']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'la cena']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        // ============ FR U7 · A1.IO.2 — dos ítems más ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 11,
            'consigna' => ['es' => 'Quieres saber el precio de una chaqueta. ¿Qué preguntas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'Ça coûte combien, cette veste ?']],
                ['clave' => 'b', 'texto' => ['fr' => 'Combien est cette veste ?']],
                ['clave' => 'c', 'texto' => ['fr' => 'Quoi coûte cette veste ?']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 12,
            'consigna' => ['es' => 'Completa la pregunta: « Quel temps ___-il ? »  (¿Qué tiempo HACE?)'],
            'aceptadas' => ['fait'],
        ],

        // ============ FR U7 · A1.PO.1 — dos ítems más: concordancia y lugar del adjetivo ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'fr', 'seq' => 8,
            // Femenino con -e: la forma que cambia el sonido.
            'consigna' => ['es' => 'Completa con «vert» en la forma correcta: « La veste est ___. »  (La chaqueta es VERDE — y «veste» es femenino.)'],
            'aceptadas' => ['verte'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.1', 'lengua' => 'fr', 'seq' => 9,
            // «grande» DELANTE, «rouge» DETRÁS: las dos reglas de posición en
            // una frase. Un solo orden válido.
            'consigna' => ['es' => 'Ordena las palabras para decir «una gran plaza roja» (fíjate en dónde va cada adjetivo).'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['fr' => 'une']],
                ['clave' => 'w2', 'texto' => ['fr' => 'grande']],
                ['clave' => 'w3', 'texto' => ['fr' => 'place']],
                ['clave' => 'w4', 'texto' => ['fr' => 'rouge']],
            ],
            'secuencias' => [['w1', 'w2', 'w3', 'w4']],
        ],

        // ============ FR U7 · A1.CE.1 y A1.CE.2 ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'fr', 'seq' => 5,
            'consigna' => ['es' => 'En la etiqueta de una camiseta lees: «Taille M · 100 % coton · 25 €». ¿Qué sabes de la camiseta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Es talla M, de algodón, y cuesta 25 €']],
                ['clave' => 'b', 'texto' => ['es' => 'Es talla M, de lana, y cuesta 25 €']],
                ['clave' => 'c', 'texto' => ['es' => 'Es talla 25 y cuesta 100 €']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'fr', 'seq' => 4,
            'consigna' => ['es' => 'Anna te escribe desde Lyon: «Ici il fait très chaud et il y a du soleil. À Quito, il pleut ?». ¿Qué tiempo hace en Lyon?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Calor y sol']],
                ['clave' => 'b', 'texto' => ['es' => 'Frío y lluvia']],
                ['clave' => 'c', 'texto' => ['es' => 'Está nublado']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U8 · A1.PO.2 — tres ítems más: el pasado ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 11,
            'consigna' => ['es' => 'Completa con «avoir», pronombre incluido: « Hier, ___ mangé une pizza. »  (Ayer comí una pizza.)'],
            'aceptadas' => ['j\'ai'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 12,
            // Formar el participio en -é: la ortografía que el oído no ayuda a
            // fijar. «regarder» (infinitivo) suena igual y es el error real;
            // el normalizador lo marca como palabra, no como acento, porque
            // la diferencia no es solo la tilde.
            'consigna' => ['es' => 'Completa con el participio de «regarder»: « Hier soir, Marc a ___ la télé. »  (Anoche Marc vio la tele.)'],
            'aceptadas' => ['regardé'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'fr', 'seq' => 13,
            // Dos órdenes: «hier» al principio o al final.
            'consigna' => ['es' => 'Ordena las palabras para decir «Ayer estudié francés». Hay dos órdenes correctos. La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['fr' => 'hier']],
                ['clave' => 'w2', 'texto' => ['fr' => 'j\'ai']],
                ['clave' => 'w3', 'texto' => ['fr' => 'étudié']],
                ['clave' => 'w4', 'texto' => ['fr' => 'le']],
                ['clave' => 'w5', 'texto' => ['fr' => 'français']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4', 'w5'],
                ['w2', 'w3', 'w4', 'w5', 'w1'],
            ],
        ],

        // ============ FR U8 · A1.IO.2 — un ítem más ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'fr', 'seq' => 13,
            'consigna' => ['es' => 'Te preguntan «Qu\'est-ce que tu as mangé hier ?» (¿Qué comiste ayer?). Comiste pasta. ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'J\'ai mangé des pâtes.']],
                ['clave' => 'b', 'texto' => ['fr' => 'Je suis mangé des pâtes.']],
                ['clave' => 'c', 'texto' => ['fr' => 'J\'ai manger des pâtes.']],
                ['clave' => 'd', 'texto' => ['fr' => 'Je mange des pâtes.']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U8 · A1.EE.1 y A1.CE.2 ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.1', 'lengua' => 'fr', 'seq' => 3,
            'consigna' => ['es' => 'Escribes una postal desde París. Completa con «visiter»: « Salut Marc ! J\'ai ___ Paris avec ma tante. C\'est génial ! »'],
            'aceptadas' => ['visité'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'fr', 'seq' => 5,
            'consigna' => ['es' => 'Lees el mensaje de Anna: «Hier, d\'abord j\'ai étudié, puis j\'ai téléphoné à ma grand-mère et après j\'ai regardé un film». ¿Qué hizo Anna en segundo lugar?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Llamó a su abuela']],
                ['clave' => 'b', 'texto' => ['es' => 'Estudió']],
                ['clave' => 'c', 'texto' => ['es' => 'Vio una película']],
            ],
            'correcta' => 'a',
        ],

        // ============ FR U9 · A1.IO.1 — tres ítems: reparar la conversación ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.1', 'lengua' => 'fr', 'seq' => 1,
            'consigna' => ['es' => 'Un francés te dice algo muy rápido y no entiendes nada. ¿Qué dices para que la conversación siga en francés?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'Pardon, je n\'ai pas compris. Plus lentement, s\'il vous plaît.']],
                ['clave' => 'b', 'texto' => ['fr' => 'No entiendo, ¿puede repetir?']],
                ['clave' => 'c', 'texto' => ['fr' => 'Oui, oui, d\'accord.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.1', 'lengua' => 'fr', 'seq' => 2,
            'consigna' => ['es' => 'Completa: « Plus ___, s\'il vous plaît. »  (Más DESPACIO, por favor.)'],
            'aceptadas' => ['lentement'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.1', 'lengua' => 'fr', 'seq' => 3,
            'consigna' => ['es' => 'No sabes cómo se dice «mochila» en francés y la necesitas en la frase. ¿Qué preguntas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['fr' => 'Comment on dit « mochila » en français ?']],
                ['clave' => 'b', 'texto' => ['fr' => 'Qu\'est-ce que ça veut dire, « mochila » ?']],
                ['clave' => 'c', 'texto' => ['fr' => 'Vous pouvez répéter « mochila » ?']],
            ],
            'correcta' => 'a',
        ],

        // ================================================================
        // ======================== ALEMÁN · A1 ============================
        // ================================================================

        // ============ DE U1 · A1.IO.3 — cuatro ítems ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'de', 'seq' => 1,
            'consigna' => ['es' => 'Completa con «sein»: « Ich ___ Sofía. »  (Yo SOY Sofía.)'],
            'aceptadas' => ['bin'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.IO.3', 'lengua' => 'de', 'seq' => 2,
            // El verbo segundo, en su forma más simple: dos órdenes válidos
            // según qué se ponga primero. «Aus Quito komme ich» también es
            // alemán correcto (énfasis en el origen).
            'consigna' => ['es' => 'Ordena las palabras para decir «Vengo de Quito». Hay dos órdenes correctos: recuerda que el verbo va SIEMPRE en segunda posición. La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['de' => 'ich']],
                ['clave' => 'w2', 'texto' => ['de' => 'komme']],
                ['clave' => 'w3', 'texto' => ['de' => 'aus']],
                ['clave' => 'w4', 'texto' => ['de' => 'quito']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4'],
                ['w3', 'w4', 'w2', 'w1'],
            ],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.IO.3', 'lengua' => 'de', 'seq' => 3,
            'consigna' => ['es' => 'Empareja cada fórmula con lo que significa.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['de' => 'Guten Abend']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['de' => 'Auf Wiedersehen']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['de' => 'Entschuldigung']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['de' => 'Bitte']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['de' => 'Tschüss']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'buenas tardes']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'adiós (formal)']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'perdón']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'por favor / de nada']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'adiós (informal)']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.3', 'lengua' => 'de', 'seq' => 4,
            // ei / ie.
            'consigna' => ['es' => '¿Cómo se pronuncia «nein»?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => '«nain», como en «aire»']],
                ['clave' => 'b', 'texto' => ['es' => '«ne-in», dos sílabas']],
                ['clave' => 'c', 'texto' => ['es' => '«niin», con i larga']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U1 · A1.CE.1 — dos ítems: letreros ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'de', 'seq' => 1,
            'consigna' => ['es' => 'Lees «AUSGANG» sobre una puerta del aeropuerto. ¿Qué señala?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'La salida']],
                ['clave' => 'b', 'texto' => ['es' => 'La entrada']],
                ['clave' => 'c', 'texto' => ['es' => 'La aduana']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'de', 'seq' => 2,
            'consigna' => ['es' => 'En la puerta de una tienda pone «GESCHLOSSEN». ¿Qué haces?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Buscar otra: está cerrada']],
                ['clave' => 'b', 'texto' => ['es' => 'Entrar: está abierta']],
                ['clave' => 'c', 'texto' => ['es' => 'Empujar: es una indicación']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U1 · A1.EE.2 — dos ítems: nacionalidad con -in ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.2', 'lengua' => 'de', 'seq' => 1,
            'consigna' => ['es' => 'Rellena la ficha de Sofía, que es de Quito.  Name: Sofía · Nationalität: ______  (femenino)'],
            'aceptadas' => ['Ecuadorianerin'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.2', 'lengua' => 'de', 'seq' => 2,
            'consigna' => ['es' => 'Ahora la de Marco, que es de Berlín.  Name: Marco · Nationalität: ______  (masculino)'],
            'aceptadas' => ['Deutscher'],
        ],

        // ============ DE U2 · A1.PO.1 — cinco ítems ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'de', 'seq' => 1,
            // La edad con SEIN: el error más repetido de la unidad.
            'consigna' => ['es' => 'Completa: « Meine Schwester ___ zwölf. »  (Mi hermana TIENE doce años — recuerda con qué verbo va la edad en alemán.)'],
            'aceptadas' => ['ist'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'de', 'seq' => 2,
            'consigna' => ['es' => 'Completa con «haben»: « Ich ___ zwei Brüder. »  (TENGO dos hermanos.)'],
            'aceptadas' => ['habe'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.1', 'lengua' => 'de', 'seq' => 3,
            // Con artículo: la regla del curso.
            'consigna' => ['es' => 'Empareja cada palabra (con su artículo) con el parentesco que nombra.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['de' => 'die Oma']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['de' => 'die Schwester']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['de' => 'der Onkel']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['de' => 'der Sohn']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['de' => 'das Kind']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'la abuela']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'la hermana']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'el tío']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'el hijo']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'el niño / la niña']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'de', 'seq' => 4,
            // El artículo es parte de la palabra: sin «der» es error.
            'consigna' => ['es' => 'Escribe «el hermano» en alemán, CON su artículo.'],
            'aceptadas' => ['der Bruder'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.PO.1', 'lengua' => 'de', 'seq' => 5,
            // Las dos ch.
            'consigna' => ['es' => '«Ich» (yo) y «acht» (ocho) llevan las dos «ch». ¿Suenan igual?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'No: «ich» lleva un soplo suave y «acht» una j fuerte como la de «jamón»']],
                ['clave' => 'b', 'texto' => ['es' => 'Sí: las dos como la ch española']],
                ['clave' => 'c', 'texto' => ['es' => 'Sí: las dos como una k']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U2 · A1.IO.3 — dos ítems más ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.3', 'lengua' => 'de', 'seq' => 5,
            'consigna' => ['es' => 'Te preguntan «Wie alt bist du?». Tienes quince. ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Ich bin fünfzehn.']],
                ['clave' => 'b', 'texto' => ['de' => 'Ich habe fünfzehn Jahre.']],
                ['clave' => 'c', 'texto' => ['de' => 'Bin fünfzehn.']],
                ['clave' => 'd', 'texto' => ['de' => 'Ich habe fünfzehn.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'de', 'seq' => 6,
            'consigna' => ['es' => 'Completa con el artículo: « Das ist ___ Onkel von Marco. »  (Es EL tío de Marco — «Onkel» es masculino.)'],
            'aceptadas' => ['der'],
        ],

        // ============ DE U2 · A1.CE.2 — dos ítems sobre la postal ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'de', 'seq' => 1,
            'consigna' => ['es' => 'Lee la postal: «Hallo Marco! Ich bin in Berlin mit meiner Schwester und meiner Tante. Meine Oma kommt aus Berlin. Bis bald, Anna.» ¿Con quién está Anna en Berlín?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Con su hermana y su tía']],
                ['clave' => 'b', 'texto' => ['es' => 'Con su abuela']],
                ['clave' => 'c', 'texto' => ['es' => 'Con Marco']],
                ['clave' => 'd', 'texto' => ['es' => 'Sola']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'de', 'seq' => 2,
            'consigna' => ['es' => 'En la misma postal, ¿quién es de Berlín?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Su abuela']],
                ['clave' => 'b', 'texto' => ['es' => 'Su tía']],
                ['clave' => 'c', 'texto' => ['es' => 'Anna']],
                ['clave' => 'd', 'texto' => ['es' => 'Marco']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U3 · A1.PO.2 — cinco ítems ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 1,
            'consigna' => ['es' => 'Completa con «lernen»: « ___ du Deutsch? »  (¿APRENDES alemán? — pregunta de sí/no: el verbo va primero.)'],
            'aceptadas' => ['Lernst'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 2,
            'consigna' => ['es' => 'Completa con «wohnen»: « Ich ___ in Quito. »  (VIVO en Quito.)'],
            'aceptadas' => ['wohne'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 3,
            // La e de apoyo en raíces en -t: arbeitet.
            'consigna' => ['es' => 'Completa con «arbeiten»: « Meine Mutter ___ in Quito. »  (Mi madre TRABAJA en Quito — fíjate en la raíz.)'],
            'aceptadas' => ['arbeitet'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 4,
            // TRES órdenes válidos y todos con el verbo segundo. Es el ítem
            // para el que se diseñó el conjunto de secuencias en #27. Lo que
            // NO vale: «am morgen ich lerne deutsch» (verbo tercero), que es
            // exactamente lo que escribe un hispanohablante.
            'consigna' => ['es' => 'Ordena las palabras para decir «Por la mañana aprendo alemán». Hay más de un orden correcto; el verbo tiene que quedar en segunda posición. La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['de' => 'am']],
                ['clave' => 'w2', 'texto' => ['de' => 'morgen']],
                ['clave' => 'w3', 'texto' => ['de' => 'lerne']],
                ['clave' => 'w4', 'texto' => ['de' => 'ich']],
                ['clave' => 'w5', 'texto' => ['de' => 'deutsch']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4', 'w5'],
                ['w4', 'w3', 'w1', 'w2', 'w5'],
                ['w4', 'w3', 'w5', 'w1', 'w2'],
            ],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 5,
            'consigna' => ['es' => 'Empareja cada verbo con lo que significa.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['de' => 'hören']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['de' => 'wohnen']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['de' => 'arbeiten']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['de' => 'kochen']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['de' => 'gehen']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'oír, escuchar']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'vivir en un lugar']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'trabajar']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'cocinar']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'ir (a pie)']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        // ============ DE U3 · A1.IO.2 — tres ítems: la hora ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 1,
            // «halb neun» = 8:30. La trampa que hace perder trenes.
            'consigna' => ['es' => 'Un alemán te dice «Es ist halb neun». ¿Qué hora es?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Las ocho y media']],
                ['clave' => 'b', 'texto' => ['es' => 'Las nueve y media']],
                ['clave' => 'c', 'texto' => ['es' => 'Las nueve menos cuarto']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 2,
            'consigna' => ['es' => 'Completa la pregunta: « Um wie viel ___ isst du? »  (¿A qué HORA comes?)'],
            'aceptadas' => ['Uhr'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 3,
            'consigna' => ['es' => 'Es la una. ¿Cuál es la forma correcta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Es ist ein Uhr.']],
                ['clave' => 'b', 'texto' => ['de' => 'Es ist eins Uhr.']],
                ['clave' => 'c', 'texto' => ['de' => 'Es sind ein Uhr.']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U3 · A1.CE.2 — un ítem más: nie ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'de', 'seq' => 3,
            'consigna' => ['es' => 'Lees en el mensaje de Marco: «Am Samstag lerne ich nie, ich spiele mit meinem Bruder». ¿Qué hace Marco los sábados?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Juega con su hermano y no estudia']],
                ['clave' => 'b', 'texto' => ['es' => 'Estudia siempre']],
                ['clave' => 'c', 'texto' => ['es' => 'Estudia con su hermano']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U4 · A1.IO.2 — tres ítems: gustos ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 4,
            'consigna' => ['es' => 'Completa con «mögen»: « Ich ___ Musik. »  (ME GUSTA la música.)'],
            'aceptadas' => ['mag'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 5,
            // gern detrás del verbo, para actividades.
            'consigna' => ['es' => 'Completa: « Ich spiele ___ Fußball. »  (Me gusta jugar al fútbol — la palabra que va detrás del verbo.)'],
            'aceptadas' => ['gern', 'gerne'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 6,
            'consigna' => ['es' => 'Quieres decir «Me gusta leer». ¿Cuál es la forma natural en alemán?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Ich lese gern.']],
                ['clave' => 'b', 'texto' => ['de' => 'Ich mag lesen.']],
                ['clave' => 'c', 'texto' => ['de' => 'Ich gern lese.']],
                ['clave' => 'd', 'texto' => ['de' => 'Mir gefällt lesen.']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U4 · A1.PO.2 — tres ítems más ============

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 6,
            // gern va detrás del verbo; «Musik» al final. Un solo orden natural.
            'consigna' => ['es' => 'Ordena las palabras para decir «Me gusta escuchar música». La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['de' => 'ich']],
                ['clave' => 'w2', 'texto' => ['de' => 'höre']],
                ['clave' => 'w3', 'texto' => ['de' => 'gern']],
                ['clave' => 'w4', 'texto' => ['de' => 'musik']],
            ],
            'secuencias' => [['w1', 'w2', 'w3', 'w4']],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 7,
            'consigna' => ['es' => 'Empareja cada adjetivo con su significado.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['de' => 'toll']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['de' => 'langweilig']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['de' => 'schwer']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['de' => 'schlecht']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['de' => 'leicht']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'genial']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'aburrido']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'difícil']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'malo']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'fácil']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 8,
            // Umlaut: «schon» sin puntos es otra palabra («ya»). El normalizador
            // lo marca como acento, que es lo que le pasó al alumno.
            'consigna' => ['es' => 'Escribe «bonito» en alemán (la palabra que se parece a «schon» pero no es «ya»).'],
            'aceptadas' => ['schön', 'schoen'],
        ],

        // ============ DE U4 · A1.EE.1 — dos ítems: la nota ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.1', 'lengua' => 'de', 'seq' => 1,
            'consigna' => ['es' => 'Estás escribiendo una nota a Anna. Completa: « Hallo Anna! Ich ___ Fußball sehr. »  (Me GUSTA mucho el fútbol.)'],
            'aceptadas' => ['mag'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.EE.1', 'lengua' => 'de', 'seq' => 2,
            'consigna' => ['es' => 'Vas a cerrar tu nota a un amigo. ¿Cuál es la despedida correcta en alemán?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Bis bald, Sofía.']],
                ['clave' => 'b', 'texto' => ['de' => 'Saludos, Sofía.']],
                ['clave' => 'c', 'texto' => ['de' => 'Guten Tag, Sofía.']],
                ['clave' => 'd', 'texto' => ['de' => 'Entschuldigung, Sofía.']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U5 · A1.CE.3 — tres ítems: seguir indicaciones ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.3', 'lengua' => 'de', 'seq' => 1,
            'consigna' => ['es' => 'Lees esta indicación: «Geradeaus, dann links. Die Apotheke ist rechts, neben der Bank.» ¿Dónde está la farmacia al final del recorrido?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'A la derecha, junto al banco']],
                ['clave' => 'b', 'texto' => ['es' => 'A la izquierda, junto al banco']],
                ['clave' => 'c', 'texto' => ['es' => 'Recto, lejos del banco']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.CE.3', 'lengua' => 'de', 'seq' => 2,
            'consigna' => ['es' => 'Completa la indicación: « ___ und dann rechts. »  (RECTO y luego a la derecha.)'],
            'aceptadas' => ['Geradeaus'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.3', 'lengua' => 'de', 'seq' => 3,
            'consigna' => ['es' => 'En un cartel del hotel lees: «Der Bahnhof ist weit. Das Museum ist hier in der Nähe, links.» ¿A dónde puedes ir andando en dos minutos?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Al museo']],
                ['clave' => 'b', 'texto' => ['es' => 'A la estación']],
                ['clave' => 'c', 'texto' => ['es' => 'A los dos']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U5 · A1.IO.2 — dos ítems más: el acusativo ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 7,
            // Masculino acusativo: einen.
            'consigna' => ['es' => 'Completa: « Es gibt ___ Park hier in der Nähe. »  (Hay UN parque aquí cerca — «der Park» es masculino.)'],
            'aceptadas' => ['einen'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 8,
            'consigna' => ['es' => 'Quieres preguntar a una señora mayor si hay una farmacia cerca. ¿Cuál es la forma correcta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Entschuldigung, gibt es eine Apotheke hier in der Nähe?']],
                ['clave' => 'b', 'texto' => ['de' => 'Hallo, gibt es eine Apotheke hier in der Nähe?']],
                ['clave' => 'c', 'texto' => ['de' => 'Entschuldigung, es gibt eine Apotheke hier in der Nähe?']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U5 · A1.PO.1 — dos ítems más ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'de', 'seq' => 6,
            // kein + neutro: no cambia.
            'consigna' => ['es' => 'Completa: « Es gibt ___ Museum in der Nähe. »  (NO HAY museo cerca — «das Museum» es neutro.)'],
            'aceptadas' => ['kein'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.1', 'lengua' => 'de', 'seq' => 7,
            'consigna' => ['es' => 'Empareja cada lugar (con su artículo) con su nombre en español.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['de' => 'die Kirche']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['de' => 'der Bahnhof']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['de' => 'die Straße']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['de' => 'das Krankenhaus']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['de' => 'der Platz']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'la iglesia']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'la estación']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'la calle']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'el hospital']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'la plaza']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        // ============ DE U6 · A1.IO.2 — dos ítems más: pedir ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 9,
            'consigna' => ['es' => 'Pide un helado con educación: « Ich ___ ein Eis, bitte. »  (QUISIERA un helado, por favor.)'],
            'aceptadas' => ['möchte', 'moechte'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 10,
            // Acusativo masculino tras möchten.
            'consigna' => ['es' => 'Quieres pedir un café (der Kaffee). ¿Cuál es la forma correcta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Ich möchte einen Kaffee, bitte.']],
                ['clave' => 'b', 'texto' => ['de' => 'Ich möchte ein Kaffee, bitte.']],
                ['clave' => 'c', 'texto' => ['de' => 'Ich will Kaffee.']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U6 · A1.CE.1 — dos ítems más ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'de', 'seq' => 3,
            'consigna' => ['es' => 'En la carta lees: «Schnitzel mit Pommes — 12 € · Fisch des Tages — 15 € · Wasser — 2 €». Pides Schnitzel y agua. ¿Cuánto pagas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => '14 €']],
                ['clave' => 'b', 'texto' => ['es' => '17 €']],
                ['clave' => 'c', 'texto' => ['es' => '12 €']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'de', 'seq' => 4,
            'consigna' => ['es' => 'En la puerta de un restaurante pone «ZIEHEN». ¿Qué haces?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Tirar de la puerta hacia mí']],
                ['clave' => 'b', 'texto' => ['es' => 'Empujar la puerta']],
                ['clave' => 'c', 'texto' => ['es' => 'Buscar otra entrada: está cerrado']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U6 · A1.PO.2 — dos ítems más: qué como ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 9,
            'consigna' => ['es' => 'Completa: « Zum Frühstück esse ich ___. »  (En el desayuno como PAN — con su artículo no hace falta aquí; solo la palabra.)'],
            'aceptadas' => ['Brot'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 10,
            'consigna' => ['es' => 'Empareja cada comida (con su artículo) con su nombre en español.'],
            'elementos' => [
                ['clave' => 'i1', 'col' => 'a', 'texto' => ['de' => 'der Käse']],
                ['clave' => 'i2', 'col' => 'a', 'texto' => ['de' => 'der Fisch']],
                ['clave' => 'i3', 'col' => 'a', 'texto' => ['de' => 'die Eier']],
                ['clave' => 'i4', 'col' => 'a', 'texto' => ['de' => 'der Zucker']],
                ['clave' => 'i5', 'col' => 'a', 'texto' => ['de' => 'das Abendessen']],
                ['clave' => 'e1', 'col' => 'b', 'texto' => ['es' => 'el queso']],
                ['clave' => 'e2', 'col' => 'b', 'texto' => ['es' => 'el pescado']],
                ['clave' => 'e3', 'col' => 'b', 'texto' => ['es' => 'los huevos']],
                ['clave' => 'e4', 'col' => 'b', 'texto' => ['es' => 'el azúcar']],
                ['clave' => 'e5', 'col' => 'b', 'texto' => ['es' => 'la cena']],
            ],
            'parejas' => [['i1', 'e1'], ['i2', 'e2'], ['i3', 'e3'], ['i4', 'e4'], ['i5', 'e5']],
        ],

        // ============ DE U7 · A1.IO.2 — dos ítems más ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 11,
            'consigna' => ['es' => 'Quieres saber el precio de una chaqueta. ¿Qué preguntas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Was kostet die Jacke?']],
                ['clave' => 'b', 'texto' => ['de' => 'Wie viel ist die Jacke?']],
                ['clave' => 'c', 'texto' => ['de' => 'Was ist die Jacke?']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 12,
            // «Es ist kalt», no «es macht kalt».
            'consigna' => ['es' => 'Completa: « Es ___ kalt. »  (HACE frío — recuerda con qué verbo va el tiempo en alemán.)'],
            'aceptadas' => ['ist'],
        ],

        // ============ DE U7 · A1.PO.1 — dos ítems más ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'de', 'seq' => 8,
            // El adjetivo predicativo NO cambia: «rot», no «rote».
            'consigna' => ['es' => 'Completa con «rot» en la forma correcta: « Die Schuhe sind ___. »  (Los zapatos son ROJOS — plural, detrás de «sind».)'],
            'aceptadas' => ['rot'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.1', 'lengua' => 'de', 'seq' => 9,
            // Verbo segundo con «in Quito» delante o detrás: tres órdenes.
            'consigna' => ['es' => 'Ordena las palabras para decir «En Quito hace frío por la noche». Hay más de un orden correcto; el verbo va en segunda posición. La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['de' => 'in']],
                ['clave' => 'w2', 'texto' => ['de' => 'quito']],
                ['clave' => 'w3', 'texto' => ['de' => 'ist']],
                ['clave' => 'w4', 'texto' => ['de' => 'es']],
                ['clave' => 'w5', 'texto' => ['de' => 'am']],
                ['clave' => 'w6', 'texto' => ['de' => 'abend']],
                ['clave' => 'w7', 'texto' => ['de' => 'kalt']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4', 'w5', 'w6', 'w7'],
                ['w5', 'w6', 'w3', 'w4', 'w1', 'w2', 'w7'],
                ['w4', 'w3', 'w1', 'w2', 'w5', 'w6', 'w7'],
                ['w4', 'w3', 'w5', 'w6', 'w1', 'w2', 'w7'],
            ],
        ],

        // ============ DE U7 · A1.CE.1 y A1.CE.2 ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'de', 'seq' => 5,
            'consigna' => ['es' => 'En la etiqueta de una camiseta lees: «Größe M · 100 % Baumwolle · 25 €». ¿Qué sabes de la camiseta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Es talla M, de algodón, y cuesta 25 €']],
                ['clave' => 'b', 'texto' => ['es' => 'Es talla M, de lana, y cuesta 25 €']],
                ['clave' => 'c', 'texto' => ['es' => 'Es talla 25 y cuesta 100 €']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'de', 'seq' => 4,
            'consigna' => ['es' => 'Anna te escribe desde Berlín: «Hier ist es sehr warm und die Sonne scheint. Regnet es in Quito?». ¿Qué tiempo hace en Berlín?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Calor y sol']],
                ['clave' => 'b', 'texto' => ['es' => 'Frío y lluvia']],
                ['clave' => 'c', 'texto' => ['es' => 'Está nublado']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U8 · A1.PO.2 — tres ítems más: el Perfekt ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 11,
            'consigna' => ['es' => 'Completa con «haben»: « Gestern ___ ich Pizza gegessen. »  (Ayer comí pizza.)'],
            'aceptadas' => ['habe'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 12,
            // Formar el participio regular: ge- + -t.
            'consigna' => ['es' => 'Completa con el participio de «spielen»: « Gestern hat Marco Fußball ___. »  (Ayer Marco jugó al fútbol.)'],
            'aceptadas' => ['gespielt'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'de', 'seq' => 13,
            // El bocadillo: haben segundo, participio ÚLTIMO. Dos órdenes según
            // dónde vaya «gestern». Lo que no vale: «ich habe gelernt gestern
            // deutsch» (participio en medio), que es lo que escribe un
            // hispanohablante.
            'consigna' => ['es' => 'Ordena las palabras para decir «Ayer aprendí alemán». Hay dos órdenes correctos: el participio va SIEMPRE al final. La mayúscula y el punto ya están puestos.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['de' => 'gestern']],
                ['clave' => 'w2', 'texto' => ['de' => 'habe']],
                ['clave' => 'w3', 'texto' => ['de' => 'ich']],
                ['clave' => 'w4', 'texto' => ['de' => 'deutsch']],
                ['clave' => 'w5', 'texto' => ['de' => 'gelernt']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4', 'w5'],
                ['w3', 'w2', 'w1', 'w4', 'w5'],
            ],
        ],

        // ============ DE U8 · A1.IO.2 — un ítem más ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'de', 'seq' => 13,
            'consigna' => ['es' => 'Te preguntan «Was hast du gestern gemacht?» (¿Qué hiciste ayer?). Jugaste al fútbol. ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Ich habe Fußball gespielt.']],
                ['clave' => 'b', 'texto' => ['de' => 'Ich habe gespielt Fußball.']],
                ['clave' => 'c', 'texto' => ['de' => 'Ich bin Fußball gespielt.']],
                ['clave' => 'd', 'texto' => ['de' => 'Ich spiele Fußball.']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U8 · A1.EE.1 y A1.CE.2 ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.1', 'lengua' => 'de', 'seq' => 3,
            // besuchen: sin ge-.
            'consigna' => ['es' => 'Escribes una postal desde Berlín. Completa con el participio de «besuchen»: « Hallo Marco! Ich habe mit meiner Tante Berlin ___. Es ist toll! »'],
            'aceptadas' => ['besucht'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'de', 'seq' => 5,
            'consigna' => ['es' => 'Lees el mensaje de Anna: «Gestern habe ich zuerst gelernt, dann habe ich mit meiner Oma telefoniert und danach habe ich einen Film gesehen». ¿Qué hizo Anna en segundo lugar?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Llamó a su abuela']],
                ['clave' => 'b', 'texto' => ['es' => 'Estudió']],
                ['clave' => 'c', 'texto' => ['es' => 'Vio una película']],
            ],
            'correcta' => 'a',
        ],

        // ============ DE U9 · A1.IO.1 — tres ítems: reparar la conversación ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.1', 'lengua' => 'de', 'seq' => 1,
            'consigna' => ['es' => 'Un alemán te dice algo muy rápido y no entiendes nada. ¿Qué dices para que la conversación siga en alemán?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Wie bitte? Langsamer, bitte.']],
                ['clave' => 'b', 'texto' => ['de' => 'No entiendo, ¿puede repetir?']],
                ['clave' => 'c', 'texto' => ['de' => 'Ja, ja, okay.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.1', 'lengua' => 'de', 'seq' => 2,
            'consigna' => ['es' => 'Completa: « ___, bitte. »  (MÁS DESPACIO, por favor — una sola palabra.)'],
            'aceptadas' => ['Langsamer'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.1', 'lengua' => 'de', 'seq' => 3,
            'consigna' => ['es' => 'No sabes cómo se dice «mochila» en alemán y la necesitas en la frase. ¿Qué preguntas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['de' => 'Wie sagt man «mochila» auf Deutsch?']],
                ['clave' => 'b', 'texto' => ['de' => 'Was bedeutet «mochila»?']],
                ['clave' => 'c', 'texto' => ['de' => 'Können Sie «mochila» wiederholen?']],
            ],
            'correcta' => 'a',
        ],


        // ================================================================
        // ========================= CHINO · A1 ============================
        // ================================================================
        //
        // En los `hueco` se acepta, en este orden: el pinyin CON TONOS (la
        // forma canónica, la que se enseña), el pinyin con NÚMEROS (ni3 hao3,
        // para quien no tiene el teclado configurado; la ü se escribe v), y
        // el CARÁCTER. Sin tono es error de «acento» (= tono). En los `orden`
        // las fichas son pinyin en minúscula o caracteres, sin puntuación.
        // Los `pares` llevan tres columnas: a = carácter, b = pinyin,
        // c = significado.

        // ============ ZH U1 · A1.IO.3 — cuatro ítems: presentarse ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'zh', 'seq' => 1,
            // El verbo de presentarse. Se pide el pinyin con tono; sin tono
            // el motor lo marca como error de acento (= tono), no de palabra.
            'consigna' => ['es' => 'Completa en pinyin (con tonos, o con el número del tono): « Wǒ ___ Sofía. »  (ME LLAMO Sofía.)'],
            'aceptadas' => ['jiào', 'jiao4', '叫'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.IO.3', 'lengua' => 'zh', 'seq' => 2,
            // El orden más simple del chino: sujeto + verbo + resto. Un solo
            // orden válido; el signo ya está puesto.
            'consigna' => ['es' => 'Ordena las fichas para decir «Soy ecuatoriana». El punto ya está puesto.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['zh' => 'wǒ']],
                ['clave' => 'w2', 'texto' => ['zh' => 'shì']],
                ['clave' => 'w3', 'texto' => ['zh' => 'èguāduō’ěr']],
                ['clave' => 'w4', 'texto' => ['zh' => 'rén']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4'],
            ],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.IO.3', 'lengua' => 'zh', 'seq' => 3,
            // Tres columnas: carácter, pinyin, significado. La razón de que
            // el tipo tenga n columnas.
            'consigna' => ['es' => 'Une cada carácter con su pinyin y con su significado (tres columnas: una ficha de cada).'],
            'elementos' => [
                ['clave' => 'c1', 'col' => 'a', 'texto' => ['zh' => '我']],
                ['clave' => 'c2', 'col' => 'a', 'texto' => ['zh' => '你']],
                ['clave' => 'c3', 'col' => 'a', 'texto' => ['zh' => '好']],
                ['clave' => 'c4', 'col' => 'a', 'texto' => ['zh' => '是']],
                ['clave' => 'c5', 'col' => 'a', 'texto' => ['zh' => '叫']],
                ['clave' => 'p1', 'col' => 'b', 'texto' => ['zh' => 'wǒ']],
                ['clave' => 'p2', 'col' => 'b', 'texto' => ['zh' => 'nǐ']],
                ['clave' => 'p3', 'col' => 'b', 'texto' => ['zh' => 'hǎo']],
                ['clave' => 'p4', 'col' => 'b', 'texto' => ['zh' => 'shì']],
                ['clave' => 'p5', 'col' => 'b', 'texto' => ['zh' => 'jiào']],
                ['clave' => 's1', 'col' => 'c', 'texto' => ['es' => 'yo']],
                ['clave' => 's2', 'col' => 'c', 'texto' => ['es' => 'tú']],
                ['clave' => 's3', 'col' => 'c', 'texto' => ['es' => 'bien']],
                ['clave' => 's4', 'col' => 'c', 'texto' => ['es' => 'ser']],
                ['clave' => 's5', 'col' => 'c', 'texto' => ['es' => 'llamarse']],
            ],
            'parejas' => [['c1', 'p1', 's1'], ['c2', 'p2', 's2'], ['c3', 'p3', 's3'], ['c4', 'p4', 's4'], ['c5', 'p5', 's5']],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.3', 'lengua' => 'zh', 'seq' => 4,
            // Los tonos son parte de la palabra.
            'consigna' => ['es' => '«mā», «má», «mǎ» y «mà» son…'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Cuatro palabras distintas (mamá, cáñamo, caballo, regañar)']],
                ['clave' => 'b', 'texto' => ['es' => 'La misma palabra dicha con más o menos énfasis']],
                ['clave' => 'c', 'texto' => ['es' => 'Cuatro formas de escribir «ma» según la región']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U1 · A1.CE.1 — dos ítems: reconocer lo escrito ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'zh', 'seq' => 1,
            'consigna' => ['es' => 'En la tarjeta de un compañero pone «李明 · 中国». ¿Qué sabes de él?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Se llama Lǐ Míng y es de China']],
                ['clave' => 'b', 'texto' => ['es' => 'Se llama Zhōngguó y es de Lǐ Míng']],
                ['clave' => 'c', 'texto' => ['es' => 'Es profesor de chino']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'zh', 'seq' => 2,
            'consigna' => ['es' => 'Alguien te da una nota que solo dice «谢谢！». ¿Qué te está diciendo?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Gracias']],
                ['clave' => 'b', 'texto' => ['es' => 'Adiós']],
                ['clave' => 'c', 'texto' => ['es' => 'Perdón']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U1 · A1.EE.2 — dos ítems: la ficha y país + 人 ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.2', 'lengua' => 'zh', 'seq' => 1,
            'consigna' => ['es' => 'Rellena la ficha de Sofía, que es de Quito, en pinyin.  名字 míngzi: Sofía · 国 guó: ______  (ecuatoriana: país + rén)'],
            'aceptadas' => ['èguāduō’ěr rén', 'èguāduō’ěrrén', "èguāduō'ěr rén", "èguāduō'ěrrén", 'e4gua1duo1er3 ren2', 'e4gua1duo1er3ren2', '厄瓜多尔人'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.2', 'lengua' => 'zh', 'seq' => 2,
            'consigna' => ['es' => 'Ahora la de Lǐ Míng, que es de Pekín.  名字 míngzi: 李明 · 国 guó: ______  (chino: país + rén)'],
            'aceptadas' => ['zhōngguó rén', 'zhōngguórén', 'zhong1guo2 ren2', 'zhong1guo2ren2', '中国人'],
        ],

        // ============ ZH U2 · A1.PO.1 — cinco ítems: la familia ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'zh', 'seq' => 1,
            // La edad SIN verbo: el error más repetido de la unidad.
            'consigna' => ['es' => 'Completa en pinyin: « Wǒ shíwǔ ___. »  (Tengo quince AÑOS — en chino no hay verbo: pronombre + número + esta palabra.)'],
            'aceptadas' => ['suì', 'sui4', '岁'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'zh', 'seq' => 2,
            // 有 no se niega con 不.
            'consigna' => ['es' => 'Completa en pinyin la negación: « Wǒ ___ gēge. »  (NO TENGO hermano mayor — recuerda con qué palabra se niega 有.)'],
            'aceptadas' => ['méiyǒu', 'méi yǒu', 'mei2you3', 'mei2 you3', '没有'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.1', 'lengua' => 'zh', 'seq' => 3,
            'consigna' => ['es' => 'Une cada miembro de la familia con su pinyin y con su significado.'],
            'elementos' => [
                ['clave' => 'c1', 'col' => 'a', 'texto' => ['zh' => '爸爸']],
                ['clave' => 'c2', 'col' => 'a', 'texto' => ['zh' => '妈妈']],
                ['clave' => 'c3', 'col' => 'a', 'texto' => ['zh' => '哥哥']],
                ['clave' => 'c4', 'col' => 'a', 'texto' => ['zh' => '妹妹']],
                ['clave' => 'p1', 'col' => 'b', 'texto' => ['zh' => 'bàba']],
                ['clave' => 'p2', 'col' => 'b', 'texto' => ['zh' => 'māma']],
                ['clave' => 'p3', 'col' => 'b', 'texto' => ['zh' => 'gēge']],
                ['clave' => 'p4', 'col' => 'b', 'texto' => ['zh' => 'mèimei']],
                ['clave' => 's1', 'col' => 'c', 'texto' => ['es' => 'papá']],
                ['clave' => 's2', 'col' => 'c', 'texto' => ['es' => 'mamá']],
                ['clave' => 's3', 'col' => 'c', 'texto' => ['es' => 'hermano mayor']],
                ['clave' => 's4', 'col' => 'c', 'texto' => ['es' => 'hermana menor']],
            ],
            'parejas' => [['c1', 'p1', 's1'], ['c2', 'p2', 's2'], ['c3', 'p3', 's3'], ['c4', 'p4', 's4']],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.1', 'lengua' => 'zh', 'seq' => 4,
            // Sujeto + 有 + número + medidor + nombre. Un solo orden.
            'consigna' => ['es' => 'Ordena las fichas para decir «En mi familia somos cuatro» (mi familia tiene cuatro personas). El punto ya está puesto.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['zh' => 'wǒ jiā']],
                ['clave' => 'w2', 'texto' => ['zh' => 'yǒu']],
                ['clave' => 'w3', 'texto' => ['zh' => 'sì']],
                ['clave' => 'w4', 'texto' => ['zh' => 'kǒu']],
                ['clave' => 'w5', 'texto' => ['zh' => 'rén']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4', 'w5'],
            ],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.PO.1', 'lengua' => 'zh', 'seq' => 5,
            // El medidor.
            'consigna' => ['es' => '¿Cuál es la frase correcta para «Tengo una hermana mayor»?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '我有一个姐姐。 Wǒ yǒu yí gè jiějie.']],
                ['clave' => 'b', 'texto' => ['zh' => '我有一姐姐。 Wǒ yǒu yī jiějie.']],
                ['clave' => 'c', 'texto' => ['zh' => '我是一个姐姐。 Wǒ shì yí gè jiějie.']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U2 · A1.IO.3 — dos ítems más (siguen a los de la U1) ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.3', 'lengua' => 'zh', 'seq' => 5,
            'consigna' => ['es' => 'Lǐ Míng te dice «我是北京人» (soy de Pekín). ¿Cómo le devuelves la pregunta con dos palabras?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '你呢？ Nǐ ne?']],
                ['clave' => 'b', 'texto' => ['zh' => '你吗？ Nǐ ma?']],
                ['clave' => 'c', 'texto' => ['zh' => '你好？ Nǐ hǎo?']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.3', 'lengua' => 'zh', 'seq' => 6,
            // La pregunta de la edad entre iguales.
            'consigna' => ['es' => 'Completa en pinyin la pregunta a un compañero de tu edad: « Nǐ ___? »  (¿CUÁNTOS AÑOS tienes? — literalmente «cuánto grande», dos sílabas.)'],
            'aceptadas' => ['duō dà', 'duōdà', 'duo1 da4', 'duo1da4', '多大'],
        ],

        // ============ ZH U2 · A1.CE.2 — dos ítems sobre el mensaje de Lǐ Míng ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'zh', 'seq' => 1,
            'consigna' => ['es' => 'Lee: «我家有五口人：爸爸、妈妈、一个哥哥、一个妹妹和我。» ¿Cuántos hermanos tiene Lǐ Míng y cuáles?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Dos: un hermano mayor y una hermana menor']],
                ['clave' => 'b', 'texto' => ['es' => 'Dos: un hermano menor y una hermana mayor']],
                ['clave' => 'c', 'texto' => ['es' => 'Cinco hermanos']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'zh', 'seq' => 2,
            'consigna' => ['es' => 'Sigue el mensaje: «我哥哥十八岁，我妹妹八岁。我有一个狗，叫小白。» ¿Qué es verdad?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Su hermano tiene 18 años, su hermana 8, y tiene un perro llamado Xiǎo Bái']],
                ['clave' => 'b', 'texto' => ['es' => 'Su hermano tiene 8 años, su hermana 18, y tiene un gato']],
                ['clave' => 'c', 'texto' => ['es' => 'Su hermano se llama Xiǎo Bái y tiene 18 años']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U3 · A1.PO.2 — cinco ítems: la rutina ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 1,
            // 在 frente a 是: la respuesta es un lugar.
            'consigna' => ['es' => 'Completa en pinyin: « Wǒ ___ xuéxiào. »  (ESTOY EN la escuela — el verbo de lugar, no 是.)'],
            'aceptadas' => ['zài', 'zai4', '在'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 2,
            // El cambio de tono de 不 delante de cuarto tono: se escribe.
            'consigna' => ['es' => 'Escribe en pinyin, con el tono que toca: « Wǒ ___ shì xuésheng. »  (NO soy estudiante — ojo: delante de shì, el tono de 不 cambia.)'],
            'aceptadas' => ['bú', 'bu2', 'bù', 'bu4', '不'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 3,
            'consigna' => ['es' => 'Une cada acción del día con su pinyin y con su significado.'],
            'elementos' => [
                ['clave' => 'c1', 'col' => 'a', 'texto' => ['zh' => '起床']],
                ['clave' => 'c2', 'col' => 'a', 'texto' => ['zh' => '吃饭']],
                ['clave' => 'c3', 'col' => 'a', 'texto' => ['zh' => '上课']],
                ['clave' => 'c4', 'col' => 'a', 'texto' => ['zh' => '睡觉']],
                ['clave' => 'p1', 'col' => 'b', 'texto' => ['zh' => 'qǐchuáng']],
                ['clave' => 'p2', 'col' => 'b', 'texto' => ['zh' => 'chī fàn']],
                ['clave' => 'p3', 'col' => 'b', 'texto' => ['zh' => 'shàngkè']],
                ['clave' => 'p4', 'col' => 'b', 'texto' => ['zh' => 'shuìjiào']],
                ['clave' => 's1', 'col' => 'c', 'texto' => ['es' => 'levantarse']],
                ['clave' => 's2', 'col' => 'c', 'texto' => ['es' => 'comer']],
                ['clave' => 's3', 'col' => 'c', 'texto' => ['es' => 'tener clase']],
                ['clave' => 's4', 'col' => 'c', 'texto' => ['es' => 'dormir']],
            ],
            'parejas' => [['c1', 'p1', 's1'], ['c2', 'p2', 's2'], ['c3', 'p3', 's3'], ['c4', 'p4', 's4']],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 4,
            // La hora ANTES del verbo. Dos órdenes válidos: 我每天六点… y
            // 每天我六点… (el tiempo puede abrir la frase). «我六点每天»
            // y cualquier hora detrás del verbo, no.
            'consigna' => ['es' => 'Ordena las fichas para decir «Cada día me levanto a las seis». Hay dos órdenes correctos; en los dos, la hora va ANTES del verbo. El punto ya está puesto.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['zh' => 'wǒ']],
                ['clave' => 'w2', 'texto' => ['zh' => 'měitiān']],
                ['clave' => 'w3', 'texto' => ['zh' => 'liù diǎn']],
                ['clave' => 'w4', 'texto' => ['zh' => 'qǐchuáng']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4'],
                ['w2', 'w1', 'w3', 'w4'],
            ],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 5,
            // Quién + cuándo + 在 dónde + verbo.
            'consigna' => ['es' => '¿Cuál es el orden correcto para «Por la mañana tengo clase en la escuela»?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '我上午在学校上课。 Wǒ shàngwǔ zài xuéxiào shàngkè.']],
                ['clave' => 'b', 'texto' => ['zh' => '我上课在学校上午。 Wǒ shàngkè zài xuéxiào shàngwǔ.']],
                ['clave' => 'c', 'texto' => ['zh' => '我在学校上课上午。 Wǒ zài xuéxiào shàngkè shàngwǔ.']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U3 · A1.IO.2 — tres ítems: la hora ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 1,
            // 两 frente a 二.
            'consigna' => ['es' => 'Son las dos. ¿Cómo se dice?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '两点 liǎng diǎn']],
                ['clave' => 'b', 'texto' => ['zh' => '二点 èr diǎn']],
                ['clave' => 'c', 'texto' => ['zh' => '两个 liǎng gè']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 2,
            'consigna' => ['es' => 'Completa en pinyin: « Xiànzài sān diǎn ___. »  (Son las tres Y MEDIA — una sílaba.)'],
            'aceptadas' => ['bàn', 'ban4', '半'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 3,
            // La palabra interrogativa ocupa el sitio de la respuesta.
            'consigna' => ['es' => 'Quieres preguntar «¿A qué hora te levantas?». ¿Cuál es correcta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '你几点起床？ Nǐ jǐ diǎn qǐchuáng?']],
                ['clave' => 'b', 'texto' => ['zh' => '几点你起床？ Jǐ diǎn nǐ qǐchuáng?']],
                ['clave' => 'c', 'texto' => ['zh' => '你起床几点？ Nǐ qǐchuáng jǐ diǎn?']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U3 · A1.CE.2 — un ítem más: el horario ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'zh', 'seq' => 3,
            'consigna' => ['es' => 'Un mensaje de Wáng lǎoshī: «明天上午八点半上课，下午三点回家。» ¿Qué dice?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Mañana hay clase a las 8:30 de la mañana y se vuelve a casa a las 3 de la tarde']],
                ['clave' => 'b', 'texto' => ['es' => 'Mañana hay clase a las 3 de la tarde y se vuelve a casa a las 8:30']],
                ['clave' => 'c', 'texto' => ['es' => 'Hoy hay clase a las 8 y media y a las 3']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U4 · A1.IO.2 — tres ítems más: gustos ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 4,
            'consigna' => ['es' => 'Completa en pinyin la pregunta: « Nǐ ___ shénme? »  (¿Qué TE GUSTA? — dos sílabas, la segunda con tono neutro.)'],
            'aceptadas' => ['xǐhuan', 'xǐ huan', 'xǐhuān', 'xi3huan', 'xi3 huan', 'xi3huan1', '喜欢'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 5,
            // 也 entre sujeto y verbo.
            'consigna' => ['es' => 'Lǐ Míng dice «我喜欢茶» y a ti también te gusta el té. ¿Qué dices?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '我也喜欢茶。 Wǒ yě xǐhuan chá.']],
                ['clave' => 'b', 'texto' => ['zh' => '我喜欢茶也。 Wǒ xǐhuan chá yě.']],
                ['clave' => 'c', 'texto' => ['zh' => '也我喜欢茶。 Yě wǒ xǐhuan chá.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 6,
            'consigna' => ['es' => 'Te preguntan «你喜欢咖啡吗？» y no te gusta el café. ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '不喜欢。 Bù xǐhuan.']],
                ['clave' => 'b', 'texto' => ['zh' => '没喜欢。 Méi xǐhuan.']],
                ['clave' => 'c', 'texto' => ['zh' => '不是。 Bú shì.']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U4 · A1.PO.2 — tres ítems más: el adjetivo con 很 ============

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 6,
            // 也 va entre sujeto y verbo. Un solo orden.
            'consigna' => ['es' => 'Ordena las fichas para decir «A mí también me gusta la música». El punto ya está puesto.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['zh' => 'wǒ']],
                ['clave' => 'w2', 'texto' => ['zh' => 'yě']],
                ['clave' => 'w3', 'texto' => ['zh' => 'xǐhuan']],
                ['clave' => 'w4', 'texto' => ['zh' => 'yīnyuè']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4'],
            ],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 7,
            'consigna' => ['es' => 'Une cada adjetivo con su pinyin y con su significado.'],
            'elementos' => [
                ['clave' => 'c1', 'col' => 'a', 'texto' => ['zh' => '大']],
                ['clave' => 'c2', 'col' => 'a', 'texto' => ['zh' => '小']],
                ['clave' => 'c3', 'col' => 'a', 'texto' => ['zh' => '好吃']],
                ['clave' => 'c4', 'col' => 'a', 'texto' => ['zh' => '好看']],
                ['clave' => 'p1', 'col' => 'b', 'texto' => ['zh' => 'dà']],
                ['clave' => 'p2', 'col' => 'b', 'texto' => ['zh' => 'xiǎo']],
                ['clave' => 'p3', 'col' => 'b', 'texto' => ['zh' => 'hǎochī']],
                ['clave' => 'p4', 'col' => 'b', 'texto' => ['zh' => 'hǎokàn']],
                ['clave' => 's1', 'col' => 'c', 'texto' => ['es' => 'grande']],
                ['clave' => 's2', 'col' => 'c', 'texto' => ['es' => 'pequeño']],
                ['clave' => 's3', 'col' => 'c', 'texto' => ['es' => 'rico (de comer)']],
                ['clave' => 's4', 'col' => 'c', 'texto' => ['es' => 'bonito']],
            ],
            'parejas' => [['c1', 'p1', 's1'], ['c2', 'p2', 's2'], ['c3', 'p3', 's3'], ['c4', 'p4', 's4']],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 8,
            // El adjetivo es el verbo: sin 是, con 很.
            'consigna' => ['es' => '¿Cómo se dice «El té está rico»?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '茶很好喝。 Chá hěn hǎohē.']],
                ['clave' => 'b', 'texto' => ['zh' => '茶是好喝。 Chá shì hǎohē.']],
                ['clave' => 'c', 'texto' => ['zh' => '茶是很好喝。 Chá shì hěn hǎohē.']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U4 · A1.EE.1 — dos ítems: la nota corta ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.1', 'lengua' => 'zh', 'seq' => 1,
            // La partícula de pregunta cierra la nota.
            'consigna' => ['es' => 'Termina la nota de Sofía en pinyin: « Lǐ Míng: Wǒ jīntiān xiàwǔ sān diǎn zài jiā. Nǐ lái ___? »  (¿VIENES? — la partícula que convierte la frase en pregunta.)'],
            'aceptadas' => ['ma', 'ma5', 'ma0', '吗'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.EE.1', 'lengua' => 'zh', 'seq' => 2,
            'consigna' => ['es' => 'Tienes que dejarle una nota a tu madre: estás en la escuela y vuelves a las seis. ¿Cuál está bien escrita?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '妈妈：我在学校。我六点回家。 Māma: Wǒ zài xuéxiào. Wǒ liù diǎn huí jiā.']],
                ['clave' => 'b', 'texto' => ['zh' => '妈妈：我是学校。我回家六点。 Māma: Wǒ shì xuéxiào. Wǒ huí jiā liù diǎn.']],
                ['clave' => 'c', 'texto' => ['zh' => '妈妈：我在学校。六点我回家在。 Māma: Wǒ zài xuéxiào. Liù diǎn wǒ huí jiā zài.']],
            ],
            'correcta' => 'a',
        ],


        // ============ ZH U5 · A1.CE.3 — tres ítems: seguir indicaciones ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.3', 'lengua' => 'zh', 'seq' => 1,
            // El punto de referencia va primero.
            'consigna' => ['es' => 'Lees: «医院在学校旁边。» ¿Dónde está el hospital?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Al lado de la escuela']],
                ['clave' => 'b', 'texto' => ['es' => 'Dentro de la escuela']],
                ['clave' => 'c', 'texto' => ['es' => 'La escuela está al lado del hospital… es decir, no se sabe cuál es la referencia']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.CE.3', 'lengua' => 'zh', 'seq' => 2,
            'consigna' => ['es' => 'Completa en pinyin la pregunta: « Qǐngwèn, shāngdiàn zài ___? »  (Disculpe, ¿DÓNDE está la tienda? — con la r final del norte.)'],
            'aceptadas' => ['nǎr', 'na3r', 'nǎ er', 'nǎer', 'na3 er5', '哪儿'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.3', 'lengua' => 'zh', 'seq' => 3,
            'consigna' => ['es' => 'Un mensaje: «饭馆在火车站前面，不远，很近。» ¿Qué haces?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Voy delante de la estación de tren: el restaurante está cerca']],
                ['clave' => 'b', 'texto' => ['es' => 'Voy detrás de la estación: el restaurante está lejos']],
                ['clave' => 'c', 'texto' => ['es' => 'Cojo el tren: el restaurante está en otra ciudad']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U5 · A1.IO.2 — dos ítems más: preguntar por un sitio ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 7,
            // La fórmula para abordar a un desconocido.
            'consigna' => ['es' => 'Completa en pinyin la fórmula para preguntar a un desconocido: « ___, yīyuàn zài nǎr? »  (DISCULPE — dos sílabas, «por favor-preguntar».)'],
            'aceptadas' => ['qǐngwèn', 'qǐng wèn', 'qing3wen4', 'qing3 wen4', '请问'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 8,
            'consigna' => ['es' => 'Preguntas «远吗？» (¿está lejos?) y te contestan «不远，很近». ¿Qué te han dicho?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'No está lejos, está muy cerca']],
                ['clave' => 'b', 'texto' => ['es' => 'Está lejos, no está cerca']],
                ['clave' => 'c', 'texto' => ['es' => 'No lo saben']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U5 · A1.PO.1 — dos ítems más: describir mi ciudad ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'zh', 'seq' => 6,
            // 有 = hay.
            'consigna' => ['es' => 'Completa en pinyin: « Jīduō ___ hěn duō shān. »  (En Quito HAY muchas montañas — el mismo verbo que «tener».)'],
            'aceptadas' => ['yǒu', 'you3', '有'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.1', 'lengua' => 'zh', 'seq' => 7,
            'consigna' => ['es' => 'Une cada lugar con su pinyin y con su significado.'],
            'elementos' => [
                ['clave' => 'c1', 'col' => 'a', 'texto' => ['zh' => '商店']],
                ['clave' => 'c2', 'col' => 'a', 'texto' => ['zh' => '医院']],
                ['clave' => 'c3', 'col' => 'a', 'texto' => ['zh' => '公园']],
                ['clave' => 'c4', 'col' => 'a', 'texto' => ['zh' => '火车站']],
                ['clave' => 'p1', 'col' => 'b', 'texto' => ['zh' => 'shāngdiàn']],
                ['clave' => 'p2', 'col' => 'b', 'texto' => ['zh' => 'yīyuàn']],
                ['clave' => 'p3', 'col' => 'b', 'texto' => ['zh' => 'gōngyuán']],
                ['clave' => 'p4', 'col' => 'b', 'texto' => ['zh' => 'huǒchēzhàn']],
                ['clave' => 's1', 'col' => 'c', 'texto' => ['es' => 'tienda']],
                ['clave' => 's2', 'col' => 'c', 'texto' => ['es' => 'hospital']],
                ['clave' => 's3', 'col' => 'c', 'texto' => ['es' => 'parque']],
                ['clave' => 's4', 'col' => 'c', 'texto' => ['es' => 'estación de tren']],
            ],
            'parejas' => [['c1', 'p1', 's1'], ['c2', 'p2', 's2'], ['c3', 'p3', 's3'], ['c4', 'p4', 's4']],
        ],

        // ============ ZH U6 · A1.IO.2 — dos ítems más: pedir ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 9,
            'consigna' => ['es' => 'Completa en pinyin lo que le dices al camarero: « Wǒ ___ yì wǎn mǐfàn. »  (QUIERO un cuenco de arroz — el verbo de pedir, una sílaba.)'],
            'aceptadas' => ['yào', 'yao4', '要'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 10,
            'consigna' => ['es' => 'Quieres saber cuánto cuesta un té. ¿Qué preguntas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '一杯茶多少钱？ Yì bēi chá duōshao qián?']],
                ['clave' => 'b', 'texto' => ['zh' => '一杯茶几钱？ Yì bēi chá jǐ qián?']],
                ['clave' => 'c', 'texto' => ['zh' => '多少钱一杯茶是？ Duōshao qián yì bēi chá shì?']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U6 · A1.CE.1 — dos ítems más: los letreros ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'zh', 'seq' => 3,
            'consigna' => ['es' => 'En el metro ves una flecha y «出口». ¿Hacia dónde lleva?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'A la salida']],
                ['clave' => 'b', 'texto' => ['es' => 'A la entrada']],
                ['clave' => 'c', 'texto' => ['es' => 'A los baños']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'zh', 'seq' => 4,
            'consigna' => ['es' => 'Dos puertas: una pone «男» y otra «女». Sofía busca el baño. ¿Cuál es el suyo?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => '女: es el de mujeres']],
                ['clave' => 'b', 'texto' => ['es' => '男: es el de mujeres']],
                ['clave' => 'c', 'texto' => ['es' => 'Ninguna: son «abierto» y «cerrado»']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U6 · A1.PO.2 — dos ítems más: qué como, con medidor ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 9,
            // 两 delante de medidor.
            'consigna' => ['es' => 'Completa en pinyin: « Wǒ yào ___ wǎn miàntiáo. »  (Quiero DOS cuencos de fideos — el «dos» que va delante de un medidor.)'],
            'aceptadas' => ['liǎng', 'liang3', '两'],
        ],

        [
            'tipo' => 'pares', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 10,
            'consigna' => ['es' => 'Une cada comida o bebida con su pinyin y con su significado.'],
            'elementos' => [
                ['clave' => 'c1', 'col' => 'a', 'texto' => ['zh' => '米饭']],
                ['clave' => 'c2', 'col' => 'a', 'texto' => ['zh' => '面条']],
                ['clave' => 'c3', 'col' => 'a', 'texto' => ['zh' => '水']],
                ['clave' => 'c4', 'col' => 'a', 'texto' => ['zh' => '茶']],
                ['clave' => 'c5', 'col' => 'a', 'texto' => ['zh' => '鱼']],
                ['clave' => 'p1', 'col' => 'b', 'texto' => ['zh' => 'mǐfàn']],
                ['clave' => 'p2', 'col' => 'b', 'texto' => ['zh' => 'miàntiáo']],
                ['clave' => 'p3', 'col' => 'b', 'texto' => ['zh' => 'shuǐ']],
                ['clave' => 'p4', 'col' => 'b', 'texto' => ['zh' => 'chá']],
                ['clave' => 'p5', 'col' => 'b', 'texto' => ['zh' => 'yú']],
                ['clave' => 's1', 'col' => 'c', 'texto' => ['es' => 'arroz']],
                ['clave' => 's2', 'col' => 'c', 'texto' => ['es' => 'fideos']],
                ['clave' => 's3', 'col' => 'c', 'texto' => ['es' => 'agua']],
                ['clave' => 's4', 'col' => 'c', 'texto' => ['es' => 'té']],
                ['clave' => 's5', 'col' => 'c', 'texto' => ['es' => 'pescado']],
            ],
            'parejas' => [['c1', 'p1', 's1'], ['c2', 'p2', 's2'], ['c3', 'p3', 's3'], ['c4', 'p4', 's4'], ['c5', 'p5', 's5']],
        ],

        // ============ ZH U7 · A1.IO.2 — dos ítems más: precios y tiempo ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 11,
            // 太…了 con sus dos piezas.
            'consigna' => ['es' => 'Una prenda cuesta 300 yuanes y te parece demasiado. ¿Qué dices?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '太贵了！ Tài guì le!']],
                ['clave' => 'b', 'texto' => ['zh' => '很贵了！ Hěn guì le!']],
                ['clave' => 'c', 'texto' => ['zh' => '太贵！ Tài guì!']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 12,
            'consigna' => ['es' => 'Completa en pinyin la respuesta a «今天天气怎么样？»: « Jīntiān hěn ___. »  (Hoy hace FRÍO — una sílaba, tercer tono.)'],
            'aceptadas' => ['lěng', 'leng3', '冷'],
        ],

        // ============ ZH U7 · A1.PO.1 — dos ítems más: este / ese, y el adjetivo-verbo ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.1', 'lengua' => 'zh', 'seq' => 8,
            // 这 + medidor propio: 件 para prendas.
            'consigna' => ['es' => 'Completa en pinyin con «este» y el medidor de prendas: « ___ yīfu hěn piàoliang. »  (ESTA prenda es muy bonita — dos sílabas: 这 + el medidor de ropa.)'],
            'aceptadas' => ['zhè jiàn', 'zhèjiàn', 'zhe4 jian4', 'zhe4jian4', '这件'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.1', 'lengua' => 'zh', 'seq' => 9,
            // El adjetivo es el verbo; 很 delante. Un solo orden.
            'consigna' => ['es' => 'Ordena las fichas para decir «Hoy hace mucho calor». El punto ya está puesto.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['zh' => 'jīntiān']],
                ['clave' => 'w2', 'texto' => ['zh' => 'hěn']],
                ['clave' => 'w3', 'texto' => ['zh' => 'rè']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3'],
            ],
        ],

        // ============ ZH U7 · A1.CE.1 y A1.CE.2 — una etiqueta y un mensaje ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.1', 'lengua' => 'zh', 'seq' => 5,
            'consigna' => ['es' => 'En la etiqueta de una camiseta pone «衣服 · 80元». ¿Qué es y cuánto cuesta?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Una prenda de ropa, 80 yuanes']],
                ['clave' => 'b', 'texto' => ['es' => 'Un par de zapatos, 80 dólares']],
                ['clave' => 'c', 'texto' => ['es' => 'Ropa de la talla 80']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'zh', 'seq' => 4,
            'consigna' => ['es' => 'Lǐ Míng escribe: «明天很冷，下雨。你来学校吗？» ¿Qué dice?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Mañana hará frío y lloverá; pregunta si vienes a la escuela']],
                ['clave' => 'b', 'texto' => ['es' => 'Mañana hará calor; pregunta si vienes a su casa']],
                ['clave' => 'c', 'texto' => ['es' => 'Hoy llueve y no va a ir a la escuela']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U8 · A1.PO.2 — tres ítems más: 了 y 没 ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 11,
            // 了 detrás del verbo.
            'consigna' => ['es' => 'Completa en pinyin: « Zuótiān wǒ qù ___ shāngdiàn. »  (Ayer FUI a la tienda — la partícula de acción terminada, tono neutro.)'],
            'aceptadas' => ['le', 'le5', 'le0', '了'],
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 12,
            // La negación del pasado, sin 了.
            'consigna' => ['es' => 'Completa en pinyin la negación: « Wǎnshang wǒ ___ zuò zuòyè. »  (Por la noche NO hice los deberes — la negación del pasado, una sílaba.)'],
            'aceptadas' => ['méi', 'mei2', '没'],
        ],

        [
            'tipo' => 'orden', 'descriptor' => 'A1.PO.2', 'lengua' => 'zh', 'seq' => 13,
            // 了 va pegado al verbo; 昨天 puede abrir la frase o ir tras el
            // sujeto. Dos órdenes válidos, en caracteres: a estas alturas
            // los cinco son conocidos.
            'consigna' => ['es' => 'Ordena las fichas para decir «Ayer vi una película». Hay dos órdenes correctos según dónde pongas «ayer». El punto ya está puesto.'],
            'palabras' => [
                ['clave' => 'w1', 'texto' => ['zh' => '昨天']],
                ['clave' => 'w2', 'texto' => ['zh' => '我']],
                ['clave' => 'w3', 'texto' => ['zh' => '看了']],
                ['clave' => 'w4', 'texto' => ['zh' => '一个']],
                ['clave' => 'w5', 'texto' => ['zh' => '电影']],
            ],
            'secuencias' => [
                ['w1', 'w2', 'w3', 'w4', 'w5'],
                ['w2', 'w1', 'w3', 'w4', 'w5'],
            ],
        ],

        // ============ ZH U8 · A1.IO.2 — un ítem más: preguntar por el pasado ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.2', 'lengua' => 'zh', 'seq' => 13,
            // La respuesta corta repite el verbo.
            'consigna' => ['es' => 'Te preguntan «你昨天去了学校吗？» y no fuiste. ¿Qué contestas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '没去。 Méi qù.']],
                ['clave' => 'b', 'texto' => ['zh' => '不去了。 Bú qù le.']],
                ['clave' => 'c', 'texto' => ['zh' => '没去了。 Méi qù le.']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U8 · A1.EE.1 y A1.CE.2 — escribir y leer el pasado ============

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.EE.1', 'lengua' => 'zh', 'seq' => 3,
            'consigna' => ['es' => 'Termina la nota en pinyin: « Māma: Wǒ hé péngyou kàn ___ yí gè diànyǐng. Wǒ liù diǎn huí jiā. »  (Vi una película con un amigo — la partícula que marca la acción terminada.)'],
            'aceptadas' => ['le', 'le5', 'le0', '了'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.CE.2', 'lengua' => 'zh', 'seq' => 5,
            'consigna' => ['es' => 'Lǐ Míng escribe: «昨天我去了公园，没看电影。太累了！» ¿Qué hizo ayer?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['es' => 'Fue al parque y no vio la película; acabó muy cansado']],
                ['clave' => 'b', 'texto' => ['es' => 'Vio una película en el parque']],
                ['clave' => 'c', 'texto' => ['es' => 'No fue al parque porque estaba cansado']],
            ],
            'correcta' => 'a',
        ],

        // ============ ZH U9 · A1.IO.1 — tres ítems: reparar la conversación ============

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.1', 'lengua' => 'zh', 'seq' => 1,
            'consigna' => ['es' => 'Wáng lǎoshī te dice algo muy rápido y no entiendes nada. ¿Qué dices para que la conversación siga en chino?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '对不起，我听不懂。请再说一遍。 Duìbuqǐ, wǒ tīng bu dǒng. Qǐng zài shuō yí biàn.']],
                ['clave' => 'b', 'texto' => ['zh' => 'No entiendo, ¿puede repetir?']],
                ['clave' => 'c', 'texto' => ['zh' => '是，是，好的。 Shì, shì, hǎo de.']],
            ],
            'correcta' => 'a',
        ],

        [
            'tipo' => 'hueco', 'descriptor' => 'A1.IO.1', 'lengua' => 'zh', 'seq' => 2,
            'consigna' => ['es' => 'Completa en pinyin: « Qǐng shuō ___ yìdiǎnr. »  (Hable más DESPACIO, por favor — una sílaba, cuarto tono.)'],
            'aceptadas' => ['màn', 'man4', '慢'],
        ],

        [
            'tipo' => 'choice', 'descriptor' => 'A1.IO.1', 'lengua' => 'zh', 'seq' => 3,
            'consigna' => ['es' => 'No sabes cómo se dice «mochila» en chino y la necesitas en la frase. ¿Qué preguntas?'],
            'opciones' => [
                ['clave' => 'a', 'texto' => ['zh' => '«mochila» 用中文怎么说？ … yòng Zhōngwén zěnme shuō?']],
                ['clave' => 'b', 'texto' => ['zh' => '«mochila» 是什么意思？ … shì shénme yìsi?']],
                ['clave' => 'c', 'texto' => ['zh' => '请再说一遍 «mochila»。 Qǐng zài shuō yí biàn «mochila».']],
            ],
            'correcta' => 'a',
        ],
    ],
];
