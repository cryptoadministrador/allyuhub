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
 * ALCANCE ACTUAL: el CURSO ENTERO de ITALIANO A1, nueve unidades.
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
 * Cubre 10 de los 13 descriptores A1 con dos o más ítems cada uno. Los tres que
 * faltan —A1.CO.1, A1.CO.2 y A1.CO.3, comprensión oral— NO pueden tener ítems sin audio:
 * sus ejercicios de `escucha` y `dictado` están escritos en U1-audio-pendiente.md
 * y entran en cuanto el equipo grabe los clips. Declarado, no disimulado.
 *
 * Las otras tres lenguas (fr, de, zh) entran aquí con el mismo formato y el
 * mismo esqueleto de nueve unidades.
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
    ],
];
