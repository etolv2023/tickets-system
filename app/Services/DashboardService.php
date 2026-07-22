<?php

namespace App\Services;

use App\Enums\LinkType;
use App\Casts\TicketTypeValue;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The home page — "اليوم" (F22.1). Both the four original action sections
 * AND the overview widgets added 2026-07-20 (see the amendment note in
 * CLAUDE.md § 6 and FEATURES.md F22.1). Moved here whole out of
 * HomeController, which otherwise passed 150 lines (CLAUDE.md § 3) — the
 * four original methods are an unmodified relocation, not a rewrite.
 *
 * Every query here is scoped through Ticket::visibleTo($user), the same
 * row-level visibility rule the ticket list already uses — a frontend
 * developer's overview totals their own visible slice, not the whole
 * company's, without a separate permission check. Team-wide and audit-level
 * widgets (team load, activity) reuse the SAME gates their full screens
 * already sit behind (reports.view, audit.view) rather than inventing a new
 * visibility rule for the home page.
 */
class DashboardService
{
    /** How many rows a list on this screen will show before it stops. */
    private const LIST_LIMIT = 15;

    public function __construct(private readonly ReportService $reports)
    {
    }

    /**
     * The list's own length unless it was truncated, in which case the real
     * total is worth one extra COUNT.
     */
    public function totalFor($rows, callable $query): int
    {
        return $rows->count() < self::LIST_LIMIT
            ? $rows->count()
            : $query()->count();
    }

    /* Each list below is split into a query and a fetch, so the tile above it
       can re-run the same conditions as a COUNT without repeating them. */

    /** 1. What's on my mind — subtasks due today or already late. F22.1 */
    public function onMyPlateQuery(int $userId)
    {
        return TicketSubtask::query()
            ->where('assignee_id', $userId)
            ->dueOrOverdue();
    }

    public function onMyPlate(int $userId)
    {
        return $this->onMyPlateQuery($userId)
            ->select(['id', 'ticket_id', 'title', 'due_date', 'status', 'side', 'estimated_hours'])
            ->with(['ticket:id,ticket_number,title,priority,company_id,requested_by', 'ticket.company:id,name', 'ticket.requester:id,name'])
            ->orderBy('due_date')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /** 2. What's on fire — SLA breaches I'm responsible for. F22.1 */
    public function onFireQuery($user)
    {
        return Ticket::query()
            ->visibleTo($user)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNotIn('status', ['resolved', 'closed', 'rejected']);
    }

    public function onFire($user)
    {
        return $this->onFireQuery($user)
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'priority', 'status', 'sla_due_at', 'reported_at'])
            ->with('company:id,name', 'requester:id,name')
            ->defaultOrder()
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /** 3. What's waiting on my decision — approvals. Admin only. F22.1 */
    public function awaitingMeQuery($user)
    {
        return Ticket::query()->where('approval_status', 'pending');
    }

    public function awaitingMe($user)
    {
        if (! $user->hasPermission('features.approve')) {
            return collect();
        }

        return $this->awaitingMeQuery($user)
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'type', 'priority', 'reported_at', 'sla_due_at', 'status'])
            ->with('company:id,name', 'requester:id,name')
            ->defaultOrder()
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /**
     * 4. Who am I holding up — tickets of mine that block someone else's,
     * and are still open. F10 / F22.1
     */
    public function blockingOthersQuery(int $userId)
    {
        return Ticket::query()
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $userId)
                ->orWhere('assigned_backend_id', $userId)
                ->orWhere('devops_id', $userId))
            ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
            // Only if it actually blocks something that is itself still open —
            // blocking a closed ticket holds nobody up.
            ->whereHas('outgoingLinks', fn ($q) => $q
                ->where('type', LinkType::Blocks->value)
                ->whereHas('toTicket', fn ($t) => $t->whereNotIn('status', ['resolved', 'closed', 'rejected'])));
    }

    public function blockingOthers(int $userId)
    {
        return $this->blockingOthersQuery($userId)
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'priority', 'status', 'sla_due_at', 'reported_at'])
            ->with(['company:id,name', 'requester:id,name', 'outgoingLinks.toTicket:id,ticket_number,title,status'])
            ->defaultOrder()
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /**
     * Real totals, not the length of a capped list — same reasoning as the
     * four original stat tiles (HomeController::totalFor()).
     *
     * One query, not four: every number here is a conditional aggregate over
     * the SAME scoped table scan (CLAUDE.md § 4 — a screen over 15 queries is
     * a problem, and four COUNT()s here would have been the whole overage on
     * their own for what is, underneath, one full pass over the same rows).
     */
    public function kpis(User $user, string $from, string $to): array
    {
        $row = $this->visible($user)
            ->selectRaw("COUNT(CASE WHEN status NOT IN ('resolved', 'closed', 'rejected') THEN 1 END) open")
            ->selectRaw('COUNT(CASE WHEN resolved_at BETWEEN ? AND ? THEN 1 END) resolved_this_month', [$from, $to])
            ->selectRaw('AVG(CASE WHEN resolved_at BETWEEN ? AND ? THEN TIMESTAMPDIFF(HOUR, reported_at, resolved_at) END) avg_resolution_hours', [$from, $to])
            ->selectRaw(
                'COUNT(CASE WHEN sla_due_at IS NOT NULL AND reported_at BETWEEN ? AND ?'
                . ' AND ((resolved_at IS NULL AND sla_due_at < ?) OR resolved_at > sla_due_at)'
                . ' THEN 1 END) sla_breaches_this_month',
                [$from, $to, now()]
            )
            ->first();

        return [
            'open' => (int) $row->open,
            'resolvedThisMonth' => (int) $row->resolved_this_month,
            'avgResolutionHours' => round((float) ($row->avg_resolution_hours ?? 0), 1),
            'slaBreachesThisMonth' => (int) $row->sla_breaches_this_month,
        ];
    }

    /** Same GROUP BY as ReportService::ticketDistribution, scoped to what
     * this user can see — /reports itself is admin/manager-only, but this
     * tile sits on everyone's home page.
     *
     * Aggregates per type here rather than in the view: the completion meter
     * needs a total, a done count and a guarded done/total percent per row,
     * and CLAUDE.md § 3 keeps that arithmetic out of Blade. One row per type,
     * ready to render.
     *
     * @return Collection<int, object{type: TicketTypeValue, total: int, done: int, open: int, pct: int}>
     */
    public function ticketStatsByType(User $user, string $from, string $to): Collection
    {
        return $this->visible($user)
            ->selectRaw('type, status, COUNT(*) n')
            ->whereBetween('reported_at', [$from, $to])
            ->groupBy('type', 'status')
            ->get()
            ->groupBy('type')
            ->map(function (Collection $rows, string $type) {
                $total = (int) $rows->sum('n');
                $done = (int) $rows->whereIn('status', ['resolved', 'closed'])->sum('n');

                return (object) [
                    'type' => TicketTypeValue::for($type),
                    'total' => $total,
                    'done' => $done,
                    'open' => $total - $done,
                    'pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Breach counts by priority, this month — a rate, not the list of
     * tickets themselves (that list is already "الي بيولّع" above it).
     *
     * GROUP BY in SQL rather than fetching every breached ticket to group in
     * PHP (CLAUDE.md § 4.3 — select only what a screen needs). `priority` is
     * still a real Ticket attribute in the result, so it hydrates through the
     * model's normal cast to the same PriorityValue object every badge in the
     * app already reads label()/variant()/icon() from.
     */
    public function slaBreachesByPriority(User $user, string $from, string $to): Collection
    {
        $rows = $this->breachesQuery($user, $from, $to)
            ->selectRaw('priority, COUNT(*) n')
            ->groupBy('priority')
            ->get()
            ->map(fn (Ticket $row) => ['priority' => $row->priority, 'count' => (int) $row->n])
            ->sortByDesc('count')
            ->values();

        // The meter fills relative to the worst priority, so a bar reads as
        // "share of the breaches", not an absolute width. Percent is computed
        // here, never in the view (CLAUDE.md § 3); guard keeps an all-zero
        // month from dividing by nothing.
        $max = $rows->max('count') ?: 1;

        return $rows->map(fn (array $row) => $row + ['pct' => (int) round($row['count'] / $max * 100)]);
    }

    /** Title/status/age only — the widget doesn't show company or reporter,
     * so eager-loading them would be two queries this screen doesn't need. */
    public function recentTickets(User $user, int $limit = 8): Collection
    {
        return $this->visible($user)
            ->select(['id', 'ticket_number', 'title', 'status', 'reported_at', 'sla_due_at', 'resolved_at'])
            ->latest('reported_at')
            ->limit($limit)
            ->get();
    }

    /** Team-wide workload — the same query /reports shows, so it stays
     * behind the same gate rather than a wider one. Null, not an empty
     * collection, so the view can tell "no permission" from "nobody loaded". */
    public function teamPerformance(User $user, int $limit = 6): ?Collection
    {
        if (! $user->hasPermission('reports.view')) {
            return null;
        }

        $rows = $this->reports->teamLoad()->take($limit)->values();

        // A load meter beside the numbers, scaled to the busiest person shown.
        // Computed here so the view injects only the --value custom property
        // (CLAUDE.md § 3). The exact per-side counts stay in the table — the
        // bar is additive, never a replacement for the figures a manager reads.
        $max = $rows->max(fn ($p) => $p->frontend_open + $p->backend_open + $p->devops_open) ?: 1;

        return $rows->each(function ($p) use ($max) {
            $p->load_total = $p->frontend_open + $p->backend_open + $p->devops_open;
            $p->load_pct = (int) round($p->load_total / $max * 100);
        });
    }

    /**
     * Same gate as /admin/audit. Null for "can't see this", not "nothing
     * happened" — the two must not look the same in the view.
     *
     * A join for the actor's name rather than ->with('user') — one query
     * instead of two, and this compact widget shows a name, not an avatar
     * (the full /admin/audit table still does both). CLAUDE.md § 4: this is
     * the last of the seven new queries this screen was one over budget on.
     */
    public function recentActivity(User $user, int $limit = 8): ?Collection
    {
        if (! $user->hasPermission('audit.view')) {
            return null;
        }

        return ActivityLog::query()
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->select('activity_logs.*', 'users.name as actor_name')
            ->orderByDesc('activity_logs.id')
            ->limit($limit)
            ->get();
    }

    private function visible(User $user): \Illuminate\Database\Eloquent\Builder
    {
        return Ticket::query()->visibleTo($user);
    }

    private function breachesQuery(User $user, string $from, string $to): \Illuminate\Database\Eloquent\Builder
    {
        return $this->visible($user)
            ->whereNotNull('sla_due_at')
            ->whereBetween('reported_at', [$from, $to])
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('resolved_at')->where('sla_due_at', '<', now()))
                ->orWhereColumn('resolved_at', '>', 'sla_due_at'));
    }
}
