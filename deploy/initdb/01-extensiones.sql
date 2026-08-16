-- Extensiones que exigen las migraciones (CLAUDE.md, Arranque).
-- Este script corre como superusuario SOLO al crear el volumen de datos.
CREATE EXTENSION IF NOT EXISTS ltree;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
