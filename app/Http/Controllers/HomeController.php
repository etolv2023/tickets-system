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
    /** How many rows a list on this screen will show before it stops. */
    private const LIST_LIMIT = 15;

    public function index(Request $request): View
    {
        $user = $request->user();

        $onMyPlate = $this->onMyPlate($user->id);
        $onFire = $this->onFire($user);
        $awaitingMe = $this->awaitingMe($user);
        $blockingOthers = $this->blockingOthers($user->id);

        return view('home.today', [
            'user' => $user,
            'onMyPlate' => $onMyPlate,
            'onFire' => $onFire,
            'awaitingMe' => $awaitingMe,
            'blockingOthers' => $blockingOthers,

            // The stat tiles must state the real total, not the length of a
            // capped list — a tile that reads 15 while 40 are on fire is worse
            // than no tile. The count only costs a query when the list actually
            // hit the cap, which on a healthy queue is never.
            'counts' => [
                'onMyPlate' => $this->totalFor($onMyPlate, fn () => $this->onMyPlateQuery($user->id)),
                'onFire' => $this->totalFor($onFire, fn () => $this->onFireQuery($user)),
                'awaitingMe' => $this->totalFor($awaitingMe, fn () => $this->awaitingMeQuery($user)),
                'blockingOthers' => $this->totalFor($blockingOthers, fn () => $this->blockingOthersQuery($user->id)),
            ],
        ]);
    }

    /**
     * The list's own length unless it was truncated, in which case the real
     * total is worth one extra COUNT.
     */
    private function totalFor($rows, callable $query): int
    {
        return $rows->count() < self::LIST_LIMIT
            ? $rows->count()
            : $query()->count();
    }

    /* Each list below is split into a query and a fetch, so the tile above it
       can re-run the same conditions as a COUNT without repeating them. */

    /** 1. What's on my mind — subtasks due today or already late. F22.1 */
    private function onMyPlateQuery(int $userId)
    {
        return TicketSubtask::query()
            ->where('assignee_id', $userId)
            ->dueOrOverdue();
    }

    private function onMyPlate(int $userId)
    {
        return $this->onMyPlateQuery($userId)
            ->select(['id', 'ticket_id', 'title', 'due_date', 'status', 'side', 'estimated_hours'])
            ->with(['ticket:id,ticket_number,title,priority,company_id,requested_by', 'ticket.company:id,name', 'ticket.requester:id,name'])
            ->orderBy('due_date')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /** 2. What's on fire — SLA breaches I'm responsible for. F22.1 */
    private function onFireQuery($user)
    {
        return Ticket::query()
            ->visibleTo($user)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNotIn('status', ['resolved', 'closed', 'rejected']);
    }

    private function onFire($user)
    {
        return $this->onFireQuery($user)
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'priority', 'status', 'sla_due_at', 'reported_at'])
            ->with('company:id,name', 'requester:id,name')
            ->defaultOrder()
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /** 3. What's waiting on my decision — approvals. Admin only. F22.1 */
    private function awaitingMeQuery($user)
    {
        return Ticket::query()->where('approval_status', 'pending');
    }

    private function awaitingMe($user)
    {
        if (! $user->hasPermission('features.approve')) {
            return collect();
        }

        return $this->awaitingMeQuery($user)
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'type', 'priority', 'reported_at', 'sla_due_at', 'status'])
            ->with('company:id,name', 'requester:id,name')
            ->defaultOrder()
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /**
     * 4. Who am I holding up — tickets of mine that block someone else's,
     * and are still open. F10 / F22.1
     */
    private function blockingOthersQuery(int $userId)
    {
        return Ticket::query()
            ->where(fn ($q) => $q
                ->where('assigned_frontend_id', $userId)
                ->orWhere('assigned_backend_id', $userId)
                ->orWhere('devops_id', $userId))
            ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
            // Only if it actually blocks something that is itself still open —
            // blocking a closed ticket holds nobody up.
            ->whereHas('outgoingLinks', fn ($q) => $q
                ->where('type', LinkType::Blocks->value)
                ->whereHas('toTicket', fn ($t) => $t->whereNotIn('status', ['resolved', 'closed', 'rejected'])));
    }

    private function blockingOthers(int $userId)
    {
        return $this->blockingOthersQuery($userId)
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'priority', 'status', 'sla_due_at', 'reported_at'])
            ->with(['company:id,name', 'requester:id,name', 'outgoingLinks.toTicket:id,ticket_number,title,status'])
            ->defaultOrder()
            ->limit(self::LIST_LIMIT)
            ->get();
    }
}
