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
            'created' => __('أنشأها'), 'assigned' => __('مُسندة إليه'), 'resolved' => __('محلولة'),
            'closed' => __('مغلقة'), 'reopened' => __('أعيد فتحها'), 'currently_open' => __('مفتوحة حاليًا'),
            'currently_overdue' => __('متأخرة حاليًا'), 'completed_early' => __('اكتملت مبكرًا'),
            'completed_on_time' => __('اكتملت في الموعد'), 'completed_late' => __('اكتملت متأخرًا'),
            'on_time_percentage' => __('الالتزام بالموعد %'), 'overdue_percentage' => __('نسبة المتأخر %'),
            'avg_resolution_hours' => __('متوسط الحل (س)'), 'avg_closing_hours' => __('متوسط الإغلاق (س)'),
            'avg_lateness_hours' => __('متوسط التأخير (س)'), 'avg_earliness_hours' => __('متوسط التبكير (س)'),
        ];
        $rows = collect($labels)->map(fn ($label, $key) => (object) ['section' => __('الإجمالي'), 'item' => $label, 'value' => $this->report[$key] ?? null]);
        foreach (['by_type' => __('النوع'), 'by_status' => __('الحالة'), 'by_priority' => __('الأولوية'), 'by_module' => __('الوحدة')] as $key => $section) {
            $rows = $rows->concat(collect($this->report[$key])->map(fn ($row) => (object) [
                'section' => $section, 'item' => $row->name ?? $row->key, 'value' => $row->ticket_count,
            ]));
        }

        return $rows;
    }

    public function headings(): array { return [__('القسم'), __('البند'), __('القيمة')]; }

    public function map($row): array { return $this->sanitizeRow([$row->section, $row->item, $row->value]); }
}
