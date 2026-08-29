<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Models\PointTransaction;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * F18 — "نقاطي".
 *
 * Scoped to one user id rather than to a filter array: this export has exactly
 * one legitimate subject, and taking it as a constructor argument means there
 * is no query parameter an employee could bend towards somebody else's ledger.
 *
 * $period may be null, which is the screen's ?period=all mode — the whole
 * ledger rather than one month.
 */
class MyPointsExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable, SanitizesCells;

    public function __construct(
        private readonly User $user,
        private readonly ?string $period,
    ) {
    }

    public function query()
    {
        return PointTransaction::query()
            ->with([
                'ticket:id,ticket_number,title,type,company_id,requested_by',
                'ticket.company:id,name',
                'ticket.requester:id,name',
                'subtask:id,title',
                'role:id,name_ar',
            ])
            ->where('user_id', $this->user->id)
            ->when($this->period !== null, fn ($q) => $q->forPeriod($this->period))
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'التاريخ', 'الشهر', 'رقم التذكرة', 'عنوان التذكرة', 'الجهة الطالبة',
            'نوع التذكرة', 'الصب تاسك', 'الجهة / الدور', 'المصدر', 'السبب', 'النقاط',
        ];
    }

    /** @param PointTransaction $row */
    public function map($row): array
    {
        return $this->sanitizeRow([
            $row->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            $row->period,
            $row->ticket?->ticket_number,
            $row->ticket?->title,
            $row->ticket?->originLabel(),
            $row->ticket?->type?->label(),
            $row->type === 'correction' ? '— تصحيح يدوي —' : $row->subtask?->title,
            $row->sideLabel(),
            $row->kindLabel(),
            $row->reason,
            (float) $row->points,
        ]);
    }

    public function title(): string
    {
        return 'نقاطي ' . ($this->period ?? 'كل الشهور');
    }
}
