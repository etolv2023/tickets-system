<?php

namespace App\Exports;

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
    use Exportable, SanitizesCells;

    /** @param array<string, mixed> $filters */
    public function __construct(
        private readonly User $user,
        private readonly array $filters = [],
    ) {
    }

    public function query()
    {
        return Ticket::query()
            ->with(['company:id,name', 'requester:id,name', 'frontend:id,name', 'backend:id,name', 'tester:id,name', 'devops:id,name', 'creator:id,name'])
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
            'الأولوية', 'الحالة', 'فتحها', 'فرونت', 'باك', 'تيستر', 'ديف أوبس',
            'وقت الإبلاغ', 'مهلة SLA', 'وقت الحل', 'العمر / زمن الحل',
            'المقدّر (س)', 'الفعلي (س)', 'صب تاسكس',
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
            $ticket->frontend?->name,
            $ticket->backend?->name,
            $ticket->tester?->name,
            $ticket->devops?->name,
            $ticket->reported_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            $ticket->sla_due_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            $ticket->resolved_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            $ticket->ageLabel(),
            $ticket->original_estimate_hours,
            $ticket->spent_hours,
            $ticket->subtasks_total > 0 ? "{$ticket->subtasks_done}/{$ticket->subtasks_total}" : null,
        ]);
    }

    public function title(): string
    {
        return 'التذاكر';
    }
}
