# AllyuHub

Plataforma de aprendizaje para Ecuador — educación ordinaria (1.º EGB → 3.º de Bachillerato)
y PCEI/EPJA (personas con escolaridad inconclusa) — con simuladores y laboratorios virtuales
propios, estructura curricular multi-marco (MINEDEC · Cambridge · IB) e integración con
Moodle vía LTI 1.3.

> *Ayllu* (quichua): la comunidad, la familia extendida. La misma comunidad de aprendizaje
> para el niño de 6 años y para su madre que retoma la básica a los 34.

## Qué hay en este repo

El **núcleo curricular**: el grafo de objetivos de aprendizaje versionado, los trayectos
(ordinaria y fases intensivas PCEI), el catálogo de recursos y la API de solo lectura que
los sirve. Los simuladores viven en su propio monorepo y se registran aquí como recursos.

| Ya funciona | Pendiente (ver CLAUDE.md) |
|---|---|
| Esquema PostgreSQL con ltree (grafo curricular) | Importador de PDF oficiales MINEDEC |
| Seeder: 13 grados · 100 asignaturas · 1010 destrezas | Dosificación PCEI real (Acuerdo 2017-00040-A) |
| 5 trayectos: ORD + 4 PCEI con fase propedéutica | Marcos Cambridge e IB + crosswalk |
| API v1: árbol, búsqueda full-text, tracks, recursos | Motor de práctica · LTI 1.3 · frontend |
| 5 tests de API en verde (SQLite) | |

## Arranque rápido

```bash
composer install
cp .env.example .env && php artisan key:generate
# Configura PostgreSQL en .env y crea las extensiones:
#   CREATE EXTENSION ltree; CREATE EXTENSION pg_trgm;
php artisan migrate --seed
php artisan serve
```

Prueba: `curl localhost:8000/api/v1/tracks` · `curl "localhost:8000/api/v1/objectives/search?q=rozamiento"`

Tests: `php artisan test`

## Documentación

- `CLAUDE.md` — guía de trabajo (convenciones, modelo mental, roadmap). Léela primero.
- Proyecto Claude "e-skool": plan maestro v2 (estrategia, PCEI, capa IA) y arquitectura v1
  (modelo de datos completo, SDK de simuladores, integración Moodle).

## Licencia

Por definir (el plan recomienda CC BY-SA 4.0 para contenido; el código, por decidir).
