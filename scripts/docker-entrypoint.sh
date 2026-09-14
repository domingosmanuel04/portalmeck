#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p \
  uploads/profiles uploads/company uploads/docs uploads/chat uploads/cvs \
  uploads/attendance uploads/activities uploads/tickets \
  gravacoes/tickets backups tmp

# Garantir permissões de escrita (hosting / volume montado)
chown -R www-data:www-data uploads gravacoes backups tmp 2>/dev/null || true
chmod -R ug+rwX uploads gravacoes backups tmp 2>/dev/null || true

# .htaccess locais (quando o volume permite)
for d in uploads/docs uploads/chat uploads/cvs uploads/attendance uploads/activities uploads/tickets gravacoes; do
  if [ -d "$d" ] && [ ! -f "$d/.htaccess" ]; then
    printf 'Require all denied\n' > "$d/.htaccess" 2>/dev/null || true
  fi
done

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
  echo "[meck] Criado .env a partir de .env.example — altere as passwords em produção."
fi

# Esperar pela BD se RUN_MIGRATIONS=1
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "[meck] A aguardar base de dados..."
  for i in $(seq 1 60); do
    if php -r '
      $h=getenv("DB_HOST")?:"db"; $p=getenv("DB_PORT")?:"3306";
      $d=getenv("DB_DATABASE")?:"intern_system_db";
      $u=getenv("DB_USERNAME")?:"meck"; $w=getenv("DB_PASSWORD")?:"meck_secret";
      try { new PDO("mysql:host=$h;port=$p;dbname=$d", $u, $w); exit(0);} catch(Throwable $e){ exit(1);}
    '; then
      echo "[meck] BD disponível."
      # Dispara auto-schema via include
      php -r 'require "/var/www/html/api/db.php"; echo "[meck] Schema OK\n";' || true
      break
    fi
    sleep 2
  done
fi

exec "$@"
