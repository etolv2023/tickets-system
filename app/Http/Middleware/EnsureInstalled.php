<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate around the install wizard, both ways (F00.1 / PLAN.md § 9):
 *   not installed  → every route redirects to /install
 *   installed      → /install answers 404, so it can't be replayed
 */
class EnsureInstalled
{
    public const LOCK_FILE = 'installed.lock';

    public function handle(Request $request, Closure $next): Response
    {
        $installed = self::isInstalled();
        $isInstallRoute = $request->routeIs('install.*');

        if ($installed && $isInstallRoute) {
            abort(404);
        }

        if (! $installed && ! $isInstallRoute) {
            return redirect()->route('install.requirements');
        }

        return $next($request);
    }

    public static function isInstalled(): bool
    {
        return file_exists(self::lockPath());
    }

    public static function lockPath(): string
    {
        return storage_path(self::LOCK_FILE);
    }
}
