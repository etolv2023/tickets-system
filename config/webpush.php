<?php

return [
    /*
     * VAPID identifies this server to the push service (RFC 8292). Generate a
     * pair once with `php artisan push:vapid` and keep it: changing the public
     * key invalidates every existing subscription, and every user has to allow
     * notifications again.
     *
     * Empty keys are not an error — they mean push is simply off, and the
     * in-page bell keeps working on its own.
     */
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),

    /*
     * A contact the push service can reach if this sender misbehaves. Must be
     * a mailto: or https: URI — Firefox rejects the token without it.
     */
    'subject' => env('VAPID_SUBJECT', 'mailto:' . env('MAIL_FROM_ADDRESS', 'no-reply@etolv.net')),
];
