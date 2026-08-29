<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Support\ImpersonationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ★ (2026-08-29) Copies "we are impersonating" out of the session and into the
 * container, once per request — F29.
 *
 * This is the whole reason ActivityLogger can stamp an impersonation id without
 * ever reading session() itself (CLAUDE.md § 3). In a queue worker nothing runs
 * this, the context stays empty, and logs written there are ordinary.
 */
class TrackImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get(ImpersonationController::SESSION_KEY);

        app(ImpersonationContext::class)->set($id === null ? null : (int) $id);

        return $next($request);
    }
}
