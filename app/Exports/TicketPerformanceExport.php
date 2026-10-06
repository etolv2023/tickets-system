<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketPerformanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable, SanitizesCells;

    public function __construct(private readonly array $report) {}

    public function collection(): Collection
    {
        $labels = [
            'created' => 'فتحها', 'assigned' => 'مسندة له', 'resolved' => 'محلولة',
            'closed' => 'مغلقة', 'reopened' => 'أعيد فتحها', 'currently_open' => 'مفتوحة حاليًا',
            'currently_overdue' => 'متأخرة حاليًا', 'completed_early' => 'اكتملت مبكرًا',
            'completed_on_time' => 'اكتملت في الموعد', 'completed_late' => 'اكتملت متأخرًا',
            'on_time_percentage' => 'الالتزام بالموعد %', 'overdue_percentage' => 'نسبة المتأخر %',
            'avg_resolution_hours' => 'متوسط الحل (س)', 'avg_closing_hours' => 'متوسط الإغلاق (س)',
            'avg_lateness_hours' => 'متوسط التأخير (س)', 'avg_earliness_hours' => 'متوسط التبكير (س)',
        ];
        $rows = collect($labels)->map(fn ($label, $key) => (object) ['section' => 'الإجمالي', 'item' => $label, 'value' => $this->report[$key] ?? null]);
        foreach (['by_type' => 'النوع', 'by_status' => 'الحالة', 'by_priority' => 'الأولوية', 'by_module' => 'الموديول'] as $key => $section) {
            $rows = $rows->concat(collect($this->report[$key])->map(fn ($row) => (object) [
                'section' => $section, 'item' => $row->name_ar ?? $row->key, 'value' => $row->ticket_count,
            ]));
        }

        return $rows;
    }

    public function headings(): array { return ['القسم', 'البند', 'القيمة']; }

    public function map($row): array { return $this->sanitizeRow([$row->section, $row->item, $row->value]); }
}
