<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * F20.2 — sending a push without a payload, and without a package.
 *
 * ★ The whole design turns on one decision: we send an EMPTY push. The service
 * worker treats it as "something happened, go look" and fetches the actual
 * notification from this server with the user's own session.
 *
 * That buys two things. Security: no ticket title, no customer name and no
 * message body ever passes through Google's or Mozilla's servers — the push
 * service only ever learns that *a* notification exists. And simplicity: the
 * hard part of the Web Push spec is RFC 8291 payload encryption (ECDH key
 * agreement, HKDF, AES128GCM), and hand-rolling that would be exactly the kind
 * of crypto nobody should hand-roll. Skipping the payload skips all of it.
 *
 * What remains is VAPID (RFC 8292): a signed JWT proving the sender is us.
 * That is ES256 — an ECDSA P-256 signature — which openssl does natively.
 */
class WebPushService
{
    /** How long the push service should hold an undelivered ping. */
    private const TTL = 3600;

    /** Re-signing per request is pointless; the audience is the only variable. */
    private const TOKEN_LIFETIME = 6 * 3600;

    public function configured(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_key'));
    }

    /**
     * Pings every browser this person has allowed.
     *
     * A dead subscription is deleted rather than retried: 404 and 410 are the
     * push service saying the browser is gone for good (permission revoked,
     * profile deleted, app uninstalled). Keeping it would mean failing forever.
     */
    public function pingUser(int $userId): void
    {
        if (! $this->configured()) {
            return;
        }

        PushSubscription::where('user_id', $userId)->get()->each(function (PushSubscription $subscription) {
            $status = $this->send($subscription);

            if (in_array($status, [404, 410], true)) {
                $subscription->delete();

                return;
            }

            if ($status >= 200 && $status < 300) {
                $subscription->forceFill(['last_used_at' => now()])->saveQuietly();
            }
        });
    }

    private function send(PushSubscription $subscription): int
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->authorizationHeader($subscription->endpoint),
                'TTL' => (string) self::TTL,
                // Required even with no body: the spec has no "no content"
                // encoding, and FCM rejects the request without a length.
                'Content-Length' => '0',
            ])->timeout(10)->send('POST', $subscription->endpoint);

            return $response->status();
        } catch (\Throwable $e) {
            // A push service being unreachable is not the user's problem and
            // must never break the action that triggered the notification.
            Log::warning('web push failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * `vapid t=<jwt>, k=<public key>` — RFC 8292 § 3.
     */
    private function authorizationHeader(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $audience = $parts['scheme'] . '://' . $parts['host'];

        $token = $this->token($audience);

        return 'vapid t=' . $token . ', k=' . config('webpush.public_key');
    }

    private function token(string $audience): string
    {
        // One token serves every subscription on the same push service for its
        // whole lifetime, and signing is the expensive part.
        return cache()->remember(
            'webpush.jwt.' . md5($audience),
            self::TOKEN_LIFETIME - 300,
            fn () => $this->sign($audience),
        );
    }

    private function sign(string $audience): string
    {
        $header = $this->base64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = $this->base64url(json_encode([
            'aud' => $audience,
            'exp' => time() + self::TOKEN_LIFETIME,
            'sub' => config('webpush.subject'),
        ]));

        $input = $header . '.' . $claims;

        $key = openssl_pkey_get_private($this->privateKeyPem());

        if ($key === false) {
            throw new RuntimeException('مفتاح VAPID الخاص مش صالح — شغّل php artisan push:vapid تاني.');
        }

        openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256);

        return $input . '.' . $this->base64url($this->derToRaw($der));
    }

    /**
     * openssl emits a DER SEQUENCE of two INTEGERs; JWS wants the two values
     * raw, fixed-width and concatenated (RFC 7515 appendix A.3). Converting is
     * the one piece of the spec that is genuinely fiddly, so it stays small and
     * explicit rather than clever.
     */
    private function derToRaw(string $der): string
    {
        // 30 <len> 02 <rlen> <r…> 02 <slen> <s…> — so the r length sits at 3.
        $position = 3;
        $rLength = ord($der[$position]);
        $position++;
        $r = substr($der, $position, $rLength);
        $position += $rLength + 1;
        $sLength = ord($der[$position]);
        $position++;
        $s = substr($der, $position, $sLength);

        // DER prefixes a 0x00 byte when the high bit would read as negative,
        // and drops leading zeroes; P-256 needs exactly 32 bytes each way.
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    /**
     * The stored private key is the raw 32-byte scalar, base64url encoded.
     * openssl needs it as a PEM, so it is wrapped back into a DER structure
     * with the P-256 curve parameters and the matching public point.
     */
    private function privateKeyPem(): string
    {
        $private = $this->base64urlDecode(config('webpush.private_key'));
        $public = $this->base64urlDecode(config('webpush.public_key'));

        $der = "\x30\x77"                            // SEQUENCE, 0x77 bytes
            . "\x02\x01\x01"                         // INTEGER version = 1
            . "\x04\x20" . $private                  // OCTET STRING, 32-byte key
            . "\xa0\x0a" . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"  // [0] prime256v1
            . "\xa1\x44" . "\x03\x42\x00" . $public; // [1] BIT STRING, 65-byte point

        return "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4));
    }
}
