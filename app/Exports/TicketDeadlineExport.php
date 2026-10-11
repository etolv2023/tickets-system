<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketDeadlineExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable, SanitizesCells;

    public function __construct(private readonly array $report) {}
    public function collection(): Collection
    {
        $summary = $this->report['summary'];
        $rows = collect([
            (object) ['section' => __('الملخص'), 'title' => __('اكتملت مبكرًا'), 'value' => $summary->completed_before],
            (object) ['section' => __('الملخص'), 'title' => __('اكتملت في الموعد'), 'value' => $summary->completed_on],
            (object) ['section' => __('الملخص'), 'title' => __('اكتملت متأخرًا'), 'value' => $summary->completed_after],
            (object) ['section' => __('الملخص'), 'title' => __('متأخرة حاليًا'), 'value' => $summary->currently_overdue],
            (object) ['section' => __('الملخص'), 'title' => __('متوسط التأخير (س)'), 'value' => $summary->avg_lateness_hours],
        ]);

        return $rows->concat($this->report['worst_overdue']->map(function ($row) {
            $row->section = __('الأكثر تأخيرًا');
            return $row;
        }));
    }
    public function headings(): array { return [__('القسم'), __('رقم التذكرة'), __('العنوان / البند'), __('النوع'), __('الأولوية'), __('الحالة'), __('الوحدة'), __('الموعد'), __('ساعات التأخير / القيمة')]; }
    public function map($row): array
    {
        $deadline = ($row->deadline_at ?? null)
            ? CarbonImmutable::parse($row->deadline_at, 'UTC')->setTimezone(config('app.display_timezone'))->format('Y-m-d H:i')
            : null;
        return $this->sanitizeRow([$row->section, $row->ticket_number ?? null, $row->title, $row->type ?? null,
            $row->priority ?? null, $row->status ?? null, $row->module ?? null, $deadline, $row->overdue_hours ?? $row->value ?? null]);
    }
}
