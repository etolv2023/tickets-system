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

    protected $fillable = ['user_id', 'ticket_id', 'side', 'points', 'rule_id', 'period', 'reason'];

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

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PointRule::class, 'rule_id');
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }
}
