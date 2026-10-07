<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TicketLookupRequest;
use App\Services\TicketLookupService;
use Illuminate\Http\JsonResponse;

/**
 * F28 — read-only ticket lookup for the other internal systems (Neo4j Dash).
 *
 * Thin by design: the signature was checked by middleware and the field
 * selection belongs to TicketLookupService. The reply shape is a contract with
 * the caller — `ticket_number` and `url` in particular.
 */
class TicketLookupController extends Controller
{
    public function index(TicketLookupRequest $request, TicketLookupService $lookup): JsonResponse
    {
        return response()->json(['data' => $lookup->search($request->term(), $request->limit())]);
    }

    public function show(string $ticketNumber, TicketLookupService $lookup): JsonResponse
    {
        $ticket = $lookup->find($ticketNumber);

        if ($ticket === null) {
            return response()->json(['error' => 'Ticket not found.'], 404);
        }

        return response()->json(['data' => $ticket]);
    }
}
