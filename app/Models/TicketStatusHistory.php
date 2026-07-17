<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ticket_status_history';

    protected $fillable = ['ticket_id', 'from_status', 'to_status', 'user_id', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromLabel(): ?string
    {
        return $this->from_status ? TicketStatus::from($this->from_status)->label() : null;
    }

    public function toLabel(): string
    {
        return TicketStatus::from($this->to_status)->label();
    }
}
