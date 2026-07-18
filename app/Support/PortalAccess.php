<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Who is allowed to read a ticket through the client portal.
 *
 * The grant used to be a plain ticket id pushed into the session, and the
 * session cookie lives for two hours and survives the browser closing. On a
 * shared or public machine that meant the next person to open the browser
 * still had the customer's ticket — a per-ticket password that stops mattering
 * once it has been typed once is not access control.
 *
 * Two things fix it, and both are needed:
 *
 *   1. A browser-session cookie. It carries a random marker and is written
 *      with no expiry, so the browser drops it on close. A grant whose marker
 *      no longer matches is dead, which is how "I closed the tab" becomes "ask
 *      me again" without the server having to detect anything.
 *
 *   2. An idle timeout. A tab left open all night is not a person still
 *      sitting there.
 *
 * Deliberately separate from the staff session lifetime: shortening that would
 * log employees out all day to solve a problem that is not theirs.
 */
class PortalAccess
{
    private const SESSION_KEY = 'portal_access';

    private const COOKIE = 'portal_key';

    /** How long a grant survives without the visitor doing anything. */
    private const IDLE_MINUTES = 30;

    /** Records that this visitor proved they hold the ticket's password. */
    public static function grant(Request $request, int $ticketId): void
    {
        $marker = $request->cookie(self::COOKIE);

        if (! is_string($marker) || $marker === '') {
            $marker = Str::random(40);

            // Zero minutes means a session cookie: no Expires, no Max-Age, so
            // the browser discards it the moment it closes.
            Cookie::queue(Cookie::make(self::COOKIE, $marker, 0, null, null, null, true, false, 'Lax'));
        }

        $grants = self::grants($request);
        $grants[$ticketId] = ['marker' => $marker, 'at' => now()->timestamp];

        $request->session()->put(self::SESSION_KEY, $grants);
    }

    public static function allows(Request $request, int $ticketId): bool
    {
        $grants = self::grants($request);
        $grant = $grants[$ticketId] ?? null;

        if (! is_array($grant)) {
            return false;
        }

        $marker = $request->cookie(self::COOKIE);

        // The browser was closed (cookie gone) or this is a different browser.
        if (! is_string($marker) || ! hash_equals((string) ($grant['marker'] ?? ''), $marker)) {
            return false;
        }

        if (($grant['at'] ?? 0) < now()->subMinutes(self::IDLE_MINUTES)->timestamp) {
            return false;
        }

        // Still here and still active — push the idle window forward.
        $grants[$ticketId]['at'] = now()->timestamp;
        $request->session()->put(self::SESSION_KEY, $grants);

        return true;
    }

    /** @return array<int, array<string, mixed>> */
    private static function grants(Request $request): array
    {
        $stored = $request->session()->get(self::SESSION_KEY, []);

        // Older sessions hold a flat list of ids from before grants carried a
        // marker. Those cannot be validated, so they are simply dropped.
        return array_filter((array) $stored, 'is_array');
    }
}
