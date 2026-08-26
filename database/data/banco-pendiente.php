<?php

/**
 * BANCO PENDIENTE — preguntas escritas y revisables que NO pueden sembrarse
 * todavía porque su área no existe en el grafo.
 *
 * DECISIÓN (Carlos, 2026-08-26): Educación para la Ciudadanía (CS.EC) no se
 * importa por ahora. Con el pre-pase de erratas de #28 —un área sin destrezas
 * REVIENTA la siembra, que es lo que cazó CS.FL por CS.F— estas tres preguntas
 * habrían parado `practica:sembrar` en producción. Se retiran del banco activo
 * ENTERAS, no se borran: el día que corra `mineduc:import` sobre el currículo
 * de Ciudadanía, se mueven de vuelta a banco-practica.php tal cual (mismo
 * formato posicional) y la siembra las recoge.
 *
 * Este fichero NO lo lee ningún comando a propósito: es una sala de espera,
 * no una segunda vía de siembra.
 */

return [
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
];
