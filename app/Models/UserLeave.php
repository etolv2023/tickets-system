<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLeave extends Model
{
    protected $fillable = ['user_id', 'start_date', 'end_date', 'type', 'note', 'approved_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'annual' => 'سنوية',
            'sick' => 'مرضية',
            default => 'أخرى',
        };
    }

    /** Any leave that overlaps the window at all — not only ones inside it. */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('start_date', '<=', $to)->where('end_date', '>=', $from);
    }

    public function covers(\Carbon\CarbonInterface $date): bool
    {
        return $date->betweenIncluded($this->start_date, $this->end_date);
    }
}
