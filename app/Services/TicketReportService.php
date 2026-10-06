<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketTypeDefinition;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Database aggregates for the management ticket reports.
 *
 * All ticket restrictions pass through Ticket::filter(). Reports deliberately
 * do not apply visibleTo(): access to these management totals is controlled by
 * the reports.view permission at the route/controller boundary.
 */
class TicketReportService
{
    /**
     * A ticket assigned to two users contributes once to each user's figures.
     * Multiple roles held by the same user on one ticket still contribute once.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function userPerformance(User $user, string $dateBasis, string $from, string $to, array $filters): array
    {
        $tickets = $this->filteredTickets($dateBasis, $from, $to, $filters);
        $completion = $this->latestResolvedHistory();
        $metrics = DB::query()->fromSub($tickets, 'tickets')
            ->leftJoinSub($completion, 'lrh', 'lrh.ticket_id', '=', 'tickets.id')
            ->select('tickets.*')
            ->selectRaw($this->deadlineSql('tickets') . ' deadline_at', [$this->displayTimezone()])
            ->selectRaw('COALESCE(lrh.completion_at, tickets.resolved_at) completion_at');
        $deadline = 't.deadline_at';
        $assigned = 'EXISTS (SELECT 1 FROM ticket_role_assignments ura WHERE ura.ticket_id = t.id AND ura.user_id = ?)';
        $open = 'EXISTS (SELECT 1 FROM ticket_statuses os WHERE os.`key` = t.status AND os.is_open = 1)';
        $reopened = "EXISTS (SELECT 1 FROM ticket_status_history rh WHERE rh.ticket_id = t.id AND rh.to_status = 'reopened')";
        $completed = 't.completion_at';
        $deadlineDay = $this->utcDaySql($deadline);
        $completedDay = $this->utcDaySql($completed);
        $eligible = "{$assigned} AND {$deadline} IS NOT NULL AND {$completed} IS NOT NULL";

        $row = DB::query()->fromSub($metrics, 't')
            ->selectRaw("COUNT(DISTINCT CASE WHEN t.created_by = ? THEN t.id END) created", [$user->id])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$assigned} THEN t.id END) assigned", [$user->id])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$assigned} AND t.resolved_at IS NOT NULL THEN t.id END) resolved", [$user->id])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$assigned} AND t.closed_at IS NOT NULL THEN t.id END) closed", [$user->id])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$assigned} AND {$reopened} THEN t.id END) reopened", [$user->id])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$assigned} AND {$open} THEN t.id END) currently_open", [$user->id])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$assigned} AND {$open} AND {$deadline} < UTC_TIMESTAMP() THEN t.id END) currently_overdue", [$user->id])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$eligible} AND {$completedDay} < {$deadlineDay} THEN t.id END) completed_early", [$user->id, $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$eligible} AND {$completedDay} = {$deadlineDay} THEN t.id END) completed_on_time", [$user->id, $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$eligible} AND {$completedDay} > {$deadlineDay} THEN t.id END) completed_late", [$user->id, $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("ROUND(100 * COUNT(DISTINCT CASE WHEN {$eligible} AND {$completedDay} <= {$deadlineDay} THEN t.id END) / NULLIF(COUNT(DISTINCT CASE WHEN {$eligible} THEN t.id END), 0), 2) on_time_percentage", [$user->id, $this->displayTimezone(), $this->displayTimezone(), $user->id])
            ->selectRaw("ROUND(100 * COUNT(DISTINCT CASE WHEN {$assigned} AND {$open} AND {$deadline} < UTC_TIMESTAMP() THEN t.id END) / NULLIF(COUNT(DISTINCT CASE WHEN {$assigned} AND {$open} THEN t.id END), 0), 2) overdue_percentage", [$user->id, $user->id])
            ->selectRaw("AVG(CASE WHEN {$assigned} AND t.reported_at IS NOT NULL AND t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.reported_at, t.resolved_at) / 3600 END) avg_resolution_hours", [$user->id])
            ->selectRaw("AVG(CASE WHEN {$assigned} AND t.resolved_at IS NOT NULL AND t.closed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.resolved_at, t.closed_at) / 3600 END) avg_closing_hours", [$user->id])
            ->selectRaw("AVG(CASE WHEN {$eligible} AND {$completed} > {$deadline} THEN TIMESTAMPDIFF(SECOND, {$deadline}, {$completed}) / 3600 END) avg_lateness_hours", [$user->id])
            ->selectRaw("AVG(CASE WHEN {$eligible} AND {$completed} < {$deadline} THEN TIMESTAMPDIFF(SECOND, {$completed}, {$deadline}) / 3600 END) avg_earliness_hours", [$user->id])
            ->first();

        return array_merge((array) $row, [
            'by_type' => $this->definitionBreakdown($tickets, $user->id, 'ticket_types', 'type'),
            'by_status' => $this->definitionBreakdown($tickets, $user->id, 'ticket_statuses', 'status'),
            'by_priority' => $this->definitionBreakdown($tickets, $user->id, 'priorities', 'priority'),
            'by_module' => $this->moduleBreakdown($tickets, $user->id),
        ]);
    }

    /**
     * One aggregate query regardless of the number of users or ticket types.
     * A ticket with two assigned users counts once for both users.
     *
     * @param  array<string, mixed>  $filters
     */
    public function usersComparison(string $dateBasis, string $from, string $to, array $filters): Collection
    {
        $types = TicketTypeDefinition::map();
        $tickets = $this->filteredTickets($dateBasis, $from, $to, $filters);
        $completion = $this->latestResolvedHistory();
        $deadline = $this->deadlineSql('t');
        $completed = 'COALESCE(lrh.completion_at, t.resolved_at)';
        $deadlineDay = $this->utcDaySql($deadline);
        $completedDay = $this->utcDaySql($completed);
        $open = 'EXISTS (SELECT 1 FROM ticket_statuses os WHERE os.`key` = t.status AND os.is_open = 1)';
        $eligible = "{$deadline} IS NOT NULL AND {$completed} IS NOT NULL";
        $assignments = DB::table('ticket_role_assignments')->select('ticket_id', 'user_id')->distinct();
        $createdCounts = DB::query()->fromSub(clone $tickets, 'created_tickets')
            ->selectRaw('created_by user_id, COUNT(*) ticket_count')
            ->whereNotNull('created_by')
            ->groupBy('created_by');

        $query = DB::query()->fromSub($tickets, 't')
            ->joinSub($assignments, 'a', 'a.ticket_id', '=', 't.id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoinSub($createdCounts, 'cc', 'cc.user_id', '=', 'a.user_id')
            ->leftJoinSub($completion, 'lrh', 'lrh.ticket_id', '=', 't.id')
            ->selectRaw('a.user_id, u.name')
            ->selectRaw('COALESCE(cc.ticket_count, 0) created')
            ->selectRaw('COUNT(DISTINCT t.id) assigned')
            ->selectRaw('COUNT(DISTINCT CASE WHEN t.resolved_at IS NOT NULL THEN t.id END) resolved')
            ->selectRaw('COUNT(DISTINCT CASE WHEN t.closed_at IS NOT NULL THEN t.id END) closed')
            ->selectRaw("COUNT(DISTINCT CASE WHEN EXISTS (SELECT 1 FROM ticket_status_history rh WHERE rh.ticket_id = t.id AND rh.to_status = 'reopened') THEN t.id END) reopened")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$open} THEN t.id END) currently_open")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$open} AND {$deadline} < UTC_TIMESTAMP() THEN t.id END) currently_overdue", [$this->displayTimezone()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$eligible} AND {$completedDay} < {$deadlineDay} THEN t.id END) completed_early", [$this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$eligible} AND {$completedDay} = {$deadlineDay} THEN t.id END) completed_on_time", [$this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$eligible} AND {$completedDay} > {$deadlineDay} THEN t.id END) completed_late", [$this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("ROUND(100 * COUNT(DISTINCT CASE WHEN {$eligible} AND {$completedDay} <= {$deadlineDay} THEN t.id END) / NULLIF(COUNT(DISTINCT CASE WHEN {$eligible} THEN t.id END), 0), 2) on_time_percentage", [$this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("ROUND(100 * COUNT(DISTINCT CASE WHEN {$open} AND {$deadline} < UTC_TIMESTAMP() THEN t.id END) / NULLIF(COUNT(DISTINCT CASE WHEN {$open} THEN t.id END), 0), 2) overdue_percentage", [$this->displayTimezone()])
            ->selectRaw('AVG(CASE WHEN t.reported_at IS NOT NULL AND t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.reported_at, t.resolved_at) / 3600 END) avg_resolution_hours')
            ->selectRaw('AVG(CASE WHEN t.resolved_at IS NOT NULL AND t.closed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.resolved_at, t.closed_at) / 3600 END) avg_closing_hours')
            ->selectRaw("AVG(CASE WHEN {$eligible} AND {$completed} > {$deadline} THEN TIMESTAMPDIFF(SECOND, {$deadline}, {$completed}) / 3600 END) avg_lateness_hours", [$this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("AVG(CASE WHEN {$eligible} AND {$completed} < {$deadline} THEN TIMESTAMPDIFF(SECOND, {$completed}, {$deadline}) / 3600 END) avg_earliness_hours", [$this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone()]);

        foreach (array_keys($types) as $index => $type) {
            $query->selectRaw("COUNT(DISTINCT CASE WHEN t.type = ? THEN t.id END) as type_{$index}", [$type]);
        }

        return $query->groupBy('a.user_id', 'u.name', 'cc.ticket_count')->orderBy('u.name')->get()
            ->map(function ($row) use ($types) {
                $counts = [];
                foreach (array_keys($types) as $index => $type) {
                    $counts[$type] = (int) $row->{"type_{$index}"};
                }
                $row->types = $counts;

                return $row;
            });
    }

    /** @param array<string, mixed> $filters */
    public function typeSummary(string $dateBasis, string $from, string $to, array $filters): Collection
    {
        return $this->definitionSummary($dateBasis, $from, $to, $filters, 'ticket_types', 'type');
    }

    /** @param array<string, mixed> $filters */
    public function statusSummary(string $dateBasis, string $from, string $to, array $filters): Collection
    {
        return $this->definitionSummary($dateBasis, $from, $to, $filters, 'ticket_statuses', 'status');
    }

    /** @param array<string, mixed> $filters */
    public function prioritySummary(string $dateBasis, string $from, string $to, array $filters): Collection
    {
        return $this->definitionSummary($dateBasis, $from, $to, $filters, 'priorities', 'priority');
    }

    /** @param array<string, mixed> $filters */
    public function moduleSummary(string $dateBasis, string $from, string $to, array $filters): Collection
    {
        $tickets = $this->filteredTickets($dateBasis, $from, $to, $filters);

        return DB::query()->fromSub($tickets, 't')
            ->leftJoin('ticket_statuses as s', 's.key', '=', 't.status')
            ->selectRaw("COALESCE(NULLIF(t.module, ''), '—') `key`, COALESCE(NULLIF(t.module, ''), '—') name_ar")
            ->selectRaw('COUNT(*) total')
            ->selectRaw('SUM(CASE WHEN s.is_open = 1 THEN 1 ELSE 0 END) open_count')
            ->selectRaw('SUM(CASE WHEN s.is_open = 0 AND t.resolved_at IS NOT NULL THEN 1 ELSE 0 END) resolved_count')
            ->groupBy('t.module')->orderBy('t.module')->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{totals: Collection, by_assignee: Collection, by_type: Collection, by_priority: Collection}
     */
    public function agingReport(string $dateBasis, string $from, string $to, array $filters): array
    {
        $tickets = $this->filteredTickets($dateBasis, $from, $to, $filters);
        $aged = DB::query()->fromSub($tickets, 't')
            ->join('ticket_statuses as s', 's.key', '=', 't.status')
            ->where('s.is_open', true)
            ->select('t.*')
            ->selectRaw($this->ageBucketSql() . ' age_bucket')
            ->selectRaw($this->overdueSql('t') . ' overdue', [$this->displayTimezone(), $this->displayTimezone()]);

        $aggregate = fn (Builder $query) => $query
            ->selectRaw('age_bucket, COUNT(DISTINCT id) ticket_count, SUM(overdue) overdue_count')
            ->groupBy('age_bucket')->orderByRaw($this->ageBucketOrderSql());

        $totals = $aggregate(DB::query()->fromSub(clone $aged, 'aged'))->get();
        $byAssignee = DB::query()->fromSub(clone $aged, 'aged')
            ->join('ticket_role_assignments as a', 'a.ticket_id', '=', 'aged.id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->selectRaw('age_bucket, a.user_id `key`, u.name, COUNT(DISTINCT aged.id) ticket_count, COUNT(DISTINCT CASE WHEN overdue = 1 THEN aged.id END) overdue_count')
            ->groupBy('age_bucket', 'a.user_id', 'u.name')->orderByRaw($this->ageBucketOrderSql())->orderBy('u.name')->get();
        $byType = $this->agedDimension($aged, 'type');
        $byPriority = $this->agedDimension($aged, 'priority');

        return compact('totals', 'byAssignee', 'byType', 'byPriority');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function deadlineReport(string $dateBasis, string $from, string $to, array $filters): array
    {
        $tickets = $this->filteredTickets($dateBasis, $from, $to, $filters);
        $completion = $this->latestResolvedHistory();
        $deadline = $this->deadlineSql('t');
        $completed = 'COALESCE(lrh.completion_at, t.resolved_at)';
        $deadlineDay = $this->utcDaySql($deadline);
        $completedDay = $this->utcDaySql($completed);
        $open = 'EXISTS (SELECT 1 FROM ticket_statuses os WHERE os.`key` = t.status AND os.is_open = 1)';

        $base = DB::query()->fromSub($tickets, 't')
            ->leftJoinSub($completion, 'lrh', 'lrh.ticket_id', '=', 't.id')
            ->whereRaw("{$deadline} IS NOT NULL", [$this->displayTimezone()]);

        $summary = (clone $base)
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$completedDay} < {$deadlineDay} THEN t.id END) completed_before", [$this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$completedDay} = {$deadlineDay} THEN t.id END) completed_on", [$this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$completedDay} > {$deadlineDay} THEN t.id END) completed_after", [$this->displayTimezone(), $this->displayTimezone(), $this->displayTimezone()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$open} AND {$deadline} < UTC_TIMESTAMP() THEN t.id END) currently_overdue", [$this->displayTimezone()])
            ->selectRaw("AVG(CASE WHEN {$completed} > {$deadline} THEN TIMESTAMPDIFF(SECOND, {$deadline}, {$completed}) / 3600 END) avg_lateness_hours", [$this->displayTimezone(), $this->displayTimezone()])
            ->first();

        $worst = (clone $base)
            ->whereRaw("{$open}")
            ->whereRaw("{$deadline} < UTC_TIMESTAMP()", [$this->displayTimezone()])
            ->select(['t.id', 't.ticket_number', 't.title', 't.type', 't.priority', 't.status', 't.module'])
            ->selectRaw("{$deadline} deadline_at", [$this->displayTimezone()])
            ->selectRaw("TIMESTAMPDIFF(SECOND, {$deadline}, UTC_TIMESTAMP()) / 3600 overdue_hours", [$this->displayTimezone()])
            ->orderByDesc('overdue_hours')->limit(20)->get();

        return ['summary' => $summary, 'worst_overdue' => $worst];
    }

    /** @param array<string, mixed> $filters */
    private function filteredTickets(string $dateBasis, string $from, string $to, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return Ticket::query()->select('tickets.*')->filter(array_merge($filters, [
            'date_basis' => $dateBasis,
            'from' => $from,
            'to' => $to,
        ]));
    }

    /** Latest resolved transition per ticket; resolved_at remains the fallback. */
    private function latestResolvedHistory(): Builder
    {
        return DB::table('ticket_status_history')
            ->selectRaw('ticket_id, MAX(created_at) completion_at')
            ->where('to_status', 'resolved')
            ->groupBy('ticket_id');
    }

    private function deadlineSql(string $alias): string
    {
        return "COALESCE(CONVERT_TZ(CONCAT({$alias}.due_date, ' 23:59:59'), ?, '+00:00'), {$alias}.sla_due_at)";
    }

    private function utcDaySql(string $expression): string
    {
        return "DATE(CONVERT_TZ({$expression}, '+00:00', ?))";
    }

    private function overdueSql(string $alias): string
    {
        return "CASE WHEN {$this->deadlineSql($alias)} IS NOT NULL AND {$this->deadlineSql($alias)} < UTC_TIMESTAMP() THEN 1 ELSE 0 END";
    }

    private function displayTimezone(): string
    {
        return (string) config('app.display_timezone');
    }

    private function definitionBreakdown($tickets, int $userId, string $table, string $column): Collection
    {
        return DB::table("{$table} as d")
            ->leftJoinSub(clone $tickets, 't', fn ($join) => $join->on("t.{$column}", '=', 'd.key'))
            ->leftJoin('ticket_role_assignments as a', function ($join) use ($userId) {
                $join->on('a.ticket_id', '=', 't.id')->where('a.user_id', '=', $userId);
            })
            ->selectRaw('d.`key`, d.name_ar, COUNT(DISTINCT CASE WHEN a.user_id IS NOT NULL THEN t.id END) ticket_count')
            ->groupBy('d.key', 'd.name_ar', 'd.position')->orderBy('d.position')->get();
    }

    private function moduleBreakdown($tickets, int $userId): Collection
    {
        return DB::query()->fromSub(clone $tickets, 't')
            ->join('ticket_role_assignments as a', function ($join) use ($userId) {
                $join->on('a.ticket_id', '=', 't.id')->where('a.user_id', '=', $userId);
            })
            ->selectRaw("COALESCE(NULLIF(t.module, ''), '—') `key`, COUNT(DISTINCT t.id) ticket_count")
            ->groupBy('t.module')->orderBy('t.module')->get();
    }

    /** @param array<string, mixed> $filters */
    private function definitionSummary(string $dateBasis, string $from, string $to, array $filters, string $table, string $column): Collection
    {
        $tickets = $this->filteredTickets($dateBasis, $from, $to, $filters);

        return DB::table("{$table} as d")
            ->leftJoinSub($tickets, 't', fn ($join) => $join->on("t.{$column}", '=', 'd.key'))
            ->leftJoin('ticket_statuses as s', 's.key', '=', 't.status')
            ->selectRaw('d.`key`, d.name_ar, COUNT(t.id) total')
            ->selectRaw('SUM(CASE WHEN s.is_open = 1 THEN 1 ELSE 0 END) open_count')
            ->selectRaw('SUM(CASE WHEN s.is_open = 0 AND t.resolved_at IS NOT NULL THEN 1 ELSE 0 END) resolved_count')
            ->groupBy('d.key', 'd.name_ar', 'd.position')->orderBy('d.position')->get();
    }

    private function ageBucketSql(): string
    {
        return "CASE
            WHEN TIMESTAMPDIFF(DAY, t.reported_at, UTC_TIMESTAMP()) <= 1 THEN '0-1'
            WHEN TIMESTAMPDIFF(DAY, t.reported_at, UTC_TIMESTAMP()) <= 3 THEN '2-3'
            WHEN TIMESTAMPDIFF(DAY, t.reported_at, UTC_TIMESTAMP()) <= 7 THEN '4-7'
            WHEN TIMESTAMPDIFF(DAY, t.reported_at, UTC_TIMESTAMP()) <= 14 THEN '8-14'
            WHEN TIMESTAMPDIFF(DAY, t.reported_at, UTC_TIMESTAMP()) <= 30 THEN '15-30'
            ELSE '30+'
        END";
    }

    private function ageBucketOrderSql(): string
    {
        return "FIELD(age_bucket, '0-1', '2-3', '4-7', '8-14', '15-30', '30+')";
    }

    private function agedDimension(Builder $aged, string $column): Collection
    {
        return DB::query()->fromSub(clone $aged, 'aged')
            ->selectRaw("age_bucket, {$column} `key`, COUNT(DISTINCT id) ticket_count, COUNT(DISTINCT CASE WHEN overdue = 1 THEN id END) overdue_count")
            ->groupBy('age_bucket', $column)->orderByRaw($this->ageBucketOrderSql())->orderBy($column)->get();
    }
}
