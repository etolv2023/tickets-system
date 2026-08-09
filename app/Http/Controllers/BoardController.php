<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\WorklogCompletionWaiver;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "بوردي" — F12.1. Only what this person is on, in the four columns that
 * describe their own work.
 */
class BoardController extends Controller
{
    /**
     * Public because BoardMoveController and BoardMoveRequest read it: a drop
     * has to resolve the column key the browser sends back to a status.
     *
     * ★ Array order is load-bearing — element 0 of 'statuses' is the canonical
     * write target for a drop into that column.
     */
    public const COLUMNS = [
        'assigned' => ['label' => 'مسندة إليّ', 'statuses' => ['assigned', 'reopened']],
        'in_progress' => ['label' => 'جاري العمل', 'statuses' => ['in_progress']],
        'dev_done' => ['label' => 'تم التطوير', 'statuses' => ['dev_done', 'testing']],
        'closed' => ['label' => 'مغلقة', 'statuses' => ['resolved', 'closed']],
    ];

    /**
     * Which columns this person may drop this card into.
     *
     * Mirrors BoardMoveController exactly, so the browser can grey out a column
     * the server would refuse — telling someone before they let go of the mouse
     * rather than bouncing the card back afterwards.
     *
     * Costs nothing: work logs are eager-loaded, the counters are columns on
     * the row, and transitionMap() is cached.
     *
     * @return array<int, string>
     */
    public static function droppableColumns(Ticket $ticket, int $userId): array
    {
        $mine = $ticket->relationLoaded('workLogs')
            ? $ticket->workLogs->where('user_id', $userId)
            : collect();

        $allowed = TicketStatusDefinition::transitionMap()[$ticket->status->value] ?? [];
        $openSubtasks = self::hasGatingSubtasks($ticket);

        return collect(self::COLUMNS)
            ->filter(function (array $column, string $key) use ($ticket, $mine, $allowed, $openSubtasks) {
                // Its own column is always a legal drop: it is a no-op.
                if (in_array($ticket->status->value, $column['statuses'], true)) {
                    return true;
                }

                return match ($key) {
                    // Two conditions, and the second is the one that was
                    // missing: a resolved ticket has done work logs, so the
                    // log check alone said "droppable" — but resolved cannot
                    // reach in_progress at all. The drop then reopened the
                    // logs and left the ticket resolved. Reopening a resolved
                    // ticket is the ارتجاع action; it needs a reason, and a
                    // drag has nowhere to ask for one.
                    'in_progress' => $mine->whereIn('status', ['pending', 'done'])->isNotEmpty()
                        && in_array('in_progress', $allowed, true),
                    // finish() needs something actually running.
                    'dev_done' => $mine->where('status', 'in_progress')->isNotEmpty()
                        && in_array($ticket->status->value, ['in_progress', 'dev_done', 'testing'], true),
                    // Putting it back down: either there is running work to
                    // unstart, or the status move alone is legal.
                    'assigned' => $mine->where('status', 'in_progress')->isNotEmpty()
                        || in_array($column['statuses'][0], $allowed, true),
                    // A ticket with unfinished subtasks can't claim to be done.
                    'closed' => ! $openSubtasks && in_array($column['statuses'][0], $allowed, true),
                    default => in_array($column['statuses'][0], $allowed, true),
                };
            })
            ->keys()
            ->all();
    }

    /**
     * Whether an open subtask actually stands between this ticket and «مغلقة».
     *
     * ★ (2026-08-03) Was `subtasks_total - subtasks_done > 0` — the raw counter,
     * which counts every open subtask whoever it belongs to. That stopped
     * matching the rule the server enforces: TicketWorkflowService::subtaskBlocker()
     * was narrowed on 2026-08-02 so a subtask on a role that logs no work (دعم,
     * ديف أوبس) no longer blocks — those roles are support around the delivery,
     * not the delivery. So the board greyed out a column the server would have
     * accepted: a card the developers had finished could not be dragged to
     * «مغلقة» while a devops step was open, while «غيّر الحالة» on the ticket
     * page moved the very same ticket without complaint.
     *
     * Read off the already-loaded relation — which carries role_id — so this is
     * still zero queries per card, and workLoggingRoleIds() is cached forever.
     * A caller that did not eager-load subtasks keeps the counter: stricter than
     * the server, never looser, so it can only ever refuse a legal drop rather
     * than offer an illegal one.
     */
    private static ?\Illuminate\Support\Collection $gatingRoleIds = null;

    private static function hasGatingSubtasks(Ticket $ticket): bool
    {
        if (! $ticket->relationLoaded('subtasks')) {
            return $ticket->subtasks_total - $ticket->subtasks_done > 0;
        }

        // Held for the request, not re-read per card. workLoggingRoleIds() is
        // cached forever, but the cache driver here is `file` — so calling it
        // once per card on a 300-card board is 300 filesystem reads for a list
        // that cannot change mid-request.
        $gating = self::$gatingRoleIds ??= Role::workLoggingRoleIds();

        // ★ (2026-08-05) …and whose owner is not waived on this ticket.
        //
        // This method exists to mirror subtaskBlocker() so a column the server
        // would refuse is greyed out before the mouse is released. When the
        // waiver taught that gate to ignore a waived person's subtasks and this
        // copy was left behind, the mirror broke in the worse direction: the
        // drop was legal and the column was dead, so «مغلقة» simply would not
        // accept the card and nothing said why.
        //
        // waivedAmong() is array work over a cached map — no query, which
        // matters on a board of hundreds of cards.
        $waived = WorklogCompletionWaiver::waivedAmong(
            $ticket->subtasks->pluck('assignee_id')->filter()->unique()->values()->all()
        );

        // Same predicate as subtaskBlocker(): not done, and either untagged
        // (general work nobody claimed) or on a work-logging role.
        return $ticket->subtasks->contains(
            fn ($subtask) => ! $subtask->status->isDone()
                && ! in_array((int) $subtask->assignee_id, $waived, true)
                && ($subtask->role_id === null || $gating->contains($subtask->role_id))
        );
    }

    /**
     * The subtask columns a card needs. Both boards load the same set, so it
     * lives here rather than being written twice and drifting.
     *
     * ★ (2026-08-03) assignee_id is on this list and must stay on it.
     * TicketSubtaskPolicy::owns() treats a NULL assignee as "nobody claimed this
     * step, so anyone who can plan may edit it" — correct for a real unowned
     * subtask, and catastrophic for a column that simply wasn't selected. Every
     * card came back with assignee_id = null, so the policy passed for everyone
     * and the board rendered a live status dropdown on other people's subtasks
     * — while the ticket page, which loads the whole model, correctly showed
     * them disabled. The server still refused the write, so nothing was
     * corrupted; the board was just offering a control it knew would bounce.
     *
     * @return array<int, string>
     */
    private static function subtaskColumns(): array
    {
        return ['id', 'ticket_id', 'title', 'status', 'side', 'role_id', 'assignee_id', 'position'];
    }

    /**
     * A board is a picture of live work, so the closed column is a short tail —
     * not the archive. Without this it fetched every ticket ever closed: at
     * 5,000 tickets that was 4,268 rows and a 6.7s render.
     */
    private const CLOSED_WINDOW_DAYS = 14;

    /** Cap per column. Anything beyond this is reported, never silently cut. */
    private const COLUMN_LIMIT = 100;

    /**
     * Ceiling for the team board, which spans everyone. Past this the screen
     * stops being a board and becomes a list — and /tickets already is one,
     * with filters and pagination.
     */
    private const BOARD_LIMIT = 300;

    public function mine(Request $request): View
    {
        $user = $request->user();

        // The board's own filter bar — type/priority/company/search. Status is
        // the columns themselves, so it isn't offered here (2026-07-22).
        $filters = $request->only('q', 'type', 'priority', 'company');

        // One query for the whole board: fetch the user's tickets, then group in
        // php rather than running a query per column (CLAUDE.md § 4).
        $tickets = Ticket::query()
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority', 'status',
                'reported_at', 'sla_due_at', 'resolved_at', 'updated_at',
                'subtasks_total', 'subtasks_done',
            ])
            // One extra query for the whole board, not one per card. Still no
            // assignee RELATION — the card shows no name, and that would be a
            // second query. assignee_id itself is not optional though: see
            // subtaskColumns().
            ->with([
                'company:id,name', 'requester:id,name',
                'workLogs:id,ticket_id,user_id,role_id,status', 'workLogs.role:id,name_ar',
                'subtasks' => fn ($q) => $q->select(self::subtaskColumns()),
                // One subquery-joined row per ticket, not every image on every
                // ticket. Loaded whole rather than with a column list: a
                // one-of-many relation joins the table to itself under an
                // alias, and a bare column list there is ambiguity waiting to
                // happen — the row is four short strings, so there is nothing
                // to win by trimming it.
                'coverImage',
                // ★ (2026-08-03) Not for display — the card shows no assignee.
                // Ticket::isAssignee() reads this relation when it is loaded and
                // otherwise runs an exists() query, and TicketPolicy::view()
                // goes through it for anyone without tickets.view.all. Every
                // @can() on a card therefore cost one query, so this screen ran
                // one SELECT per card: 23 cards measured 34 queries, and it grew
                // with the board. Loaded once here it is a single extra query
                // for the whole page. The team board doesn't need it — that
                // route requires tickets.view.all, which short-circuits view()
                // before it ever asks.
                'roleAssignments:id,ticket_id,user_id,role_id',
            ])
            ->assignedTo($user->id)
            ->filter($filters)
            ->onBoard()
            ->defaultOrder()
            ->get();

        return view('board.mine', [
            'columns' => $this->columns($tickets),
            'user' => $user,
            'filters' => $filters,
            'routeName' => 'board.mine',
            'isTeam' => false,
            'selectedCompany' => filled($filters['company'] ?? null)
                ? \App\Models\Company::whereKey($filters['company'])->value('name')
                : null,
        ]);
    }

    /**
     * Buckets the loaded tickets into the four columns.
     *
     * @return array<string, array{label: string, tickets: \Illuminate\Support\Collection, hidden: int}>
     */
    private function columns($tickets): array
    {
        $columns = [];

        foreach (self::COLUMNS as $key => $column) {
            $all = $tickets->whereIn('status.value', $column['statuses'])->values();

            $columns[$key] = [
                'label' => $column['label'],
                'tickets' => $all->take(self::COLUMN_LIMIT),
                // Surfaced in the header rather than dropped quietly — a column
                // that says 12 while holding 112 is lying.
                'hidden' => max(0, $all->count() - self::COLUMN_LIMIT),
            ];
        }

        return $columns;
    }

    /** F12.2 — the same board over every ticket, in swimlanes. */
    public function team(Request $request): View
    {
        abort_unless($request->user()->hasPermission('tickets.view.all'), 403);

        $lane = $request->query('lane', 'assignee');

        // The board's own filter bar — plus assignee, which /tickets has too.
        $filters = $request->only('q', 'type', 'priority', 'company', 'assignee');

        $tickets = Ticket::query()
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority', 'status',
                'reported_at', 'sla_due_at', 'resolved_at', 'updated_at',
                'subtasks_total', 'subtasks_done',
            ])
            ->with([
                'company:id,name', 'requester:id,name',
                'workLogs:id,ticket_id,user_id,role_id,status', 'workLogs.role:id,name_ar',
                'incomingLinks.fromTicket:id,status',
                'subtasks' => fn ($q) => $q->select(self::subtaskColumns()),
                // One subquery-joined row per ticket, not every image on every
                // ticket. Loaded whole rather than with a column list: a
                // one-of-many relation joins the table to itself under an
                // alias, and a bare column list there is ambiguity waiting to
                // happen — the row is four short strings, so there is nothing
                // to win by trimming it.
                'coverImage',
            ])
            // Only the assignee lane reads it — assigneeOf() takes the ticket's
            // first role assignment as the swimlane owner. Loading assignees
            // unconditionally would spend a query the priority lane never uses.
            ->when($lane === 'assignee', fn ($q) => $q->with('roleAssignments.user:id,name,avatar_path,is_active'))
            ->filter($filters)
            ->onBoard()
            ->defaultOrder()
            // A hard ceiling on the whole board. The lanes below are built in
            // php from these rows, so this is what bounds the page.
            ->limit(self::BOARD_LIMIT)
            ->get();

        $total = Ticket::query()->onBoard()->count();

        return view('board.team', [
            'lanes' => $this->swimlanes($tickets, $lane),
            'lane' => $lane,
            'user' => $request->user(),
            'shown' => $tickets->count(),
            'total' => $total,
            'filters' => $filters,
            'routeName' => 'board.team',
            'isTeam' => true,
            'selectedCompany' => filled($filters['company'] ?? null)
                ? \App\Models\Company::whereKey($filters['company'])->value('name')
                : null,
            'selectedAssignee' => filled($filters['assignee'] ?? null)
                ? \App\Models\User::whereKey($filters['assignee'])->value('name')
                : null,
        ]);
    }

    /**
     * Groups by assignee or by priority, in php — the tickets are already
     * loaded, and a query per lane would be pure waste (§ 4).
     *
     * Each lane carries the assignee model alongside its label so the header
     * can show an avatar. That user is already eager-loaded on the ticket, so
     * resolving it here costs nothing.
     *
     * @return array<int, array{label: string, user: ?\App\Models\User, columns: array}>
     */
    private function swimlanes($tickets, string $lane): array
    {
        if ($lane === 'priority') {
            return $tickets
                ->groupBy(fn (Ticket $t) => $t->priority->label())
                ->map(fn ($group, $label) => [
                    'label' => $label,
                    'user' => null,
                    'columns' => $this->columns($group),
                ])
                ->values()
                ->all();
        }

        return $tickets
            ->groupBy(fn (Ticket $t) => $this->assigneeOf($t)?->id ?? 0)
            ->map(function ($group) {
                $assignee = $this->assigneeOf($group->first());

                return [
                    'label' => $assignee?->name ?? 'مش موزعة',
                    'user' => $assignee,
                    'columns' => $this->columns($group),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Whoever the board considers to own this ticket's current work — the first
     * person assigned to it, in any role. Role-based since the fixed columns
     * were dropped (2026-07-24); the swimlane only needs one representative owner.
     */
    private function assigneeOf(Ticket $ticket): ?\App\Models\User
    {
        if (! $ticket->relationLoaded('roleAssignments')) {
            return null;
        }

        return $ticket->roleAssignments->first()?->user;
    }
}
