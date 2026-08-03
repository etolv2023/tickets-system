<?php

namespace App\Http\Controllers;

use App\Http\Requests\Portal\PortalCommentRequest;
use App\Http\Requests\Portal\PortalVerifyRequest;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Services\AttachmentService;
use App\Services\TicketService;
use App\Support\PortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * F24 — the client portal. One ticket, no login, a per-ticket password. The
 * client never sees internal notes, attachments, assignees, or any other
 * ticket in the system — this is the entire surface they're allowed to reach.
 */
class PortalController extends Controller
{
    private const MAX_ATTEMPTS = 10;

    /** Files a single ticket+IP may push through the portal in an hour. */
    private const MAX_UPLOADS_PER_HOUR = 20;

    public function __construct(
        private readonly TicketService $tickets,
        private readonly AttachmentService $attachments,
    ) {
    }

    public function show(Request $request, ?string $ticketNumber = null): View
    {
        $ticket = $ticketNumber !== null
            ? Ticket::where('ticket_number', $ticketNumber)->first()
            : null;

        if ($ticket !== null && $this->hasAccess($request, $ticket)) {
            $ticket->load(['company:id,name', 'requester:id,name']);

            // authorName() reads both sides, so both have to be eager-loaded —
            // without this the portal 500s under preventLazyLoading.
            //
            // Attachments come with the comments, and ONLY with the comments.
            // A file is visible to the client exactly when it hangs off a
            // public comment: that makes "share this with the customer" the
            // same decision as "is this reply internal?", with no second
            // switch to forget. Ticket-level attachments stay hidden — they
            // are working material, not correspondence. F24
            $comments = $ticket->comments()
                ->with(['user:id,name', 'contact:id,name', 'attachments'])
                ->where('is_internal', false)
                ->orderBy('created_at')
                ->get();

            $upload = $this->attachments->portalUploadHint();

            return view('portal.show', [
                'ticket' => $ticket,
                // F24 again: the description can now carry inline images, and a
                // ticket-level attachment is not correspondence.
                'descriptionForPortal' => $this->attachments->stripInlineImagesForPortal($ticket->description),
                'comments' => $comments,
                'uploadAccept' => $upload['accept'],
                'uploadHint' => $upload['hint'],
            ]);
        }

        return view('portal.gate', ['ticketNumber' => $ticketNumber]);
    }

    public function verify(PortalVerifyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $key = $this->throttleKey($data['ticket_number'], $request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'password' => 'محاولات كتير غلط. جرب تاني بعد ' . RateLimiter::availableIn($key) . ' ثانية.',
            ]);
        }

        $ticket = Ticket::where('ticket_number', $data['ticket_number'])->first();

        // Same message whether the ticket number is wrong or the password is —
        // otherwise a wrong password on a real ticket number leaks that the
        // number exists.
        if ($ticket === null || ! $ticket->checkPortalPassword($data['password'])) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['password' => 'رقم التذكرة أو الباسورد غلط.']);
        }

        RateLimiter::clear($key);

        $request->session()->regenerate();

        // The grant is tied to a browser-session cookie and an idle window, so
        // closing the browser ends it. See PortalAccess.
        PortalAccess::grant($request, $ticket->id);

        return redirect()->route('portal.show', $ticket->ticket_number);
    }

    public function comment(PortalCommentRequest $request, string $ticketNumber): RedirectResponse
    {
        $ticket = Ticket::where('ticket_number', $ticketNumber)->firstOrFail();

        // An upload endpoint reachable with only a ticket password needs its
        // own ceiling, independent of the per-request validation.
        $key = 'portal-upload:' . $ticket->id . '|' . $request->ip();

        if ($request->hasFile('files') && RateLimiter::tooManyAttempts($key, self::MAX_UPLOADS_PER_HOUR)) {
            throw ValidationException::withMessages([
                'files' => 'رفعت ملفات كتير. جرّب تاني بعد ' . ceil(RateLimiter::availableIn($key) / 60) . ' دقيقة.',
            ]);
        }

        $comment = $this->tickets->addPortalComment($ticket, $request->validated('body'));

        if ($request->hasFile('files')) {
            try {
                $this->attachments->attachFromPortal($ticket, $request->file('files'), $comment);
                RateLimiter::hit($key, 3600);
            } catch (RuntimeException $e) {
                // RuntimeException is what the upload pipeline throws for a
                // rejection it wrote itself — "too big", "wrong type", "virus
                // found". Those are in Arabic and are safe to repeat back.
                throw ValidationException::withMessages(['files' => $e->getMessage()]);
            } catch (Throwable $e) {
                // Anything else is ours, not theirs. A client must never see a
                // SQL statement, a file path or a stack frame.
                report($e);

                throw ValidationException::withMessages([
                    'files' => 'حصلت مشكلة وإحنا بنحفظ الملف. الرسالة اتسجلت، جرّب ترفعه تاني.',
                ]);
            }
        }

        return redirect()->route('portal.show', $ticketNumber)->with('status', 'تم إرسال ردك.');
    }

    private function hasAccess(Request $request, Ticket $ticket): bool
    {
        return PortalAccess::allows($request, $ticket->id);
    }

    /**
     * A client downloading a file off their own ticket.
     *
     * Deliberately a separate route from the staff one rather than a loosened
     * version of it: that endpoint authorises through TicketPolicy and assumes
     * an authenticated employee, and widening it to also understand portal
     * sessions would put the internal attachments one boolean away from an
     * anonymous visitor. This route can only ever reach the client-visible set.
     */
    public function attachment(Request $request, string $ticketNumber, TicketAttachment $attachment): BinaryFileResponse
    {
        $this->authorizePortalAttachment($request, $ticketNumber, $attachment);

        $path = $this->attachments->path($attachment->stored_name);

        // A row whose file is gone is a 404, not a 500 — the client asked for
        // something that isn't there, which is exactly what 404 means.
        abort_unless(is_file($path), 404);

        return response()->file($path, $this->attachmentHeaders($attachment));
    }

    public function attachmentThumbnail(Request $request, string $ticketNumber, TicketAttachment $attachment): BinaryFileResponse
    {
        $this->authorizePortalAttachment($request, $ticketNumber, $attachment);

        abort_unless($attachment->isImage() && $attachment->thumbnail_name !== null, 404);

        $path = $this->attachments->path($attachment->thumbnail_name);

        abort_unless(is_file($path), 404);

        return response()->file(
            $path,
            ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff']
        );
    }

    /**
     * Four conditions, all required. A 404 rather than a 403 throughout: a
     * visitor holding one ticket's password learns nothing about what other
     * attachments exist.
     */
    private function authorizePortalAttachment(Request $request, string $ticketNumber, TicketAttachment $attachment): void
    {
        $ticket = Ticket::where('ticket_number', $ticketNumber)->first();

        abort_if($ticket === null, 404);
        abort_unless($this->hasAccess($request, $ticket), 404);
        abort_unless($attachment->ticket_id === $ticket->id, 404);

        // The visibility rule itself: on a comment, and that comment is public.
        $comment = $attachment->comment_id === null
            ? null
            : TicketComment::find($attachment->comment_id);

        abort_if($comment === null || $comment->is_internal, 404);
    }

    /**
     * @return array<string, string>
     */
    private function attachmentHeaders(TicketAttachment $attachment): array
    {
        // Arabic filenames need RFC 5987: raw UTF-8 in a header arrives as
        // mojibake, so send an ASCII fallback plus the encoded real name.
        $name = $attachment->original_name;
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?: 'file';

        return [
            'Content-Type' => $attachment->mime_type,
            // Never inline. A client's own upload coming back at them must not
            // be able to render in our origin.
            'Content-Disposition' => sprintf(
                'attachment; filename="%s"; filename*=UTF-8\'\'%s',
                addslashes($ascii),
                rawurlencode($name)
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Frame-Options' => 'DENY',
        ];
    }

    /** Keyed by ticket number AND ip — same shape as login's throttle. */
    private function throttleKey(string $ticketNumber, Request $request): string
    {
        return Str::transliterate(Str::lower($ticketNumber) . '|' . $request->ip());
    }
}
