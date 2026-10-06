<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketComparisonExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable, SanitizesCells;

    public function __construct(private readonly Collection $rows) {}
    public function collection(): Collection { return $this->rows; }
    public function headings(): array { return ['الموظف', 'فتحها', 'المسند', 'المحلول', 'المغلق', 'أعيد فتحها', 'المفتوح', 'المتأخر', 'الالتزام %', 'متوسط الحل (س)']; }
    public function map($row): array
    {
        return $this->sanitizeRow([$row->name, $row->created, $row->assigned, $row->resolved, $row->closed,
            $row->reopened, $row->currently_open, $row->currently_overdue, $row->on_time_percentage, $row->avg_resolution_hours]);
    }
}
