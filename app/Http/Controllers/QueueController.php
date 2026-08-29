<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PriorityDefinition;
use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\TicketTypeDefinition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/** The work queues: approvals (F15), testing (F16) and ready-to-close (F30). */
class QueueController extends Controller
{
    /** Statuses that mean the ticket is done with — it cannot be "ready" to close. */
    private const SETTLED = ['resolved', 'closed', 'rejected'];

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

    /**
     * ★ (2026-08-29) F30 — «جاهزة للقفل»: كل صب تاسكاتها خلصت والتذكرة لسه مفتوحة.
     *
     * The gap this catches is specific and common: the work is finished, every
     * step is ticked, and nobody moved the ticket. It is invisible on /tickets —
     * the row looks like any other open ticket — and invisible on the board for
     * the same reason. It only appears when somebody asks this exact question.
     *
     * subtasks_total > 0 matters: a ticket with no steps has not "finished all
     * of them", it has never had any, and listing those would bury the real ones.
     *
     * Both numbers are counters SubtaskService maintains on every mutation
     * (§ 4.6), so this is a comparison between two columns rather than a count
     * over ticket_subtasks per row.
     *
     * No permission on the route: visibleTo() decides what is in the result,
     * exactly as on /tickets. A developer sees their own finished-but-open work
     * — the person best placed to close it — and a manager sees the team's.
     */
    public function ready(Request $request): View
    {
        $filters = $request->only('q', 'type', 'priority', 'company', 'assignee', 'relation', 'from', 'to', 'status');

        $tickets = Ticket::query()
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority',
                'status', 'reported_at', 'sla_due_at', 'created_by',
                'subtasks_total', 'subtasks_done', 'branches_count',
            ])
            ->with([
                'company:id,name,code', 'requester:id,name', 'creator:id,name',
                'roleAssignments.user:id,name,avatar_path,is_active',
            ])
            ->visibleTo($request->user())
            ->where('subtasks_total', '>', 0)
            ->whereColumn('subtasks_done', '>=', 'subtasks_total')
            ->whereNotIn('status', self::SETTLED)
            ->filter($filters)
            ->defaultOrder()
            ->paginate(25)
            ->withQueryString();

        return view('queues.ready', [
            'tickets' => $tickets,
            'filters' => $filters,
            // Only the statuses a ticket in this queue can be in. Offering
            // «تم الحل» on a queue defined as "not resolved" is a filter whose
            // only possible answer is an empty list.
            'statuses' => Arr::except(TicketStatusDefinition::options(), self::SETTLED),
            'types' => TicketTypeDefinition::options(),
            'priorities' => PriorityDefinition::options(),
            'selectedCompany' => filled($filters['company'] ?? null)
                ? Company::whereKey($filters['company'])->value('name')
                : null,
            'selectedAssignee' => filled($filters['assignee'] ?? null)
                ? User::whereKey($filters['assignee'])->value('name')
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
