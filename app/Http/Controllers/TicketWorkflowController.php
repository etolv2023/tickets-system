<?php

namespace App\Http\Controllers;

use App\Casts\TicketStatusValue;
use App\Http\Requests\Tickets\AssignTicketRequest;
use App\Http\Requests\Tickets\ChangeTicketStatusRequest;
use App\Models\Ticket;
use App\Models\TicketWorkLog;
use App\Services\ActivityLogger;
use App\Services\TicketWorkflowService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Everything that moves a ticket (F06, F07, F15, F16). The rules live in
 * TicketWorkflowService; this only authorises and reports.
 */
class TicketWorkflowController extends Controller
{
    public function __construct(private readonly TicketWorkflowService $workflow)
    {
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        $before = $ticket->roleAssignments()->pluck('user_id', 'role_id')->all();
        $roleAssignments = $request->validated('role_assignments') ?? [];

        try {
            $this->workflow->assign($ticket, $roleAssignments, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['assign' => $e->getMessage()]);
        }

        $logger->log(
            action: 'ticket.assigned',
            userId: $request->user()->id,
            subject: $ticket,
            changes: [
                'from' => $before,
                'to' => $ticket->roleAssignments()->pluck('user_id', 'role_id')->all(),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تم التوزيع.');
    }

    public function start(Request $request, Ticket $ticket, TicketWorkLog $workLog): RedirectResponse
    {
        $this->authorizeLog($request, $ticket, $workLog);

        try {
            $this->workflow->start($workLog, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['worklog' => $e->getMessage()]);
        }

        return back()->with('status', 'بالتوفيق — الشغل بدأ.');
    }

    public function finish(Request $request, Ticket $ticket, TicketWorkLog $workLog): RedirectResponse
    {
        $this->authorizeLog($request, $ticket, $workLog);

        try {
            $this->workflow->finish($workLog, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['worklog' => $e->getMessage()]);
        }

        return back()->with('status', 'تمام — سجّلنا إنك خلصت.');
    }

    /** F15 */
    public function approve(Request $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('approve', $ticket);

        $this->workflow->approve($ticket, $request->user()->id);

        $logger->log(
            action: 'ticket.approved',
            userId: $request->user()->id,
            subject: $ticket,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تمت الموافقة — التذكرة بقت جاهزة للتوزيع.');
    }

    public function reject(Request $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('approve', $ticket);

        // A rejection without a reason is just a wall. F15
        $data = $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            ['reason.required' => 'لازم تكتب سبب الرفض.'],
            ['reason' => 'سبب الرفض']
        );

        $this->workflow->reject($ticket, $request->user()->id, $data['reason']);

        $logger->log(
            action: 'ticket.rejected',
            userId: $request->user()->id,
            subject: $ticket,
            changes: ['reason' => $data['reason']],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تم رفض التذكرة.');
    }

    /** F16: the tester confirms. */
    public function verify(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('resolve', $ticket);

        try {
            $this->workflow->transition($ticket, TicketStatusValue::for('resolved'), $request->user()->id, 'تم التأكيد');
        } catch (DomainException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return back()->with('status', 'تم تعليم التذكرة كمحلولة.');
    }

    /** F16: the tester sends it back — with a reason, always. */
    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('reopen', $ticket);

        $data = $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            ['reason.required' => 'لازم تكتب سبب الارتجاع عشان المبرمج يعرف يصلح إيه.'],
            ['reason' => 'سبب الارتجاع']
        );

        try {
            $this->workflow->transition($ticket, TicketStatusValue::for('reopened'), $request->user()->id, $data['reason']);
            // The work starts over, so the commitments reopen with it.
            $ticket->workLogs()->update(['status' => 'in_progress', 'finished_at' => null]);
        } catch (DomainException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return back()->with('status', 'رجّعنا التذكرة للمبرمجين.');
    }

    public function notifyClient(Request $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('notifyClient', $ticket);

        $this->workflow->markClientNotified($ticket, $request->user()->id);

        $logger->log(
            action: 'ticket.client_notified',
            userId: $request->user()->id,
            subject: $ticket,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'سجّلنا إن العميل اتبلغ.');
    }

    /** F06: the manual "غيّر الحالة" action, with an optional "waiting on" recipient. */
    public function changeStatus(ChangeTicketStatusRequest $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validated();

        $recipient = ($data['recipient_type'] ?? null) === null ? null : [
            'type' => $data['recipient_type'],
            'user_id' => $data['recipient_user_id'] ?? null,
            'contact_id' => $data['recipient_contact_id'] ?? null,
        ];

        try {
            $this->workflow->changeStatus(
                $ticket,
                TicketStatusValue::for($data['to_status']),
                $request->user()->id,
                $data['note'] ?? null,
                $recipient,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        $logger->log(
            action: 'ticket.status_changed',
            userId: $request->user()->id,
            subject: $ticket,
            changes: ['to' => $data['to_status'], 'recipient' => $recipient],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'اتغيّرت الحالة.');
    }

    /** F24: only ever readable once, right after (re)generating. Sensitive — logged. */
    public function regeneratePortalPassword(Request $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $plaintext = $ticket->generatePortalPassword();

        $logger->log(
            action: 'ticket.portal_password_regenerated',
            userId: $request->user()->id,
            subject: $ticket,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('portalPassword', $plaintext);
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('close', $ticket);

        try {
            $this->workflow->close($ticket, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return back()->with('status', 'تم إغلاق التذكرة.');
    }

    /** The log has to belong to this ticket, and to this user. F07 */
    private function authorizeLog(Request $request, Ticket $ticket, TicketWorkLog $log): void
    {
        abort_unless($log->ticket_id === $ticket->id, 404);

        $this->authorize('manageWorkLog', $ticket);

        abort_unless($log->user_id === $request->user()->id, 403, 'ده مش شغلك انت.');
    }
}
