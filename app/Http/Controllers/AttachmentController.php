<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only way a stored file reaches a browser (F04.2, CLAUDE.md § 5).
 *
 * Files live outside the web root, so there is no url that bypasses this. Every
 * hit authorises against the parent ticket first — a signed link would prove
 * someone holds a url, not that they may read this customer's ticket.
 */
class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments)
    {
    }

    /** Forced download. Never rendered inline, whatever the file claims to be. */
    public function download(Request $request, TicketAttachment $attachment): BinaryFileResponse
    {
        $this->authorizeAttachment($request, $attachment);

        return response()
            ->download(
                $this->attachments->path($attachment->stored_name),
                $attachment->original_name,
                $this->hardenedHeaders($attachment->mime_type),
            );
    }

    /**
     * The gallery's <img src>. Serves the generated thumbnail — our own bytes,
     * re-encoded at upload — and never the original the user sent.
     */
    public function thumbnail(Request $request, TicketAttachment $attachment): Response
    {
        $this->authorizeAttachment($request, $attachment);

        abort_unless($attachment->isImage() && $attachment->thumbnail_name !== null, 404);

        return response()->file(
            $this->attachments->path($attachment->thumbnail_name),
            $this->hardenedHeaders($attachment->mime_type) + ['Cache-Control' => 'private, max-age=3600'],
        );
    }

    /** Full-size image for the lightbox, still behind the same authorisation. */
    public function view(Request $request, TicketAttachment $attachment): Response
    {
        $this->authorizeAttachment($request, $attachment);

        abort_unless($attachment->isImage(), 404);

        return response()->file(
            $this->attachments->path($attachment->stored_name),
            $this->hardenedHeaders($attachment->mime_type) + ['Cache-Control' => 'private, max-age=3600'],
        );
    }

    private function authorizeAttachment(Request $request, TicketAttachment $attachment): void
    {
        $attachment->loadMissing('ticket');

        $this->authorize('download', $attachment->ticket);
    }

    /**
     * @return array<string, string>
     */
    private function hardenedHeaders(string $mime): array
    {
        return [
            'Content-Type' => $mime,
            // Stops a browser sniffing a "png" into something executable.
            'X-Content-Type-Options' => 'nosniff',
            // An uploaded svg or html could otherwise run script on our origin.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'X-Frame-Options' => 'DENY',
        ];
    }
}
