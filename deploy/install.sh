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
docker compose -f deploy/docker-compose.yml --env-file deploy/.env.db up -d --wait

echo '== 5/7 · Dependencias y build (contenedores efímeros) =='
docker compose -f deploy/docker-compose.yml --env-file deploy/.env.db \
    run --rm --no-deps app composer install --no-dev --no-interaction --optimize-autoloader
docker run --rm -v "$REPO_DIR":/app -w /app node:22-alpine \
    sh -c 'npm ci --no-audit --no-fund && npm run build'

echo '== 6/7 · Laravel: clave, migraciones, semilla, claves LTI =='
APP() { docker compose -f deploy/docker-compose.yml --env-file deploy/.env.db exec -T app "$@"; }
grep -q '^APP_KEY=.\+' .env || APP php artisan key:generate --force
APP php artisan migrate --seed --force
APP php artisan lti:keys
APP php artisan config:cache
APP php artisan route:cache
chown -R www-data:www-data storage bootstrap/cache

echo '== 7/7 · Fachada nginx del host + certificado =='
cp deploy/nginx-host-allyu.conf /etc/nginx/sites-available/allyu
ln -sf /etc/nginx/sites-available/allyu /etc/nginx/sites-enabled/allyu
nginx -t && systemctl reload nginx
if [ ! -d /etc/letsencrypt/live/allyu.cuysoft.io ]; then
    certbot --nginx -d allyu.cuysoft.io --non-interactive --agree-tos \
        --email sistemas@neueschule.edu.ec --redirect
fi

echo
echo '✔ LISTO. Comprueba: curl -sI https://allyu.cuysoft.io/up'
echo '  Siguiente paso: registrar el Moodle beta como Platform (lti:platform:add)'
echo '  siguiendo docs/lti-moodle.md §1-2.'
