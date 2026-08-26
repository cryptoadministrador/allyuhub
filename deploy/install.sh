#!/usr/bin/env bash
# Instalación de AllyuHub en el droplet `elearnium` (Debian 12, Docker ya
# presente por el Moodle beta). IDEMPOTENTE: se puede re-ejecutar.
# Uso: bash deploy/install.sh   (desde /root/allyuhub, como root)
set -euo pipefail
cd "$(dirname "$0")/.."
REPO_DIR="$(pwd)"

echo '== 1/7 · Swap de seguridad (2 GB de RAM y el Moodle de vecino) =='
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile
    mkswap /swapfile && swapon /swapfile
    grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo '== 2/7 · Contraseña de la BD (solo la primera vez) =='
if [ ! -f deploy/.env.db ]; then
    echo "DB_PASSWORD=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)" > deploy/.env.db
    chmod 600 deploy/.env.db
fi
export "$(cat deploy/.env.db)"

echo '== 3/7 · .env de producción =='
if [ ! -f .env ]; then
    cp deploy/env.production.example .env
    sed -i "s/^DB_PASSWORD=$/DB_PASSWORD=${DB_PASSWORD}/" .env
fi

echo '== 4/7 · Contenedores =='
# Primero SOLO la BD: la app aún no tiene vendor/ y su healthcheck fallaría.
docker compose -f deploy/docker-compose.yml --env-file deploy/.env.db up -d --wait db

echo '== 5/7 · Dependencias y build (contenedores efímeros) =='
docker compose -f deploy/docker-compose.yml --env-file deploy/.env.db \
    run --rm -T --no-deps --user root app composer install --no-dev --no-interaction --optimize-autoloader
# -T: sin TTY (permite correr desatendido); --user root: el código montado es
# de root y composer corre como www-data en la imagen — el chown final deja
# storage/ para el usuario de ejecución.
docker run --rm -v "$REPO_DIR":/app -w /app node:22-alpine \
    sh -c 'npm ci --no-audit --no-fund && npm run build'

echo '== 6/7 · Laravel: clave, migraciones, cachés, claves LTI =='
docker compose -f deploy/docker-compose.yml --env-file deploy/.env.db up -d app worker
APP() { docker compose -f deploy/docker-compose.yml --env-file deploy/.env.db exec -T --user root app "$@"; }

# La caché NUNCA se queda a medias. El incidente del 2026-08-16 ya estaba
# descrito aquí ("un seeder abortó y la caché de rutas quedó vieja") — pero la
# defensa estaba escrita DESPUÉS de la línea que abortaba, así que con
# `set -euo pipefail` no se disparaba nunca. Dos cambios estructurales:
#
#  1. Este trap recachea SIEMPRE que la app esté en pie, salga el script como
#     salga: publicar código sin recachear es lo único que no puede pasar.
#  2. La SIEMBRA de contenido sale del camino crítico (ver 8/8, al final):
#     sembrar contenido y publicar código son dos cosas distintas, y un fallo
#     de contenido no puede dejar el código a medio publicar.
recachear() {
    APP php artisan optimize:clear
    APP php artisan config:cache
    APP php artisan route:cache
    chown -R www-data:www-data storage bootstrap/cache
}
APP_EN_PIE=1
trap 'estado=$?; if [ "$estado" -ne 0 ] && [ "${APP_EN_PIE:-0}" = 1 ]; then
    echo "✗ el deploy abortó (código $estado): recacheo defensivo para no dejar la caché rancia"
    recachear || echo "✗ el recacheo defensivo también falló: corre optimize:clear/config:cache/route:cache a mano"
fi' EXIT

grep -q '^APP_KEY=.\+' .env || APP php artisan key:generate --force
# SOLO migraciones: el esquema tiene que casar con el código o nada de lo que
# sigue tiene sentido. La semilla va al final, fuera del camino crítico.
APP php artisan migrate --force
APP php artisan lti:keys
recachear

echo '== 7/8 · Fachada nginx del host + certificado =='
cp deploy/nginx-host-allyu.conf /etc/nginx/sites-available/allyu
ln -sf /etc/nginx/sites-available/allyu /etc/nginx/sites-enabled/allyu
nginx -t && systemctl reload nginx
# certbot corre SIEMPRE, no solo la primera vez: el cp de arriba machaca el
# bloque 443 que certbot inyecta en el vhost, y con el guard `[ ! -d live ]`
# el redeploy del 2026-08-16 dejó allyu solo en :80 — el HTTPS caía en el
# server block del Moodle con 404 y certificado ajeno. `--reinstall` reusa el
# certificado vigente (no gasta cuota de Let's Encrypt) y vuelve a cablear 443.
certbot --nginx -d allyu.cuysoft.io --non-interactive --agree-tos \
    --email sistemas@neueschule.edu.ec --redirect --reinstall
curl -sf -o /dev/null https://allyu.cuysoft.io/up \
    || { echo '✗ el /up por HTTPS no responde tras el deploy'; exit 6; }

echo '== 8/8 · Siembra de contenido (fuera del camino crítico) =='
# Si esto falla, el CÓDIGO ya está publicado, cacheado y sirviendo por HTTPS:
# se avisa y el script termina BIEN. Sembrar contenido es reintentar `db:seed`
# a mano cuando el problema de datos esté resuelto — no re-desplegar.
if ! APP php artisan db:seed --force; then
    echo '⚠ la siembra de contenido falló. El deploy del CÓDIGO está completo;'
    echo '  arregla el dato que nombra el error de arriba y corre:'
    echo '    docker compose -f deploy/docker-compose.yml --env-file deploy/.env.db exec -T app php artisan db:seed --force'
fi

echo
echo '✔ LISTO. Comprueba: curl -sI https://allyu.cuysoft.io/up'
echo '  Siguiente paso: registrar el Moodle beta como Platform (lti:platform:add)'
echo '  siguiendo docs/lti-moodle.md §1-2.'
