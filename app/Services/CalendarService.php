<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Models\User;
use App\Models\UserLeave;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the calendar draws (F13) and the capacity it reports (F14).
 *
 * The whole point of F14 is that a calendar which ignores leave and holidays
 * lies. So capacity here is: zero if the person is off or the office is shut,
 * otherwise their daily hours — and the load is the estimate of what's due.
 */
class CalendarService
{
    /**
     * Everything visible in a date window, in one pass.
     *
     * @param  array<string, mixed>  $filters
     * @return array{subtasks: Collection, tickets: Collection, slas: Collection, leaves: Collection}
     */
    public function itemsBetween(CarbonImmutable $from, CarbonImmutable $to, array $filters = [], ?int $onlyUserId = null): array
    {
        // "show" narrows the calendar to one kind of thing. A month with every
        // subtask, every ticket deadline and every SLA marker on it is a wall;
        // being able to ask for just one of them is what makes it readable.
        $show = $filters['show'] ?? 'all';
        $wantSubtasks = $show === 'all' || $show === 'subtasks';
        $wantTickets = $show === 'all' || $show === 'tickets';

        $subtasks = ! $wantSubtasks ? collect() : TicketSubtask::query()
            ->select(['id', 'ticket_id', 'title', 'assignee_id', 'side', 'status', 'due_date', 'estimated_hours'])
            // The assignee's initials sit on every chip, so the relation has
            // to come with the query — under preventLazyLoading a missing one
            // is a 500, not a slow page.
            ->with([
                'assignee:id,name,avatar_path,is_active',
                'ticket:id,ticket_number,title,priority,company_id,requested_by,type',
                'ticket.company:id,name',
                'ticket.requester:id,name',
            ])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->when($onlyUserId, fn ($q) => $q->where('assignee_id', $onlyUserId))
            ->when($filters['assignee'] ?? null, fn ($q, $v) => $q->where('assignee_id', $v))
            // Categorised by role now (2026-07-24), not the hardcoded side.
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->where('role_id', $v))
            ->when(
                ($filters['company'] ?? null) || ($filters['type'] ?? null) || ($filters['priority'] ?? null) || ($filters['status'] ?? null),
                fn ($q) => $q->whereHas('ticket', fn ($t) => $t
                    ->when($filters['company'] ?? null, fn ($w, $v) => $w->where('company_id', $v))
                    ->when($filters['type'] ?? null, fn ($w, $v) => $w->where('type', $v))
                    ->when($filters['priority'] ?? null, fn ($w, $v) => $w->where('priority', $v))
                    // A subtask follows its ticket's status — filter by the parent.
                    ->when($filters['status'] ?? null, fn ($w, $v) => $w->where('status', $v)))
            )
            ->get();

        $tickets = ! $wantTickets ? collect() : Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'priority', 'due_date', 'company_id', 'requested_by', 'status'])
            ->with('company:id,name', 'requester:id,name')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['company'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($onlyUserId, fn ($q) => $q->assignedTo((int) $onlyUserId))
            ->get();

        // SLA deadlines are drawn differently from work — they're a promise, not
        // a task, and F13 asks for a red marker rather than a block.
        // An SLA marker is a ticket fact, so it follows the ticket toggle.
        $slas = ! $wantTickets ? collect() : Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'priority', 'sla_due_at', 'company_id', 'requested_by', 'status'])
            ->with('company:id,name', 'requester:id,name')
            ->whereNotNull('sla_due_at')
            ->whereBetween('sla_due_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['company'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($onlyUserId, fn ($q) => $q->assignedTo((int) $onlyUserId))
            ->get();

        $leaves = UserLeave::query()
            ->with('user:id,name,avatar_path,is_active')
            ->overlapping($from->toDateString(), $to->toDateString())
            ->when($onlyUserId, fn ($q) => $q->where('user_id', $onlyUserId))
            ->get();

        return compact('subtasks', 'tickets', 'slas', 'leaves');
    }

    /**
     * Load vs capacity per person per day (F14).
     *
     * @param  Collection<int, TicketSubtask>  $subtasks
     * @param  Collection<int, UserLeave>  $leaves
     * @return array<string, array{load: float, capacity: float, state: string}>  keyed "userId|Y-m-d"
     */
    public function capacityMap(Collection $subtasks, Collection $leaves, Collection $users): array
    {
        $map = [];

        foreach ($subtasks as $subtask) {
            if ($subtask->assignee_id === null || $subtask->status->isDone()) {
                continue;
            }

            $key = $subtask->assignee_id . '|' . $subtask->due_date->toDateString();
            $map[$key]['load'] = ($map[$key]['load'] ?? 0) + (float) $subtask->estimated_hours;
        }

        foreach ($map as $key => $entry) {
            [$userId, $date] = explode('|', $key);
            $day = CarbonImmutable::parse($date);
            $user = $users->get((int) $userId);

            $capacity = $this->capacityFor($user, $day, $leaves);
            $load = $entry['load'];

            $map[$key] = [
                'load' => $load,
                'capacity' => $capacity,
                'state' => $this->capacityState($load, $capacity),
            ];
        }

        return $map;
    }

    /**
     * F14: someone on leave has zero capacity, and so does a day the office is
     * shut. Anything still due on them that day then reads as over capacity —
     * which is exactly the signal the screen exists to give.
     */
    public function capacityFor(?User $user, CarbonImmutable $day, Collection $leaves): float
    {
        if ($user === null) {
            return 0.0;
        }

        if (! $this->isWorkingDay($day)) {
            return 0.0;
        }

        $onLeave = $leaves->contains(
            fn (UserLeave $l) => $l->user_id === $user->id && $l->covers($day)
        );

        return $onLeave ? 0.0 : (float) $user->daily_capacity_hours;
    }

    public function isWorkingDay(CarbonImmutable $day): bool
    {
        if (Holiday::nameFor($day) !== null) {
            return false;
        }

        $workDays = (array) Setting::get('work_days', [0, 1, 2, 3, 4]);

        return in_array($day->dayOfWeek, array_map('intval', $workDays), true);
    }

    /** F14: at the limit grey, near it amber, over it red. */
    private function capacityState(float $load, float $capacity): string
    {
        if ($capacity <= 0) {
            return $load > 0 ? 'over' : 'off';
        }

        return match (true) {
            $load > $capacity => 'over',
            $load >= $capacity * 0.85 => 'near',
            default => 'at',
        };
    }

    /**
     * The grid for a month view: whole weeks, starting on the configured day
     * (F13 — the week starts Saturday by default).
     *
     * @return array<int, CarbonImmutable>
     */
    public function monthGrid(CarbonImmutable $anchor): array
    {
        $weekStart = (int) Setting::get('week_start_day', 6);

        $first = $anchor->startOfMonth()->startOfWeek($weekStart);
        $last = $anchor->endOfMonth()->endOfWeek(($weekStart + 6) % 7);

        $days = [];

        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    /** @return array<int, string> Arabic weekday names, in the configured order. */
    public function weekdayNames(): array
    {
        $weekStart = (int) Setting::get('week_start_day', 6);
        $names = [];

        for ($i = 0; $i < 7; $i++) {
            $names[] = CarbonImmutable::now()->startOfWeek($weekStart)->addDays($i)->translatedFormat('l');
        }

        return $names;
    }
}
