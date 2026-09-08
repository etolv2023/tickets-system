#!/usr/bin/env bash
# Builds the delivery zip (PLAN.md § 9): the project + vendor/ + built assets,
# ready to unzip on a server and open /install.
#
# Usage:
#   bash deploy/build-release.sh [output.zip]
#   BASE_URL=http://host/subdir/public bash deploy/build-release.sh [output.zip]
#
# Two ways to host this, and they need two different builds:
#
#   1. The web server's document root points AT this app's public/ folder, so
#      the app owns the domain (or subdomain) root — http://host/. No BASE_URL
#      needed; this is the default and matches every path in the compiled CSS
#      and every @vite()-generated tag by default.
#
#   2. This folder sits inside an EXISTING site, reached through a path like
#      http://host/tickets-system/public/ — several unrelated apps sharing one
#      server, each in its own subdirectory. Laravel's own PHP-generated URLs
#      (asset(), route(), @vite() tags) adapt to this automatically, request by
#      request — no config needed for those. But a handful of paths are baked
#      into the build as literal strings at build time, because a compiled CSS
#      file has no request to be "aware" of at the point a browser reads it:
#      the self-hosted @font-face url()s. Those need BASE_URL set to the exact
#      path this copy will be served from BEFORE this script runs, so Vite can
#      bake the right prefix into them once, here, rather than never.
#
#   Building for case 1 and then deploying into case 2 is exactly what breaks:
#   the page loads, its own JS/CSS resolve fine (they ask Laravel per request),
#   but the fonts named inside that CSS still point at domain root and 404 —
#   which is also why the page look ends up broken: no Cairo, no IBM Plex Mono,
#   the browser's fallback font in their place.
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

if [ -n "${BASE_URL:-}" ]; then
    echo "==> building for a subdirectory deployment: ${BASE_URL}"
    ASSET_URL="$BASE_URL" npm run build
else
    echo "==> building for a domain-root deployment (default — set BASE_URL to change this)"
    npm run build
fi

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

# ★ (2026-09-08) Put the runtime directories back.
#
# `-x 'storage/logs/*'` does not only skip the FILES in storage/logs — it also
# matches the directory's own entry, `storage/logs/`, because zip's `*` happily
# matches the empty string after that slash. So each of the three -x patterns
# above silently deleted the folder it was meant to empty, and a fresh unzip
# produced a tree with no storage/logs, no storage/framework/sessions and no
# storage/framework/cache/data at all.
#
# What that looks like on the server is nothing to do with zipping: the app
# installs fine, then throws a 500 on the first POST — file_put_contents(...
# /cache/data/66/38/...): No such file or directory — from whichever middleware
# touched the cache first. Laravel does try to create that path itself, but
# FileStore::ensureCacheDirectoryExists() uses @mkdir, so when it cannot, it
# fails silently and the write is what reports the problem, one layer too late
# and naming a file rather than the missing folder.
#
# Each of these is a git-tracked placeholder whose only job is to make the
# folder exist; re-adding them costs three entries and restores all three
# directories. tickets/ and branding/ need no placeholder — Storage creates
# those on first upload, inside storage/app, which does ship.
echo "==> restoring the runtime directories the excludes above emptied"
zip -q "$OUT" \
  storage/framework/cache/data/.gitignore \
  storage/framework/sessions/.gitignore \
  storage/logs/.gitignore

echo "==> restoring dev dependencies for continued local work"
composer install --no-interaction >/dev/null 2>&1 || true

SIZE=$(du -h "$OUT" | cut -f1)
echo "==> done: $OUT ($SIZE)"
echo "    On the server: unzip, point the web root at public/, open the site."
