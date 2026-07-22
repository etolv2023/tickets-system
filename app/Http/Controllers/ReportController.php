<?php

namespace App\Http\Controllers;

use App\Enums\PointSide;
use App\Enums\SubtaskSide;
use App\Enums\SubtaskStatus;
use App\Models\Company;
use App\Models\PointTransaction;
use App\Models\PriorityDefinition;
use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\TicketSubtask;
use App\Models\TicketTypeDefinition;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
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

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'period' => $this->period($request),
            'distribution' => $this->reports->ticketDistribution($from, $to),
            'companies' => $this->reports->companyPerformance($from, $to),
            'resolution' => $this->reports->resolutionTimes($from, $to),
            'breaches' => $this->reports->slaBreaches($from, $to),
            'load' => $this->reports->teamLoad(),
            'time' => $this->reports->timeReport($from, $to),
            'months' => $this->months(),
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

        return view('reports.points', [
            'period' => $period,
            'months' => $this->months(),
        ] + $this->reports->pointsReport($period));
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

        $filters = $request->only(['person', 'period', 'from', 'to', 'side', 'type', 'kind', 'company', 'q']);

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
            ->when($filters['side'] ?? null, fn ($q, $v) => $q->where('side', $v))
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
            'sides' => PointSide::options(),
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

        $filters = $request->only([
            'person', 'from', 'to', 'ticket_date_basis', 'subtask_date_basis',
            'type', 'priority', 'status', 'company', 'side', 'subtask_status',
        ]);

        $tickets = $show !== 'subtasks'
            ? Ticket::query()
                ->select([
                    'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority',
                    'status', 'reported_at', 'due_date', 'resolved_at',
                ])
                ->with(['company:id,name', 'requester:id,name', 'roleAssignments.user:id,name,avatar_path,is_active'])
                ->filter([
                    'assignee' => $filters['person'] ?? null,
                    'from' => $filters['from'] ?? null,
                    'to' => $filters['to'] ?? null,
                    'date_basis' => $filters['ticket_date_basis'] ?? null,
                    'type' => $filters['type'] ?? null,
                    'priority' => $filters['priority'] ?? null,
                    'status' => $filters['status'] ?? null,
                    'company' => $filters['company'] ?? null,
                ])
                ->defaultOrder()
                ->paginate(25, ['*'], 'tickets_page')
                ->withQueryString()
            : null;

        $subtasks = $show !== 'tickets'
            ? TicketSubtask::query()
                ->select([
                    'id', 'ticket_id', 'title', 'assignee_id', 'side', 'status',
                    'start_date', 'due_date', 'estimated_hours', 'spent_hours', 'completed_at',
                ])
                ->with(['assignee:id,name,avatar_path,is_active', 'ticket:id,ticket_number,title,company_id,requested_by,type'])
                ->filter([
                    'person' => $filters['person'] ?? null,
                    'from' => $filters['from'] ?? null,
                    'to' => $filters['to'] ?? null,
                    'date_basis' => $filters['subtask_date_basis'] ?? null,
                    'side' => $filters['side'] ?? null,
                    'status' => $filters['subtask_status'] ?? null,
                    'type' => $filters['type'] ?? null,
                    'company' => $filters['company'] ?? null,
                ])
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
            'sides' => SubtaskSide::options(),
            'subtaskStatuses' => SubtaskStatus::options(),
            'ticketDateBases' => Ticket::DATE_BASES,
            'subtaskDateBases' => TicketSubtask::DATE_BASES,
        ]);
    }

    private function period(Request $request): string
    {
        $period = (string) $request->query('period', '');

        return preg_match('/^\d{4}-\d{2}$/', $period) === 1
            ? $period
            : CarbonImmutable::now()->format('Y-m');
    }

    /** @return array{0: string, 1: string} */
    private function bounds(Request $request): array
    {
        return $this->reports->periodBounds($this->period($request));
    }

    /** @return array<string, string> the last 12 months, for the picker */
    private function months(): array
    {
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $month = CarbonImmutable::now()->subMonths($i);
            $months[$month->format('Y-m')] = $month->translatedFormat('F Y');
        }

        return $months;
    }
}
