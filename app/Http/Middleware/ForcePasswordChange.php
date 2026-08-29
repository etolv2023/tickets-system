<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users created by the Excel import never chose their password — one was
 * generated for them. They reach nothing until they replace it (F00.2 / F02).
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        /*
         * ★ (2026-08-29) F29 — never while impersonating.
         *
         * Impersonating a user imported from Excel would otherwise trap the
         * administrator on the change-password screen with no way forward, and
         * the only way out of it would be SETTING THAT PERSON'S PASSWORD. The
         * demand belongs to the account holder, not to a visitor in their
         * session.
         */
        if ($request->session()->has(ImpersonationController::SESSION_KEY)) {
            return $next($request);
        }

        if ($user?->must_change_password && ! $this->isAllowed($request)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }

    /** The change-password screen itself, and the way out, stay reachable. */
    private function isAllowed(Request $request): bool
    {
        return $request->routeIs('password.change', 'password.change.store', 'logout');
    }
}
