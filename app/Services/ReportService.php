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
                ->assignedTo($user->id)
                ->orWhere('created_by', $user->id))
            ->groupBy('type')
            ->pluck('n', 'type')
            ->all();

        // F06 role-assignment extension: a role-based award has side = null
        // and role_id set instead — grouped separately so it isn't merged
        // into (or lost inside) the side=null bucket.
        $points = PointTransaction::query()
            ->selectRaw('side, role_id, SUM(points) total')
            ->where('user_id', $user->id)
            ->forPeriod($period)
            ->groupBy('side', 'role_id')
            ->with('role:id,name_ar')
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
            ->assignedTo($user->id)
            ->count();

        $reopened = DB::table('ticket_status_history')
            ->join('tickets', 'tickets.id', '=', 'ticket_status_history.ticket_id')
            ->where('ticket_status_history.to_status', 'reopened')
            ->whereBetween('ticket_status_history.created_at', [$from, $to])
            // Role-based assignment: the ticket has a role assignment for this
            // user. A raw join, so this is an EXISTS subquery, not whereHas.
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('ticket_role_assignments')
                ->whereColumn('ticket_role_assignments.ticket_id', 'tickets.id')
                ->where('ticket_role_assignments.user_id', $user->id))
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
    /**
     * ★ (2026-08-19) $type narrows the whole screen to one ticket type.
     *
     * Every figure moves together or the page lies: filtering the per-person
     * table while leaving the headline total counting every type would put two
     * numbers on one screen that cannot both be right. So the constraint is a
     * closure applied to each query rather than a WHERE written six times —
     * one definition of "this type", and no query can quietly miss it.
     *
     * The join is on the TICKET's type, not on anything stored in the ledger.
     * point_transactions has no type column and should not get one: a ticket
     * retyped from بج to فيتشر must move its history with it, and a copy
     * frozen at award time would strand it.
     *
     * A manual correction with no ticket (F18 allows one) drops out of every
     * type-filtered figure, which is correct — it belongs to no type. It is
     * still in the unfiltered view, which is the one that claims to be complete.
     */
    public function pointsReport(string $period, ?string $type = null): array
    {
        $ofType = fn ($query) => $query->when(
            filled($type),
            fn ($q) => $q->whereExists(fn ($sub) => $sub
                ->selectRaw(1)
                ->from('tickets')
                ->whereColumn('tickets.id', 'point_transactions.ticket_id')
                ->where('tickets.type', $type))
        );

        $byPerson = PointTransaction::query()
            ->tap($ofType)
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

        // F06 role-assignment extension: same reasoning as employeeProfile()'s
        // $points above — role_id joins side in the grouping so a role-based
        // award gets its own row instead of collapsing into side = null.
        $bySide = PointTransaction::query()
            ->tap($ofType)
            ->selectRaw('side, role_id, SUM(points) total, COUNT(*) awards')
            ->forPeriod($period)
            ->groupBy('side', 'role_id')
            ->orderByDesc('total')
            ->with('role:id,name_ar')
            ->get();

        // Where the points came from, broken down by the ticket's type.
        // Left unfiltered on purpose even when $type is set: this table IS the
        // type breakdown, and filtering it would reduce it to the single row
        // the user already chose. It stays the map that shows where they are.
        $byType = PointTransaction::query()
            ->join('tickets', 'tickets.id', '=', 'point_transactions.ticket_id')
            ->selectRaw('tickets.type, SUM(point_transactions.points) total, COUNT(*) awards')
            ->selectRaw('COUNT(DISTINCT tickets.id) tickets')
            ->forPeriod($period)
            ->groupBy('tickets.type')
            ->orderByDesc('total')
            ->get();

        $topTickets = PointTransaction::query()
            ->tap($ofType)
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
            ->tap($ofType)
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
            'tickets' => (int) PointTransaction::query()->tap($ofType)->forPeriod($period)
                ->whereNotNull('ticket_id')->distinct()->count('ticket_id'),
            'correctionsTotal' => (float) ($corrections->total ?? 0),
            'correctionsCount' => (int) ($corrections->awards ?? 0),
            // Last month, so the headline number has something to mean.
            // Same filter as everything above — comparing one type's month
            // against last month's grand total would read as a collapse.
            'previous' => (float) PointTransaction::query()
                ->tap($ofType)
                ->forPeriod(\Carbon\CarbonImmutable::createFromFormat('Y-m', $period)
                    ->subMonth()->format('Y-m'))
                ->sum('points'),
            'type' => $type,
        ];
    }

    /**
     * ★ (2026-08-19) F18.3 — what the month's points are worth in money.
     *
     * One GROUP BY over (person, ticket type), multiplied by the type's rate on
     * the way out. The multiplication happens in PHP rather than in SQL on
     * purpose: the rate lives on ticket_types and is cached
     * (TicketTypeDefinition::map()), so joining it into the aggregate would add
     * a join to every row to fetch a handful of values the process already
     * holds. There are never more than a dozen types.
     *
     * Money is COMPUTED, never stored. point_transactions records points and
     * F18 forbids rewriting it, so the only place a rate can live is the type
     * row — which means repricing a type reprices its history too. That is what
     * a rate card is: the figure this screen shows is always "what this month
     * is worth at today's rates", not "what was promised in April".
     *
     * PENALTIES COUNT, and they count negative. A docked subtask is a negative
     * points row (F18.1), so it flows through the same multiplication and comes
     * out as money taken off. Filtering penalties out here would produce a
     * payout figure higher than the ledger behind it — the exact discrepancy
     * this screen exists to prevent.
     *
     * Rows whose ticket has no type, and manual corrections with no ticket at
     * all, are grouped under a null type and priced at zero. They are shown
     * rather than dropped: money that cannot be attributed to a rate is
     * information an admin needs, and silently omitting it would make the
     * columns fail to add up.
     *
     * `unpriced` counts TYPES that earned points this month and still have no
     * rate — not the points themselves. Summing the points was the first
     * version and it was wrong: penalties are negative, so a month with real
     * unpriced work could total to a negative figure, or to zero, and the
     * warning would either read as nonsense or vanish entirely at exactly the
     * moment it mattered. A count of types is never negative and is the number
     * the admin can actually act on — it is how many rows above need filling in.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, byType: Collection<int, array<string, mixed>>, total: float, unpriced: int}
     */
    public function moneyReport(string $period): array
    {
        $rates = collect(\App\Models\TicketTypeDefinition::map())
            ->map(fn ($t) => ['name' => $t->name_ar, 'rate' => (float) $t->point_value]);

        $grouped = PointTransaction::query()
            ->leftJoin('tickets', 'tickets.id', '=', 'point_transactions.ticket_id')
            ->join('users', 'users.id', '=', 'point_transactions.user_id')
            ->forPeriod($period)
            ->groupBy('point_transactions.user_id', 'users.name', 'tickets.type')
            ->orderBy('users.name')
            ->get([
                DB::raw('point_transactions.user_id AS user_id'),
                DB::raw('users.name AS user_name'),
                DB::raw('tickets.type AS ticket_type'),
                DB::raw('SUM(point_transactions.points) AS points'),
                DB::raw('COUNT(*) AS entries'),
            ]);

        $people = [];
        $byType = [];

        foreach ($grouped as $row) {
            $type = $row->ticket_type;
            $rate = $type !== null ? ($rates[$type]['rate'] ?? 0.0) : 0.0;
            $points = (float) $row->points;
            $money = $points * $rate;

            $people[$row->user_id] ??= [
                'user_id' => (int) $row->user_id,
                'name' => $row->user_name,
                'points' => 0.0,
                'money' => 0.0,
                'types' => [],
            ];

            $people[$row->user_id]['points'] += $points;
            $people[$row->user_id]['money'] += $money;
            $people[$row->user_id]['types'][] = [
                'type' => $type,
                'label' => $type !== null ? ($rates[$type]['name'] ?? $type) : 'غير منسوبة',
                'rate' => $rate,
                'points' => $points,
                'money' => $money,
                'entries' => (int) $row->entries,
            ];

            $key = $type ?? '—';
            $byType[$key] ??= [
                'label' => $type !== null ? ($rates[$type]['name'] ?? $type) : 'غير منسوبة',
                'rate' => $rate,
                'points' => 0.0,
                'money' => 0.0,
            ];
            $byType[$key]['points'] += $points;
            $byType[$key]['money'] += $money;

        }

        $rows = collect($people)->sortByDesc('money')->values();

        // Types that earned something this month and are still priced at zero.
        // Surfaced so a zero total on a busy month reads as "nobody set the
        // rates" rather than as "nobody worked".
        $unpriced = collect($byType)
            ->filter(fn (array $t) => $t['rate'] === 0.0 && $t['points'] != 0.0)
            ->count();

        return [
            'rows' => $rows,
            'byType' => collect($byType)->sortByDesc('money')->values(),
            'total' => (float) $rows->sum('money'),
            'unpriced' => $unpriced,
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
            ->when($filters['assignee'] ?? null, fn ($q, $v) => $q->whereHas('ticket', fn ($t) => $t->assignedTo((int) $v)))
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

    /**
     * F19.3 — open tickets per person. Role-based since the fixed columns were
     * dropped (2026-07-24): the per-side split (frontend/backend/devops) is now
     * a single "open assignments" count, since assignment is no longer keyed to
     * a fixed set of sides. `open_load` counts open assignment slots (holding two
     * roles on one ticket is two slots of work — rare, and reasonable to weigh).
     */
    public function teamLoad(): Collection
    {
        return User::query()
            ->select(['id', 'name', 'avatar_path', 'is_active'])
            ->without('role')
            ->withCount(['assignedTickets as open_load' => fn ($q) => $q
                ->whereNotIn('status', ['resolved', 'closed', 'rejected'])])
            ->active()
            ->get()
            ->filter(fn ($u) => $u->open_load > 0)
            ->sortByDesc(fn ($u) => $u->open_load)
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
            ->assignedTo($user->id)
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
                ->assignedTo($user->id)
                ->orWhere('created_by', $user->id))
            ->orderByDesc('resolved_at')
            ->limit(100)
            ->get();
    }
}
