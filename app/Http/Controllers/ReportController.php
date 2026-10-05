<?php

namespace App\Http\Controllers;

use App\Models\SubtaskStatusDefinition;
use App\Models\Company;
use App\Models\PointTransaction;
use App\Models\PriorityDefinition;
use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\TicketSubtask;
use App\Models\TicketTypeDefinition;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F19 — the numbers. You come here on purpose; they're never pushed at you. */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        [$from, $to] = $this->bounds($request);

        // ★ (2026-10-05) The cards narrow together (ReportService::constrain).
        // The load and time cards are not bound by these: one is "now", the
        // other is time_entries, and the screen says so beside each.
        $filters = $request->only(['company', 'type', 'priority', 'person']);

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'period' => $this->period($request),
            'filters' => $filters,
            'distribution' => $this->reports->ticketDistribution($from, $to, $filters),
            'companies' => $this->reports->companyPerformance($from, $to, $filters),
            'resolution' => $this->reports->resolutionTimes($from, $to, $filters),
            'breaches' => $this->reports->slaBreaches($from, $to, $filters),
            'load' => $this->reports->teamLoad(),
            'time' => $this->reports->timeReport($from, $to),
            'months' => $this->months(),
            'types' => TicketTypeDefinition::options(),
            'priorities' => PriorityDefinition::options(),
            'selectedCompany' => filled($filters['company'] ?? null)
                ? Company::whereKey($filters['company'])->value('name')
                : null,
            'selectedPerson' => filled($filters['person'] ?? null)
                ? User::whereKey($filters['person'])->value('name')
                : null,
        ]);
    }

    /** F19.1 */
    public function employee(Request $request, User $user): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $period = $this->period($request);

        return view('reports.employee', [
            'employee' => $user,
            'period' => $period,
            'months' => $this->months(),
            'data' => $this->reports->employeeProfile($user, $period),
        ]);
    }

    /** F19.2 */
    public function leaderboard(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.view.all'), 403);

        $period = $this->period($request);
        $filters = $request->only(['person', 'assignee']);

        return view('reports.leaderboard', [
            'period' => $period,
            'months' => $this->months(),
            'filters' => $filters,
            'rows' => $this->reports->leaderboard($period, $filters),
            'selectedPerson' => filled($filters['person'] ?? null)
                ? User::whereKey($filters['person'])->value('name')
                : null,
            'selectedAssignee' => filled($filters['assignee'] ?? null)
                ? User::whereKey($filters['assignee'])->value('name')
                : null,
        ]);
    }

    /** F18 — the points report: where the month's points came from. */
    public function points(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.view.all'), 403);

        $period = $this->period($request);

        // ★ (2026-08-19) An unknown key would silently filter the whole screen
        // down to nothing and read as "a quiet month" rather than as a typo, so
        // anything not in the type list is dropped back to "all types".
        $type = (string) $request->query('type', '');
        $types = TicketTypeDefinition::options();
        $type = array_key_exists($type, $types) ? $type : null;

        return view('reports.points', [
            'period' => $period,
            'months' => $this->months(),
            'types' => $types,
        ] + $this->reports->pointsReport($period, $type));
    }

    /**
     * F18/F19.3 — the points ledger, row by row.
     *
     * The sibling of /points-report the way team-activity is the sibling of
     * /reports: that one aggregates, this one shows the actual transactions
     * behind the aggregate. When someone disputes a bonus figure, this is the
     * screen that answers it — every row traces to one subtask, one rule and
     * one moment.
     */
    public function pointsDetail(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.view.all'), 403);

        $filters = $request->only(['person', 'period', 'from', 'to', 'role', 'type', 'kind', 'company', 'q']);

        $rows = PointTransaction::query()
            ->with([
                'user:id,name,avatar_path,is_active',
                'ticket:id,ticket_number,title,type,company_id,requested_by',
                'ticket.company:id,name',
                'ticket.requester:id,name',
                'subtask:id,title,side',
                'creator:id,name',
                // F06 role-assignment extension: a role-based row's side is
                // null — this is what the blade falls back to instead.
                'role:id,name_ar',
            ])
            ->when($filters['person'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['period'] ?? null, fn ($q, $v) => $q->forPeriod($v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->where('role_id', $v))
            ->when($filters['kind'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->whereHas('ticket', fn ($t) => $t->where('type', $v)))
            ->when($filters['company'] ?? null, fn ($q, $v) => $q->whereHas('ticket', fn ($t) => $t->where('company_id', $v)))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('reason', 'like', "%{$v}%")
                ->orWhereHas('ticket', fn ($t) => $t->where('ticket_number', 'like', "%{$v}%")
                    ->orWhere('title', 'like', "%{$v}%"))
                ->orWhereHas('subtask', fn ($t) => $t->where('title', 'like', "%{$v}%"))))
            ->orderByDesc('created_at');

        // The total of what is on screen, not of the whole ledger — a filtered
        // view whose total ignores the filter is worse than no total. One
        // aggregate query rather than three separate counts.
        $summary = (clone $rows)->toBase()->reorder()->selectRaw(
            'COALESCE(SUM(points), 0) AS total, COUNT(*) AS entries, COUNT(DISTINCT user_id) AS people'
        )->first();

        return view('reports.points-detail', [
            'rows' => $rows->paginate(50)->withQueryString(),
            'summary' => $summary,
            'filters' => $filters,
            'months' => $this->months(),
            // The points ledger filter is by role now (dynamic), not a
            // hardcoded PointSide (2026-07-24).
            'roles' => \App\Models\Role::assignableList(),
            'types' => TicketTypeDefinition::options(),
            'selectedPerson' => filled($filters['person'] ?? null)
                ? User::whereKey($filters['person'])->value('name')
                : null,
            'selectedCompany' => filled($filters['company'] ?? null)
                ? Company::whereKey($filters['company'])->value('name')
                : null,
        ]);
    }

    /**
     * "نقاطي" — everyone can see their own. F18
     *
     * Defaults to the current month, same as every other points screen. A
     * separate ?period=all mode (a plain link, not a value in the shared
     * month picker) skips forPeriod() entirely and sums the whole ledger —
     * $period itself still resolves to the current month via period()'s own
     * fallback, so the picker keeps showing a real, selected month even
     * while "all" is active.
     */
    public function myPoints(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.view.own'), 403);

        $period = $this->period($request);
        $isAll = $request->query('period') === 'all';

        $transactions = $request->user()->pointTransactions()
            // F06 role-assignment extension: a role-based row's side is null —
            // this is what the blade falls back to instead.
            ->with('ticket:id,ticket_number,title,type', 'subtask:id,title', 'role:id,name_ar')
            ->when(! $isAll, fn ($q) => $q->forPeriod($period))
            ->orderByDesc('created_at')
            ->get();

        return view('reports.my-points', [
            'period' => $period,
            'isAll' => $isAll,
            'months' => $this->months(),
            'total' => $isAll
                ? (float) $request->user()->pointTransactions()->sum('points')
                : $request->user()->pointsFor($period),
            'transactions' => $transactions,
        ]);
    }

    /**
     * F19.3: one filterable screen over a team member's raw tickets and
     * subtasks, side by side — not the pre-aggregated numbers /employees/{user}
     * shows, the actual rows.
     */
    public function teamActivity(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $show = in_array($request->query('show'), ['tickets', 'subtasks'], true)
            ? $request->query('show')
            : 'both';

        $filters = $request->only(\App\Support\TeamActivityFilters::KEYS);

        $tickets = $show !== 'subtasks'
            ? Ticket::query()
                ->select([
                    'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority',
                    'status', 'reported_at', 'sla_due_at', 'due_date', 'resolved_at',
                ])
                ->with(['company:id,name', 'requester:id,name', 'roleAssignments.user:id,name,avatar_path,is_active'])
                // One translation, shared with the export (TeamActivityFilters).
                ->filter(\App\Support\TeamActivityFilters::forTickets($filters))
                ->defaultOrder()
                ->paginate(25, ['*'], 'tickets_page')
                ->withQueryString()
            : null;

        $subtasks = $show !== 'tickets'
            ? TicketSubtask::query()
                ->select([
                    'id', 'ticket_id', 'title', 'assignee_id', 'side', 'role_id', 'status',
                    'start_date', 'due_date', 'estimated_hours', 'spent_hours', 'completed_at',
                ])
                ->with(['assignee:id,name,avatar_path,is_active', 'role:id,name_ar', 'ticket:id,ticket_number,title,company_id,requested_by,type'])
                ->filter(\App\Support\TeamActivityFilters::forSubtasks($filters))
                ->orderByDesc('due_date')
                ->paginate(25, ['*'], 'subtasks_page')
                ->withQueryString()
            : null;

        return view('reports.team-activity', [
            'show' => $show,
            'filters' => $filters,
            'tickets' => $tickets,
            'subtasks' => $subtasks,
            // Only what is selected, so the filter boxes can show a name.
            // The lists come from /lookup as the user types.
            'selectedPerson' => filled($filters['person'] ?? null)
                ? User::whereKey($filters['person'])->value('name')
                : null,
            'selectedCompany' => filled($filters['company'] ?? null)
                ? Company::whereKey($filters['company'])->value('name')
                : null,
            'types' => TicketTypeDefinition::options(),
            'priorities' => PriorityDefinition::options(),
            'statuses' => TicketStatusDefinition::options(),
            // The subtask filter is by role now (dynamic from the DB), not a
            // hardcoded side (2026-07-24).
            'roles' => \App\Models\Role::assignableList(),
            'subtaskStatuses' => SubtaskStatusDefinition::options(),
            'ticketDateBases' => Ticket::DATE_BASES,
            'subtaskDateBases' => TicketSubtask::DATE_BASES,
            'lateness' => Ticket::LATENESS,
        ]);
    }

    private function period(Request $request): string
    {
        return $this->reports->resolvePeriod($request->query('period'));
    }

    /** @return array{0: string, 1: string} */
    private function bounds(Request $request): array
    {
        return $this->reports->periodBounds($this->period($request));
    }

    /**
     * The last 12 months, for the picker. Shared with the board, which needs
     * the same list — so the list itself lives on ReportService next to
     * periodBounds() rather than being written twice.
     *
     * ★ The `current` sentinel that list opens with is dropped here: these
     * screens are reached on purpose and do not remember their filter bar, so
     * a self-renewing default buys them nothing and would only add a second
     * option meaning "September" during September.
     *
     * @return array<string, string>
     */
    private function months(): array
    {
        return \Illuminate\Support\Arr::except($this->reports->monthOptions(), ['current']);
    }
}
