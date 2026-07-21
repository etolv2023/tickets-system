<?php

namespace App\Models;

use App\Enums\SubtaskSide;
use App\Enums\SubtaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketSubtask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_id', 'title', 'description', 'assignee_id', 'side', 'status',
        'start_date', 'due_date', 'estimated_hours', 'points', 'rule_id', 'blocked_reason',
        'position', 'created_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'side' => SubtaskSide::class,
            'status' => SubtaskStatus::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'estimated_hours' => 'decimal:2',
            'spent_hours' => 'decimal:2',
            'points' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** F18: the point_rules row that supplied this subtask's default points, if any. */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(PointRule::class, 'rule_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'subtask_id');
    }

    /** F18: the one point_transactions row this subtask has ever been paid, if any. */
    public function pointTransaction(): HasOne
    {
        return $this->hasOne(PointTransaction::class, 'subtask_id');
    }

    /** F13: late means due before today and still not done. */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== SubtaskStatus::Done
            && $this->due_date->isPast();
    }

    /** F09: estimated minus spent, never below zero. */
    public function remainingHours(): float
    {
        if ($this->estimated_hours === null) {
            return 0.0;
        }

        return max(0, (float) $this->estimated_hours - (float) $this->spent_hours);
    }

    /**
     * F09: green inside the estimate, amber over it, red past double. The colour
     * is the judgement — the bar just carries it.
     */
    public function estimateVariant(): string
    {
        if ($this->estimated_hours === null || (float) $this->estimated_hours == 0.0) {
            return 'low';
        }

        $ratio = (float) $this->spent_hours / (float) $this->estimated_hours;

        return match (true) {
            $ratio > 2.0 => 'urgent',
            $ratio > 1.0 => 'high',
            default => 'resolved',
        };
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', SubtaskStatus::Done->value);
    }

    /** The date columns a date-range filter may run against. */
    public const DATE_BASES = [
        'start_date' => 'تاريخ البداية',
        'due_date' => 'تاريخ الاستحقاق',
        'completed_at' => 'تاريخ الإنجاز',
    ];

    /**
     * The team-activity report's filter set (F19.3). Person, side, status, a
     * date range against a caller-chosen column, and the parent ticket's own
     * type/company — so "show me bug-related subtasks" works without the
     * caller joining by hand.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $dateBasis = array_key_exists($filters['date_basis'] ?? null, self::DATE_BASES)
            ? $filters['date_basis']
            : 'start_date';

        return $query
            ->when($filters['person'] ?? null, fn (Builder $q, $v) => $q->where('assignee_id', $v))
            ->when($filters['side'] ?? null, fn (Builder $q, $v) => $q->where('side', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate($dateBasis, '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate($dateBasis, '<=', $v))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->whereHas('ticket', fn (Builder $t) => $t->where('type', $v)))
            ->when($filters['company'] ?? null, fn (Builder $q, $v) => $q->whereHas('ticket', fn (Builder $t) => $t->where('company_id', $v)));
    }

    /** Due today or already late — the "what's on my plate" question. F22.1 */
    public function scopeDueOrOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_date')->whereDate('due_date', '<=', today());
    }
}
