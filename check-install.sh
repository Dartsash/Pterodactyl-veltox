#!/usr/bin/env bash
#
# Veltox doctor - tells you exactly what is missing/broken on the panel.
#   bash check-install.sh [/var/www/pterodactyl]
#
PANEL_DIR="${1:-/var/www/pterodactyl}"
RED=$'\e[31m'; GRN=$'\e[32m'; YLW=$'\e[33m'; NC=$'\e[0m'
fail=0
pass() { echo "${GRN}[ok]${NC}   $*"; }
bad()  { echo "${RED}[bad]${NC}  $*"; fail=$((fail+1)); }
warn() { echo "${YLW}[warn]${NC} $*"; }

cd "$PANEL_DIR" 2>/dev/null || { bad "$PANEL_DIR not found"; exit 1; }
echo "=== Veltox doctor: $PANEL_DIR ==="

[ -f artisan ] && pass "panel root looks correct" || bad "artisan not found - wrong directory"
[ -f .env ] && pass ".env present" || bad ".env missing (copy .env.example, then: php artisan key:generate --force)"
grep -q '^APP_KEY=base64' .env 2>/dev/null && pass "APP_KEY set" || bad "APP_KEY empty -> php artisan key:generate --force"
[ -d vendor ] && pass "vendor/ present" || bad "vendor/ missing -> composer install --no-dev --optimize-autoloader"

# ---- frontend / theme
if [ -f public/assets/manifest.json ]; then
  pass "public/assets/manifest.json present (theme can load)"
  for f in $(grep -o '"src": "/assets/[^"]*"' public/assets/manifest.json | cut -d'"' -f4); do
    [ -f "public${f}" ] || bad "asset listed in manifest but missing on disk: public${f}"
  done
else
  bad "public/assets/manifest.json MISSING -> theme will not render (git ignores it by default!)"
fi
JS_COUNT=$(ls public/assets/*.js 2>/dev/null | wc -l)
[ "$JS_COUNT" -gt 0 ] && pass "compiled JS bundles: $JS_COUNT" || bad "no compiled JS in public/assets -> yarn install && yarn build:production"
[ -f public/css/custom-theme.css ] && pass "custom-theme.css present" || warn "public/css/custom-theme.css missing"
grep -q 'custom-theme.css' resources/views/layouts/admin.blade.php 2>/dev/null \
  && pass "custom-theme.css linked in admin layout" || warn "custom-theme.css not linked in resources/views/layouts/admin.blade.php"

# ---- storage skeleton
for d in storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
  [ -d "$d" ] && pass "dir $d" || bad "dir $d missing -> mkdir -p $d && chown -R www-data:www-data storage bootstrap/cache"
done

# ---- namespaces
if grep -rl '^namespace App\\' app 2>/dev/null | grep -q .; then
  bad "files still use namespace App\\ (Pterodactyl autoloads Pterodactyl\\ only):"
  grep -rl '^namespace App\\' app | sed 's/^/       /'
else
  pass "all PHP classes use the Pterodactyl\\ namespace"
fi

# ---- database tables
if command -v php >/dev/null; then
  for t in staff_roles admin_permissions user_permissions veltox_notifications addons server_addons; do
    RES=$(php artisan tinker --execute "echo Schema::hasTable('$t') ? 'yes' : 'no';" 2>/dev/null | tail -1)
    case "$RES" in
      *yes*) pass "table $t exists" ;;
      *no*)  bad "table $t missing -> php artisan migrate --force" ;;
      *)     warn "could not check table $t" ;;
    esac
  done
fi

# ---- permissions
OWNER=$(stat -c '%U' storage 2>/dev/null)
case "$OWNER" in
  www-data|nginx|apache) pass "storage owned by $OWNER" ;;
  *) bad "storage owned by $OWNER -> chown -R www-data:www-data $PANEL_DIR" ;;
esac

echo
[ "$fail" -eq 0 ] && echo "${GRN}No blocking problems found.${NC}" || echo "${RED}Problems found: $fail${NC}"
echo "Last errors from the log:"
tail -n 15 storage/logs/laravel-*.log 2>/dev/null | tail -n 15
