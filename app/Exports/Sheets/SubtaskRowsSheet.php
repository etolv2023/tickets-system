<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\ExportsDescriptions;
use App\Exports\Concerns\SanitizesCells;
use App\Models\TicketSubtask;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The subtask half of the team-activity workbook. Each row carries its parent
 * ticket's number and title, because a subtask called "الواجهة" means nothing
 * on its own in a file somebody opens a week later.
 */
class SubtaskRowsSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use SanitizesCells, ExportsDescriptions;

    /** @param array<string, mixed> $filters keys TicketSubtask::scopeFilter understands */
    public function __construct(
        private readonly array $filters,
        private readonly string $title = 'الصب تاسكس',
    ) {
    }

    public function query()
    {
        return TicketSubtask::query()
            ->select([
                'id', 'ticket_id', 'title', 'assignee_id', 'side', 'role_id', 'status',
                'start_date', 'due_date', 'estimated_hours', 'spent_hours', 'points', 'completed_at',
            ])
            ->selectRaw($this->descriptionSelect('description'))
            ->with([
                'assignee:id,name',
                'role:id,name_ar',
                'ticket:id,ticket_number,title,company_id,requested_by,type',
                'ticket.company:id,name',
                'ticket.requester:id,name',
            ])
            ->filter($this->filters)
            ->orderByDesc('due_date');
    }

    public function headings(): array
    {
        return [
            'رقم التذكرة', 'عنوان التذكرة', 'الجهة الطالبة', 'نوع التذكرة',
            'الصب تاسك', 'المسؤول', 'الجهة / الدور', 'الحالة',
            'البداية', 'الاستحقاق', 'تاريخ الإنجاز',
            'المقدّر (س)', 'الفعلي (س)', 'النقاط', 'وصف الصب تاسك',
        ];
    }

    /** @param TicketSubtask $s */
    public function map($s): array
    {
        return $this->sanitizeRow([
            $s->ticket?->ticket_number,
            $s->ticket?->title,
            $s->ticket?->originLabel(),
            $s->ticket?->type?->label(),
            $s->title,
            $s->assignee?->name,
            // Role-based since 2026-07-24; older rows still carry a fixed side.
            $s->role?->name_ar ?? $s->side?->label() ?? '—',
            $s->status->label(),
            $s->start_date?->format('Y-m-d'),
            $s->due_date?->format('Y-m-d'),
            $s->completed_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
            $s->estimated_hours === null ? null : (float) $s->estimated_hours,
            (float) $s->spent_hours,
            $s->points === null ? null : (float) $s->points,
            $this->plainDescription($s->description_excerpt),
        ]);
    }

    public function title(): string
    {
        return $this->title;
    }
}
