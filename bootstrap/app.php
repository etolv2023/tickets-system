<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\PreventDuplicateSubmit;
use App\Http\Middleware\QueryCount;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // F26 — machine-to-machine only, and deliberately a separate group: the
        // api group has no session and no CSRF, which is what lets a queue
        // worker on another server call in. It authenticates by signature
        // instead (VerifyWebhookSignature), applied per-route in api.php.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // EnsureInstalled runs on every web request: before the app is
        // installed there is no database to serve anything else from.
        // Global, and first: as web-group middleware the priority sorter ran it
        // after auth, so it missed the query that loads the logged-in user and
        // every screen under-reported. Wrapping the whole pipeline is the only
        // way the number it prints is the number that actually ran.
        $middleware->prepend([QueryCount::class]);

        $middleware->web(append: [
            EnsureInstalled::class,
            SecurityHeaders::class,
            ForcePasswordChange::class,
            // The server-side half of the double-click guard. After the session
            // starts (it keys off the session id for guests) and before any
            // controller runs.
            PreventDuplicateSubmit::class,
        ]);

        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);

        // Laravel's middleware sorter hoists anything in its priority map above
        // middleware that aren't in it. SubstituteBindings (priority 9) sits in
        // the web group ahead of the route's auth middleware (priority 5), which
        // drags auth up past everything appended after it — including
        // EnsureInstalled. The result: before installation, / redirected to
        // /login instead of /install. Naming it in the priority map, ahead of
        // auth, is what actually pins the order.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureInstalled::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
