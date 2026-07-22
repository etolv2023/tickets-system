<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubtaskStatusRequest;
use App\Http\Requests\Admin\UpdateSubtaskStatusRequest;
use App\Models\Label;
use App\Models\SubtaskStatusDefinition;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ★ (2026-07-24) The admin owns the subtask-status list, same pattern as ticket
 * statuses and types. The four system statuses (مستنية/جاري العمل/خلصت/متوقفة)
 * carry code behaviour so their keys are fixed and they can't be deleted; the
 * admin can still rename/recolour them and add their own neutral statuses.
 */
class SubtaskStatusController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        return view('admin.subtask-statuses.index', [
            'statuses' => SubtaskStatusDefinition::orderBy('position')->get(),
            'colors' => Label::COLORS,
        ]);
    }

    public function store(StoreSubtaskStatusRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validated();

        $status = SubtaskStatusDefinition::create($data + [
            'is_system' => false,
            'position' => (int) SubtaskStatusDefinition::max('position') + 1,
        ]);

        $logger->log(
            action: 'subtask_status.created',
            userId: $request->user()->id,
            subject: $status,
            changes: ['to' => $status->only('key', 'name_ar', 'needs_reason')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', "تم إضافة حالة «{$data['name_ar']}».");
    }

    public function update(UpdateSubtaskStatusRequest $request, SubtaskStatusDefinition $subtaskStatus, ActivityLogger $logger): RedirectResponse
    {
        $before = $subtaskStatus->only('name_ar', 'color', 'needs_reason');

        // A system status's needs_reason is behavioural (done/in_progress must
        // never demand a reason, blocked must); only its name and colour move.
        $data = $subtaskStatus->is_system
            ? $request->safe()->only('name_ar', 'color')
            : $request->validated();

        $subtaskStatus->update($data);

        $logger->log(
            action: 'subtask_status.updated',
            userId: $request->user()->id,
            subject: $subtaskStatus,
            changes: ['from' => $before, 'to' => $subtaskStatus->only('name_ar', 'color', 'needs_reason')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تم حفظ الحالة.');
    }

    public function destroy(Request $request, SubtaskStatusDefinition $subtaskStatus, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        if ($subtaskStatus->is_system) {
            return back()->withErrors(['status' => 'الحالات الأساسية للنظام مينفعش تتحذف.']);
        }

        $name = $subtaskStatus->name_ar;

        try {
            $subtaskStatus->delete();
        } catch (QueryException) {
            // FK on ticket_subtasks.status is RESTRICT — a subtask on this
            // status right now must not lose it silently.
            return back()->withErrors(['status' => "فيه صب تاسكس لسه بحالة «{$name}» — غيّرها الأول."]);
        }

        $logger->log(
            action: 'subtask_status.deleted',
            userId: $request->user()->id,
            subject: $subtaskStatus,
            changes: ['from' => $subtaskStatus->only('key', 'name_ar')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', "تم حذف حالة «{$name}».");
    }
}
