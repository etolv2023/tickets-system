<?php

namespace App\Http\Controllers;

use App\Enums\PointSide;
use App\Models\Rating;
use App\Models\Ticket;
use App\Notifications\NotificationEvent;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** F17 — 1..10 per assigned role. Optional; it never blocks closing. */
class RatingController extends Controller
{
    public function store(Request $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('ratings.give'), 403);
        $this->authorize('view', $ticket);

        $data = $request->validate([
            'side' => ['required', Rule::enum(PointSide::class)],
            // 1..10, mirrored by a CHECK on the table. F17
            'score' => ['required', 'integer', 'between:1,10'],
            'comment' => ['nullable', 'string', 'max:255'],
        ], [
            'score.between' => 'التقييم لازم يكون من 1 لـ 10.',
        ], [
            'side' => 'الجهة', 'score' => 'التقييم', 'comment' => 'التعليق',
        ]);

        $side = PointSide::from($data['side']);
        $rateeId = $ticket->{$side->participantColumn()};

        // F17: a side with nobody on it has no rating box at all, so a request
        // naming one is forged.
        if ($rateeId === null) {
            return back()->withErrors(['side' => 'مفيش حد متعيّن على الجهة دي.']);
        }

        $rating = Rating::updateOrCreate(
            ['ticket_id' => $ticket->id, 'ratee_id' => $rateeId, 'side' => $side->value],
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
                'side' => $side->value,
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
