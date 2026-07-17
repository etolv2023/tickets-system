<?php

namespace App\Http\Controllers;

use App\Enums\LinkType;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "اليوم" — F22.1.
 *
 * Not a dashboard. No totals row, no charts, no "إجمالي التذاكر: 1,432". It
 * answers exactly four questions, and the numbers live at /reports where you go
 * to them on purpose (CLAUDE.md § 6).
 */
class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('home.today', [
            'user' => $user,
            'onMyPlate' => $this->onMyPlate($user->id),
            'onFire' => $this->onFire($user),
            'awaitingMe' => $this->awaitingMe($user),
            'blockingOthers' => $this->blockingOthers($user->id),
        ]);
    }

    /** 1. What's on my mind — subtasks due today or already late. F22.1 */
    private function onMyPlate(int $userId)
    {
        return TicketSubtask::query()
            ->select(['id', 'ticket_id', 'title', 'due_date', 'status', 'side', 'estimated_hours'])
            ->with(['ticket:id,ticket_number,title,priority,company_id', 'ticket.company:id,name'])
            ->where('assignee_id', $userId)
            ->dueOrOverdue()
            ->orderBy('due_date')
            ->limit(15)
            ->get();
    }

    /** 2. What's on fire — SLA breaches I'm responsible for. F22.1 */
    private function onFire($user)
    {
        return Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'company_id', 'priority', 'status', 'sla_due_at', 'reported_at'])
            ->with('company:id,name')
            ->visibleTo($user)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
            ->defaultOrder()
            ->limit(15)
            ->get();
    }

    /** 3. What's waiting on my decision — approvals. Admin only. F22.1 */
    private function awaitingMe($user)
    {
        if (! $user->hasPermission('features.approve')) {
            return collect();
        }

        return Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'company_id', 'type', 'priority', 'reported_at', 'sla_due_at', 'status'])
            ->with('company:id,name')
            ->where('approval_status', 'pending')
            ->defaultOrder()
            ->limit(15)
            ->get();
    }

    /**
     * 4. Who am I holding up — tickets of mine that block someone else's,
     * and are still open. F10 / F22.1
     */
    private function blockingOthers(int $userId)
    {
        return Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'company_id', 'priority', 'status', 'sla_due_at', 'reported_at'])
            ->with(['company:id,name', 'outgoingLinks.toTicket:id,ticket_number,title,status'])
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $userId)
                ->orWhere('assigned_backend_id', $userId))
            ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
            // Only if it actually blocks something that is itself still open —
            // blocking a closed ticket holds nobody up.
            ->whereHas('outgoingLinks', fn ($q) => $q
                ->where('type', LinkType::Blocks->value)
                ->whereHas('toTicket', fn ($t) => $t->whereNotIn('status', ['resolved', 'closed', 'rejected'])))
            ->defaultOrder()
            ->limit(15)
            ->get();
    }
}
