#!/usr/bin/env bash
# Empaqueta un release de Tareas en Familia (T-S0-05, doc 02 §6).
#
# Uso: scripts/empaquetar.sh <salida.tar.gz>
#
# Requiere el build de la SPA ya hecho (`npm run build` en src/web deja src/api/public/app).
# El paquete trae el código de CI4 con `vendor/` sin dependencias de desarrollo y SIN:
# pruebas, `.env`, `writable/` (vive en `compartido/` en el servidor) ni configuración de PHPUnit.
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Uso: $0 <salida.tar.gz>" >&2
  exit 2
fi

raiz="$(cd "$(dirname "$0")/.." && pwd)"
api="$raiz/src/api"
salida="$1"

if [[ ! -f "$api/public/app/index.html" ]]; then
  echo "Falta el build de la SPA (src/api/public/app/index.html). Corre: npm --prefix src/web run build" >&2
  exit 1
fi

trabajo="$(mktemp -d)"
trap 'rm -rf "$trabajo"' EXIT
destino="$trabajo/release"
mkdir -p "$destino"

rsync -a \
  --exclude '/vendor/' \
  --exclude '/tests/' \
  --exclude '/writable/' \
  --exclude '/.env' \
  --exclude '/env' \
  --exclude '/phpunit.xml' \
  --exclude '/phpunit.xml.dist' \
  --exclude '/build/' \
  --exclude '.DS_Store' \
  "$api/" "$destino/"

# Dependencias de producción con la plataforma del hosting (config.platform.php = 8.3 en composer.json).
composer install --working-dir="$destino" --no-dev --optimize-autoloader --no-interaction --no-progress --quiet

# Versión del release: commit corto (o "local" fuera de git).
version="$(git -C "$raiz" rev-parse --short HEAD 2>/dev/null || echo local)"
echo "$version" > "$destino/VERSION"

mkdir -p "$(dirname "$salida")"
tar -czf "$salida" -C "$destino" .
echo "Paquete listo: $salida ($(du -h "$salida" | cut -f1), versión $version)"
