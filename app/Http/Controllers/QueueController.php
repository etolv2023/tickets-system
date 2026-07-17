<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The two work queues: approvals (F15) and testing (F16). */
class QueueController extends Controller
{
    /** F15 — features waiting on an admin's decision. */
    public function approvals(Request $request): View
    {
        abort_unless($request->user()->hasPermission('features.approve'), 403);

        return view('queues.approvals', [
            'tickets' => Ticket::query()
                ->select([
                    'id', 'ticket_number', 'company_id', 'title', 'type', 'scope',
                    'priority', 'status', 'reported_at', 'sla_due_at', 'created_by',
                ])
                ->with(['company:id,name', 'creator:id,name,avatar_path,is_active'])
                ->where('approval_status', 'pending')
                ->defaultOrder()
                ->paginate(25),
        ]);
    }

    /** F16 — what this tester is expected to verify. */
    public function testing(Request $request): View
    {
        $user = $request->user();

        abort_unless($user->hasPermission('tickets.resolve'), 403);

        return view('queues.testing', [
            'tickets' => Ticket::query()
                ->select([
                    'id', 'ticket_number', 'company_id', 'title', 'type', 'scope',
                    'priority', 'status', 'reported_at', 'sla_due_at',
                    'assigned_frontend_id', 'assigned_backend_id',
                ])
                ->with([
                    'company:id,name',
                    'frontend:id,name,avatar_path,is_active',
                    'backend:id,name,avatar_path,is_active',
                ])
                ->where('tester_id', $user->id)
                ->whereIn('status', ['dev_done', 'testing'])
                ->defaultOrder()
                ->paginate(25),
            // dev_done with no tester is nobody's job unless support or a manager
            // picks it up — surface those rather than let them rot. F16
            'unassigned' => $user->hasPermission('tickets.view.all')
                ? Ticket::query()
                    ->select(['id', 'ticket_number', 'company_id', 'title', 'priority', 'status', 'reported_at', 'sla_due_at'])
                    ->with('company:id,name')
                    ->where('status', 'dev_done')
                    ->whereNull('tester_id')
                    ->defaultOrder()
                    ->limit(25)
                    ->get()
                : collect(),
        ]);
    }
}
