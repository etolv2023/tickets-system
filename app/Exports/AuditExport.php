<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Models\ActivityLog;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * F23 — the audit trail as a sheet.
 *
 * `changes` is a json blob whose shape differs per action, so it goes out as
 * compact JSON rather than flattened — a guessed flattening would lose the
 * half it didn't guess. JSON_UNESCAPED_UNICODE keeps the Arabic readable
 * instead of turning it into \uXXXX.
 *
 * FromQuery: this table only ever grows.
 */
class AuditExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable, SanitizesCells;

    /** @param array<string, mixed> $filters */
    public function __construct(private readonly array $filters = [])
    {
    }

    public function query()
    {
        // The same filter chain the screen runs — see AuditController::index.
        return ActivityLog::query()
            ->with('user:id,name')
            ->when($this->filters['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($this->filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', "{$v}%"))
            ->when($this->filters['subject'] ?? null, fn ($q, $v) => $q->where('subject_type', 'like', "%{$v}"))
            ->when($this->filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($this->filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id');
    }

    public function headings(): array
    {
        return ['التاريخ', 'المستخدم', 'الفعل', 'الكائن', 'رقم الكائن', 'التفاصيل', 'IP', 'المتصفح'];
    }

    /** @param ActivityLog $log */
    public function map($log): array
    {
        return $this->sanitizeRow([
            $log->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i:s'),
            $log->user?->name ?? 'النظام',
            $log->action,
            $log->subject_type === null ? null : class_basename($log->subject_type),
            $log->subject_id,
            $log->changes === null ? null : json_encode($log->changes, JSON_UNESCAPED_UNICODE),
            $log->ip,
            $log->user_agent,
        ]);
    }

    public function title(): string
    {
        return 'سجل التدقيق';
    }
}
