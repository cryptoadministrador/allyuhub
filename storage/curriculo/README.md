# Documentos curriculares oficiales

El comando `php artisan mineduc:import <archivo> --official` procesa los PDF de esta
carpeta. Este entorno no puede descargarlos directamente (educacion.gob.ec bloquea
conexiones que no vienen del navegador), así que descárgalos tú y colócalos aquí.

## Los que hay que descargar (Currículo 2016, base vigente)

Desde https://educacion.gob.ec/curriculo/ (o sección Documentos):

| Archivo | Cubre |
|---|---|
| CCNN_COMPLETO.pdf | Ciencias Naturales EGB (subniveles 2-4) + Biología/Física/Química BGU |
| M_COMPLETO.pdf | Matemática EGB + BGU |
| LL_COMPLETO.pdf | Lengua y Literatura EGB + BGU |
| CCSS_COMPLETO.pdf | Estudios Sociales EGB + Historia/Filosofía/Ciudadanía BGU |
| ECA_COMPLETO.pdf | Educación Cultural y Artística |
| EF_COMPLETO.pdf | Educación Física |
| Curriculo-integrador-preparatoria.pdf | 1.º EGB (ámbitos) |

## Para el importador PCEI (roadmap §2) — fuentes ACTUALIZADAS 2025

OJO: el Acuerdo 2017-00040-A fue **derogado** por el MINEDUC-2025-00034-A
(RO 2.º Supl. Nº 121, 10-sep-2025). Descargar:

| Archivo | URL | Cubre |
|---|---|---|
| curriculo-alfabetizacion-priorizado.pdf | educacion.gob.ec/wp-content/uploads/downloads/2025/09/ | ALFA (mód. 1-2) + POST (mód. 3-6), 100 días/módulo — Acuerdo 2025-00034-A |
| EPJA_Completo_Adaptaciones-Curriculares.pdf | educacion.gob.ec/wp-content/uploads/downloads/2017/05/ | Referencia histórica: adaptaciones Básica Superior + Bachillerato (2017, derogado) |
| MINEDUC-MINEDUC-2025-00034-A (RO Nº 121) | esacc.corteconstitucional.gob.ec (buscar en registroficial.gob.ec) | Texto del acuerdo que expide el currículo EPJA por competencias |

Flujo (ALFA/POST ya implementado):

```bash
php artisan epja:import storage/curriculo/curriculo-alfabetizacion-priorizado.pdf --dry-run
php artisan epja:import storage/curriculo/curriculo-alfabetizacion-priorizado.pdf --official
```

Ojo: el priorizado usa códigos PROPIOS (`A.RS.n`, `P.CC.n`, `CAI.JA.b.n`), no los del 2016.
La trampa de los códigos 2016 AGRUPADOS (`LL.4.1. (1, 2)` = LL.4.1.1 + LL.4.1.2) aplica al
anexo de ADAPTACIONES de Básica Superior/Bachillerato (importador pendiente).

También sirven los PDF del Currículo Priorizado con énfasis en competencias (2023)
— impórtalos como versión nueva del framework, no encima de la 2016.

## Flujo

```bash
php artisan mineduc:import storage/curriculo/CCNN_COMPLETO.pdf --dry-run   # revisar
php artisan mineduc:import storage/curriculo/CCNN_COMPLETO.pdf --official  # importar
```

`--official` marca is_verified=true, retira los marcadores del seeder de esa área
y registra el sha256 del PDF para trazabilidad. Sin `--official`, lo importado queda
sin verificar y NUNCA pisa una destreza ya verificada.

fixture-ccnn-superior.txt es un ejemplo del formato para probar el parser.
