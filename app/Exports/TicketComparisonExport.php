<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class TicketComparisonExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    use Exportable, SanitizesCells;

    public function __construct(private readonly Collection $rows) {}
    public function collection(): Collection { return $this->rows; }
    public function title(): string { return __('مقارنة أداء الموظفين'); }

    public function headings(): array { return [__('الموظف'), __('أنشأها'), __('المسند'), __('المحلول'), __('المغلق'), __('أعيد فتحها'), __('المفتوح'), __('المتأخر'), __('الالتزام %'), __('متوسط الحل (س)')]; }
    public function map($row): array
    {
        return $this->sanitizeRow([$row->name, $row->created, $row->assigned, $row->resolved, $row->closed,
            $row->reopened, $row->currently_open, $row->currently_overdue, $row->on_time_percentage, $row->avg_resolution_hours]);
    }
}
