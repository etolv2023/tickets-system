<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\SanitizesCells;
use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * A tab of ticket rows built from a filter set, for the workbooks that carry
 * tickets next to something else.
 *
 * Assignment is role-based (2026-07-24), so who is on the ticket is one column
 * listing every role holder rather than four fixed ones — the same shape
 * TicketsExport uses, and the only shape that survives an admin adding a role.
 *
 * FromQuery, not FromArray: a person's year of tickets is not a small number.
 */
class TicketRowsSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use SanitizesCells;

    /** @param array<string, mixed> $filters keys Ticket::scopeFilter understands */
    public function __construct(
        private readonly array $filters,
        private readonly string $title = 'التذاكر',
    ) {
    }

    public function query()
    {
        return Ticket::query()
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority',
                'status', 'reported_at', 'sla_due_at', 'due_date', 'resolved_at',
                'original_estimate_hours', 'spent_hours', 'subtasks_total', 'subtasks_done',
            ])
            ->with([
                'company:id,name', 'requester:id,name',
                'roleAssignments.role:id,name_ar', 'roleAssignments.user:id,name',
            ])
            ->filter($this->filters)
            ->defaultOrder();
    }

    public function headings(): array
    {
        return [
            'رقم التذكرة', 'العنوان', 'الجهة الطالبة', 'النوع', 'الأولوية', 'الحالة', 'التوزيع',
            'تاريخ الفتح', 'مهلة SLA', 'تاريخ الاستحقاق', 'تاريخ الحل',
            'المقدّر (س)', 'الفعلي (س)', 'صب تاسكس',
        ];
    }

    /** @param Ticket $t */
    public function map($t): array
    {
        $tz = config('app.display_timezone');

        return $this->sanitizeRow([
            $t->ticket_number,
            $t->title,
            $t->originLabel(),
            $t->type->label(),
            $t->priority->label(),
            $t->status->label(),
            $t->roleAssignments->map(fn ($a) => "{$a->role->name_ar}: {$a->user?->name}")->implode('، '),
            $t->reported_at?->timezone($tz)->format('Y-m-d H:i'),
            $t->sla_due_at?->timezone($tz)->format('Y-m-d H:i'),
            $t->due_date?->format('Y-m-d'),
            $t->resolved_at?->timezone($tz)->format('Y-m-d H:i'),
            $t->original_estimate_hours === null ? null : (float) $t->original_estimate_hours,
            (float) $t->spent_hours,
            $t->subtasks_total > 0 ? "{$t->subtasks_done}/{$t->subtasks_total}" : null,
        ]);
    }

    public function title(): string
    {
        return $this->title;
    }
}
