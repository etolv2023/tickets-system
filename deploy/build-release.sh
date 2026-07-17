#!/usr/bin/env bash
# Builds the delivery zip (PLAN.md § 9): the project + vendor/ + built assets,
# ready to unzip on a server and open /install.
#
# Usage:  bash deploy/build-release.sh [output.zip]
#
# Ships WITHOUT: .env, installed.lock, the local database, node_modules, and any
# dev-only files — so a fresh unzip lands on the install wizard, not on someone
# else's configuration.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/dist/etolv-tickets-$(date +%Y%m%d).zip}"

cd "$ROOT"

echo "==> production dependencies (no dev, optimised autoloader)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> building front-end assets"
npm ci
npm run build

echo "==> clearing cached config so nothing local leaks into the zip"
php artisan config:clear
php artisan route:clear
php artisan view:clear

mkdir -p "$(dirname "$OUT")"
rm -f "$OUT"

echo "==> zipping to $OUT"
# -x excludes: never ship secrets, the install lock, local db, dev deps, or vcs.
zip -rq "$OUT" . \
  -x '.env' \
  -x 'storage/installed.lock' \
  -x 'node_modules/*' \
  -x 'dist/*' \
  -x '.git/*' \
  -x 'storage/framework/sessions/*' \
  -x 'storage/framework/cache/data/*' \
  -x 'storage/logs/*' \
  -x 'storage/app/private/tickets/*' \
  -x 'storage/app/public/branding/*' \
  -x '.env.backup' \
  -x 'tests/*'

echo "==> restoring dev dependencies for continued local work"
composer install --no-interaction >/dev/null 2>&1 || true

SIZE=$(du -h "$OUT" | cut -f1)
echo "==> done: $OUT ($SIZE)"
echo "    On the server: unzip, point the web root at public/, open the site."
