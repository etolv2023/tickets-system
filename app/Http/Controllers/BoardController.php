<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "بوردي" — F12.1. Only what this person is on, in the four columns that
 * describe their own work.
 */
class BoardController extends Controller
{
    private const COLUMNS = [
        'assigned' => ['label' => 'مسندة إليّ', 'statuses' => ['assigned', 'reopened']],
        'in_progress' => ['label' => 'جاري العمل', 'statuses' => ['in_progress']],
        'dev_done' => ['label' => 'تم التطوير', 'statuses' => ['dev_done', 'testing']],
        'closed' => ['label' => 'مغلقة', 'statuses' => ['resolved', 'closed']],
    ];

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
                'id', 'ticket_number', 'company_id', 'title', 'type', 'priority', 'status',
                'reported_at', 'sla_due_at', 'resolved_at', 'updated_at',
                'assigned_frontend_id', 'assigned_backend_id', 'tester_id',
                'subtasks_total', 'subtasks_done',
            ])
            ->with(['company:id,name', 'workLogs:id,ticket_id,user_id,side,status'])
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $user->id)
                ->orWhere('assigned_backend_id', $user->id)
                ->orWhere('tester_id', $user->id))
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
                'id', 'ticket_number', 'company_id', 'title', 'type', 'priority', 'status',
                'reported_at', 'sla_due_at', 'resolved_at', 'updated_at',
                'assigned_frontend_id', 'assigned_backend_id', 'tester_id',
                'subtasks_total', 'subtasks_done',
            ])
            ->with([
                'company:id,name',
                'workLogs:id,ticket_id,user_id,side,status',
                'frontend:id,name,avatar_path,is_active',
                'backend:id,name,avatar_path,is_active',
                'incomingLinks.fromTicket:id,status',
            ])
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
     */
    private function swimlanes($tickets, string $lane): array
    {
        $groups = $lane === 'priority'
            ? $tickets->groupBy(fn (Ticket $t) => $t->priority->label())
            : $tickets->groupBy(fn (Ticket $t) => $t->frontend?->name ?? $t->backend?->name ?? 'مش موزعة');

        return $groups->map(fn ($group) => $this->columns($group))->all();
    }
}
