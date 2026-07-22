<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketWorkLog extends Model
{
    protected $fillable = [
        'ticket_id', 'user_id', 'role_id', 'status', 'started_at', 'finished_at', 'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
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

    /**
     * The role this commitment is for (F07). Replaces the old WorkSide `side`:
     * a work log now belongs to whatever role was flagged logs_work, not to a
     * hardcoded frontend/backend enum.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** The role's Arabic name — what the my-work panel and cards used to get from side->label(). */
    public function roleLabel(): string
    {
        return $this->role?->name_ar ?? '—';
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
