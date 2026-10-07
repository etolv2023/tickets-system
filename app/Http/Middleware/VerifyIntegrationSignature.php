<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * F28 — the lock on the read-only lookup other internal systems use.
 *
 * Same idea as VerifyWebhookSignature (F26) and kept as a separate class and a
 * separate secret on purpose: the exception intake WRITES tickets, this READS
 * them, and a secret that leaks from one system must not open the other door.
 *
 * The signature is HMAC-SHA256 over
 *
 *     timestamp . "\n" . METHOD . "\n" . request-uri-with-query
 *
 * The F26 signature covers the body because that endpoint has one. These are
 * GET requests: the "body" is the query string, so the URI is what has to be
 * signed — otherwise a captured signature for `?q=a` could be replayed as
 * `?q=b` or `?limit=20` against a different search. The timestamp is signed
 * too, which is what makes the freshness window enforceable.
 *
 * Deliberate choices, all inherited from F26:
 *
 *   - No secret configured means 503, never "allow".
 *   - hash_equals, never ==.
 *   - Refusals answer 401 with nothing useful; the real reason is only logged.
 *   - The raw URI is signed, not the parsed query, so key order and encoding
 *     cannot make a valid call fail or an altered one pass.
 */
class VerifyIntegrationSignature
{
    public const SIGNATURE_HEADER = 'X-Integration-Signature';

    public const TIMESTAMP_HEADER = 'X-Integration-Timestamp';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('tickets.integrations.neo4j_dash.secret', '');

        if ($secret === '') {
            $this->refuse($request, 'integration_not_configured');

            return response()->json(['error' => 'Integration is not configured.'], 503);
        }

        $timestamp = (string) $request->header(self::TIMESTAMP_HEADER, '');
        $signature = (string) $request->header(self::SIGNATURE_HEADER, '');

        if ($timestamp === '' || $signature === '') {
            $this->refuse($request, 'missing_headers');

            return $this->unauthorized();
        }

        // Digits only, so a non-numeric value is rejected for what it is and
        // not by accident of how (int) casts it.
        if (! ctype_digit($timestamp)) {
            $this->refuse($request, 'malformed_timestamp');

            return $this->unauthorized();
        }

        $skew = abs(now()->getTimestamp() - (int) $timestamp);

        if ($skew > (int) config('tickets.integrations.neo4j_dash.max_skew_seconds', 300)) {
            $this->refuse($request, 'stale_timestamp', ['skew_seconds' => $skew]);

            return $this->unauthorized();
        }

        $expected = hash_hmac(
            'sha256',
            $timestamp . "\n" . $request->getMethod() . "\n" . $request->getRequestUri(),
            $secret,
        );

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
     * The detail withheld from the caller is exactly what an operator needs
     * when a legitimate server starts failing — usually clock drift, or a
     * secret changed on one side only.
     *
     * @param  array<string, mixed>  $context
     */
    private function refuse(Request $request, string $reason, array $context = []): void
    {
        Log::warning('integration lookup refused', [
            'reason' => $reason,
            'ip' => $request->ip(),
        ] + $context);
    }
}
