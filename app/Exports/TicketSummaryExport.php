<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketSummaryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable, SanitizesCells;

    public function __construct(private readonly Collection $rows) {}
    public function collection(): Collection { return $this->rows; }
    public function headings(): array { return [__('البند'), __('الإجمالي'), __('المفتوح'), __('المحلول')]; }
    public function map($row): array { return $this->sanitizeRow([$row->name ?? $row->key, $row->total, $row->open_count, $row->resolved_count]); }
}
