<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTicketStatusRequest;
use App\Http\Requests\Admin\UpdateTicketStatusRequest;
use App\Models\TicketStatusDefinition;
use App\Models\TicketStatusTransition;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * F06.1 — the admin owns the status list and the transition graph between
 * them. The automatic engine (assign/start/finish/resolve) still only ever
 * moves a ticket between the 10 system keys it knows by name; what's editable
 * here is everything else: adding a status like "بلوك", and which manual
 * moves ("غيّر الحالة") are legal between any two statuses.
 */
class TicketStatusController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $statuses = TicketStatusDefinition::orderBy('position')->get();
        $edges = TicketStatusTransition::all(['from_status_id', 'to_status_id'])
            ->map(fn ($t) => "{$t->from_status_id}-{$t->to_status_id}")
            ->flip();

        return view('admin.ticket-statuses.index', [
            'statuses' => $statuses,
            'edges' => $edges,
            'colors' => \App\Models\Label::COLORS,
        ]);
    }

    public function store(StoreTicketStatusRequest $request): RedirectResponse
    {
        $data = $request->validated();

        TicketStatusDefinition::create($data + [
            'is_system' => false,
            'position' => (int) TicketStatusDefinition::max('position') + 1,
        ]);

        return back()->with('status', "تم إضافة حالة «{$data['name_ar']}».");
    }

    public function update(UpdateTicketStatusRequest $request, TicketStatusDefinition $ticketStatus): RedirectResponse
    {
        $ticketStatus->update($request->validated());

        return back()->with('status', 'تم حفظ الحالة.');
    }

    public function destroy(Request $request, TicketStatusDefinition $ticketStatus): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        if ($ticketStatus->is_system) {
            return back()->withErrors(['status' => 'الحالات الأساسية للنظام مينفعش تتحذف.']);
        }

        $name = $ticketStatus->name_ar;

        try {
            $ticketStatus->delete();
        } catch (QueryException $e) {
            // FK on tickets.status is RESTRICT on purpose — a ticket sitting in
            // this status right now must not lose its status silently.
            return back()->withErrors(['status' => "فيه تذاكر لسه في حالة «{$name}» — انقلها لحالة تانية الأول."]);
        }

        return back()->with('status', "تم حذف حالة «{$name}».");
    }

    /**
     * The whole transition graph, replaced in one submit — a matrix of
     * checkboxes is much easier to reason about than one request per edge.
     */
    public function updateTransitions(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $checked = $request->input('transitions', []);
        $ids = TicketStatusDefinition::pluck('id');

        DB::transaction(function () use ($checked, $ids) {
            foreach ($ids as $fromId) {
                foreach ($ids as $toId) {
                    if ($fromId === $toId) {
                        continue;
                    }

                    $isChecked = isset($checked[$fromId][$toId]);

                    if ($isChecked) {
                        TicketStatusTransition::firstOrCreate(['from_status_id' => $fromId, 'to_status_id' => $toId]);
                    } else {
                        TicketStatusTransition::where('from_status_id', $fromId)->where('to_status_id', $toId)->delete();
                    }
                }
            }
        });

        return back()->with('status', 'تم حفظ الانتقالات.');
    }
}
