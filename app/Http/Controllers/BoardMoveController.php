<?php

namespace App\Http\Controllers;

use App\Casts\TicketStatusValue;
use App\Http\Requests\BoardMoveRequest;
use App\Models\Ticket;
use App\Services\ActivityLogger;
use App\Services\TicketWorkflowService;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * Dragging a card between board columns (F12.1).
 *
 * Separate from TicketWorkflowController::changeStatus for the same reason the
 * calendar's drag got its own endpoint: different input (a column key, not a
 * status), different failure contract (422 + a message the browser uses to
 * snap the card back, not a redirect), and different side effects — a drag
 * never names a recipient, so it must not run createFollowUpSubtask().
 *
 * Keeping changeStatus byte-identical is what makes this carry zero regression
 * risk for the panel and the status dropdown.
 */
class BoardMoveController extends Controller
{
    public function __construct(
        private readonly TicketWorkflowService $workflow,
    ) {
    }

    public function move(BoardMoveRequest $request, Ticket $ticket, ActivityLogger $logger): JsonResponse
    {
        $column = BoardController::COLUMNS[$request->validated('column')];
        $actorId = $request->user()->id;

        // Already in this bucket. A "reopened" card dropped inside "مسندة إليّ"
        // is a no-op, not a transition to assigned — transition()'s own
        // from === to check would not have caught that, because reopened →
        // assigned is a real and legal move, just not the one that was asked
        // for. No history row, no notification.
        if (in_array($ticket->status->value, $column['statuses'], true)) {
            return response()->json(['ok' => true, 'moved' => false]);
        }

        try {
            $this->apply($ticket, $request->validated('column'), $column['statuses'][0], $actorId);
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $ticket->refresh();

        // The move was accepted but the ticket did not land in the column it
        // was dropped into. That is not a failure: finish() only moves a ticket
        // to dev_done once EVERY side is done, so a frontend dev dropping into
        // "تم التطوير" while the backend is still working has genuinely
        // finished their own work — the ticket just isn't there yet. Without
        // saying so, the card silently bounces back and reads as a bug.
        $landed = in_array($ticket->status->value, $column['statuses'], true);

        $logger->log(
            action: 'ticket.status_changed',
            userId: $actorId,
            subject: $ticket,
            changes: ['to' => $ticket->status->value, 'via' => 'board'],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'ok' => true,
            'moved' => true,
            'landed' => $landed,
            'status' => $ticket->status->value,
            'label' => $ticket->status->label(),
            'message' => $landed
                ? null
                : "اتسجّل إن شغلك خلص، بس التذكرة لسه في «{$ticket->status->label()}» — فيه جهة تانية لسه شغالة.",
        ]);
    }

    /**
     * in_progress and dev_done are computed from ticket_work_logs, never
     * written by hand — so a drop into those columns presses the same button
     * the card already carries. Writing the status directly would leave the
     * work logs behind and hand the point engine a ticket that claims to be
     * finished by nobody.
     */
    private function apply(Ticket $ticket, string $column, string $target, int $actorId): void
    {
        // Dropping into "جاري العمل" means "this is what I'm on now" — whether
        // the side hasn't started yet or was finished and is being taken back.
        // Both readings are the same gesture, so the log's state picks the verb
        // rather than the user having to.
        if ($column === 'in_progress') {
            $this->drive($ticket, $actorId, ['pending' => 'start', 'done' => 'resume']);

            return;
        }

        if ($column === 'dev_done') {
            $this->drive($ticket, $actorId, ['in_progress' => 'finish']);

            return;
        }

        // Dropping back into "مسندة إليّ" means "I'm not on this now", so the
        // commitment comes back down with the badge. Writing the status alone
        // left every side still saying in_progress: the card kept offering
        // خلصت for work it thought had started, and "جاري العمل" then refused
        // to take the card back because no log was pending or done.
        //
        // unstart() moves the ticket itself once nobody is left working, so the
        // transition below is only needed when it didn't (another side is still
        // running, or nothing was running to begin with).
        if ($column === 'assigned') {
            $this->drive($ticket, $actorId, ['in_progress' => 'unstart'], quiet: true);

            if (in_array($ticket->refresh()->status->value, BoardController::COLUMNS['assigned']['statuses'], true)) {
                return;
            }
        }

        $this->workflow->transition($ticket, TicketStatusValue::for($target), $actorId, 'نقل من البورد');
    }

    /**
     * Presses بدأت / خلصت / رجّع on EVERY side this person owns, not just the
     * first.
     *
     * A "both" developer holds two work logs on one ticket. Acting on only one
     * of them left the other behind: the frontend log went done while the
     * backend stayed pending, and the next drag then failed with "الشغل ده
     * مبدوء بالفعل" because it kept re-reading the same finished log.
     *
     * @param  array<string, string>  $verbs  work-log status => service method
     * @param  bool  $quiet  nothing to act on is a valid outcome, not a refusal
     */
    private function drive(Ticket $ticket, int $actorId, array $verbs, bool $quiet = false): void
    {
        $logs = $ticket->workLogs->where('user_id', $actorId);

        if ($logs->isEmpty()) {
            throw new DomainException('مفيش شغل مسند لك على التذكرة دي — استخدم صفحة التذكرة.');
        }

        $actionable = $logs->filter(fn ($log) => isset($verbs[$log->status]));

        if ($actionable->isEmpty() && $quiet) {
            return;
        }

        if ($actionable->isEmpty()) {
            throw new DomainException(isset($verbs['in_progress'])
                ? 'مفيش شغل شغّال دلوقتي تقفله — اسحبها لـ«جاري العمل» الأول.'
                : 'شغلك على التذكرة دي في المكان الصح بالفعل.');
        }

        foreach ($actionable as $log) {
            // Every one of these reaches for $log->ticket. The log came out of
            // an eager-loaded collection, so that relation is not set on it —
            // and preventLazyLoading() turns the read into an exception. Hand it
            // the ticket we already have rather than paying for a second query.
            $log->setRelation('ticket', $ticket);

            $this->workflow->{$verbs[$log->status]}($log, $actorId);
        }
    }
}
