<?php

namespace App\Services;

use App\Casts\TicketTypeValue;
use App\Models\PointTransaction;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\TicketTypeDefinition;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\DateBounds;
use Illuminate\Database\Eloquent\Builder;
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
            ->whereNull('tickets.deleted_at')
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
                ->whereNull('tickets.deleted_at')
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
            ->whereNull('tickets.deleted_at')
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
            ->whereHas('ticket')
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
                ->whereNotNull('ticket_id')->whereHas('ticket')->distinct()->count('ticket_id'),
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
            ->where(fn ($query) => $query
                ->whereNull('point_transactions.ticket_id')
                ->orWhereNull('tickets.deleted_at'))
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

    /**
     * ★ (2026-10-05) The /reports cards used to answer for the whole system
     * only; now each one can be narrowed to a customer, a type, a priority or
     * a person — and every card on the screen narrows TOGETHER, or two numbers
     * on one page would stop agreeing (the same rule F19.4 set for the points
     * report). One definition of the constraint, applied to every query that
     * takes it.
     *
     * The person is "holds a role on the ticket", the same reading teamLoad()
     * and the employee profile use.
     *
     * @param  array<string, mixed>  $filters  company, type, priority, person
     */
    private function constrain(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['company'] ?? null, fn (Builder $q, $v) => $q->where('tickets.company_id', (int) $v))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->where('tickets.type', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('tickets.priority', $v))
            ->when($filters['person'] ?? null, fn (Builder $q, $v) => $q->assignedTo((int) $v));
    }

    /**
     * F19.3 — how the work splits.
     *
     * @param  array<string, mixed>  $filters  see constrain()
     */
    public function ticketDistribution(string $from, string $to, array $filters = []): Collection
    {
        return $this->constrain(Ticket::query(), $filters)
            ->selectRaw('type, status, COUNT(*) n')
            ->whereBetween('reported_at', [$from, $to])
            ->groupBy('type', 'status')
            ->get()
            ->groupBy('type')
            ->map(function (Collection $rows, string $type) {
                $total = (int) $rows->sum('n');
                $done = (int) $rows->whereIn('status', TicketStatusDefinition::resolvedKeys())->sum('n');
                $open = (int) $rows->whereIn('status', TicketStatusDefinition::openKeys())->sum('n');

                return (object) [
                    'type' => TicketTypeValue::for($type),
                    'total' => $total,
                    'done' => $done,
                    'open' => $open,
                ];
            })
            ->values();
    }

    /**
     * F19.3 — which customer sends the most.
     *
     * @param  array<string, mixed>  $filters  see constrain()
     */
    public function companyPerformance(string $from, string $to, array $filters = []): Collection
    {
        $resolved = TicketStatusDefinition::resolvedKeys();
        $placeholders = implode(', ', array_fill(0, count($resolved), '?'));

        return $this->constrain(Ticket::query(), $filters)
            ->selectRaw('company_id, COUNT(*) total')
            ->selectRaw("SUM(status IN ({$placeholders})) resolved", $resolved)
            ->selectRaw('AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, reported_at, resolved_at) END) avg_hours')
            ->whereBetween('reported_at', [$from, $to])
            ->groupBy('company_id')
            ->orderByDesc('total')
            // Grouped rows, not tickets: there is no single requester here.
            ->with('company:id,name')
            ->get();
    }

    /**
     * F19.3 — resolution time by priority and by type.
     *
     * @param  array<string, mixed>  $filters  see constrain()
     */
    public function resolutionTimes(string $from, string $to, array $filters = []): array
    {
        $shape = fn (string $column) => $this->constrain(Ticket::query(), $filters)
            ->selectRaw("{$column} k, COUNT(*) n")
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, reported_at, resolved_at)) avg_hours')
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->groupBy($column)
            ->get();

        return ['byPriority' => $shape('priority'), 'byType' => $shape('type')];
    }

    /**
     * F19.3 — who is over their SLA.
     *
     * @param  array<string, mixed>  $filters  see constrain()
     */
    public function slaBreaches(string $from, string $to, array $filters = []): Collection
    {
        return $this->constrain(Ticket::query(), $filters)
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'priority', 'status', 'sla_due_at', 'resolved_at'])
            ->with('company:id,name', 'requester:id,name')
            ->whereBetween('reported_at', [$from, $to])
            ->slaBreached()
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
                ->whereIn('status', TicketStatusDefinition::openKeys())])
            ->active()
            ->get()
            ->filter(fn ($u) => $u->open_load > 0)
            ->sortByDesc(fn ($u) => $u->open_load)
            ->values();
    }

    /** F19.3 — estimated vs actual per person. */
    public function timeReport(string $from, string $to): Collection
    {
        $time = DB::table('time_entries')
            ->join('tickets', 'tickets.id', '=', 'time_entries.ticket_id')
            ->selectRaw('time_entries.user_id, SUM(time_entries.hours) logged, COUNT(DISTINCT time_entries.ticket_id) tickets')
            ->whereNull('tickets.deleted_at')
            ->whereBetween('time_entries.spent_on', [$from, $to])
            ->groupBy('time_entries.user_id');

        $accuracy = DB::table('ticket_subtasks')
            ->selectRaw('assignee_id AS user_id, AVG(estimated_hours / spent_hours) accuracy')
            ->where('status', 'done')
            ->whereNull('deleted_at')
            ->whereNotNull('estimated_hours')
            ->where('estimated_hours', '>', 0)
            ->where('spent_hours', '>', 0)
            ->groupBy('assignee_id');

        return User::query()
            ->without('role')
            ->joinSub($time, 'time_report', 'time_report.user_id', '=', 'users.id')
            ->leftJoinSub($accuracy, 'estimate_accuracy', 'estimate_accuracy.user_id', '=', 'users.id')
            ->select('users.*', 'time_report.logged', 'time_report.tickets', 'estimate_accuracy.accuracy')
            ->orderByDesc('time_report.logged')
            ->get()
            ->map(function (User $user) {

                return (object) [
                    'user' => $user,
                    'logged' => (float) $user->logged,
                    'tickets' => (int) $user->tickets,
                    'accuracy' => $user->accuracy === null ? null : round((float) $user->accuracy, 2),
                ];
            });
    }

    /**
     * ★ (2026-10-05) F19.5 — who resolved how many of what.
     *
     * The employee profile already says "كام بج حل" for one person and one
     * month; this is the same question asked across the team, over any date
     * range, with the answer laid out as a matrix (people down, types across)
     * — the shape a manager compares with, rather than one card per person.
     *
     * "Resolved" means: status is resolved or closed today, AND resolved_at
     * falls in the range. The first half matters — a reopened ticket keeps its
     * old resolved_at, and counting it would pay a fix that was sent back.
     *
     * The person's attachment to the ticket is a choice (Ticket::RELATIONS,
     * minus 'any'): holding a role on it is the default and is what "حل" means
     * for a developer; opening it is what it means for support; owning a
     * subtask on it catches work done on a ticket assigned to somebody else.
     * With a person chosen the same question is asked of one row, and the
     * tickets behind the numbers are listed so a figure can be checked rather
     * than believed.
     *
     * Three aggregate queries at most, never a loop over models (§ 4). The
     * narrowing (dates, type, company, priority) is one Ticket::filter() call
     * reused as an id subquery, so the matrix and the list cannot drift.
     *
     * @param  array<string, mixed>  $filters  from, to, person, relation, type, company, priority
     * @param  bool  $paginate  false for the export, which carries the whole range on one tab
     * @return array<string, mixed>
     */
    public function resolvedByType(array $filters, bool $paginate = true): array
    {
        $relation = in_array($filters['relation'] ?? null, ['assigned', 'created', 'subtask'], true)
            ? $filters['relation']
            : 'assigned';

        $types = array_filter(
            TicketTypeDefinition::options(),
            fn (string $key) => $key !== 'undefined',
            ARRAY_FILTER_USE_KEY,
        );

        $scope = fn () => Ticket::query()->filter([
            'status' => 'resolved',
            'date_basis' => 'resolved_at',
            'from' => $filters['from'],
            'to' => $filters['to'],
            'type' => $filters['type'] ?? null,
            'company' => $filters['company'] ?? null,
            'priority' => $filters['priority'] ?? null,
        ]);

        $person = (int) ($filters['person'] ?? 0) ?: null;

        if ($person !== null) {
            $byType = $scope()->involving($person, $relation)
                ->selectRaw('type, COUNT(*) n, AVG(TIMESTAMPDIFF(HOUR, reported_at, resolved_at)) avg_hours')
                ->groupBy('type')
                ->get()
                ->keyBy('type');

            $tickets = $scope()->involving($person, $relation)
                ->select(['id', 'ticket_number', 'title', 'type', 'priority', 'status', 'company_id', 'requested_by', 'reported_at', 'sla_due_at', 'due_date', 'resolved_at'])
                ->with('company:id,name', 'requester:id,name')
                ->orderByDesc('resolved_at');

            $tickets = $paginate ? $tickets->paginate(25)->withQueryString() : $tickets->get();

            $total = (int) $byType->sum('n');

            return [
                'relation' => $relation,
                'types' => $types,
                'byType' => $byType,
                'total' => $total,
                // Weighted across the types, so it is the person's real average
                // rather than the average of five averages.
                'avgHours' => $total > 0
                    ? round($byType->sum(fn ($r) => (float) $r->avg_hours * (int) $r->n) / $total)
                    : null,
                'lateCount' => $scope()->involving($person, $relation)->late()->count(),
                'tickets' => $tickets,
                'rows' => collect(),
                'totals' => [],
            ];
        }

        $ids = $scope()->select('tickets.id');

        // The join decides whose ticket it is. COUNT(DISTINCT) on the subtask
        // path: three subtasks on one ticket are still one ticket resolved.
        $counts = match ($relation) {
            'created' => DB::table('tickets')
                ->selectRaw('created_by AS user_id, type, COUNT(*) n')
                ->whereNull('deleted_at')
                ->whereIn('id', $ids)
                ->groupBy('created_by', 'type'),
            'subtask' => DB::table('ticket_subtasks AS s')
                ->join('tickets', 'tickets.id', '=', 's.ticket_id')
                ->selectRaw('s.assignee_id AS user_id, tickets.type, COUNT(DISTINCT tickets.id) n')
                ->whereNull('s.deleted_at')
                ->whereNull('tickets.deleted_at')
                ->whereNotNull('s.assignee_id')
                ->whereIn('tickets.id', $ids)
                ->groupBy('s.assignee_id', 'tickets.type'),
            default => DB::table('ticket_role_assignments AS tra')
                ->join('tickets', 'tickets.id', '=', 'tra.ticket_id')
                ->selectRaw('tra.user_id, tickets.type, COUNT(DISTINCT tickets.id) n')
                ->whereNull('tickets.deleted_at')
                ->whereIn('tickets.id', $ids)
                ->groupBy('tra.user_id', 'tickets.type'),
        };

        $counts = $counts->get();

        $users = User::query()
            ->whereIn('id', $counts->pluck('user_id')->unique())
            ->get(['id', 'name', 'avatar_path', 'is_active', 'role_id'])
            ->keyBy('id');

        $rows = $counts->groupBy('user_id')
            ->map(function (Collection $group, int $userId) use ($users) {
                $perType = $group->pluck('n', 'type')->map(fn ($n) => (int) $n)->all();

                return (object) [
                    'user' => $users[$userId] ?? null,
                    'counts' => $perType,
                    'total' => array_sum($perType),
                ];
            })
            ->filter(fn ($row) => $row->user !== null)
            ->sortByDesc('total')
            ->values();

        $totals = [];

        foreach (array_keys($types) as $key) {
            $totals[$key] = (int) $counts->where('type', $key)->sum('n');
        }

        return [
            'relation' => $relation,
            'types' => $types,
            'rows' => $rows,
            'totals' => $totals,
            // Distinct tickets, not the sum of a matrix that counts a ticket
            // once per person on it.
            'total' => $scope()->count(),
            // Resolved after their SLA or delivery date — Ticket::scopeLate.
            'lateCount' => $scope()->late()->count(),
            'avgHours' => null,
            'byType' => collect(),
            'tickets' => null,
        ];
    }

    /**
     * The month picker's options, newest first.
     *
     * The first entry is the sentinel `current` rather than a literal month.
     * Anything that remembers a filter bar — sticky-filters.js keeps the
     * board's in localStorage — would otherwise store "2026-09" and go on
     * opening September once the calendar has moved on. `current` is resolved
     * at render time, so the default state can never go stale.
     *
     * @return array<string, string>
     */
    public function monthOptions(): array
    {
        $months = ['current' => 'الشهر الحالي'];

        for ($i = 0; $i < 12; $i++) {
            $month = \Carbon\CarbonImmutable::now()->subMonths($i);
            $months[$month->format('Y-m')] = $month->translatedFormat('F Y');
        }

        return $months;
    }

    /**
     * A `period` query parameter as an actual `Y-m` month.
     *
     * `current` and anything malformed both land on the running month, so a
     * hand-edited url degrades to the default rather than to an exception.
     */
    public function resolvePeriod(?string $period): string
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $period) === 1
            ? (string) $period
            : \Carbon\CarbonImmutable::now()->format('Y-m');
    }

    /**
     * UTC bounds of the display-timezone month — see DateBounds::month().
     *
     * @return array{0: string, 1: string}
     */
    public function periodBounds(string $period): array
    {
        return DateBounds::month($period);
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
