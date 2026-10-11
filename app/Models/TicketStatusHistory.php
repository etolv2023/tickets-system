<?php

namespace App\Models;

use App\Casts\TicketStatusValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ticket_status_history';

    protected $fillable = [
        'ticket_id', 'from_status', 'to_status', 'user_id', 'note',
        'recipient_type', 'recipient_user_id', 'recipient_contact_id',
    ];

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

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function recipientContact(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'recipient_contact_id');
    }

    public function fromLabel(): ?string
    {
        return $this->from_status ? TicketStatusValue::for($this->from_status)->label() : null;
    }

    public function toLabel(): string
    {
        return TicketStatusValue::for($this->to_status)->label();
    }

    /** The note in the reader's language when the system wrote it; as typed when a person did. */
    public function noteText(): ?string
    {
        return \App\Support\Phrase::fromStored($this->note);
    }

    /** "مستني رد من فلان (تيم)" / "مستني رد من فلان (عميل)" — null if no recipient was named. */
    public function recipientLabel(): ?string
    {
        return match ($this->recipient_type) {
            'team' => $this->recipientUser ? __(':name (الفريق)', ['name' => $this->recipientUser->name]) : null,
            'company' => $this->recipientContact ? __(':name (العميل)', ['name' => $this->recipientContact->name]) : null,
            default => null,
        };
    }
}
