<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'subtask_id', 'user_id', 'hours', 'spent_on', 'note'];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'spent_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(TicketSubtask::class, 'subtask_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('spent_on', [$from, $to]);
    }
}
