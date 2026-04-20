#!/bin/bash
set -e

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-realassessoria}"

echo "[entrypoint] Aguardando MySQL em $DB_HOST..."
until php -r "
\$c = @new mysqli('$DB_HOST', '$DB_USER', '$DB_PASS');
exit(\$c->connect_error ? 1 : 0);
" 2>/dev/null; do
    sleep 2
done
echo "[entrypoint] MySQL disponível."

TABLE_COUNT=$(php -r "
\$c = new mysqli('$DB_HOST', '$DB_USER', '$DB_PASS', '$DB_NAME');
\$r = \$c->query(\"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='usuarios'\");
echo \$r->fetch_row()[0];
" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -eq 0 ]; then
    echo "[entrypoint] Inicializando banco de dados..."
    php -r "
\$c = new mysqli('$DB_HOST', '$DB_USER', '$DB_PASS', '$DB_NAME');
\$sql = file_get_contents('/var/www/html/docker/init.sql');
foreach (array_filter(array_map('trim', explode(';', \$sql))) as \$q) {
    \$c->query(\$q);
}
echo 'OK';
" 2>/dev/null
    echo "[entrypoint] Banco inicializado. Login: admin@realassessoria.local / admin123"
fi

exec "$@"
