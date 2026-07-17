<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;
use finfo;

/**
 * Secure upload for ticket files (F04.2, CLAUDE.md § 5).
 *
 * Four defences, in order:
 *   1. finfo reads the real bytes — the extension and browser type are both
 *      attacker-controlled, so neither is trusted.
 *   2. Files are stored under storage/app/private, outside the web root. Even a
 *      stored shell has no URL that could execute it.
 *   3. The name on disk is a uuid; the original name lives in the database and
 *      is only ever used as a download header.
 *   4. Images are decoded and re-encoded, which destroys anything hidden in the
 *      original bytes.
 */
class AttachmentService
{
    /** F04.2: jpg, jpeg, png, gif, webp, pdf — and nothing else. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_PER_TICKET = 10;

    private const THUMB_WIDTH = 320;

    private const DISK = 'private';

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, TicketAttachment>
     */
    public function attachMany(Ticket $ticket, array $files, int $userId, ?TicketComment $comment = null): array
    {
        $existing = $ticket->attachments()->count();

        if ($existing + count($files) > self::MAX_PER_TICKET) {
            $room = max(0, self::MAX_PER_TICKET - $existing);

            throw new RuntimeException(
                'الحد ' . self::MAX_PER_TICKET . " مرفق للتذكرة. فاضل مكان لـ {$room} بس."
            );
        }

        return array_map(
            fn (UploadedFile $file) => $this->attach($ticket, $file, $userId, $comment),
            array_values($files)
        );
    }

    public function attach(Ticket $ticket, UploadedFile $file, int $userId, ?TicketComment $comment = null): TicketAttachment
    {
        $mime = $this->assertAllowed($file);
        $extension = self::ALLOWED[$mime];

        $dir = "tickets/{$ticket->id}";
        $stored = "{$dir}/" . Str::uuid() . ".{$extension}";
        $thumb = null;

        if ($mime === 'application/pdf') {
            // A PDF can't be re-encoded without a rasteriser, so it is stored
            // as-is — safe because it is never served inline and never executed.
            Storage::disk(self::DISK)->put($stored, file_get_contents($file->getRealPath()));
        } else {
            $image = ImageManager::gd()->read($file->getRealPath());

            // Re-encoding is what kills a payload smuggled in EXIF or a comment
            // segment: the bytes we write are ours, not the uploader's.
            Storage::disk(self::DISK)->put($stored, (string) $image->encodeByExtension($extension, quality: 85));

            // The gallery renders thumbnails; a 4MB original never reaches a list. § 4.8
            $thumb = "{$dir}/thumb-" . Str::uuid() . ".{$extension}";
            Storage::disk(self::DISK)->put(
                $thumb,
                (string) $image->scaleDown(width: self::THUMB_WIDTH)->encodeByExtension($extension, quality: 75)
            );
        }

        return TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment?->id,
            'stored_name' => $stored,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'thumbnail_name' => $thumb,
            'mime_type' => $mime,
            'size_bytes' => Storage::disk(self::DISK)->size($stored),
            'uploaded_by' => $userId,
        ]);
    }

    public function delete(TicketAttachment $attachment): void
    {
        Storage::disk(self::DISK)->delete(array_filter([
            $attachment->stored_name,
            $attachment->thumbnail_name,
        ]));

        $attachment->delete();
    }

    public function path(string $storedName): string
    {
        return Storage::disk(self::DISK)->path($storedName);
    }

    /** @return string the real mime type */
    private function assertAllowed(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('الملف أكبر من 5 ميجا.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        if (! array_key_exists($mime, self::ALLOWED)) {
            throw new RuntimeException(
                "«{$file->getClientOriginalName()}» نوعه الحقيقي {$mime} — مرفوض. المسموح: صور أو PDF."
            );
        }

        // A real image must also decode. This catches a file that merely starts
        // with the right magic bytes.
        if ($mime !== 'application/pdf' && @getimagesize($file->getRealPath()) === false) {
            throw new RuntimeException("«{$file->getClientOriginalName()}» مش صورة سليمة.");
        }

        return $mime;
    }
}
