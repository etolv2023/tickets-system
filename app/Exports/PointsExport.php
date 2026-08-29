<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * F19.2 — the month's leaderboard, for the bonus run.
 *
 * Takes the screen's person/assignee filters, not just the month: the board
 * grew those two filters and an export that quietly ignored them would hand
 * back a different ranking from the one on screen.
 */
class PointsExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable, SanitizesCells;

    /** @param array<string, mixed> $filters */
    public function __construct(
        private readonly string $period,
        private readonly array $filters = [],
    ) {
    }

    public function headings(): array
    {
        return ['#', 'الموظف', 'الدور', 'النقاط', 'عدد المرات', 'الشهر'];
    }

    public function array(): array
    {
        $rows = app(ReportService::class)->leaderboard($this->period, $this->filters);

        return $rows->values()->map(fn ($row, $i) => $this->sanitizeRow([
            $i + 1,
            $row->user?->name ?? '—',
            $row->user?->role?->name_ar ?? '—',
            (float) $row->total,
            $row->awards,
            $this->period,
        ]))->all();
    }

    public function title(): string
    {
        return 'النقاط ' . $this->period;
    }
}
