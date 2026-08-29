<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * F15 — what is waiting on an admin's decision.
 *
 * Carries the same bounded slice of the description the queue card shows: an
 * approver decides from what the ticket asks for, and a list of titles is not
 * enough to decide from. Bounded, never the whole LONGTEXT (§ 4.3).
 */
class ApprovalsExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable, SanitizesCells;

    /** @param array<string, mixed> $filters the screen's assignee/relation pair */
    public function __construct(private readonly array $filters = [])
    {
    }

    public function query()
    {
        return Ticket::query()
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type',
                'priority', 'status', 'reported_at', 'sla_due_at', 'created_by',
            ])
            ->selectRaw('LEFT(description, 1000) AS description_excerpt')
            ->with(['company:id,name', 'requester:id,name', 'creator:id,name'])
            ->where('approval_status', 'pending')
            // The same people filter the screen offers (2026-08-02).
            ->when($this->filters['assignee'] ?? null,
                fn ($q, $v) => $q->involving((int) $v, $this->filters['relation'] ?? null))
            ->defaultOrder();
    }

    public function headings(): array
    {
        return ['رقم التذكرة', 'العنوان', 'الجهة الطالبة', 'النوع', 'الأولوية', 'اللي فتحها', 'تاريخ الفتح', 'مهلة SLA', 'من الوصف'];
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
            $t->creator?->name,
            $t->reported_at?->timezone($tz)->format('Y-m-d H:i'),
            $t->sla_due_at?->timezone($tz)->format('Y-m-d H:i'),
            $t->descriptionExcerpt(500),
        ]);
    }

    public function title(): string
    {
        return 'مستني الموافقة';
    }
}
