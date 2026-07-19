<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
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
        $openSubtasks = $ticket->subtasks_total - $ticket->subtasks_done > 0;

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

        // One query for the whole board: fetch the user's tickets, then group in
        // php rather than running a query per column (CLAUDE.md § 4).
        $tickets = Ticket::query()
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority', 'status',
                'reported_at', 'sla_due_at', 'resolved_at', 'updated_at',
                'assigned_frontend_id', 'assigned_backend_id', 'tester_id', 'devops_id',
                'subtasks_total', 'subtasks_done',
            ])
            // One extra query for the whole board, not one per card. No
            // assignee: that would be a second query for a name the card
            // doesn't show — the accordion needs title + status only.
            ->with([
                'company:id,name', 'requester:id,name',
                'workLogs:id,ticket_id,user_id,side,status',
                'subtasks' => fn ($q) => $q->select(['id', 'ticket_id', 'title', 'status', 'side', 'position']),
            ])
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $user->id)
                ->orWhere('assigned_backend_id', $user->id)
                ->orWhere('tester_id', $user->id)
                ->orWhere('devops_id', $user->id))
            ->onBoard()
            ->defaultOrder()
            ->get();

        return view('board.mine', [
            'columns' => $this->columns($tickets),
            'user' => $user,
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

        $tickets = Ticket::query()
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority', 'status',
                'reported_at', 'sla_due_at', 'resolved_at', 'updated_at',
                'assigned_frontend_id', 'assigned_backend_id', 'tester_id', 'devops_id',
                'subtasks_total', 'subtasks_done',
            ])
            ->with([
                'company:id,name', 'requester:id,name',
                'workLogs:id,ticket_id,user_id,side,status',
                'frontend:id,name,avatar_path,is_active',
                'backend:id,name,avatar_path,is_active',
                'incomingLinks.fromTicket:id,status',
                'subtasks' => fn ($q) => $q->select(['id', 'ticket_id', 'title', 'status', 'side', 'position']),
            ])
            // Only the assignee lane reads it — assigneeOf() falls back to
            // devops for the swimlane label. Loading it unconditionally put
            // this page at 15 queries, which is § 4's ceiling exactly.
            ->when($lane === 'assignee', fn ($q) => $q->with('devops:id,name,avatar_path,is_active'))
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

    /** Whoever the board considers to own this ticket's current work. */
    private function assigneeOf(Ticket $ticket): ?\App\Models\User
    {
        return $ticket->frontend ?? $ticket->backend ?? $ticket->devops;
    }
}
