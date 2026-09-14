#!/usr/bin/env bash
# Gera um ZIP pronto para upload FTP/cPanel (sem .env, uploads, caches).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/meck-rh-hosting.zip}"
cd "$ROOT"

chmod +x scripts/docker-entrypoint.sh 2>/dev/null || true

zip -r "$OUT" . \
  -x "*.git*" \
  -x "*node_modules*" \
  -x "*.env" \
  -x "*tmp/*" \
  -x "*uploads/*/*" \
  -x "*gravacoes/*/*" \
  -x "*backups/*" \
  -x "*agent-transcripts*" \
  -x "*meck-rh-hosting.zip" \
  -x "*test_*.php" \
  -x "*reset_*.php" \
  -x "*setup_*.php" \
  -x "*scratch/*" \
  -x "*test_ui_form.html"


echo "Pacote criado: $OUT"
echo "Lembrete: configure .env no servidor a partir de .env.example"
