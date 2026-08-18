<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExceptionWebhookRequest;
use App\Services\ExceptionIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * F26 — the one endpoint the error reporters call.
 *
 * Thin by design: the signature was checked by middleware and every decision
 * belongs to ExceptionIntakeService. All this does is call it and shape the
 * reply.
 *
 * The reply matters more than it looks. The sender writes the ticket number and
 * URL back onto its own alert row, so the Back Office can link to the work
 * rather than only describing the error — which means `ticket_number` and
 * `ticket_url` are a contract, not a convenience.
 */
class ExceptionWebhookController extends Controller
{
    public function __invoke(ExceptionWebhookRequest $request, ExceptionIntakeService $intake): JsonResponse
    {
        try {
            $result = $intake->handle($request->validated());
        } catch (\Throwable $e) {
            // 500 on purpose, with the reason in the body. The caller is a
            // queue job on another server that logs whatever comes back, and
            // "no back-end developer is active" is a configuration problem
            // somebody has to read to fix — swallowing it into a 200 would
            // leave errors silently unticketed for as long as it took anyone
            // to notice.
            Log::error('exception intake failed', [
                'fingerprint' => $request->input('fingerprint'),
                'server' => $request->input('server_name'),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            // created | recreated | commented — what actually happened, so the
            // sender's log says which rather than assuming.
            'action' => $result['action'],
            'ticket_number' => $result['ticket_number'],
            'ticket_url' => $result['ticket_id'] === null
                ? null
                : route('tickets.show', $result['ticket_id']),
        ]);
    }
}
