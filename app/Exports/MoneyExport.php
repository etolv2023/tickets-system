<?php

namespace App\Exports;

use App\Exports\Concerns\CachesSheets;
use App\Exports\Sheets\ArraySheet;
use App\Models\TicketTypeDefinition;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * F18.3 — /admin/point-values: what the month comes to in money.
 *
 * The one export in this system that leaves the building as a payroll figure,
 * so it carries its own workings rather than a bottom line: the rate card it
 * was priced with, every person's total, and the per-type split behind each
 * person's total. Somebody disputing their number has to be able to find the
 * row that made it without asking anyone.
 *
 * The unpriced warning travels with it for the same reason. A type that earned
 * points while still priced at zero makes a month look quiet when it was only
 * unpriced, and that reading is much harder to catch in a spreadsheet than on
 * the screen, where the tile shouts it.
 */
class MoneyExport implements WithMultipleSheets
{
    use Exportable, CachesSheets;

    public function __construct(private readonly string $period)
    {
    }

    /** @return array<int, ArraySheet> */
    protected function buildSheets(): array
    {
        $data = app(ReportService::class)->moneyReport($this->period);

        return [
            $this->summary($data),
            $this->people($data['rows']),
            $this->breakdown($data['rows']),
            $this->byType($data['byType']),
            $this->rateCard(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function summary(array $data): ArraySheet
    {
        return new ArraySheet('الملخص', ['البند', 'القيمة'], [
            ['الشهر', $this->period],
            ['إجمالي المستحقات', (float) $data['total']],
            ['عدد الموظفين', $data['rows']->count()],
            ['أنواع اتصرفت عليها نقاط وسعرها لسه صفر', $data['unpriced']],
            [
                'تنبيه',
                $data['unpriced'] > 0
                    ? 'فيه أنواع شغّالة بسعر صفر — الإجمالي أقل من الحقيقة لحد ما تتسعّر.'
                    : 'كل الأنواع الي اتصرف عليها متسعّرة.',
            ],
        ]);
    }

    private function people(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            'المستحقات لكل موظف',
            ['الموظف', 'إجمالي النقاط', 'المستحق'],
            collect($rows)->map(fn (array $r) => [
                $r['name'],
                round($r['points'], 2),
                round($r['money'], 2),
            ])->all(),
        );
    }

    /** One row per person per ticket type — the arithmetic behind each total. */
    private function breakdown(iterable $rows): ArraySheet
    {
        $out = [];

        foreach ($rows as $person) {
            foreach ($person['types'] as $t) {
                $out[] = [
                    $person['name'],
                    $t['label'],
                    round($t['points'], 2),
                    $t['rate'],
                    round($t['money'], 2),
                    $t['entries'],
                ];
            }
        }

        return new ArraySheet(
            'التفصيل',
            ['الموظف', 'نوع التذكرة', 'النقاط', 'سعر النقطة', 'المستحق', 'عدد الصفوف'],
            $out,
        );
    }

    private function byType(iterable $rows): ArraySheet
    {
        return new ArraySheet(
            'حسب نوع التذكرة',
            ['النوع', 'سعر النقطة', 'النقاط', 'المستحق'],
            collect($rows)->map(fn (array $t) => [
                $t['label'],
                $t['rate'],
                round($t['points'], 2),
                round($t['money'], 2),
            ])->all(),
        );
    }

    /** The rate card as it stood when the file was pulled. */
    private function rateCard(): ArraySheet
    {
        return new ArraySheet(
            'سعر النقطة',
            ['النوع', 'المفتاح', 'سعر النقطة'],
            TicketTypeDefinition::orderBy('position')->get()
                ->map(fn ($t) => [$t->name_ar, $t->key, (float) $t->point_value])
                ->all(),
        );
    }
}
