<?php

namespace App\Exports;

use App\Exports\Concerns\CachesSheets;
use App\Exports\Sheets\ArraySheet;
use App\Models\User;
use App\Services\TimeTrackingService;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * F09 — /my-timesheet for one week: the ticket × day grid, then the individual
 * entries behind it.
 *
 * The grid is the screen, but it is the entries that answer "what was that
 * 3.5 hours on Tuesday?" — a file with only the grid has to be taken on trust.
 */
class TimesheetExport implements WithMultipleSheets
{
    use Exportable, CachesSheets;

    public function __construct(
        private readonly User $user,
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
    ) {
    }

    /** @return array<int, ArraySheet> */
    protected function buildSheets(): array
    {
        $week = app(TimeTrackingService::class)->weekFor(
            $this->user->id,
            $this->from->toDateString(),
            $this->to->toDateString(),
        );

        return [$this->grid($week), $this->entries($week['entries'])];
    }

    /** @param array{entries: \Illuminate\Support\Collection, byDay: array<string, float>, total: float} $week */
    private function grid(array $week): ArraySheet
    {
        $days = collect(range(0, 6))->map(fn (int $i) => $this->from->addDays($i));

        // Same bucketing the screen does: one pass, then O(1) per cell.
        $cells = $week['entries']->groupBy(fn ($e) => $e->ticket_id . '|' . $e->spent_on->toDateString());

        $rows = $week['entries']->groupBy('ticket_id')->map(function ($group, $ticketId) use ($days, $cells) {
            $ticket = $group->first()->ticket;

            $row = [$ticket?->ticket_number, $ticket?->title];

            foreach ($days as $day) {
                $row[] = (float) ($cells[$ticketId . '|' . $day->toDateString()] ?? collect())->sum('hours');
            }

            $row[] = (float) $group->sum('hours');

            return $row;
        })->values()->all();

        $footer = ['', 'إجمالي اليوم'];

        foreach ($days as $day) {
            $footer[] = (float) ($week['byDay'][$day->toDateString()] ?? 0);
        }

        $footer[] = (float) $week['total'];
        $rows[] = $footer;

        return new ArraySheet(
            'الأسبوع',
            array_merge(
                ['رقم التذكرة', 'عنوان التذكرة'],
                $days->map(fn ($d) => $d->translatedFormat('D j M'))->all(),
                ['إجمالي'],
            ),
            $rows,
        );
    }

    private function entries(iterable $entries): ArraySheet
    {
        return new ArraySheet(
            'التسجيلات',
            ['التاريخ', 'رقم التذكرة', 'عنوان التذكرة', 'الصب تاسك', 'ملاحظة', 'ساعات'],
            collect($entries)->map(fn ($e) => [
                $e->spent_on->format('Y-m-d'),
                $e->ticket?->ticket_number,
                $e->ticket?->title,
                $e->subtask?->title,
                $e->note,
                (float) $e->hours,
            ])->all(),
        );
    }
}
