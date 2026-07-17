<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F14 — holidays and leave, the two things that make the calendar honest. */
class CalendarSettingController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        return view('admin.calendar.index', [
            'holidays' => Holiday::orderBy('date')->get(),
            'leaves' => UserLeave::with(['user:id,name,avatar_path,is_active', 'approver:id,name'])
                ->where('end_date', '>=', now()->subMonths(2))
                ->orderByDesc('start_date')
                ->get(),
            'users' => User::active()->without('role')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeHoliday(Request $request, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:100'],
            'is_recurring' => ['boolean'],
        ], [], ['date' => 'التاريخ', 'name' => 'الاسم']);

        $holiday = Holiday::create($data + ['is_recurring' => $request->boolean('is_recurring')]);

        $logger->log(
            action: 'holiday.created',
            userId: $request->user()->id,
            subject: $holiday,
            changes: ['to' => $holiday->only('date', 'name', 'is_recurring')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تم إضافة العطلة.');
    }

    public function destroyHoliday(Request $request, Holiday $holiday): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $holiday->delete();

        return back()->with('status', 'تم حذف العطلة.');
    }

    public function storeLeave(Request $request, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['required', 'in:annual,sick,other'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'end_date.after_or_equal' => 'تاريخ النهاية مينفعش يكون قبل البداية.',
        ], [
            'user_id' => 'الموظف', 'start_date' => 'من', 'end_date' => 'لـ', 'type' => 'النوع',
        ]);

        $leave = UserLeave::create($data + ['approved_by' => $request->user()->id]);

        $logger->log(
            action: 'leave.created',
            userId: $request->user()->id,
            subject: $leave,
            changes: ['to' => $leave->only('user_id', 'start_date', 'end_date', 'type')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تم تسجيل الإجازة.');
    }

    public function destroyLeave(Request $request, UserLeave $leave): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $leave->delete();

        return back()->with('status', 'تم حذف الإجازة.');
    }
}
