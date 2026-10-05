<?php

namespace App\Exports;

use App\Exports\Concerns\CachesSheets;
use App\Exports\Sheets\ArraySheet;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * ★ (2026-10-05) F19.5 — /reports/resolved-by-type as a workbook.
 *
 * The same ReportService call as the screen, with the same settled filters,
 * so the file says what the page said. Team mode is the matrix on one tab;
 * person mode is the per-type table plus every ticket behind it (the screen
 * pages those, the sheet does not).
 */
class ResolvedByTypeExport implements WithMultipleSheets
{
    use Exportable, CachesSheets;

    /** @param array<string, mixed> $filters already through ResolvedByTypeController::resolve() */
    public function __construct(private readonly array $filters)
    {
    }

    /** @return array<int, ArraySheet> */
    protected function buildSheets(): array
    {
        $data = app(ReportService::class)->resolvedByType($this->filters, paginate: false);

        if ($data['tickets'] === null) {
            return [$this->matrix($data)];
        }

        return [$this->person($data), $this->tickets($data)];
    }

    /** @param array<string, mixed> $data */
    private function matrix(array $data): ArraySheet
    {
        $rows = collect($data['rows'])->map(function ($row) use ($data) {
            $cells = [$row->user->name, $row->user->role?->name_ar];

            foreach (array_keys($data['types']) as $key) {
                $cells[] = $row->counts[$key] ?? 0;
            }

            $cells[] = $row->total;

            return $cells;
        })->all();

        if ($rows !== []) {
            $rows[] = array_merge(['إجمالي النوع', null], array_values($data['totals']), [array_sum($data['totals'])]);
        }

        return new ArraySheet(
            'الحلول بالنوع',
            array_merge(['الموظف', 'الدور'], array_values($data['types']), ['الإجمالي']),
            $rows,
        );
    }

    /** @param array<string, mixed> $data */
    private function person(array $data): ArraySheet
    {
        $rows = [];

        foreach ($data['types'] as $key => $label) {
            $cell = $data['byType'][$key] ?? null;
            $rows[] = [$label, (int) ($cell->n ?? 0), $cell ? round((float) $cell->avg_hours) : null];
        }

        $rows[] = ['الإجمالي', $data['total'], null];

        return new ArraySheet('إيه الي حلّه', ['النوع', 'اتحلت', 'متوسط زمن الحل (ساعة)'], $rows);
    }

    /** @param array<string, mixed> $data */
    private function tickets(array $data): ArraySheet
    {
        $tz = config('app.display_timezone');

        // The screen pages; the sheet carries the whole range (paginate: false
        // above hands back the plain collection).
        $rows = collect($data['tickets']);

        return new ArraySheet(
            'التذاكر',
            ['رقم التذكرة', 'العنوان', 'الجهة الطالبة', 'النوع', 'الأولوية', 'الحالة', 'تاريخ الفتح', 'تاريخ الحل', 'التأخير'],
            $rows->map(fn ($t) => [
                $t->ticket_number,
                $t->title,
                $t->originLabel(),
                $t->type->label(),
                $t->priority->label(),
                $t->status->label(),
                $t->reported_at?->timezone($tz)->format('Y-m-d H:i'),
                $t->resolved_at?->timezone($tz)->format('Y-m-d H:i'),
                $t->missedDeadlines() !== [] ? 'متأخرة' : ($t->hasDeadline() ? 'في معادها' : null),
            ])->all(),
        );
    }
}
