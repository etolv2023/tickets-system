<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * F26 — the only thing standing between this endpoint and anyone who can reach
 * the host.
 *
 * The intake creates tickets and assigns work to named people, so it cannot be
 * open. It also cannot use the session: the caller is a queue worker on another
 * server with no user and no cookie jar. So the request is signed.
 *
 * The signature is HMAC-SHA256 over `timestamp . "\n" . rawBody`, which is
 * doing three jobs at once:
 *
 *   - it proves the caller holds the secret, without the secret crossing the
 *     wire the way a bearer token does;
 *   - it covers the BODY, so a valid signature cannot be lifted off one call
 *     and pasted onto a different payload;
 *   - it covers the TIMESTAMP, which is what makes the freshness window below
 *     enforceable — a captured request cannot have its clock edited without
 *     invalidating the signature that carries it.
 *
 * Deliberate choices:
 *
 *   - No secret configured means 503, not "allow". An intake with no secret is
 *     an open door, and defaulting to open is how one gets left open.
 *   - hash_equals, never ==. String comparison that returns early leaks how
 *     much of a guess was right, one byte at a time.
 *   - The RAW body is signed, not the parsed fields. Re-encoding JSON to check
 *     a signature means the bytes verified are not the bytes that arrived, and
 *     any difference in key order or unicode escaping breaks a valid call.
 *   - Failures answer 401 with nothing useful in the body. A caller that got
 *     the secret wrong learns that it was wrong; it does not learn whether the
 *     timestamp, the header or the digest was the part that failed.
 */
class VerifyWebhookSignature
{
    public const SIGNATURE_HEADER = 'X-Exception-Signature';

    public const TIMESTAMP_HEADER = 'X-Exception-Timestamp';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('tickets.intake.secret', '');

        if ($secret === '') {
            $this->refuse($request, 'intake_not_configured');

            return response()->json(['error' => 'Exception intake is not configured.'], 503);
        }

        $timestamp = (string) $request->header(self::TIMESTAMP_HEADER, '');
        $signature = (string) $request->header(self::SIGNATURE_HEADER, '');

        if ($timestamp === '' || $signature === '') {
            $this->refuse($request, 'missing_headers');

            return $this->unauthorized();
        }

        // ctype_digit before the arithmetic: (int) on a non-numeric string is 0,
        // which is 1970 — comfortably outside any window, but only by accident.
        // Rejecting it explicitly means the window is doing the work, not a cast.
        if (! ctype_digit($timestamp)) {
            $this->refuse($request, 'malformed_timestamp');

            return $this->unauthorized();
        }

        $skew = abs(now()->getTimestamp() - (int) $timestamp);

        // Absolute, so a sender whose clock runs fast is refused too. A future
        // timestamp is exactly as suspicious as an old one, and allowing it
        // would hand an attacker a replay window that opens later.
        if ($skew > (int) config('tickets.intake.max_skew_seconds', 300)) {
            $this->refuse($request, 'stale_timestamp', ['skew_seconds' => $skew]);

            return $this->unauthorized();
        }

        $expected = hash_hmac('sha256', $timestamp . "\n" . $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            $this->refuse($request, 'bad_signature');

            return $this->unauthorized();
        }

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['error' => 'Invalid signature.'], 401);
    }

    /**
     * Every refusal is logged with its real reason — the detail withheld from
     * the caller is exactly the detail an operator needs when a legitimate
     * server starts failing. Usually it is clock drift or a secret that was
     * updated on one side only.
     *
     * @param  array<string, mixed>  $context
     */
    private function refuse(Request $request, string $reason, array $context = []): void
    {
        Log::warning('exception intake refused', [
            'reason' => $reason,
            'ip' => $request->ip(),
        ] + $context);
    }
}
