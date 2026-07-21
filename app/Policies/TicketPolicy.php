<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Object-level access (CLAUDE.md § 5). The Gate says whether a role may touch
 * tickets at all; this says whether it may touch THIS one.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tickets.view.all')
            || $user->hasPermission('tickets.view.assigned');
    }

    /**
     * Mirrors Ticket::visibleTo(). The scope keeps other people's tickets out of
     * lists; this stops a guessed id in the url from opening one (F03).
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasPermission('tickets.view.all')) {
            return true;
        }

        if ($this->isAssigned($user, $ticket)) {
            return true;
        }

        return $user->hasPermission('tickets.view.own') && $ticket->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tickets.create');
    }

    /** A resolved/closed ticket is frozen — only reopen() moves it from here. */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.edit') && ! $ticket->isLocked();
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.delete');
    }

    /** Anyone who can see the ticket and is allowed to comment. F05 */
    public function comment(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('comments.create') && $this->view($user, $ticket) && ! $ticket->isLocked();
    }

    public function commentInternally(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('comments.internal') && $this->view($user, $ticket) && ! $ticket->isLocked();
    }

    /** Attachments inherit the ticket's visibility — that's the whole point. F04.2 */
    public function download(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function attach(User $user, Ticket $ticket): bool
    {
        return $this->comment($user, $ticket) || $this->update($user, $ticket);
    }

    /** F06 / F15: a feature can't be assigned before an admin approves it. */
    public function assign(User $user, Ticket $ticket): Response
    {
        if (! $user->hasPermission('tickets.assign')) {
            return Response::deny('ليس لديك صلاحية لتوزيع التذاكر.');
        }

        if ($ticket->type->needsApproval() && $ticket->approval_status !== 'approved') {
            return Response::deny('الفيتشر لازم توافق عليه الأول قبل ما يتوزع.');
        }

        if ($ticket->isLocked()) {
            return Response::deny('التذكرة مقفولة. لازم ترجّعها «مرتجعة» الأول.');
        }

        return Response::allow();
    }

    /** F07: only the person the side is assigned to may start or finish it. */
    public function manageWorkLog(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('worklog.manage') && $this->isAssigned($user, $ticket) && ! $ticket->isLocked();
    }

    public function approve(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('features.approve')
            && $ticket->type->needsApproval()
            && $ticket->approval_status === 'pending';
    }

    /** F16 */
    public function test(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.resolve') && $ticket->tester_id === $user->id;
    }

    public function resolve(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.resolve') && $this->view($user, $ticket);
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.reopen') && $this->view($user, $ticket);
    }

    public function notifyClient(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.notify_client') && $this->view($user, $ticket);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.close') && $this->view($user, $ticket);
    }

    /** F06: manual status changes (with an optional "waiting on" recipient) piggyback on general edit rights. */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        // F15 (2026-07-21): a feature/module awaiting approval is frozen. Moving
        // its status — the tickets-list picker offered pending_approval→assigned,
        // and the "غيّر الحالة" panel the same — flipped it out of the approvals
        // queue while approval_status stayed 'pending', so assign() then refused
        // it (F15) and it could no longer be approved: stranded, un-assignable.
        // The approve/reject decision owns its movement; until then its status
        // does not change. This renders the picker as a read-only badge and hides
        // the panel; transition() enforces the same invariant server-side.
        if ($ticket->type->needsApproval() && $ticket->approval_status === 'pending') {
            return false;
        }

        return $user->hasPermission('tickets.edit') && $this->view($user, $ticket);
    }

    private function isAssigned(User $user, Ticket $ticket): bool
    {
        return in_array($user->id, [
            $ticket->assigned_frontend_id,
            $ticket->assigned_backend_id,
            $ticket->tester_id,
            $ticket->devops_id,
        ], true);
    }
}
