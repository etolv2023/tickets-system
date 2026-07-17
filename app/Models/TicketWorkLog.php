<?php

namespace App\Models;

use App\Enums\WorkSide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketWorkLog extends Model
{
    protected $fillable = [
        'ticket_id', 'user_id', 'side', 'status', 'started_at', 'finished_at', 'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'side' => WorkSide::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'مستنية',
            'in_progress' => 'جاري العمل',
            'done' => 'خلصت',
            default => $this->status,
        };
    }

    public function statusVariant(): string
    {
        return match ($this->status) {
            'done' => 'resolved',
            'in_progress' => 'inquiry',
            default => 'neutral',
        };
    }
}
