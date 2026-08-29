<?php

namespace App\Exports;

use App\Exports\Concerns\ExportsDescriptions;
use App\Exports\Concerns\SanitizesCells;
use App\Models\Ticket;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * F19.3 — the ticket list as a sheet.
 *
 * Two things this must not do: leak tickets the exporter can't see, and carry a
 * live formula out to whoever opens the file (CLAUDE.md § 5).
 */
class TicketsExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    use Exportable, SanitizesCells, ExportsDescriptions;

    /** @param array<string, mixed> $filters */
    public function __construct(
        private readonly User $user,
        private readonly array $filters = [],
    ) {
    }

    public function query()
    {
        return Ticket::query()
            // Named columns, not *: the row carries a bounded slice of the
            // description below, and `tickets.*` would drag the whole LONGTEXT
            // alongside it and undo the bound (§ 4.3).
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'reporter_name', 'title',
                'type', 'priority', 'status', 'created_by',
                'reported_at', 'sla_due_at', 'resolved_at', 'updated_at',
                'original_estimate_hours', 'spent_hours', 'subtasks_total', 'subtasks_done',
            ])
            // The ticket's own words, bounded in SQL — see ExportsDescriptions.
            ->selectRaw($this->descriptionSelect('description'))
            // Role-based assignment (2026-07-24): the sheet lists every role
            // holder in one column instead of four fixed ones.
            ->with(['company:id,name', 'requester:id,name', 'roleAssignments.role:id,name_ar', 'roleAssignments.user:id,name', 'creator:id,name'])
            // The same visibility gate as the screen. An export is not a
            // back door around row-level access.
            ->visibleTo($this->user)
            ->filter($this->filters)
            ->defaultOrder();
    }

    public function headings(): array
    {
        return [
            'رقم التذكرة', 'العنوان', 'الشركة', 'المُبلغ', 'النوع',
            'الأولوية', 'الحالة', 'فتحها', 'التوزيع',
            'وقت الإبلاغ', 'مهلة SLA', 'وقت الحل', 'العمر / زمن الحل',
            'المقدّر (س)', 'الفعلي (س)', 'صب تاسكس', 'الوصف',
        ];
    }

    /** @param Ticket $ticket */
    public function map($ticket): array
    {
        return $this->sanitizeRow([
            $ticket->ticket_number,
            $ticket->title,
            $ticket->originLabel(),
            $ticket->reporter_name,
            $ticket->type->label(),
            $ticket->priority->label(),
            $ticket->status->label(),
            $ticket->creator?->name,
            $ticket->roleAssignments
                ->map(fn ($a) => "{$a->role->name_ar}: {$a->user?->name}")
                ->implode('، '),
            $ticket->reported_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            $ticket->sla_due_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            $ticket->resolved_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            $ticket->ageLabel(),
            $ticket->original_estimate_hours,
            $ticket->spent_hours,
            $ticket->subtasks_total > 0 ? "{$ticket->subtasks_done}/{$ticket->subtasks_total}" : null,
            $this->plainDescription($ticket->description_excerpt),
        ]);
    }

    public function title(): string
    {
        return 'التذاكر';
    }
}
