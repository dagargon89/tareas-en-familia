#!/usr/bin/env bash
# Despliegue en el hosting (T-S0-05, doc 02 §6). Corre EN EL SERVIDOR, llamado por el workflow por SSH.
#
#   deploy_remoto.sh desplegar --base DIR --paquete ARCHIVO [--publico DIR] [--php BIN] [--conservar N]
#   deploy_remoto.sh revertir  --base DIR [--publico DIR] [--php BIN]
#   deploy_remoto.sh estado    --base DIR
#
# Estructura (doc 02 §6):
#   DIR/releases/<AAAAMMDD-HHMMSS>/   código de cada release
#   DIR/app-tareas -> releases/<id>   release activo (lo usan el cron y la CLI)
#   DIR/compartido/.env               secretos del entorno (se crea a mano una vez)
#   DIR/compartido/writable/          sesiones, logs, caché, evidencias y respaldos (persisten)
#   DIR/public_html/                  copia de public/ del release activo
#
# El index.php publicado apunta a la ruta REAL del release (no al enlace app-tareas), para que la
# caché de rutas de PHP no siga sirviendo el release anterior tras el cambio.
# Las migraciones corren antes de activar el release y no se revierten: deben ser compatibles hacia atrás.
set -euo pipefail
umask 022

modo="${1:-}"
shift || true

base=""
paquete=""
publico=""
php_bin="php"
conservar=5

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base) base="$2"; shift 2 ;;
    --paquete) paquete="$2"; shift 2 ;;
    --publico) publico="$2"; shift 2 ;;
    --php) php_bin="$2"; shift 2 ;;
    --conservar) conservar="$2"; shift 2 ;;
    *) echo "Opción desconocida: $1" >&2; exit 2 ;;
  esac
done

falla() { echo "ERROR: $*" >&2; exit 1; }
info() { echo "· $*"; }

[[ -n "$base" ]] || falla "Falta --base"
[[ -d "$base" ]] || falla "No existe la carpeta base: $base"
base="$(cd "$base" && pwd)"
publico="${publico:-$base/public_html}"
compartido="$base/compartido"
releases="$base/releases"
activo="$base/app-tareas"

[[ "$conservar" =~ ^[0-9]+$ && "$conservar" -ge 2 ]] || falla "--conservar debe ser un número ≥ 2"

# Publica public/ del release en public_html sin borrar lo ajeno (p. ej. .well-known del certificado).
publicar() {
  local rel="$1"
  mkdir -p "$publico/app"
  rsync -a --delete "$rel/public/app/" "$publico/app/"
  local f
  for f in "$rel"/public/* "$rel"/public/.htaccess "$rel"/public/.user.ini; do
    [[ -e "$f" && ! -d "$f" ]] || continue
    [[ "$(basename "$f")" == "index.php" ]] && continue
    cp -p "$f" "$publico/"
  done
  # index.php → Paths.php del release real; se escribe aparte y se mueve (cambio atómico).
  local temporal="$publico/.index.php.nuevo"
  sed "s|FCPATH . '../app/Config/Paths.php'|'$rel/app/Config/Paths.php'|" "$rel/public/index.php" > "$temporal"
  grep -qF "'$rel/app/Config/Paths.php'" "$temporal" || { rm -f "$temporal"; falla "No se pudo reescribir index.php"; }
  mv -f "$temporal" "$publico/index.php"
}

# Cambia el enlace app-tareas de forma atómica.
activar() {
  local id="$1"
  ln -sfn "releases/$id" "$base/.app-tareas.nuevo"
  mv -Tf "$base/.app-tareas.nuevo" "$activo"
}

actual_id() { [[ -L "$activo" ]] && basename "$(readlink "$activo")" || true; }

case "$modo" in
  estado)
    echo "Release activo: $(actual_id || echo ninguno)"
    [[ -f "$compartido/release.anterior" ]] && echo "Release anterior: $(cat "$compartido/release.anterior")"
    [[ -d "$releases" ]] && ls -1 "$releases"
    exit 0
    ;;
  desplegar|revertir) ;;
  *) echo "Uso: $0 {desplegar|revertir|estado} --base DIR [opciones]" >&2; exit 2 ;;
esac

[[ -f "$compartido/.env" ]] || falla "Falta $compartido/.env (créalo una vez con la plantilla del doc 11 §4)"

# Un despliegue a la vez.
exec 9>"$compartido/deploy.lock"
flock -n 9 || falla "Hay otro despliegue en curso"

"$php_bin" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
  || falla "Se requiere PHP 8.3 o superior en la CLI ($("$php_bin" -r 'echo PHP_VERSION;'))"

if [[ "$modo" == "revertir" ]]; then
  [[ -f "$compartido/release.anterior" ]] || falla "No hay release anterior registrado"
  anterior="$(cat "$compartido/release.anterior")"
  [[ -d "$releases/$anterior" ]] || falla "El release anterior ya no existe: $anterior"
  actual="$(actual_id)"
  info "Revirtiendo de $actual a $anterior"
  publicar "$releases/$anterior"
  activar "$anterior"
  echo "$anterior" > "$compartido/release.actual"
  [[ -n "$actual" ]] && echo "$actual" > "$compartido/release.anterior"
  info "Listo: activo $anterior"
  exit 0
fi

# ===== desplegar =====
[[ -n "$paquete" && -f "$paquete" ]] || falla "Falta --paquete o no existe: $paquete"

id="$(date +%Y%m%d-%H%M%S)"
rel="$releases/$id"
[[ ! -e "$rel" ]] || falla "Ya existe el release $id"
mkdir -p "$rel"
info "Release $id"

limpiar_si_falla() { rm -rf "$rel"; }
trap limpiar_si_falla ERR

tar -xzf "$paquete" -C "$rel"

# writable y .env compartidos entre releases.
for dir in cache logs session uploads evidencias respaldos; do
  mkdir -p "$compartido/writable/$dir"
done
rm -rf "$rel/writable"
ln -s "$compartido/writable" "$rel/writable"
ln -sfn "$compartido/.env" "$rel/.env"

info "Migraciones y catálogos"
# `tareas:migrar` sale con 1 si algo falla (`spark migrate` sale con 0 aunque falle).
(cd "$rel" && "$php_bin" spark tareas:migrar --no-header) || { limpiar_si_falla; falla "Las migraciones fallaron; el release activo no cambió"; }

trap - ERR

anterior="$(actual_id)"
info "Publicando en $publico"
publicar "$rel"
activar "$id"
echo "$id" > "$compartido/release.actual"
[[ -n "$anterior" ]] && echo "$anterior" > "$compartido/release.anterior"
rm -f "$paquete"

# Conserva los N releases más recientes (siempre el activo y el anterior).
# Sin sustitución de procesos: en CloudLinux/CageFS (Hostinger) no existe /dev/fd.
mapfile -t todos <<< "$(find "$releases" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r)"
for viejo in "${todos[@]:$conservar}"; do
  [[ "$viejo" == "$id" || "$viejo" == "$anterior" ]] && continue
  rm -rf "${releases:?}/$viejo"
  info "Borrado release viejo $viejo"
done

info "Listo: activo $id (versión $(cat "$rel/VERSION" 2>/dev/null || echo ?))"
info "Cron en hPanel (cada minuto): cd $activo && $php_bin spark tareas:programadas >/dev/null 2>&1"
