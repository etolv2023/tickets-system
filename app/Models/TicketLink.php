<?php

namespace App\Models;

use App\Casts\LinkTypeCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketLink extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['from_ticket_id', 'to_ticket_id', 'type', 'created_by'];

    protected function casts(): array
    {
        return ['type' => LinkTypeCast::class, 'created_at' => 'datetime'];
    }

    public function fromTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'from_ticket_id');
    }

    public function toTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'to_ticket_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
