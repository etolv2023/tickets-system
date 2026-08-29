<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePointCorrectionRequest;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PointCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * F18 — /admin/point-rules. ★ (2026-07-23): the admin-editable matrix (type ×
 * scope/role → default points) is gone by explicit request — a subtask
 * always defaults to a flat point value and is edited by hand from then on,
 * same as it always was per-subtask. This screen is now the manual-correction
 * ledger only.
 *
 * ★ (2026-08-29) A correction can now be edited or cancelled from here, behind
 * two permissions of its own. Neither one rewrites the ledger: see
 * PointCorrectionService, which only ever inserts.
 */
class PointRuleController extends Controller
{
    /** How many ledger rows the screen shows. */
    private const RECENT = 30;

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.rules.manage'), 403);

        return view('admin.point-rules.index', [
            // A correction is attributed to a role now (dynamic), not a
            // hardcoded PointSide (2026-07-24).
            'roles' => Role::assignableList(),
            'users' => User::active()->orderBy('name')->get(['id', 'name']),
            'corrections' => PointTransaction::query()
                ->where('type', 'correction')
                ->with([
                    'user:id,name',
                    'ticket:id,ticket_number,title',
                    'correctedBy:id,name',
                    'role:id,name_ar',
                    // ★ (2026-08-29) Whether this row still stands, and what it
                    // was replaced by. Eager-loaded because the view asks per
                    // row and Model::preventLazyLoading() would (rightly) throw
                    // on the N+1 that reading them lazily would be.
                    'reversal:id,reverses_id',
                    'replacement:id,replaces_id,points,reason',
                    'reverses:id',
                ])
                ->latest('created_at')
                // Raised from 20 when cancelling was added: an edit writes three
                // rows where there used to be one, so the old window showed
                // barely a handful of actual corrections.
                ->limit(self::RECENT)
                ->get(),
        ]);
    }

    /**
     * F18: a manual correction — a new ledger row, never an edit of one that
     * already exists (PointTransaction::booted() would refuse that anyway).
     * Positive tops someone up, negative claws back a mistake; either way the
     * reason is mandatory, because this row has no subtask to explain itself.
     */
    public function storeCorrection(
        Request $request,
        PointCorrectionService $corrections,
        ActivityLogger $logger,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('points.rules.manage'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'points' => ['required', 'numeric', 'min:-999', 'max:999', 'not_in:0'],
            'role_id' => ['required', 'integer', \Illuminate\Validation\Rule::in(Role::assignableList()->pluck('id'))],
            'reason' => ['required', 'string', 'max:255'],
            'ticket_number' => ['nullable', 'string', 'max:50'],
        ], [], [
            'user_id' => 'المستخدم',
            'points' => 'النقاط',
            'role_id' => 'الدور',
            'reason' => 'السبب',
            'ticket_number' => 'رقم التذكرة',
        ]);

        try {
            $ticket = $corrections->resolveTicket($data['ticket_number'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['ticket_number' => $e->getMessage()])->withInput();
        }

        $correction = PointTransaction::create([
            'user_id' => $data['user_id'],
            'ticket_id' => $ticket?->id,
            'role_id' => $data['role_id'],
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
            changes: ['to' => $correction->only('user_id', 'ticket_id', 'role_id', 'points', 'reason')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تم تسجيل التصحيح.');
    }

    /**
     * ★ (2026-08-29) «تعديل» — a reversal plus the corrected row, in one
     * transaction. The route holds points.corrections.edit.
     */
    public function updateCorrection(
        UpdatePointCorrectionRequest $request,
        PointTransaction $correction,
        PointCorrectionService $corrections,
        ActivityLogger $logger,
    ): RedirectResponse {
        try {
            $result = $corrections->replace($correction, $request->validated(), $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['correction' => $e->getMessage()])->withInput();
        }

        // The whole point of this screen is money moving, so the log records
        // both sides — what stopped counting and what took its place.
        $logger->log(
            action: 'point_correction.replaced',
            userId: $request->user()->id,
            subject: $correction,
            changes: [
                'from' => $correction->only('user_id', 'ticket_id', 'role_id', 'points', 'reason'),
                'to' => $result['replacement']->only('id', 'user_id', 'ticket_id', 'role_id', 'points', 'reason'),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'اتعدّل التصحيح — الأصلي اتلغى بسطر عكسي، والقيمة الجديدة اتسجلت.');
    }

    /**
     * ★ (2026-08-29) «حذف» — one reversing row, and the original stays
     * readable. The route holds points.corrections.delete.
     */
    public function destroyCorrection(
        Request $request,
        PointTransaction $correction,
        PointCorrectionService $corrections,
        ActivityLogger $logger,
    ): RedirectResponse {
        try {
            $reversal = $corrections->cancel($correction, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['correction' => $e->getMessage()]);
        }

        $logger->log(
            action: 'point_correction.cancelled',
            userId: $request->user()->id,
            subject: $correction,
            changes: [
                'from' => $correction->only('user_id', 'role_id', 'points', 'reason'),
                'to' => ['reversal_id' => $reversal->id, 'points' => $reversal->points],
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'اتلغى التصحيح بسطر عكسي. الأصلي لسه ظاهر في الدفتر.');
    }
}
