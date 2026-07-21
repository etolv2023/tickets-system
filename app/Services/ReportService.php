<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The aggregate queries behind /reports and the employee profile (F19).
 *
 * Everything here is a GROUP BY, never a loop over models. period is a stored
 * column, so a month's points are an indexed lookup rather than a DATE_FORMAT
 * over the whole ledger (§ 4.6).
 */
class ReportService
{
    /** F19.1 — one person, one month. */
    public function employeeProfile(User $user, string $period): array
    {
        [$from, $to] = $this->periodBounds($period);

        // What they resolved that month, broken down by type.
        $byType = Ticket::query()
            ->selectRaw('type, COUNT(*) n')
            ->whereBetween('resolved_at', [$from, $to])
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $user->id)
                ->orWhere('assigned_backend_id', $user->id)
                ->orWhere('devops_id', $user->id)
                ->orWhere('created_by', $user->id))
            ->groupBy('type')
            ->pluck('n', 'type')
            ->all();

        $points = PointTransaction::query()
            ->selectRaw('side, SUM(points) total')
            ->where('user_id', $user->id)
            ->forPeriod($period)
            ->groupBy('side')
            ->get();

        return [
            'byType' => $byType,
            'points' => $points,
            'pointsTotal' => (float) $points->sum('total'),
            'avgRating' => Rating::where('ratee_id', $user->id)
                ->whereBetween('rated_at', [$from, $to])
                ->avg('score'),
            'avgResolutionHours' => $this->avgResolutionHours($user, $from, $to),
            'hoursLogged' => (float) TimeEntry::where('user_id', $user->id)
                ->whereBetween('spent_on', [$from, $to])
                ->sum('hours'),
            'estimateAccuracy' => $this->estimateAccuracy($user),
            'reopenRate' => $this->reopenRate($user, $from, $to),
            'tickets' => $this->ticketsTouched($user, $from, $to),
        ];
    }

    /**
     * F19: estimated / actual, averaged over the person's finished subtasks.
     * Above 1 means they finish faster than they guess; below 1, slower.
     */
    public function estimateAccuracy(User $user): ?float
    {
        $row = DB::table('ticket_subtasks')
            ->selectRaw('AVG(estimated_hours / spent_hours) accuracy')
            ->where('assignee_id', $user->id)
            ->where('status', 'done')
            ->whereNull('deleted_at')
            ->whereNotNull('estimated_hours')
            ->where('estimated_hours', '>', 0)
            ->where('spent_hours', '>', 0)
            ->first();

        return $row?->accuracy === null ? null : round((float) $row->accuracy, 2);
    }

    /** F19: how often the tester sends this person's work back. */
    public function reopenRate(User $user, string $from, string $to): array
    {
        $resolved = Ticket::query()
            ->whereBetween('resolved_at', [$from, $to])
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $user->id)
                ->orWhere('assigned_backend_id', $user->id)
                ->orWhere('devops_id', $user->id))
            ->count();

        $reopened = DB::table('ticket_status_history')
            ->join('tickets', 'tickets.id', '=', 'ticket_status_history.ticket_id')
            ->where('ticket_status_history.to_status', 'reopened')
            ->whereBetween('ticket_status_history.created_at', [$from, $to])
            ->where(fn ($q) => $q
                ->where('tickets.assigned_frontend_id', $user->id)
                ->orWhere('tickets.assigned_backend_id', $user->id)
                ->orWhere('tickets.devops_id', $user->id))
            ->count();

        return [
            'resolved' => $resolved,
            'reopened' => $reopened,
            'rate' => $resolved > 0 ? round($reopened / $resolved * 100) : 0,
        ];
    }

    /** F19.2 — the month's ranking. */
    /**
     * The points report: the same ledger read four ways.
     *
     * The leaderboard answers "who is ahead". This answers the questions a
     * manager actually asks at bonus time — where did the points come from,
     * which side earns them, is the month bigger or smaller than the last, and
     * which tickets paid the most.
     *
     * @return array<string, mixed>
     */
    public function pointsReport(string $period): array
    {
        $byPerson = PointTransaction::query()
            ->selectRaw('user_id, SUM(points) total, COUNT(*) awards')
            ->selectRaw("SUM(CASE WHEN side = 'support'  THEN points ELSE 0 END) support")
            ->selectRaw("SUM(CASE WHEN side = 'frontend' THEN points ELSE 0 END) frontend")
            ->selectRaw("SUM(CASE WHEN side = 'backend'  THEN points ELSE 0 END) backend")
            ->selectRaw("SUM(CASE WHEN side = 'tester'   THEN points ELSE 0 END) tester")
            ->selectRaw("SUM(CASE WHEN side = 'devops'   THEN points ELSE 0 END) devops")
            ->forPeriod($period)
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->with('user:id,name,avatar_path,is_active,role_id', 'user.role:id,name_ar')
            ->get();

        $bySide = PointTransaction::query()
            ->selectRaw('side, SUM(points) total, COUNT(*) awards')
            ->forPeriod($period)
            ->groupBy('side')
            ->orderByDesc('total')
            ->get();

        // Where the points came from: a ticket's type is what the matrix
        // priced, so this is the report that justifies the matrix.
        $byType = PointTransaction::query()
            ->join('tickets', 'tickets.id', '=', 'point_transactions.ticket_id')
            ->selectRaw('tickets.type, SUM(point_transactions.points) total, COUNT(*) awards')
            ->selectRaw('COUNT(DISTINCT tickets.id) tickets')
            ->forPeriod($period)
            ->groupBy('tickets.type')
            ->orderByDesc('total')
            ->get();

        $topTickets = PointTransaction::query()
            ->selectRaw('ticket_id, SUM(points) total, COUNT(*) people')
            // A manual correction may not reference a ticket at all (ticket_id
            // nullable since F18's rework); this table is about tickets.
            ->whereNotNull('ticket_id')
            ->forPeriod($period)
            ->groupBy('ticket_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('ticket:id,ticket_number,title,type,company_id,requested_by',
                'ticket.company:id,name', 'ticket.requester:id,name')
            ->get();

        // F18: manual adjustments, shown apart from what subtasks earned —
        // an admin scanning the total should be able to tell how much of it
        // was typed in by hand.
        $corrections = PointTransaction::query()
            ->selectRaw('COUNT(*) awards, SUM(points) total')
            ->where('type', 'correction')
            ->forPeriod($period)
            ->first();

        return [
            'byPerson' => $byPerson,
            'bySide' => $bySide,
            'byType' => $byType,
            'topTickets' => $topTickets,
            'total' => (float) $byPerson->sum('total'),
            'people' => $byPerson->count(),
            'tickets' => (int) PointTransaction::query()->forPeriod($period)
                ->whereNotNull('ticket_id')->distinct()->count('ticket_id'),
            'correctionsTotal' => (float) ($corrections->total ?? 0),
            'correctionsCount' => (int) ($corrections->awards ?? 0),
            // Last month, so the headline number has something to mean.
            'previous' => (float) PointTransaction::query()
                ->forPeriod(\Carbon\CarbonImmutable::createFromFormat('Y-m', $period)
                    ->subMonth()->format('Y-m'))
                ->sum('points'),
        ];
    }

    /**
     * F19.2 — the month's ranking.
     *
     * @param  array{person?: int|string|null, assignee?: int|string|null}  $filters
     *         person: only this user's own rows. assignee: only rows whose
     *         ticket has this user on one of its assignment columns — a
     *         manager asking "what did the team on ticket X earn", not "what
     *         did this one person earn" (that's `person`).
     */
    public function leaderboard(string $period, array $filters = []): Collection
    {
        return PointTransaction::query()
            ->selectRaw('user_id, SUM(points) total, COUNT(*) awards')
            ->forPeriod($period)
            ->when($filters['person'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['assignee'] ?? null, fn ($q, $v) => $q->whereHas('ticket', fn ($t) => $t
                ->where('assigned_frontend_id', $v)
                ->orWhere('assigned_backend_id', $v)
                ->orWhere('devops_id', $v)
                ->orWhere('tester_id', $v)))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->with('user:id,name,avatar_path,is_active,role_id')
            ->get();
    }

    /** F19.3 — how the work splits. */
    public function ticketDistribution(string $from, string $to): Collection
    {
        return Ticket::query()
            ->selectRaw('type, status, COUNT(*) n')
            ->whereBetween('reported_at', [$from, $to])
            ->groupBy('type', 'status')
            ->get();
    }

    /** F19.3 — which customer sends the most. */
    public function companyPerformance(string $from, string $to): Collection
    {
        return Ticket::query()
            ->selectRaw('company_id, COUNT(*) total')
            ->selectRaw("SUM(status IN ('resolved','closed')) resolved")
            ->selectRaw('AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, reported_at, resolved_at) END) avg_hours')
            ->whereBetween('reported_at', [$from, $to])
            ->groupBy('company_id')
            ->orderByDesc('total')
            // Grouped rows, not tickets: there is no single requester here.
            ->with('company:id,name')
            ->get();
    }

    /** F19.3 — resolution time by priority and by type. */
    public function resolutionTimes(string $from, string $to): array
    {
        $shape = fn (string $column) => Ticket::query()
            ->selectRaw("{$column} k, COUNT(*) n")
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, reported_at, resolved_at)) avg_hours')
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->groupBy($column)
            ->get();

        return ['byPriority' => $shape('priority'), 'byType' => $shape('type')];
    }

    /** F19.3 — who is over their SLA. */
    public function slaBreaches(string $from, string $to): Collection
    {
        return Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'priority', 'status', 'sla_due_at', 'resolved_at'])
            ->with('company:id,name', 'requester:id,name')
            ->whereNotNull('sla_due_at')
            ->whereBetween('reported_at', [$from, $to])
            ->where(fn ($q) => $q
                // Either still open past its deadline, or resolved after it.
                ->where(fn ($w) => $w->whereNull('resolved_at')->where('sla_due_at', '<', now()))
                ->orWhereColumn('resolved_at', '>', 'sla_due_at'))
            ->orderBy('sla_due_at')
            ->get();
    }

    /** F19.3 — open tickets per developer. */
    public function teamLoad(): Collection
    {
        return User::query()
            ->select(['id', 'name', 'avatar_path', 'is_active'])
            ->without('role')
            ->withCount([
                'assignedFrontend as frontend_open' => fn ($q) => $q->whereNotIn('status', ['resolved', 'closed', 'rejected']),
                'assignedBackend as backend_open' => fn ($q) => $q->whereNotIn('status', ['resolved', 'closed', 'rejected']),
                'assignedDevops as devops_open' => fn ($q) => $q->whereNotIn('status', ['resolved', 'closed', 'rejected']),
            ])
            ->active()
            ->get()
            ->filter(fn ($u) => $u->frontend_open > 0 || $u->backend_open > 0 || $u->devops_open > 0)
            ->sortByDesc(fn ($u) => $u->frontend_open + $u->backend_open + $u->devops_open)
            ->values();
    }

    /** F19.3 — estimated vs actual per person. */
    public function timeReport(string $from, string $to): Collection
    {
        return DB::table('time_entries')
            ->selectRaw('user_id, SUM(hours) logged, COUNT(DISTINCT ticket_id) tickets')
            ->whereBetween('spent_on', [$from, $to])
            ->groupBy('user_id')
            ->orderByDesc('logged')
            ->get()
            ->map(function ($row) {
                $user = User::without('role')->find($row->user_id);

                return (object) [
                    'user' => $user,
                    'logged' => (float) $row->logged,
                    'tickets' => $row->tickets,
                    'accuracy' => $user ? $this->estimateAccuracy($user) : null,
                ];
            });
    }

    /** @return array{0: string, 1: string} */
    public function periodBounds(string $period): array
    {
        $start = \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i:s', $period . '-01 00:00:00');

        return [$start->toDateTimeString(), $start->endOfMonth()->toDateTimeString()];
    }

    private function avgResolutionHours(User $user, string $from, string $to): ?float
    {
        $avg = Ticket::query()
            ->whereBetween('resolved_at', [$from, $to])
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $user->id)
                ->orWhere('assigned_backend_id', $user->id)
                ->orWhere('devops_id', $user->id))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, reported_at, resolved_at)) h')
            ->value('h');

        return $avg === null ? null : round((float) $avg, 1);
    }

    private function ticketsTouched(User $user, string $from, string $to): Collection
    {
        return Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'type', 'priority', 'status', 'company_id', 'requested_by', 'reported_at', 'resolved_at'])
            ->with('company:id,name', 'requester:id,name')
            ->whereBetween('resolved_at', [$from, $to])
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $user->id)
                ->orWhere('assigned_backend_id', $user->id)
                ->orWhere('tester_id', $user->id)
                ->orWhere('devops_id', $user->id)
                ->orWhere('created_by', $user->id))
            ->orderByDesc('resolved_at')
            ->limit(100)
            ->get();
    }
}
