<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class TicketAttachment extends Model
{
    protected $fillable = [
        'ticket_id', 'comment_id', 'stored_name', 'original_name',
        'thumbnail_name', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'comment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function sizeLabel(): string
    {
        return Number::fileSize($this->size_bytes, precision: 1);
    }
}
