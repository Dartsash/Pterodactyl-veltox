#!/usr/bin/env bash
#
# Veltox for Pterodactyl - installer for a CLEAN panel (Pterodactyl 1.14.x)
#
#   bash install.sh                      # installs into /var/www/pterodactyl
#   bash install.sh /path/to/panel       # custom path
#   bash install.sh /var/www/pterodactyl --build   # also rebuilds the frontend (needs node + yarn)
#
set -euo pipefail

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PANEL_DIR="${1:-/var/www/pterodactyl}"
BUILD="no"
for a in "$@"; do [ "$a" = "--build" ] && BUILD="yes"; done

RED=$'\e[31m'; GRN=$'\e[32m'; YLW=$'\e[33m'; BLU=$'\e[34m'; NC=$'\e[0m'
info() { echo "${BLU}==>${NC} $*"; }
ok()   { echo "${GRN} OK ${NC} $*"; }
warn() { echo "${YLW}WARN${NC} $*"; }
die()  { echo "${RED}ERR ${NC} $*"; exit 1; }

[ "$(id -u)" -eq 0 ] || die "run as root (sudo bash install.sh)"
[ -f "$PANEL_DIR/artisan" ] || die "$PANEL_DIR is not a Pterodactyl panel (artisan not found)"
[ -f "$PANEL_DIR/.env" ]    || die "$PANEL_DIR/.env not found - install and configure the stock panel first"

PANEL_VER=$(grep -oP "'version'\s*=>\s*'\K[^']+" "$PANEL_DIR/config/app.php" 2>/dev/null || echo unknown)
SRC_VER=$(grep -oP "'version'\s*=>\s*'\K[^']+" "$SRC_DIR/config/app.php" 2>/dev/null || echo unknown)
info "panel version: $PANEL_VER   |   veltox build for: $SRC_VER"
if [ "$PANEL_VER" != "$SRC_VER" ] && [ "$PANEL_VER" != "unknown" ]; then
  warn "versions differ - install stock Pterodactyl $SRC_VER first, otherwise files will conflict"
  read -r -p "continue anyway? [y/N] " a; [ "${a,,}" = "y" ] || exit 1
fi

# ------------------------------------------------------------------ 1. backup
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="/root/veltox-backup-$STAMP"
mkdir -p "$BACKUP"
info "backing up current panel -> $BACKUP"
tar -czf "$BACKUP/panel-files.tar.gz" -C "$PANEL_DIR" \
    --exclude=node_modules --exclude=vendor --exclude=storage/logs . 2>/dev/null || true
set +e
DB_NAME=$(grep -E '^DB_DATABASE=' "$PANEL_DIR/.env" | cut -d= -f2- | tr -d '"')
DB_USER=$(grep -E '^DB_USERNAME=' "$PANEL_DIR/.env" | cut -d= -f2- | tr -d '"')
DB_PASS=$(grep -E '^DB_PASSWORD=' "$PANEL_DIR/.env" | cut -d= -f2- | tr -d '"')
if command -v mysqldump >/dev/null && [ -n "$DB_NAME" ]; then
  mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP/database.sql" 2>/dev/null \
    && ok "database dump saved" || warn "mysqldump failed - make a DB backup manually before continuing"
fi
set -e
ok "backup ready ($BACKUP)"

# ---------------------------------------------------------------- 2. copy files
cd "$PANEL_DIR"
php artisan down || true

info "copying Veltox files"
if command -v rsync >/dev/null; then
  rsync -a --human-readable \
    --exclude='.git/' --exclude='.env' --exclude='node_modules/' --exclude='vendor/' \
    --exclude='storage/logs/' --exclude='storage/framework/cache/' \
    --exclude='storage/framework/sessions/' --exclude='storage/framework/views/' \
    --exclude='install.sh' --exclude='check-install.sh' --exclude='make-release.sh' \
    "$SRC_DIR/" "$PANEL_DIR/"
else
  cp -a "$SRC_DIR/." "$PANEL_DIR/"
  rm -f "$PANEL_DIR/install.sh" "$PANEL_DIR/check-install.sh" "$PANEL_DIR/make-release.sh"
fi
ok "files copied"

# --------------------------------------------------------- 3. php dependencies
info "composer install"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction
ok "vendor/ ready"

# ------------------------------------------------------------ 4. frontend build
if [ "$BUILD" = "yes" ]; then
  info "building frontend (yarn)"
  command -v yarn >/dev/null || npm install -g yarn
  yarn install --frozen-lockfile
  yarn build:production
  ok "frontend rebuilt"
else
  if [ -f "$PANEL_DIR/public/assets/manifest.json" ]; then
    ok "prebuilt theme found (public/assets/manifest.json)"
  else
    die "public/assets/manifest.json is missing - re-download the release or run: bash install.sh $PANEL_DIR --build"
  fi
fi

# --------------------------------------------------------------- 5. migrations
info "database migrations"
php artisan migrate --force
ok "migrations applied"

# ------------------------------------------------------------------ 6. caches
info "clearing caches"
php artisan optimize:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear
ok "caches cleared"

# -------------------------------------------------------------- 7. permissions
WEBUSER="www-data"
id -u nginx  >/dev/null 2>&1 && WEBUSER="nginx"
id -u apache >/dev/null 2>&1 && WEBUSER="apache"
id -u www-data >/dev/null 2>&1 && WEBUSER="www-data"
info "fixing ownership -> $WEBUSER"
chown -R "$WEBUSER:$WEBUSER" "$PANEL_DIR"
chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
ok "permissions fixed"

# ------------------------------------------------------------------ 8. services
php artisan up
systemctl restart pteroq 2>/dev/null || true
systemctl restart php8.3-fpm 2>/dev/null || systemctl restart php8.2-fpm 2>/dev/null \
  || systemctl restart php8.1-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo
ok "Veltox installed. Open the panel and hard-refresh with Ctrl+F5."
echo "   Backup: $BACKUP"
echo "   Rollback: tar -xzf $BACKUP/panel-files.tar.gz -C $PANEL_DIR"
