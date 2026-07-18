<?php

namespace App\Services;

use RuntimeException;

/**
 * ClamAV scanning over the clamd socket (CLAUDE.md § 5).
 *
 * Talks the INSTREAM protocol directly rather than pulling in a package: it is
 * a length-prefixed byte stream and a one-line reply, and a dependency that
 * wraps thirty lines of socket code is not worth carrying.
 *
 * Failure mode is the important design decision here. When the daemon is
 * unreachable the scanner does NOT wave the file through — for an upload from
 * the client portal, "we could not check it" and "it is clean" are not the same
 * sentence. Whether that hard-fails is decided by the caller, which knows
 * whether the uploader is a known employee or an anonymous visitor.
 */
class VirusScanner
{
    /** clamd rejects a chunk larger than its StreamMaxLength; stay well under. */
    private const CHUNK = 8192;

    private const TIMEOUT = 30;

    private readonly string $host;

    private readonly int $port;

    private readonly bool $enabled;

    /**
     * Every argument is optional and falls back to config, so the container can
     * build this by reflection with no binding at all. A service whose only
     * dependencies are scalars should not be able to take the app down because
     * a provider did not run — and it did exactly that once.
     */
    public function __construct(?string $host = null, ?int $port = null, ?bool $enabled = null)
    {
        $this->host = $host ?? (string) config('uploads.clamav.host', '127.0.0.1');
        $this->port = $port ?? (int) config('uploads.clamav.port', 3310);
        $this->enabled = $enabled ?? (bool) config('uploads.clamav.enabled', false);
    }

    public static function fromConfig(): self
    {
        return new self();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return bool true when the file is clean
     *
     * @throws RuntimeException when the file is infected, or when scanning was
     *                          required but could not be performed
     */
    public function assertClean(string $path, string $displayName): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $verdict = $this->scan($path);

        if ($verdict === null) {
            throw new RuntimeException(
                "مقدرناش نفحص «{$displayName}» ضد الفيروسات دلوقتي. جرّب تاني بعد شوية."
            );
        }

        if ($verdict !== true) {
            throw new RuntimeException(
                "«{$displayName}» فيه برمجية خبيثة ({$verdict}) — اترفض."
            );
        }

        return true;
    }

    /**
     * @return true|string|null  true = clean, string = the signature name,
     *                           null = the daemon could not be reached
     */
    public function scan(string $path): true|string|null
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, self::TIMEOUT);

        if ($socket === false) {
            report(new RuntimeException("clamd unreachable at {$this->host}:{$this->port} — {$errstr}"));

            return null;
        }

        stream_set_timeout($socket, self::TIMEOUT);

        try {
            fwrite($socket, "zINSTREAM\0");

            $file = @fopen($path, 'rb');

            if ($file === false) {
                return null;
            }

            try {
                while (! feof($file)) {
                    $chunk = fread($file, self::CHUNK);

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    // Each chunk is prefixed with its length as a network-order
                    // uint32; a zero-length prefix ends the stream.
                    fwrite($socket, pack('N', strlen($chunk)) . $chunk);
                }
            } finally {
                fclose($file);
            }

            fwrite($socket, pack('N', 0));

            $reply = trim((string) fgets($socket, 4096));
        } finally {
            fclose($socket);
        }

        if ($reply === '') {
            return null;
        }

        if (str_ends_with($reply, 'OK')) {
            return true;
        }

        if (str_ends_with($reply, 'FOUND')) {
            // "stream: Eicar-Test-Signature FOUND" -> "Eicar-Test-Signature"
            return trim(str_replace(['stream:', 'FOUND'], '', $reply));
        }

        report(new RuntimeException("clamd returned an unexpected reply: {$reply}"));

        return null;
    }
}
