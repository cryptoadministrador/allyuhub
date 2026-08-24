import '@testing-library/jest-dom/vitest';
import * as axeMatchers from 'vitest-axe/matchers';
import { configure } from '@testing-library/react';
import { expect } from 'vitest';

expect.extend(axeMatchers);

/**
 * EL TECHO DE `waitFor` ES UNA SUPOSICIÓN SOBRE LA MÁQUINA, NO UNA ASERCIÓN.
 *
 * Testing Library corta cualquier `waitFor` a los 1000 ms por defecto. La suite
 * espera sobre el debounce de 300 ms de la búsqueda más un fetch y un re-render:
 * en una máquina ociosa sobra de largo, pero medido bajo carga el test más lento
 * pasa de 1,5 s a 2,2 s — el margen se estrecha justo donde no se ve.
 *
 * Esto viene de un fallo REAL: una corrida de la suite falló una vez, en el CI
 * de mi máquina y justo detrás de un bucle de mutaciones que la tenía saturada,
 * y no volvió a reproducirse en once corridas posteriores (tres de ellas con
 * carga provocada a propósito). Un rojo que no se reproduce es peor que un rojo:
 * enseña a no creerse la suite.
 *
 * 5000 ms no afloja ninguna garantía. Un componente que de verdad se cuelgue
 * sigue cayendo por el timeout del propio test, que es lo que mide «esto no
 * termina». Lo que deja de caer es «esta máquina iba ocupada».
 */
configure({ asyncUtilTimeout: 5000 });
