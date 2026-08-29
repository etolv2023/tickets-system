<?php

namespace App\Http\Controllers\Export;

use App\Exports\ApprovalsExport;
use App\Exports\SearchExport;
use App\Exports\TestingQueueExport;
use App\Exports\TicketsExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Export\Concerns\LogsExport;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * F19.3 — the ticket-shaped exports: the list itself, the two work queues and
 * the search results.
 *
 * Every one repeats its screen's authorisation check rather than trusting the
 * route. An export is the widest read in the system, so the gate is spelled
 * out next to the query it guards.
 */
class TicketExportController extends Controller
{
    use LogsExport;

    /** /tickets */
    public function tickets(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->only('q', 'status', 'type', 'priority', 'company', 'assignee', 'relation', 'from', 'to', 'date_basis');

        $this->logExport($request, 'export.tickets', $filters);

        return (new TicketsExport($request->user(), $filters))->download($this->filename('tickets'));
    }

    /** F15 — /approvals */
    public function approvals(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('features.approve'), 403);

        $filters = $request->only('assignee', 'relation');

        $this->logExport($request, 'export.approvals', $filters);

        return (new ApprovalsExport($filters))->download($this->filename('approvals'));
    }

    /** F16 — /testing-queue */
    public function testing(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('tickets.resolve'), 403);

        $this->logExport($request, 'export.testing_queue');

        return (new TestingQueueExport($request->user()))->download($this->filename('testing-queue'));
    }

    /** F21 — /search */
    public function search(Request $request): BinaryFileResponse
    {
        $term = trim((string) $request->query('q', ''));

        // The same floor the screen enforces: MySQL's fulltext index ignores
        // tokens under three characters, so a shorter term has no answer.
        abort_if(mb_strlen($term) < 3, 422, 'اكتب 3 حروف على الأقل.');

        $this->logExport($request, 'export.search', ['q' => $term]);

        return (new SearchExport($request->user(), $term))->download($this->filename('search'));
    }
}
