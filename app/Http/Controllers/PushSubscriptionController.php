<?php

namespace App\Http\Controllers;

use App\Http\Requests\PushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F20.2 — a browser registering (or dropping) itself for push. */
class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        $endpoint = $request->validated('endpoint');

        // updateOrCreate on the hash, not create: the browser re-subscribes on
        // every service-worker update and would otherwise pile up a row each
        // time — and a row per update means a ping per update.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashFor($endpoint)],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $endpoint,
                'p256dh' => $request->validated('keys.p256dh'),
                'auth' => $request->validated('keys.auth'),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');

        // Scoped to the caller: knowing an endpoint must not be enough to
        // unsubscribe somebody else's browser.
        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashFor($endpoint))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
