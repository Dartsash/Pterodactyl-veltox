#!/usr/bin/env bash
#
# Build a clean, downloadable Veltox release from a WORKING panel.
# Run it on the server where everything already works:
#
#   bash make-release.sh /var/www/pterodactyl 1.0.0
#
# Result: /root/veltox-<version>.tar.gz  ->  upload it to GitHub Releases.
#
set -euo pipefail
PANEL_DIR="${1:-/var/www/pterodactyl}"
VERSION="${2:-$(date +%Y.%m.%d)}"
OUT="/root/veltox-${VERSION}.tar.gz"

[ -f "$PANEL_DIR/artisan" ] || { echo "not a panel: $PANEL_DIR"; exit 1; }
cd "$PANEL_DIR"

echo "==> rebuilding the frontend so the release contains a fresh theme"
if command -v yarn >/dev/null; then
  yarn install --frozen-lockfile
  yarn build:production
else
  echo "!! yarn not found - packing existing public/assets"
fi
[ -f public/assets/manifest.json ] || { echo "!! manifest.json missing, aborting"; exit 1; }

echo "==> packing $OUT"
tar -czf "$OUT" \
  --exclude='./.git' \
  --exclude='./.env' \
  --exclude='./node_modules' \
  --exclude='./vendor' \
  --exclude='./storage/logs/*' \
  --exclude='./storage/framework/cache/*' \
  --exclude='./storage/framework/sessions/*' \
  --exclude='./storage/framework/views/*' \
  --exclude='./storage/clockwork/*' \
  --exclude='./storage/blueprint-*' \
  --exclude='./storage/theme-fix-*' \
  --exclude='./storage/version-manager-*' \
  --exclude='./bootstrap/cache/*.php' \
  --exclude='./*.tar.gz' \
  --exclude='./.build-cache.json' \
  .

echo "==> checking the archive contains the compiled theme"
tar -tzf "$OUT" | grep -q 'public/assets/manifest.json' && echo "   manifest.json: OK" || echo "   !! manifest.json MISSING"
tar -tzf "$OUT" | grep -c 'public/assets/.*\.js' | xargs -I{} echo "   js bundles: {}"
tar -tzf "$OUT" | grep -q '\.env$' && echo "   !! WARNING: .env leaked into the archive" || echo "   .env: not included (good)"

echo
echo "Done: $OUT"
echo "Upload it to GitHub -> Releases -> Draft a new release -> attach the file."
