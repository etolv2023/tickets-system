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

    /** @param array<string, mixed> $filters company, type, priority, person — see ReportService::constrain() */
    public function __construct(
        private readonly string $period,
        private readonly string $from,
        private readonly string $to,
        private readonly array $filters = [],
    ) {
    }

    /** @return array<int, ArraySheet> */
    protected function buildSheets(): array
    {
        $reports = app(ReportService::class);
        $resolution = $reports->resolutionTimes($this->from, $this->to, $this->filters);

        return [
            $this->distribution($reports->ticketDistribution($this->from, $this->to, $this->filters)),
            $this->byPriority($resolution['byPriority']),
            $this->byType($resolution['byType']),
            $this->breaches($reports->slaBreaches($this->from, $this->to, $this->filters)),
            $this->companies($reports->companyPerformance($this->from, $this->to, $this->filters)),
            $this->load($reports->teamLoad()),
            $this->time($reports->timeReport($this->from, $this->to)),
        ];
    }

    private function distribution(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            __('توزيع التذاكر'),
            [__('النوع'), __('الإجمالي'), __('محلولة'), __('مفتوحة')],
            collect($rows)->map(fn ($row) => [
                $row->type->label(), $row->total, $row->done, $row->open,
            ])->all(),
        );
    }

    private function byPriority(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            __('زمن الحل بالأولوية'),
            [__('الأولوية'), __('متوسط الساعات'), __('العدد')],
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
            __('زمن الحل بالنوع'),
            [__('النوع'), __('متوسط الساعات'), __('العدد')],
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
            __('تجاوز SLA'),
            [__('رقم التذكرة'), __('العنوان'), __('الجهة الطالبة'), __('الأولوية'), __('الحالة'), __('المهلة كانت'), __('وقت الحل')],
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
            __('أداء الشركات'),
            [__('الشركة'), __('التذاكر'), __('محلولة'), __('متوسط زمن الحل (ساعة)')],
            collect($rows)->map(fn ($r) => [
                // No company_id is internal work, not a missing customer. F25
                $r->company_id === null ? __('عمل داخلي') : ($r->company?->name ?? '—'),
                $r->total,
                $r->resolved,
                $r->avg_hours ? round((float) $r->avg_hours) : null,
            ])->all(),
        );
    }

    private function load(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            __('عبء عمل الفريق'),
            [__('الموظف'), __('إسنادات مفتوحة')],
            collect($rows)->map(fn ($u) => [$u->name, $u->open_load])->all(),
        );
    }

    private function time(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            __('تقرير الوقت'),
            [__('الموظف'), __('ساعات مسجّلة'), __('تذاكر'), __('دقة التقدير')],
            collect($rows)->map(fn ($r) => [
                $r->user?->name ?? '—',
                $r->logged,
                $r->tickets,
                $r->accuracy,
            ])->all(),
        );
    }
}
