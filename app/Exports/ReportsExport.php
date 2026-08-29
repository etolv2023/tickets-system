<?php

namespace App\Exports;

use App\Casts\PriorityValue;
use App\Casts\TicketTypeValue;
use App\Exports\Concerns\CachesSheets;
use App\Exports\Sheets\ArraySheet;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * F19.3 — /reports as a workbook. One tab per card on the screen, in the same
 * order, so the file reads like the page it came from.
 *
 * Every tab is a GROUP BY result except the SLA breach list, which the month
 * bounds — so the workbook is small and builds in memory.
 */
class ReportsExport implements WithMultipleSheets
{
    use Exportable, CachesSheets;

    public function __construct(
        private readonly string $period,
        private readonly string $from,
        private readonly string $to,
    ) {
    }

    /** @return array<int, ArraySheet> */
    protected function buildSheets(): array
    {
        $reports = app(ReportService::class);
        $resolution = $reports->resolutionTimes($this->from, $this->to);

        return [
            $this->distribution($reports->ticketDistribution($this->from, $this->to)),
            $this->byPriority($resolution['byPriority']),
            $this->byType($resolution['byType']),
            $this->breaches($reports->slaBreaches($this->from, $this->to)),
            $this->companies($reports->companyPerformance($this->from, $this->to)),
            $this->load($reports->teamLoad()),
            $this->time($reports->timeReport($this->from, $this->to)),
        ];
    }

    private function distribution(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            'توزيع التذاكر',
            ['النوع', 'الإجمالي', 'محلولة', 'مفتوحة'],
            collect($rows)->groupBy('type')->map(function ($group, $type) {
                $total = $group->sum('n');
                $done = $group->whereIn('status', ['resolved', 'closed'])->sum('n');

                return [TicketTypeValue::for($type)->label(), $total, $done, $total - $done];
            })->values()->all(),
        );
    }

    private function byPriority(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            'زمن الحل بالأولوية',
            ['الأولوية', 'متوسط الساعات', 'العدد'],
            collect($rows)->map(fn ($r) => [
                PriorityValue::for($r->k)->label(),
                round((float) $r->avg_hours),
                $r->n,
            ])->all(),
        );
    }

    private function byType(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            'زمن الحل بالنوع',
            ['النوع', 'متوسط الساعات', 'العدد'],
            collect($rows)->map(fn ($r) => [
                TicketTypeValue::for($r->k)->label(),
                round((float) $r->avg_hours),
                $r->n,
            ])->all(),
        );
    }

    private function breaches(iterable $rows): ArraySheet
    {
        $tz = config('app.display_timezone');

        return new ArraySheet(
            'خرق SLA',
            ['رقم التذكرة', 'العنوان', 'الجهة الطالبة', 'الأولوية', 'الحالة', 'المهلة كانت', 'وقت الحل'],
            collect($rows)->map(fn ($t) => [
                $t->ticket_number,
                $t->title,
                $t->originLabel(),
                $t->priority->label(),
                $t->status->label(),
                $t->sla_due_at?->timezone($tz)->format('Y-m-d H:i'),
                $t->resolved_at?->timezone($tz)->format('Y-m-d H:i'),
            ])->all(),
        );
    }

    private function companies(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            'أداء الشركات',
            ['الشركة', 'التذاكر', 'اتحلت', 'متوسط زمن الحل (ساعة)'],
            collect($rows)->map(fn ($r) => [
                // No company_id is internal work, not a missing customer. F25
                $r->company_id === null ? 'شغل داخلي' : ($r->company?->name ?? '—'),
                $r->total,
                $r->resolved,
                $r->avg_hours ? round((float) $r->avg_hours) : null,
            ])->all(),
        );
    }

    private function load(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            'حِمل التيم',
            ['الموظف', 'إسنادات مفتوحة'],
            collect($rows)->map(fn ($u) => [$u->name, $u->open_load])->all(),
        );
    }

    private function time(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            'تقرير الوقت',
            ['الموظف', 'ساعات مسجّلة', 'تذاكر', 'دقة التقدير'],
            collect($rows)->map(fn ($r) => [
                $r->user?->name ?? '—',
                $r->logged,
                $r->tickets,
                $r->accuracy,
            ])->all(),
        );
    }
}
