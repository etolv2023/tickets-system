<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** F19.2 — the month's leaderboard, for the bonus run. */
class PointsExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable, SanitizesCells;

    public function __construct(private readonly string $period)
    {
    }

    public function headings(): array
    {
        return ['#', 'الموظف', 'الدور', 'النقاط', 'عدد المرات', 'الشهر'];
    }

    public function array(): array
    {
        $rows = app(ReportService::class)->leaderboard($this->period);

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
