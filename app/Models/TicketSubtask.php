<?php

namespace App\Models;

use App\Casts\SubtaskStatusCast;
use App\Enums\SubtaskSide;
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
        'ticket_id', 'title', 'description', 'assignee_id', 'side', 'role_id', 'status',
        'start_date', 'due_date', 'due_at', 'estimated_hours', 'points', 'blocked_reason',
        'position', 'created_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'side' => SubtaskSide::class,
            'status' => SubtaskStatusCast::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'due_at' => 'datetime',
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

    /** F06 role-assignment extension: set when this subtask earns for a role beyond the fixed `side` sides. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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

    /**
     * ★ (2026-08-05) Every ledger row this subtask carries — which stopped
     * being "at most one" when «تراكم التأخير» made a subtask dockable every
     * morning it stays overdue. pointTransaction() above is still the right
     * relation for the award, because there is still only ever one of those.
     */
    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'subtask_id');
    }

    /**
     * F13: late means due before today and still not done.
     *
     * ★ (2026-08-19) Reads `due_at` when the row has one and `due_date`
     * otherwise — deliberately NOT deadline(), which normalises a due_date to
     * 23:59. This flag has always gone red at midnight on the due day rather
     * than at the end of it. That is a planning signal, not the payout rule,
     * and routing it through deadline() would have silently moved the red
     * marker on every subtask already in the database. finishedLate() is the
     * one that decides money, and that one does use deadline().
     */
    public function isOverdue(): bool
    {
        $due = $this->due_at ?? $this->due_date;

        return $due !== null
            && ! $this->status->isDone()
            && $due->isPast();
    }

    /**
     * ★ (2026-08-19) The exact moment this subtask stops being on time — the
     * one place the two deadline columns are reconciled.
     *
     * `due_date` is a DATE and has always meant "by the end of that day", which
     * is right for planned work: a subtask due «النهاردة» is not late at 4pm.
     * F26's exception tickets need an hour on the clock (four WORKING hours
     * from the error), and a DATE cannot carry one — so `due_at` was added as
     * an optional, exact override.
     *
     * Precedence is `due_at` first because it is the more specific promise: a
     * row that has one was given a moment on purpose, and falling back to
     * end-of-day there would quietly hand back the hours it was meant to
     * remove. A row without one behaves exactly as it did before this existed,
     * which is every subtask a human has ever typed.
     *
     * ->copy(): the date cast hands back the model's own cached Carbon, so
     * endOfDay() on it would quietly move due_date to 23:59 for the rest of the
     * request — including for whoever reads it after us.
     */
    public function deadline(): ?\Illuminate\Support\Carbon
    {
        if ($this->due_at !== null) {
            return $this->due_at;
        }

        return $this->due_date?->copy()->endOfDay();
    }

    /**
     * ★ (2026-08-05) Whether this subtask's points count AGAINST its owner.
     *
     * The one definition of "late" in the system. PointEngineService reads it
     * to decide the sign of the ledger row it writes; the ticket page reads it
     * to say so before the fact. Two copies of this rule that drifted apart
     * would mean the screen promising points the payout doesn't give.
     *
     * Not the same question as isOverdue() above, which asks "is this sitting
     * unfinished past its date" — a red flag on a plan. This asks "does
     * finishing it earn or cost", and stays true after it is done: work
     * delivered late is late forever, which is the entire rule.
     *
     * $finishedAt is the moment the work landed. Left out it means "as of now",
     * which reads correctly in both places it is used: on a done subtask
     * completed_at is the real answer, and on an open one now() says what the
     * owner needs to hear — finish it today and it already costs you.
     *
     * No due date is never late. Nothing was promised, so nothing was missed.
     */
    public function finishedLate(?\Illuminate\Support\Carbon $finishedAt = null): bool
    {
        $deadline = $this->deadline();

        if ($deadline === null) {
            return false;
        }

        $finished = $finishedAt ?? $this->completed_at ?? now();

        return $finished->gt($deadline);
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
        return $query->where('status', '!=', 'done');
    }

    /** The date columns a date-range filter may run against. */
    public const DATE_BASES = [
        'start_date' => 'تاريخ البداية',
        'due_date' => 'تاريخ الاستحقاق',
        'completed_at' => 'تاريخ الإنجاز',
    ];

    /**
     * The team-activity report's filter set (F19.3). Person, role, status, a
     * date range against a caller-chosen column, and the parent ticket's own
     * type/company — so "show me bug-related subtasks" works without the
     * caller joining by hand. Role-based since 2026-07-24 (was a hardcoded side).
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
            ->when($filters['role'] ?? null, fn (Builder $q, $v) => $q->where('role_id', $v))
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
