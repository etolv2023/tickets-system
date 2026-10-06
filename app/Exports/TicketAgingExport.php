<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketAgingExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable, SanitizesCells;

    public function __construct(private readonly array $report) {}
    public function collection(): Collection
    {
        $rows = collect();
        foreach (['totals' => 'الإجمالي', 'by_assignee' => 'المسؤول', 'by_type' => 'النوع', 'by_priority' => 'الأولوية'] as $key => $section) {
            $rows = $rows->concat(collect($this->report[$key])->map(fn ($row) => (object) [
                'section' => $section, 'bucket' => $row->age_bucket, 'item' => $row->name ?? $row->key ?? null,
                'count' => $row->ticket_count, 'overdue' => $row->overdue_count,
            ]));
        }
        return $rows;
    }
    public function headings(): array { return ['القسم', 'العمر بالأيام', 'البند', 'مفتوحة', 'منها متأخرة']; }
    public function map($row): array { return $this->sanitizeRow([$row->section, $row->bucket, $row->item, $row->count, $row->overdue]); }
}
