<?php

use App\Http\Controllers\Api\ExceptionWebhookController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Machine-to-machine only. Everything a person touches is in web.php and
| behind a session; this file exists for the one thing that is not a person
| — the error reporters on the other servers (F26).
|
| No session, no CSRF and no auth middleware: the caller is a queue worker on
| another host with no cookie jar. Authentication is the signature instead,
| which is checked before the controller and cannot be skipped by reaching the
| route another way.
|
*/

Route::post('webhooks/exceptions', ExceptionWebhookController::class)
    ->middleware([
        VerifyWebhookSignature::class,
        // A second line behind the signature, not instead of it. The signature
        // stops forged calls; this stops a sender that is genuinely ours from
        // opening a thousand tickets during an outage where the same error
        // fires in a loop. Generous, because a real incident legitimately
        // produces a burst — several servers reporting several errors at once
        // is normal, and this only refuses the pathological case.
        'throttle:120,1',
    ])
    ->name('api.webhooks.exceptions');
