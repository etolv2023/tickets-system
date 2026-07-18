<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a write that is byte-for-byte identical to one made seconds ago.
 *
 * The button guard in JavaScript catches nearly all of this, but "nearly" is
 * not a word that belongs near a comment posted three times or a rating
 * recorded twice. This is the guard that holds when the first one cannot run:
 * scripts blocked, a stalled request the user gave up on, a resubmitted form.
 *
 * The window is short on purpose. Two identical submissions three seconds
 * apart are a stutter; two identical submissions a minute apart are somebody
 * genuinely logging the same hour twice, and that is their business.
 *
 * Uploads are included by name and size rather than by content — hashing the
 * bytes of a 10MB video on every request costs more than this is worth, and a
 * repeat click sends the same file anyway.
 */
class PreventDuplicateSubmit
{
    private const WINDOW_SECONDS = 4;

    /** Methods that change something and are not naturally idempotent. */
    private const GUARDED = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::GUARDED, true)) {
            return $next($request);
        }

        // Never de-duplicate a login attempt: two identical tries in four
        // seconds is exactly what a person retyping a password does, and the
        // throttle middleware is what should be judging that, not this.
        if ($request->routeIs('login', 'portal.verify', 'password.change.store')) {
            return $next($request);
        }

        $key = $this->fingerprint($request);

        if (Cache::has($key)) {
            // Send them back to where they were with no error. The first
            // submission did the work; from the user's side it simply worked.
            return $request->expectsJson()
                ? response()->json(['message' => 'الطلب ده اتنفذ بالفعل.'], 409)
                : back();
        }

        Cache::put($key, true, self::WINDOW_SECONDS);

        return $next($request);
    }

    private function fingerprint(Request $request): string
    {
        $input = $request->except(['_token', '_method']);

        // Files: name + size, not contents.
        $files = collect($request->allFiles())->flatten()->map(
            fn ($file) => $file?->getClientOriginalName() . ':' . $file?->getSize()
        )->sort()->values()->all();

        return 'dupe:' . hash('xxh128', implode('|', [
            $request->user()?->id ?? $request->session()->getId(),
            $request->method(),
            $request->path(),
            json_encode($input),
            json_encode($files),
        ]));
    }
}
