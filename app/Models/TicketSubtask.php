<?php

namespace App\Models;

use App\Enums\SubtaskSide;
use App\Enums\SubtaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketSubtask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_id', 'title', 'description', 'assignee_id', 'side', 'status',
        'start_date', 'due_date', 'estimated_hours', 'blocked_reason',
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

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'subtask_id');
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

    /** Due today or already late — the "what's on my plate" question. F22.1 */
    public function scopeDueOrOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_date')->whereDate('due_date', '<=', today());
    }
}
