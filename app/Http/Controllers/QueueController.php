<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The two work queues: approvals (F15) and testing (F16). */
class QueueController extends Controller
{
    /** F15 — features waiting on an admin's decision. */
    public function approvals(Request $request): View
    {
        abort_unless($request->user()->hasPermission('features.approve'), 403);

        // ★ (2026-08-02) Same people filter as the tickets list: on a queue of
        // pending features, "whose is this?" is the question an approver asks
        // most, and the three ways someone is attached to a ticket (holds a
        // role, opened it, owns a subtask on it) are exactly as different here.
        $filters = $request->only('assignee', 'relation');

        return view('queues.approvals', [
            'tickets' => Ticket::query()
                ->select([
                    'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type',
                    'priority', 'status', 'reported_at', 'sla_due_at', 'created_by',
                ])
                // An approver decides from what the ticket asks for, so the card
                // shows a line of it. A bounded slice, not the whole LONGTEXT —
                // § 4.3. The expression is a constant, no user input.
                ->selectRaw('LEFT(description, 1000) AS description_excerpt')
                ->with(['company:id,name', 'requester:id,name', 'creator:id,name,avatar_path,is_active'])
                ->where('approval_status', 'pending')
                ->when($filters['assignee'] ?? null,
                    fn ($q, $v) => $q->involving((int) $v, $filters['relation'] ?? null))
                ->defaultOrder()
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'selectedAssignee' => filled($filters['assignee'] ?? null)
                ? \App\Models\User::whereKey($filters['assignee'])->value('name')
                : null,
        ]);
    }

    /** F16 — what this tester is expected to verify. */
    public function testing(Request $request): View
    {
        $user = $request->user();

        abort_unless($user->hasPermission('tickets.resolve'), 403);

        // Role-based tester queue (2026-07-24): "my tickets to test" is the
        // tickets where I hold an is_tester role.
        $testerRoleIds = \App\Models\Role::testerRoleIds();

        return view('queues.testing', [
            'tickets' => Ticket::query()
                ->select([
                    'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type',
                    'priority', 'status', 'reported_at', 'sla_due_at',
                ])
                ->with([
                    'company:id,name', 'requester:id,name',
                    // The developers to talk to — everyone who logged work on it.
                    'workLogs.user:id,name,avatar_path,is_active',
                ])
                ->whereHas('roleAssignments', fn ($q) => $q
                    ->whereIn('role_id', $testerRoleIds)
                    ->where('user_id', $user->id))
                ->whereIn('status', ['dev_done', 'testing'])
                ->defaultOrder()
                ->paginate(25),
            // dev_done with no tester is nobody's job unless support or a manager
            // picks it up — surface those rather than let them rot. F16
            'unassigned' => $user->hasPermission('tickets.view.all')
                ? Ticket::query()
                    ->select(['id', 'ticket_number', 'company_id', 'requested_by', 'title', 'priority', 'status', 'reported_at', 'sla_due_at'])
                    ->with('company:id,name', 'requester:id,name')
                    ->where('status', 'dev_done')
                    ->whereDoesntHave('roleAssignments', fn ($q) => $q->whereIn('role_id', $testerRoleIds))
                    ->defaultOrder()
                    ->limit(25)
                    ->get()
                : collect(),
        ]);
    }
}
