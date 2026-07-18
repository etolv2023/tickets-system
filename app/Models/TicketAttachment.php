<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * A human file size, in Arabic.
     *
     * Deliberately not Number::fileSize(): that routes through the intl
     * extension, which is off by default on Windows PHP builds, so a missing
     * extension took the whole ticket page down with a 500 over a label that
     * reads "١٫٢ ميجا". A unit table cannot fail.
     */
    public function sizeLabel(): string
    {
        $units = ['بايت', 'كيلو', 'ميجا', 'جيجا'];
        $size = max(0, (int) $this->size_bytes);
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        // Bytes are always whole; anything larger reads better with one decimal.
        $value = $unit === 0 ? (string) $size : number_format($size, 1);

        return $value . ' ' . $units[$unit];
    }
}
