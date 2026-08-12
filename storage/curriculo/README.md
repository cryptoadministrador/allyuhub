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
| EPJA_Completo_Adaptaciones-Curriculares.pdf | Dosificación PCEI (Acuerdo 2017-00040-A) |

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
