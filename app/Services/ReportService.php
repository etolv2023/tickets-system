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
                ->orWhere('assigned_backend_id', $user->id))
            ->count();

        $reopened = DB::table('ticket_status_history')
            ->join('tickets', 'tickets.id', '=', 'ticket_status_history.ticket_id')
            ->where('ticket_status_history.to_status', 'reopened')
            ->whereBetween('ticket_status_history.created_at', [$from, $to])
            ->where(fn ($q) => $q
                ->where('tickets.assigned_frontend_id', $user->id)
                ->orWhere('tickets.assigned_backend_id', $user->id))
            ->count();

        return [
            'resolved' => $resolved,
            'reopened' => $reopened,
            'rate' => $resolved > 0 ? round($reopened / $resolved * 100) : 0,
        ];
    }

    /** F19.2 — the month's ranking. */
    public function leaderboard(string $period): Collection
    {
        return PointTransaction::query()
            ->selectRaw('user_id, SUM(points) total, COUNT(*) awards')
            ->forPeriod($period)
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
            ->select(['id', 'ticket_number', 'title', 'company_id', 'priority', 'status', 'sla_due_at', 'resolved_at'])
            ->with('company:id,name')
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
            ])
            ->active()
            ->get()
            ->filter(fn ($u) => $u->frontend_open > 0 || $u->backend_open > 0)
            ->sortByDesc(fn ($u) => $u->frontend_open + $u->backend_open)
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
                ->orWhere('assigned_backend_id', $user->id))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, reported_at, resolved_at)) h')
            ->value('h');

        return $avg === null ? null : round((float) $avg, 1);
    }

    private function ticketsTouched(User $user, string $from, string $to): Collection
    {
        return Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'type', 'priority', 'status', 'company_id', 'reported_at', 'resolved_at'])
            ->with('company:id,name')
            ->whereBetween('resolved_at', [$from, $to])
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $user->id)
                ->orWhere('assigned_backend_id', $user->id)
                ->orWhere('tester_id', $user->id)
                ->orWhere('created_by', $user->id))
            ->orderByDesc('resolved_at')
            ->limit(100)
            ->get();
    }
}
