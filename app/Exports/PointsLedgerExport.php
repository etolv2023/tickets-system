<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Models\PointTransaction;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * F18/F19.3 — the points ledger, row by row.
 *
 * This is the file someone opens when they dispute a bonus figure, so every
 * row has to stand on its own: which ticket, what it was called, which subtask
 * paid, and whether the number was earned, docked or typed in by hand. A row
 * carrying only a ticket number sends the reader back to the screen, which
 * defeats the export.
 *
 * FromQuery rather than FromArray: the ledger grows with every resolved ticket
 * and an unfiltered month is thousands of rows (§ 4).
 */
class PointsLedgerExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable, SanitizesCells;

    /** @param array<string, mixed> $filters */
    public function __construct(private readonly array $filters = [])
    {
    }

    public function query()
    {
        return PointTransaction::query()
            ->with([
                'user:id,name,role_id', 'user.role:id,name_ar',
                'ticket:id,ticket_number,title,type,company_id,requested_by',
                'ticket.company:id,name',
                'ticket.requester:id,name',
                'subtask:id,title',
                'creator:id,name',
                // A role-based row's side is null; this is the fallback.
                'role:id,name_ar',
            ])
            // The same definition the screen uses — one scope, two callers.
            ->filter($this->filters)
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'التاريخ', 'الشهر', 'الموظف', 'دور الموظف',
            'رقم التذكرة', 'عنوان التذكرة', 'الجهة الطالبة', 'نوع التذكرة',
            'الصب تاسك', 'الجهة / الدور', 'المصدر', 'اللي عمل التصحيح', 'السبب', 'النقاط',
        ];
    }

    /** @param PointTransaction $row */
    public function map($row): array
    {
        return $this->sanitizeRow([
            $row->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            $row->period,
            $row->user?->name,
            $row->user?->role?->name_ar,
            $row->ticket?->ticket_number,
            $row->ticket?->title,
            $row->ticket?->originLabel(),
            $row->ticket?->type?->label(),
            // A correction has no subtask behind it — saying so beats a dash
            // the reader has to interpret.
            $row->type === 'correction' ? '— تصحيح يدوي —' : $row->subtask?->title,
            $row->sideLabel(),
            $row->kindLabel(),
            // Only a correction has an author: nobody "made" a late penalty,
            // the due date did.
            $row->type === 'correction' ? $row->creator?->name : null,
            $row->reason,
            (float) $row->points,
        ]);
    }

    public function title(): string
    {
        return 'دفتر النقاط';
    }
}
