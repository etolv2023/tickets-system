<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePriorityRequest;
use App\Http\Requests\Admin\UpdatePriorityRequest;
use App\Models\Label;
use App\Models\PriorityDefinition;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F22.1 — the admin owns the priority list, same pattern as ticket statuses
 * (TicketStatusController). SLA hours live directly on the row instead of a
 * separate settings key, so one screen edits a priority's name, colour and
 * deadline together.
 */
class PriorityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        return view('admin.priorities.index', [
            'priorities' => PriorityDefinition::orderBy('position')->get(),
            'colors' => Label::COLORS,
        ]);
    }

    public function store(StorePriorityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        PriorityDefinition::create($data + [
            'is_system' => false,
            'position' => (int) PriorityDefinition::max('position') + 1,
        ]);

        return back()->with('status', "تم إضافة أولوية «{$data['name_ar']}».");
    }

    public function update(UpdatePriorityRequest $request, PriorityDefinition $priority): RedirectResponse
    {
        $priority->update($request->validated());

        return back()->with('status', 'تم حفظ الأولوية.');
    }

    public function destroy(Request $request, PriorityDefinition $priority): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        if ($priority->is_system) {
            return back()->withErrors(['priority' => 'الأولويات الأساسية للنظام مينفعش تتحذف.']);
        }

        $name = $priority->name_ar;

        try {
            $priority->delete();
        } catch (QueryException $e) {
            // FK on tickets.priority is RESTRICT on purpose — a ticket sitting
            // on this priority right now must not lose it silently.
            return back()->withErrors(['priority' => "فيه تذاكر لسه بأولوية «{$name}» — غيّرها الأول."]);
        }

        return back()->with('status', "تم حذف أولوية «{$name}».");
    }
}
