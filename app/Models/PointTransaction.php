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
}
