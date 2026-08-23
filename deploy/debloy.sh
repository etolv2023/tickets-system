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
# setups — check your vhost/pool config, this script cannot guess it).
# Laravel writes logs, sessions, compiled views, and every upload (attachments,
# the logo, import files) into storage/ and bootstrap/cache/ on every request,
# as whichever user PHP is actually running as. If that user doesn't own those
# two directories, nothing in THIS script fails — git pull, composer, the
# builds and the cache commands all run as whoever invoked this script, not as
# the web server — so the deploy reports success and the breakage only shows
# up later, silently, the first time a real request tries to write a log or
# save an upload. Fixing ownership needs the privilege to chown (root, or
# sudo); passing WEB_USER without that only prints a warning, it does not fail
# the deploy over it.

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

echo "==> write permissions for storage/ and bootstrap/cache/"
# ug+rwX (capital X), not 775: adds read/write for owner+group and execute
# ONLY on things that already have it for someone (directories, and any file
# already executable) — it can't accidentally make a random uploaded file
# executable the way a flat 775 would.
chmod -R ug+rwX storage bootstrap/cache

if [ -n "${WEB_USER:-}" ]; then
    if [ "$(id -u)" -eq 0 ]; then
        chown -R "${WEB_USER}:${WEB_GROUP:-$WEB_USER}" storage bootstrap/cache
        echo "    owner set to ${WEB_USER}:${WEB_GROUP:-$WEB_USER}"
    else
        echo "    !! WEB_USER=${WEB_USER} set, but this script isn't running as root — can't chown." >&2
        echo "       Re-run with sudo, or chown storage/ and bootstrap/cache/ to ${WEB_USER} yourself." >&2
    fi
else
    echo "    WEB_USER not set — leaving ownership as-is. Set it (and run as root/sudo) if the"
    echo "    web server user differs from whoever is running this script."
fi

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

echo "==> restarting the queue workers so they pick up the new code"
# Only ends the CURRENT worker processes cleanly on their next iteration; the
# process supervisor (systemd, Supervisor, whatever is used for this
# install) is what actually starts replacements — this script does not
# assume a specific one exists or know its name.
#
# ★ (2026-08-23) There are TWO workers to supervise, not one:
#
#   php artisan queue:work --queue=default   application jobs (imports, push)
#   php artisan queue:work --queue=discord   Discord notifications only
#
# Same connection and same `jobs` table — only the queue name differs, so this
# needs no new table and no new connection. They are split because Discord jobs
# are the only ones that wait on an external service: they get rate limited,
# back off for as long as Discord tells them to, time out, and may scan a
# channel before sending. Behind a single worker each of those pauses would also
# be a pause for a password-reset email or a stuck import.
#
# Consequences worth knowing:
#   - Stop the discord worker and the application is unaffected; its jobs simply
#     queue up and deliver when it comes back.
#   - Neither worker waits on the other. A queue:restart during a Discord
#     back-off does not delay the default worker.
#   - `queue:restart` below signals BOTH, since the flag is connection-wide.
#
# A Supervisor program per queue, e.g.:
#
#   [program:tickets-worker-default]
#   command=php /path/to/artisan queue:work --queue=default --tries=3 --sleep=1
#   numprocs=1 ; autostart=true ; autorestart=true
#
#   [program:tickets-worker-discord]
#   command=php /path/to/artisan queue:work --queue=discord --tries=4 --sleep=3
#   numprocs=1 ; autostart=true ; autorestart=true
#
# --tries on the command line is a ceiling; SendDiscordMessage sets its own
# $tries/$backoff and those win.
php artisan queue:restart

echo "==> verifying the Discord integration against the real server"
# Read-only: it checks the token, the guild, that the configured channel really
# is a forum, and that the bot holds the permissions it needs. It posts nothing.
# Non-fatal on purpose — a Discord misconfiguration must not fail a deploy of the
# ticket system itself, which works without it.
php artisan discord:check || echo "    (discord:check reported problems — the ticket system still works; fix Discord separately)"

echo "==> done."
echo "    Reminder: TWO queue workers must be supervised —"
echo "      php artisan queue:work --queue=default"
echo "      php artisan queue:work --queue=discord"
echo ""
echo "    If PHP is running under opcache with validate_timestamps=0"
echo "    (deploy/php-production.ini's comment covers the default, on), reload"
echo "    PHP-FPM/Apache here — this script doesn't guess your service manager's name."
