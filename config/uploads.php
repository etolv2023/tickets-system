<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Virus scanning
    |--------------------------------------------------------------------------
    |
    | Talks to a clamd daemon over TCP. Off by default so a fresh checkout runs
    | without extra infrastructure; turn it on wherever the portal is reachable
    | by clients.
    |
    | strict = what happens when the daemon cannot be reached. true rejects the
    | upload (correct for anonymous portal uploads: unscanned is not clean),
    | false lets it through (tolerable for staff uploads, where the uploader is
    | a known employee).
    |
    */

    'clamav' => [
        'enabled' => env('CLAMAV_ENABLED', false),
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'strict' => env('CLAMAV_STRICT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | What a client may upload from the portal
    |--------------------------------------------------------------------------
    |
    | Deliberately narrower and smaller than what staff may upload. The portal
    | is gated by a per-ticket password, not by an account, so everything that
    | arrives through it is treated as hostile until proven otherwise.
    |
    | Images are re-encoded on the way in, which destroys anything smuggled in
    | the original bytes. PDF and video cannot be re-encoded, so they rest on
    | the MIME sniff, the never-served-inline rule, and ClamAV — which is why
    | they are only permitted when scanning is actually switched on.
    |
    */

    'portal' => [
        'max_bytes' => (int) env('PORTAL_UPLOAD_MAX_BYTES', 10 * 1024 * 1024),
        'max_per_comment' => (int) env('PORTAL_UPLOAD_MAX_FILES', 3),

        // Always allowed: re-encoded on arrival.
        'always' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ],

        // Allowed only while ClamAV is enabled — these reach disk as sent.
        'when_scanned' => [
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ],
    ],

];
