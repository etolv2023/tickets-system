<?php

namespace App\Http\Controllers;

use App\Enums\SubtaskSide;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\User;
use App\Services\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F13 — month / week / day / timeline. Built with CSS Grid: a calendar library
 * is 200KB for something we can draw ourselves (PLAN.md § 8).
 */
class CalendarController extends Controller
{
    public function __construct(private readonly CalendarService $calendar)
    {
    }

    /** Only this person's work. */
    public function mine(Request $request): View
    {
        return $this->render($request, $request->user()->id, 'كاليندري');
    }

    /** F13 — the whole team's. */
    public function team(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        return $this->render($request, null, 'كاليندر التيم');
    }

    private function render(Request $request, ?int $onlyUserId, string $title): View
    {
        $view = in_array($request->query('view'), ['month', 'week', 'day', 'timeline'], true)
            ? $request->query('view')
            : 'month';

        $anchor = $request->query('date')
            ? CarbonImmutable::parse($request->query('date'))
            : CarbonImmutable::now();

        [$from, $to] = $this->window($view, $anchor);

        $filters = $request->only('assignee', 'company', 'type', 'priority', 'side', 'show', 'status');
        $items = $this->calendar->itemsBetween($from, $to, $filters, $onlyUserId);

        $users = User::active()->without('role')->get(['id', 'name', 'avatar_path', 'is_active', 'daily_capacity_hours'])->keyBy('id');

        return view('calendar.index', [
            'title' => $title,
            'view' => $view,
            'anchor' => $anchor,
            'from' => $from,
            'to' => $to,
            'days' => $view === 'month' ? $this->calendar->monthGrid($anchor) : $this->daysBetween($from, $to),
            'weekdays' => $this->calendar->weekdayNames(),
            'items' => $items,
            'capacity' => $this->calendar->capacityMap($items['subtasks'], $items['leaves'], $users),
            'users' => $users,
            'holidays' => Holiday::map(),
            'filters' => $filters,
            // Only the names of what is currently selected, so the filter
            // boxes can show them. The lists themselves come from /lookup.
            'selectedCompany' => filled($filters['company'] ?? null)
                ? Company::whereKey($filters['company'])->value('name')
                : null,
            'selectedAssignee' => filled($filters['assignee'] ?? null)
                ? User::whereKey($filters['assignee'])->value('name')
                : null,
            'sides' => SubtaskSide::options(),
            'isTeam' => $onlyUserId === null,
            'routeName' => $onlyUserId === null ? 'calendar.team' : 'calendar.mine',
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function window(string $view, CarbonImmutable $anchor): array
    {
        $weekStart = (int) \App\Models\Setting::get('week_start_day', 6);

        return match ($view) {
            'day' => [$anchor->startOfDay(), $anchor->endOfDay()],
            'week' => [$anchor->startOfWeek($weekStart), $anchor->startOfWeek($weekStart)->addDays(6)->endOfDay()],
            // Timeline spans a month too, but renders as rows per person.
            default => [$anchor->startOfMonth()->startOfWeek($weekStart), $anchor->endOfMonth()->endOfWeek(($weekStart + 6) % 7)],
        };
    }

    /** @return array<int, CarbonImmutable> */
    private function daysBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];

        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }
}
