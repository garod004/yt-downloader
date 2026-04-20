#!/bin/bash
set -e

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-realassessoria}"

echo "[entrypoint] Aguardando MySQL em $DB_HOST..."
until mysqladmin ping -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --silent 2>/dev/null; do
    sleep 2
done
echo "[entrypoint] MySQL disponível."

TABLE_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='usuarios';" \
    -s -N 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -eq 0 ]; then
    echo "[entrypoint] Inicializando banco de dados..."
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < /var/www/html/docker/init.sql
    echo "[entrypoint] Banco inicializado. Login padrão: admin@realassessoria.local / admin123"
fi

exec "$@"
