<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\Ticket;
use App\Notifications\NotificationEvent;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** F17 — 1..10 per assigned role. Optional; it never blocks closing. */
class RatingController extends Controller
{
    public function store(Request $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('ratings.give'), 403);
        $this->authorize('view', $ticket);

        $data = $request->validate([
            // Role-based ratings (2026-07-24): you rate the person as the role
            // they hold on the ticket, so the box names a role_id.
            'role_id' => ['required', 'integer'],
            // 1..10, mirrored by a CHECK on the table. F17
            'score' => ['required', 'integer', 'between:1,10'],
            'comment' => ['nullable', 'string', 'max:255'],
        ], [
            'score.between' => 'التقييم لازم يكون من 1 لـ 10.',
        ], [
            'role_id' => 'الدور', 'score' => 'التقييم', 'comment' => 'التعليق',
        ]);

        // The ratee is whoever holds that role on the ticket. A role nobody
        // holds has no rating box, so a request naming one is forged.
        $rateeId = $ticket->assigneeIdForRole((int) $data['role_id']);

        if ($rateeId === null) {
            return back()->withErrors(['role_id' => 'مفيش حد متعيّن على الدور ده.']);
        }

        $rating = Rating::updateOrCreate(
            ['ticket_id' => $ticket->id, 'ratee_id' => $rateeId, 'role_id' => (int) $data['role_id']],
            // comment is nullable, so an omitted field never reaches $data at all
            // — reading it directly threw on every comment-less rating.
            ['score' => $data['score'], 'comment' => $data['comment'] ?? null, 'rated_by' => $request->user()->id]
        );

        $logger->log(
            action: 'rating.given',
            userId: $request->user()->id,
            subject: $rating,
            changes: [
                'ticket' => $ticket->ticket_number,
                'ratee' => $rateeId,
                'role_id' => (int) $data['role_id'],
                'score' => $data['score'],
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        // Only the person rated hears about it, never the whole circle: a
        // score is between the rater and the rated.
        app(NotificationService::class)->dispatchTo(
            $rateeId,
            $ticket,
            NotificationEvent::RatingGiven,
            "اتقيّمت بـ{$data['score']}/10 على {$ticket->ticket_number}",
            $request->user()->id,
        );

        return back()->with('status', 'تم حفظ التقييم.');
    }
}
