<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PointSide;
use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F18 — /admin/point-rules. ★ (2026-07-23): the admin-editable matrix (type ×
 * scope/role → default points) is gone by explicit request — a subtask
 * always defaults to a flat point value and is edited by hand from then on,
 * same as it always was per-subtask. This screen is now the manual-correction
 * ledger only.
 */
class PointRuleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.rules.manage'), 403);

        return view('admin.point-rules.index', [
            'sides' => PointSide::cases(),
            'users' => User::active()->orderBy('name')->get(['id', 'name']),
            'corrections' => PointTransaction::query()
                ->where('type', 'correction')
                ->with(['user:id,name', 'ticket:id,ticket_number,title', 'correctedBy:id,name'])
                ->latest('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * F18: a manual correction — a new ledger row, never an edit of one that
     * already exists (PointTransaction::booted() would refuse that anyway).
     * Positive tops someone up, negative claws back a mistake; either way the
     * reason is mandatory, because this row has no subtask to explain itself.
     */
    public function storeCorrection(Request $request, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('points.rules.manage'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'points' => ['required', 'numeric', 'min:-999', 'max:999', 'not_in:0'],
            'side' => ['required', \Illuminate\Validation\Rule::enum(PointSide::class)],
            'reason' => ['required', 'string', 'max:255'],
            'ticket_number' => ['nullable', 'string', 'max:50'],
        ], [], [
            'user_id' => 'المستخدم',
            'points' => 'النقاط',
            'side' => 'الجهة',
            'reason' => 'السبب',
            'ticket_number' => 'رقم التذكرة',
        ]);

        $ticket = filled($data['ticket_number'] ?? null)
            ? Ticket::where('ticket_number', $data['ticket_number'])->first()
            : null;

        if (filled($data['ticket_number'] ?? null) && $ticket === null) {
            return back()->withErrors(['ticket_number' => 'مفيش تذكرة بالرقم ده.'])->withInput();
        }

        $correction = PointTransaction::create([
            'user_id' => $data['user_id'],
            'ticket_id' => $ticket?->id,
            'side' => $data['side'],
            'points' => $data['points'],
            'type' => 'correction',
            'created_by' => $request->user()->id,
            'period' => now()->format('Y-m'),
            'reason' => $data['reason'],
        ]);

        $logger->log(
            action: 'point_correction.created',
            userId: $request->user()->id,
            subject: $correction,
            changes: ['to' => $correction->only('user_id', 'ticket_id', 'side', 'points', 'reason')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تم تسجيل التصحيح.');
    }
}
