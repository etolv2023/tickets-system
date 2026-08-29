<?php

namespace App\Models;

use App\Enums\PointSide;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F18 — immutable. Nothing in the app updates or deletes one of these; a
 * correction is a new row with negative points.
 */
class PointTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'ticket_id', 'subtask_id', 'side', 'role_id', 'points', 'type',
        // ★ (2026-08-05) What UNIQUE(subtask_id, charge_key) counts as "the same
        // charge": 'award' once ever, 'penalty:YYYY-MM-DD' once per day.
        'charge_key',
        'created_by', 'period', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'side' => PointSide::class,
            'points' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The ledger is append-only. Anything that tries to rewrite history is a
        // bug, and it should fail loudly rather than quietly succeed.
        static::updating(fn () => throw new \RuntimeException(
            'دفتر النقاط مش بيتعدّل. التصحيح بيتعمل بسطر جديد بنقاط سالبة.'
        ));

        static::deleting(fn () => throw new \RuntimeException(
            'دفتر النقاط مش بيتحذف. التصحيح بيتعمل بسطر جديد بنقاط سالبة.'
        ));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(TicketSubtask::class, 'subtask_id');
    }

    /** Who entered a manual correction. Null on an automatic award. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Set only for a role-based award (F18 role extension) — null for a subtask-based one. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    /**
     * The ledger screen's filter set (F18/F19.3).
     *
     * Lives on the model rather than in the controller (CLAUDE.md § 3) because
     * two callers need it: /points-report/detail and its export. A filtered
     * screen whose export answers a different question is worse than no export,
     * so there is exactly one definition of what the filter means.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['person'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v))
            ->when($filters['period'] ?? null, fn (Builder $q, $v) => $q->forPeriod($v))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['role'] ?? null, fn (Builder $q, $v) => $q->where('role_id', $v))
            // 'kind' is this row's own type (award / penalty / correction);
            // 'type' is the parent ticket's. Two different columns on two
            // different tables, and the screen offers both.
            ->when($filters['kind'] ?? null, fn (Builder $q, $v) => $q->where('type', $v))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->whereHas('ticket', fn (Builder $t) => $t->where('type', $v)))
            ->when($filters['company'] ?? null, fn (Builder $q, $v) => $q->whereHas('ticket', fn (Builder $t) => $t->where('company_id', $v)))
            ->when($filters['q'] ?? null, fn (Builder $q, $v) => $q->where(fn (Builder $w) => $w
                ->where('reason', 'like', "%{$v}%")
                ->orWhereHas('ticket', fn (Builder $t) => $t->where('ticket_number', 'like', "%{$v}%")
                    ->orWhere('title', 'like', "%{$v}%"))
                ->orWhereHas('subtask', fn (Builder $t) => $t->where('title', 'like', "%{$v}%"))));
    }

    /**
     * How this row describes itself: an automatic award, a late-delivery
     * penalty, or a hand-typed correction. The screen renders these as badges;
     * a sheet needs the same three words.
     */
    public function kindLabel(): string
    {
        return match ($this->type) {
            'correction' => 'تصحيح يدوي',
            'penalty' => 'خصم تأخير',
            default => 'صرف تلقائي',
        };
    }

    /**
     * Which role earned it. A subtask-based row carries a fixed `side`; a
     * role-based one carries `role_id` and leaves side null — the same
     * fallback the ledger screen prints.
     */
    public function sideLabel(): string
    {
        return $this->side?->label() ?? $this->role?->name_ar ?? '—';
    }
}
