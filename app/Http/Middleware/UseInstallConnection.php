<?php

namespace App\Http\Middleware;

use App\Services\InstallService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Points every install request at the database step 2 verified.
 *
 * Writing .env is not enough on its own: Laravel loads env vars immutably, so
 * any server that reuses one PHP process across requests (artisan serve,
 * Octane) never sees the new values and the later steps would quietly run
 * against whatever database was configured at boot.
 *
 * It has to be middleware rather than a controller call: a Form Request's
 * unique:users,email rule is validated before the controller body runs, and it
 * must query the new database too.
 */
class UseInstallConnection
{
    public function __construct(private readonly InstallService $installer)
    {
    }

    public const SESSION_KEY = 'install.db';

    public function handle(Request $request, Closure $next): Response
    {
        $credentials = $request->session()->get(self::SESSION_KEY);

        if (is_array($credentials)) {
            $this->installer->useConnection($credentials);
        }

        return $next($request);
    }
}
