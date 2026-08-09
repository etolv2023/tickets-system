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
 * Five defences, in order:
 *   1. finfo reads the real bytes — the extension and browser type are both
 *      attacker-controlled, so neither is trusted.
 *   2. ClamAV scans the temp file before anything is written to permanent
 *      storage, so an infected upload never lands on disk at all.
 *   3. Files are stored under storage/app/private, outside the web root. Even a
 *      stored shell has no URL that could execute it.
 *   4. The name on disk is a uuid; the original name lives in the database and
 *      is only ever used as a download header.
 *   5. Images are decoded and re-encoded, which destroys anything hidden in the
 *      original bytes.
 *
 * Uploads arrive from two very different places, so they get two different sets
 * of rules. Staff are authenticated employees. The portal is gated by a
 * per-ticket password and nothing else, so a portal upload is treated as
 * hostile: a tighter type list, its own size cap, and no unscannable format
 * unless ClamAV is actually running.
 */
class AttachmentService
{
    /** F04.2: jpg, jpeg, png, gif, webp, pdf, mp4, webm, mov — and nothing else. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];

    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Video is stored as sent, not re-encoded, so it gets its own — much larger — cap. */
    public const MAX_VIDEO_BYTES = 200 * 1024 * 1024;

    private const THUMB_WIDTH = 320;

    /* A decoded image is width * height * 4 bytes in memory regardless of how
       small the file is — a 10KB "decompression bomb" can claim gigabytes. */
    private const MAX_PIXELS = 50_000_000;

    private const DISK = 'private';

    public function __construct(private readonly VirusScanner $scanner)
    {
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, TicketAttachment>
     */
    public function attachMany(Ticket $ticket, array $files, int $userId, ?TicketComment $comment = null): array
    {
        return array_map(
            fn (UploadedFile $file) => $this->attach($ticket, $file, $userId, $comment),
            array_values($files)
        );
    }

    /**
     * ★ (2026-08-02) An image pasted into the editor is stored as an attachment
     * (finfo, re-encode, size cap, uuid name — § 5) but has to APPEAR where it
     * was pasted, because the text around it is usually about that picture.
     *
     * The editor can't write the real URL: at paste time the attachment has no
     * id, and on the create form the ticket doesn't exist either. So it writes a
     * placeholder carrying a token it made up, posts the same tokens alongside
     * the files in upload order, and this swaps them for real URLs once the rows
     * exist. A token with no matching file — the user pasted then removed the
     * attachment — leaves the placeholder, which the purifier then drops as an
     * img with an unreachable src rather than leaving a live wrong link.
     *
     * @param  array<int, TicketAttachment>  $attachments  in upload order
     * @param  array<int, string>  $tokens  the editor's tokens, same order
     */
    public function resolveInlineImages(?string $html, array $attachments, array $tokens): ?string
    {
        if (blank($html) || $tokens === []) {
            return $html;
        }

        foreach (array_values($tokens) as $index => $token) {
            $attachment = $attachments[$index] ?? null;

            if ($attachment === null || ! preg_match('/^[A-Za-z0-9-]{6,64}$/', (string) $token)) {
                continue;
            }

            // ★ (2026-08-03) Matched on the PATH, not on the absolute url.
            //
            // The two halves of this placeholder are built from different
            // sources: the browser writes window.location.origin (editor.js),
            // this side used to look for config('app.url'). They agree only when
            // the two strings are character-for-character identical, and on
            // production they were not — APP_URL said http://tickets.etolv.net
            // while the host 301s every request to https, so the browser could
            // only ever be on https. str_replace found nothing, every inline
            // image kept a /attachments/pending/… src, and there is no such
            // route: the picture died on the way in. Verified by printing both
            // strings on the server, not inferred.
            //
            // A scheme, a host, a port or a proxy in front can now all differ
            // without breaking it — the token is the identity, and the token is
            // already validated as [A-Za-z0-9-] above, so it is regex-safe.
            $html = preg_replace(
                '#(?:[a-z][a-z0-9+.-]*://[^"\'\s<>]*?)?/attachments/pending/' . preg_quote($token, '#') . '#i',
                str_replace('$', '\\$', route('attachments.view', $attachment)),
                $html
            );

            // The purifier runs on the description BEFORE this, and fills a
            // missing alt from the last path segment — which at that point is
            // the token. Left alone, every inline image ends up labelled with an
            // internal id, read aloud by a screen reader. Swap it for the file's
            // own name in the same pass.
            $html = str_replace(
                'alt="' . $token . '"',
                'alt="' . e($attachment->original_name) . '"',
                $html
            );
        }

        return $html;
    }

    /** The placeholder the editor writes. Absolute, so it survives the purifier's scheme whitelist. */
    public static function pendingUrl(string $token): string
    {
        return rtrim(config('app.url'), '/') . '/attachments/pending/' . $token;
    }

    /**
     * The description is stored once and the customer portal renders that same
     * HTML — so an image pasted into it would appear on the client's side of the
     * wall too. It must not.
     *
     * F24 draws the line at the comment: a file reaches the customer exactly
     * when it hangs off a public reply, which makes "share this" the same
     * decision as "is this reply internal?". An image pasted into the
     * DESCRIPTION is a ticket-level attachment — working material — and
     * PortalController deliberately never loads those.
     *
     * So the image is removed rather than re-pointed at the portal's route:
     * rewriting it would have quietly published a file the rule hides. A short
     * marker is left in its place, because the sentence around it usually says
     * "as in the screenshot" and a silent gap reads as a rendering fault.
     */
    public function stripInlineImagesForPortal(?string $html): ?string
    {
        if (blank($html)) {
            return $html;
        }

        // Inline replacement, not a block: the img sits inside a <p>, and
        // wrapping it in another one nests paragraphs — invalid HTML that
        // browsers silently split, breaking the surrounding sentence.
        return preg_replace(
            '#<img[^>]*>#i',
            '<em>[صورة داخلية]</em>',
            $html
        );
    }

    /**
     * A client's upload from the portal. Same storage pipeline, stricter gate:
     * the portal's own type list and size cap, mandatory scanning, and no
     * uploader id — the person on the other end is a contact, not a user.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, TicketAttachment>
     */
    public function attachFromPortal(Ticket $ticket, array $files, TicketComment $comment): array
    {
        $max = (int) config('uploads.portal.max_per_comment', 3);

        if (count($files) > $max) {
            throw new RuntimeException("مسموح بـ {$max} ملفات في الرسالة الواحدة.");
        }

        return array_map(
            fn (UploadedFile $file) => $this->attach($ticket, $file, null, $comment, portal: true),
            array_values($files)
        );
    }

    public function attach(
        Ticket $ticket,
        UploadedFile $file,
        ?int $userId,
        ?TicketComment $comment = null,
        bool $portal = false,
    ): TicketAttachment {
        $mime = $portal ? $this->assertAllowedFromPortal($file) : $this->assertAllowed($file);
        $extension = $portal ? $this->portalTypes()[$mime] : self::ALLOWED[$mime];

        // Scanned here, on the temp file, before a single byte is written to
        // permanent storage. For a portal upload an unreachable daemon is a
        // refusal, not a shrug.
        $this->scan($file, strict: $portal);

        $dir = "tickets/{$ticket->id}";
        $stored = "{$dir}/" . Str::uuid() . ".{$extension}";
        $thumb = null;

        if (! str_starts_with($mime, 'image/')) {
            // A PDF needs a rasteriser and a video needs a transcoder, so
            // neither can be rewritten on the way in. They are stored as sent —
            // safe because they are never served inline, never executed, and
            // (from the portal) never accepted unless ClamAV cleared them.
            // Streamed rather than read whole: a 10MB video should not become
            // a 10MB string in memory.
            Storage::disk(self::DISK)->writeStream($stored, fopen($file->getRealPath(), 'rb'));
        } else {
            // ★ (2026-08-04) Wrapped, because Intervention's own failure message
            // is English and went straight to the user's screen — the upload
            // panel would answer an Arabic form with "Unable to decode input"
            // (§ 7.5). getimagesize() has already passed by this point, so a
            // failure here means a file whose header is a valid image and whose
            // body is not: truncated, or deliberately malformed.
            try {
                $image = ImageManager::gd()->read($file->getRealPath());

                // Re-encoding is what kills a payload smuggled in EXIF or a
                // comment segment: the bytes we write are ours, not the
                // uploader's.
                $encoded = (string) $image->encodeByExtension($extension, quality: 85);

                // The gallery renders thumbnails; a 4MB original never reaches
                // a list. § 4.8
                $thumbnail = (string) $image
                    ->scaleDown(width: self::THUMB_WIDTH)
                    ->encodeByExtension($extension, quality: 75);
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    "«{$file->getClientOriginalName()}» مقدرناش نقراها كصورة — الملف غالباً ناقص أو باظ."
                );
            }

            Storage::disk(self::DISK)->put($stored, $encoded);

            $thumb = "{$dir}/thumb-" . Str::uuid() . ".{$extension}";
            Storage::disk(self::DISK)->put($thumb, $thumbnail);
        }

        try {
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
        } catch (\Throwable $e) {
            // The bytes are already on disk at this point. Without this, a
            // failed insert leaves a file nothing in the database refers to —
            // invisible, undeletable through the UI, and still taking space.
            Storage::disk(self::DISK)->delete(array_filter([$stored, $thumb]));

            throw $e;
        }
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
        $mime = $this->sniff($file);

        if (! array_key_exists($mime, self::ALLOWED)) {
            throw new RuntimeException(
                "«{$file->getClientOriginalName()}» نوعه الحقيقي {$mime} — مرفوض. المسموح: صور أو PDF أو فيديو."
            );
        }

        $limit = str_starts_with($mime, 'video/') ? self::MAX_VIDEO_BYTES : self::MAX_BYTES;

        if ($file->getSize() > $limit) {
            $mb = (int) ($limit / 1024 / 1024);

            throw new RuntimeException("«{$file->getClientOriginalName()}» أكبر من {$mb} ميجا.");
        }

        $this->assertDecodable($file, $mime);

        return $mime;
    }

    /**
     * The portal's gate. Narrower list, its own cap, and the unscannable
     * formats are only on the menu while ClamAV is actually running.
     *
     * @return string the real mime type
     */
    private function assertAllowedFromPortal(UploadedFile $file): string
    {
        $max = (int) config('uploads.portal.max_bytes', 10 * 1024 * 1024);

        if ($file->getSize() > $max) {
            $mb = round($max / 1024 / 1024, 1);

            throw new RuntimeException("الملف أكبر من {$mb} ميجا.");
        }

        $mime = $this->sniff($file);
        $allowed = $this->portalTypes();

        if (! array_key_exists($mime, $allowed)) {
            throw new RuntimeException(
                "«{$file->getClientOriginalName()}» نوعه الحقيقي {$mime} — مرفوض."
            );
        }

        $this->assertDecodable($file, $mime);

        return $mime;
    }

    /** The accept="" list and the hint under the portal's file input. */
    public function portalUploadHint(): array
    {
        $types = $this->portalTypes();
        $mb = round(((int) config('uploads.portal.max_bytes', 10 * 1024 * 1024)) / 1024 / 1024, 1);
        $count = (int) config('uploads.portal.max_per_comment', 3);

        $labels = collect($types)->values()
            ->map(fn (string $ext) => match ($ext) {
                'jpg', 'png', 'gif', 'webp' => 'صور',
                'pdf' => 'PDF',
                'mp4', 'webm', 'mov' => 'فيديو',
                default => $ext,
            })
            ->unique()
            ->implode(' · ');

        return [
            'accept' => implode(',', array_keys($types)),
            'hint' => "{$labels} · حد أقصى {$mb} ميجا للملف · {$count} ملفات",
        ];
    }

    /**
     * What the portal accepts right now. The unscannable half of the list is
     * withheld unless the scanner is switched on, so turning ClamAV off can
     * never silently widen what an anonymous visitor may upload.
     *
     * @return array<string, string> mime => extension
     */
    private function portalTypes(): array
    {
        $always = (array) config('uploads.portal.always', []);

        return $this->scanner->isEnabled()
            ? $always + (array) config('uploads.portal.when_scanned', [])
            : $always;
    }

    /** Trusts the bytes, never the extension or the browser-supplied type. */
    private function sniff(UploadedFile $file): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
    }

    /**
     * An image must actually decode, and must not be a decompression bomb.
     * This catches a file that merely starts with the right magic bytes.
     */
    private function assertDecodable(UploadedFile $file, string $mime): void
    {
        if (! str_starts_with($mime, 'image/')) {
            return;
        }

        $size = @getimagesize($file->getRealPath());

        if ($size === false) {
            throw new RuntimeException("«{$file->getClientOriginalName()}» مش صورة سليمة.");
        }

        if (($size[0] * $size[1]) > self::MAX_PIXELS) {
            throw new RuntimeException("«{$file->getClientOriginalName()}» أبعادها كبيرة جداً.");
        }
    }

    /**
     * @param  bool  $strict  when true, a scanner that cannot be reached is a
     *                        refusal rather than a pass
     */
    private function scan(UploadedFile $file, bool $strict): void
    {
        if (! $this->scanner->isEnabled()) {
            return;
        }

        $name = $file->getClientOriginalName();

        if ($strict || config('uploads.clamav.strict', true)) {
            $this->scanner->assertClean($file->getRealPath(), $name);

            return;
        }

        // Lenient path: a known employee is uploading and the daemon is down.
        // A positive hit still stops the upload; only "unknown" is tolerated.
        $verdict = $this->scanner->scan($file->getRealPath());

        if (is_string($verdict)) {
            throw new RuntimeException("«{$name}» فيه برمجية خبيثة ({$verdict}) — اترفض.");
        }
    }
}
