<?php

namespace App\Exports;

use App\Casts\TicketTypeValue;
use App\Exports\Concerns\CachesSheets;
use App\Exports\Sheets\ArraySheet;
use App\Models\TicketTypeDefinition;
use App\Models\User;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * F19.1 — one person, one month: the headline facts on one tab and the tickets
 * behind them on another, so the numbers can be checked rather than believed.
 */
class EmployeeProfileExport implements WithMultipleSheets
{
    use Exportable, CachesSheets;

    public function __construct(
        private readonly User $employee,
        private readonly string $period,
    ) {
    }

    /** @return array<int, ArraySheet> */
    protected function buildSheets(): array
    {
        $data = app(ReportService::class)->employeeProfile($this->employee, $this->period);

        return [$this->summary($data), $this->points($data['points']), $this->tickets($data['tickets'])];
    }

    /** @param array<string, mixed> $data */
    private function summary(array $data): ArraySheet
    {
        $rows = [
            ['الموظف', $this->employee->name],
            ['الدور', $this->employee->role?->name_ar],
            ['الشهر', $this->period],
        ];

        // Types are admin-defined now, so the list comes from the table rather
        // than from a fixed enum (2026-08-19).
        foreach (TicketTypeDefinition::orderBy('position')->get() as $type) {
            $rows[] = [$type->name_ar, $data['byType'][$type->key] ?? 0];
        }

        $rows[] = ['إجمالي نقاط الشهر', (float) $data['pointsTotal']];
        $rows[] = ['متوسط التقييم', $data['avgRating'] === null ? '—' : round((float) $data['avgRating'], 2)];
        $rows[] = ['متوسط زمن الحل (ساعة)', $data['avgResolutionHours'] ?? '—'];
        $rows[] = ['دقة التقدير (مقدّر ÷ فعلي)', $data['estimateAccuracy'] ?? '—'];
        $rows[] = ['ساعات مسجّلة', $data['hoursLogged']];
        $rows[] = ['تذاكر اتحلت', $data['reopenRate']['resolved']];
        $rows[] = ['منها رجّعها التيستر', $data['reopenRate']['reopened']];
        $rows[] = ['معدل الارتجاع', $data['reopenRate']['rate'] . '٪'];

        return new ArraySheet('الملخص', ['البند', 'القيمة'], $rows);
    }

    private function points(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            'النقاط حسب الجهة',
            ['الجهة / الدور', 'الإجمالي'],
            collect($rows)->map(fn ($r) => [
                // A role-based award has side = null and role_id set instead.
                $r->side?->label() ?? $r->role?->name_ar ?? '—',
                (float) $r->total,
            ])->all(),
        );
    }

    private function tickets(iterable $rows): ArraySheet
    {
        $tz = config('app.display_timezone');

        return new ArraySheet(
            'التذاكر',
            ['رقم التذكرة', 'العنوان', 'الجهة الطالبة', 'النوع', 'الأولوية', 'الحالة', 'تاريخ الفتح', 'تاريخ الحل'],
            collect($rows)->map(fn ($t) => [
                $t->ticket_number,
                $t->title,
                $t->originLabel(),
                $t->type->label(),
                $t->priority->label(),
                $t->status->label(),
                $t->reported_at?->timezone($tz)->format('Y-m-d H:i'),
                $t->resolved_at?->timezone($tz)->format('Y-m-d H:i'),
            ])->all(),
        );
    }
}
