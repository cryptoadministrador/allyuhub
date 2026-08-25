<?php

namespace App\Services\Audio;

/**
 * La DECLARACIÓN de que un fichero es audio curricular publicado: material del
 * curso, curado por el equipo de contenido, público por naturaleza.
 *
 * Existe para que `AlmacenDeAudio::publicar()` no acepte un string. El almacén
 * sirve sin autenticación y con caché inmutable de un año — perfecto para el
 * saludo en francés de la unidad 1, inaceptable para la voz de un menor. Con
 * un string, el día que exista «grábate y compárate» (frente D) alguien pasa
 * la grabación del alumno por aquí sin darse cuenta y queda servida a
 * cualquiera durante un año. Con este tipo, esa frase hay que ESCRIBIRLA:
 * `new ClipCurricular($grabacionDelAlumno)` es una mentira que se ve en el
 * diff y en la revisión.
 *
 * Es la misma lección que el default de `origen`: lo que gobierna exposición
 * no se hereda en silencio — se declara.
 */
final class ClipCurricular
{
    public function __construct(public readonly string $ruta) {}
}
