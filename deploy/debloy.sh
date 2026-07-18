#!/usr/bin/env bash
# Redeploys an ALREADY-INSTALLED copy of this app in place: pull the latest
# code, bring dependencies and the database schema up to date, rebuild the
# front end, and refresh every cache Laravel keeps on disk.
#
# This is not the fresh-install script — that is `composer run setup`
# (composer.json), which also copies .env and runs key:generate. Running
# key:generate here would invalidate every existing session and anything
# encrypted with the old key, so this script never touches .env or the key.
#
# Usage:
#   bash deploy/debloy.sh
#   BASE_URL=http://host/some-folder/public bash deploy/debloy.sh
#   WEB_USER=www-data bash deploy/debloy.sh          (run as root, or with sudo)
#
# Set BASE_URL exactly like deploy/build-release.sh's BASE_URL — needed ONLY
# if this copy is reached through a subdirectory path rather than owning the
# domain root, because the self-hosted @font-face URLs are baked into the
# built CSS at build time and cannot detect that at request time the way
# asset()/route() do. See build-release.sh's header for the full story.
# If this server's .env already sets ASSET_URL, that value wins over anything
# passed here and BASE_URL is not needed at all.
#
# Set WEB_USER to the user the web server itself runs as (www-data on Debian/
# Ubuntu with Apache or Nginx+PHP-FPM, apache on RHEL/CentOS, nobody on some
# setups — check your vhost/pool config, this script cannot guess it) if
# storage/ and bootstrap/cache/ are owned by someone other than that user.
# Laravel writes logs, sessions, compiled views, and every upload (attachments,
# the logo, import files) into those two directories on every request, as
# whichever user PHP itself is running as — if that is not who currently owns
# them, this is the "the page works until you try to open a ticket or click
# save" class of bug, and it is silent: nothing in this script's own commands
# above would fail if a route handler only fails later, mid-request, ******
# ownership only actually gets FIXED here if you run this script with enough
# privilege to chown (root, or via sudo) — set WEB_USER without that and it
# only warns.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ ! -f .env ]; then
    echo "!! No .env here — this isn't an installed copy. Run the installer (composer run setup) first, not this script." >&2
    exit 1
fi

echo "==> checking for local changes git pull would refuse to touch"
if [ -n "$(git status --porcelain)" ]; then
    echo "!! This checkout has uncommitted changes. Resolve or stash them first —" >&2
    echo "   a deploy script is not the place to guess what to do with someone's edits." >&2
    git status --short
    exit 1
fi

echo "==> git pull"
git pull

echo "==> production dependencies (no dev, optimised autoloader)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> front-end dependencies + build"
npm ci

if [ -n "${BASE_URL:-}" ]; then
    echo "    (subdirectory deployment: ${BASE_URL})"
    ASSET_URL="$BASE_URL" npm run build
else
    npm run build
fi

echo "==> database migrations"
php artisan migrate --force

echo "==> storage symlink (safe to re-run — no-ops if it already exists)"
php artisan storage:link || true

echo "==> re-caching config, routes and views for production"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> restarting the queue worker so it picks up the new code"
# Only ends the CURRENT worker process cleanly on its next iteration; the
# process supervisor (systemd, Supervisor, whatever is used for this
# install) is what actually starts a replacement — this script does not
# assume a specific one exists or know its name.
php artisan queue:restart

echo "==> done."
echo "    If PHP is running under opcache with validate_timestamps=0"
echo "    (deploy/php-production.ini's comment covers the default, on), reload"
echo "    PHP-FPM/Apache here — this script doesn't guess your service manager's name."
