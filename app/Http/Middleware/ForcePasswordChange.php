<?php

namespace App\Http\Middleware;

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
