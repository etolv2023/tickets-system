<?php

namespace App\Exports;

use App\Casts\TicketTypeValue;
use App\Exports\Concerns\CachesSheets;
use App\Exports\Sheets\ArraySheet;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * F18 — /points-report as a workbook: the same tables the screen shows, one
 * per tab, plus the headline figures the screen puts in tiles.
 *
 * All aggregates, so the whole thing is small enough to build in memory.
 */
class PointsSummaryExport implements WithMultipleSheets
{
    use Exportable, CachesSheets;

    public function __construct(
        private readonly string $period,
        private readonly ?string $type = null,
    ) {
    }

    /** @return array<int, ArraySheet> */
    protected function buildSheets(): array
    {
        $data = app(ReportService::class)->pointsReport($this->period, $this->type);

        return [
            $this->headline($data),
            $this->byPerson($data['byPerson']),
            $this->byType($data['byType']),
            $this->bySide($data['bySide'], (float) $data['total']),
            $this->topTickets($data['topTickets']),
        ];
    }

    /** @param array<string, mixed> $data */
    private function headline(array $data): ArraySheet
    {
        $delta = $data['previous'] > 0
            ? round((($data['total'] - $data['previous']) / $data['previous']) * 100) . __('٪')
            : '—';

        return new ArraySheet(__('الملخص'), [__('البند'), __('القيمة')], [
            [__('الشهر'), $this->period],
            // Says so on the sheet: a filtered total that looks like a whole
            // month is the kind of number someone plans a payroll around.
            [__('النوع المفلتر'), $this->type === null ? __('كل الأنواع') : TicketTypeValue::for($this->type)->label()],
            [__('إجمالي نقاط الشهر'), (float) $data['total']],
            [__('إجمالي الشهر السابق'), (float) $data['previous']],
            [__('الفرق عن الشهر السابق'), $delta],
            [__('موظفون حصلوا على نقاط'), $data['people']],
            [__('تذاكر احتُسبت عليها نقاط'), $data['tickets']],
            [__('متوسط نقاط التذكرة'), $data['tickets'] ? round($data['total'] / $data['tickets'], 2) : '—'],
            [__('تصحيحات يدوية — العدد'), $data['correctionsCount']],
            [__('تصحيحات يدوية — الإجمالي'), (float) $data['correctionsTotal']],
        ]);
    }

    private function byPerson(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            __('لكل موظف'),
            [__('الموظف'), __('الدور'), __('الدعم'), __('الواجهة الأمامية'), __('الأنظمة الخلفية'), __('الاختبار'), __('عمليات التطوير'), __('عدد المرات'), __('الإجمالي')],
            collect($rows)->map(fn ($r) => [
                $r->user?->name ?? '—',
                $r->user?->role?->label() ?? '—',
                (float) $r->support,
                (float) $r->frontend,
                (float) $r->backend,
                (float) $r->tester,
                (float) $r->devops,
                $r->awards,
                (float) $r->total,
            ])->all(),
        );
    }

    private function byType(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            __('حسب نوع التذكرة'),
            [__('النوع'), __('تذاكر'), __('صفوف نقاط'), __('الإجمالي'), __('متوسط التذكرة')],
            collect($rows)->map(fn ($r) => [
                TicketTypeValue::for($r->type)->label(),
                $r->tickets,
                $r->awards,
                (float) $r->total,
                round($r->total / max(1, $r->tickets), 2),
            ])->all(),
        );
    }

    private function bySide(iterable $rows, float $total): ArraySheet
    {
        return new ArraySheet(
            __('حسب الجهة / الدور'),
            [__('الجهة / الدور'), __('صفوف'), __('الإجمالي'), __('النسبة')],
            collect($rows)->map(fn ($r) => [
                // Same fallback the screen prints: a role-based row has no side.
                $r->side?->label() ?? $r->role?->label() ?? '—',
                $r->awards,
                (float) $r->total,
                $total > 0 ? round(($r->total / $total) * 100) . __('٪') : '—',
            ])->all(),
        );
    }

    private function topTickets(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            __('أعلى التذاكر'),
            [__('رقم التذكرة'), __('عنوان التذكرة'), __('الجهة الطالبة'), __('النوع'), __('المشاركون'), __('النقاط')],
            collect($rows)->map(fn ($r) => [
                $r->ticket?->ticket_number,
                $r->ticket?->title,
                $r->ticket?->originLabel(),
                $r->ticket?->type?->label(),
                $r->people,
                (float) $r->total,
            ])->all(),
        );
    }
}
