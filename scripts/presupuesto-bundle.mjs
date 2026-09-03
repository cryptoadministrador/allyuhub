#!/usr/bin/env node
// Guardián del presupuesto de bundle. Sustituye al `stat` de un solo
// `app-*.js` que había en el CI: desde que #29 trocea las páginas en chunks,
// ese fichero es solo el marco y medirlo dejaba ciega la cuenta justo cuando
// empiezan las entregas más cargadas de frontend.
//
// Dos techos, los dos leídos del manifest de Vite (la verdad de lo que baja un
// navegador), no de un informe:
//
//   TOTAL  ≤ 450 KB — la suma de TODO el JS que sirve la app.
//   PÁGINA ≤  40 KB — ninguna página sola pesa más. Con esto el «app-*.js es
//                     el archivo más gordo» deja de ser un supuesto: si una
//                     página engorda por encima del marco, salta aquí.
//
// Sin dependencias: lee public/build/manifest.json y suma los .js.
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const BUILD = 'public/build';
const ASSETS = join(BUILD, 'assets');
const TECHO_TOTAL = 450 * 1024;
const TECHO_PAGINA = 40 * 1024;

const kb = (b) => (b / 1024).toFixed(2);

// El manifest mapea cada entrada de origen a su fichero servido. Nos vale para
// distinguir una PÁGINA (resources/js/pages/*.jsx) de un chunk compartido.
const manifest = JSON.parse(readFileSync(join(BUILD, 'manifest.json'), 'utf8'));

// El tamaño real en disco de cada .js de assets/ — la suma es lo que cuenta
// para el total, chunks compartidos incluidos.
const jsAssets = readdirSync(ASSETS).filter((f) => f.endsWith('.js'));
const total = jsAssets.reduce((s, f) => s + statSync(join(ASSETS, f)).size, 0);

// Las páginas: entradas del manifest cuyo origen vive en pages/ y no es un test.
const paginas = Object.entries(manifest)
    .filter(([src]) => src.startsWith('resources/js/pages/') && src.endsWith('.jsx') && !src.includes('__tests__'))
    .map(([src, chunk]) => ({
        nombre: src.replace('resources/js/pages/', '').replace('.jsx', ''),
        bytes: statSync(join(BUILD, chunk.file)).size,
    }));

const fallos = [];
if (total > TECHO_TOTAL) {
    fallos.push(`TOTAL: ${kb(total)} KB > ${kb(TECHO_TOTAL)} KB (${jsAssets.length} chunks).`);
}
for (const p of paginas.filter((p) => p.bytes > TECHO_PAGINA)) {
    fallos.push(`PÁGINA ${p.nombre}: ${kb(p.bytes)} KB > ${kb(TECHO_PAGINA)} KB.`);
}

const masGorda = paginas.sort((a, b) => b.bytes - a.bytes)[0];
console.log(
    `bundle: total ${kb(total)} KB / 450 · ${jsAssets.length} chunks · ` +
    `página más gorda ${masGorda ? `${masGorda.nombre} ${kb(masGorda.bytes)} KB` : '—'} / 40`,
);

if (fallos.length > 0) {
    console.error('✗ presupuesto de bundle excedido:');
    for (const f of fallos) console.error(`  ${f}`);
    process.exit(1);
}
console.log('✔ presupuesto de bundle en regla.');
