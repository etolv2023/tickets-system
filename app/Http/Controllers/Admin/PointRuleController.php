<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PointSide;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F18 — /admin/point-rules. Inline editing, saved over ajax. */
class PointRuleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.rules.manage'), 403);

        $roles = Role::query()->assignableOnTickets()->orderBy('name_ar')->get();

        return view('admin.point-rules.index', [
            // Role-based rows (side null) excluded here — they'd throw on
            // ->side->value and belong in roleRules below instead.
            'rules' => PointRule::whereNotNull('side')->get()->keyBy(
                fn (PointRule $r) => "{$r->ticket_type->value}|{$r->scope}|{$r->side->value}"
            ),
            // The combinations F18's matrix actually defines. Anything not here
            // has no rule by design, and the engine awards zero for it.
            //
            // ★★★★ Fix (2026-07-21): bug/any and feature/any were missing —
            // both already had a real devops rule in point_rules (seeded
            // alongside new_module/any), but with no row rendering them here
            // an admin could never see or edit them from this screen.
            'rows' => [
                ['type' => TicketType::Inquiry, 'scope' => 'any'],
                ['type' => TicketType::Bug, 'scope' => 'frontend'],
                ['type' => TicketType::Bug, 'scope' => 'backend'],
                ['type' => TicketType::Bug, 'scope' => 'both'],
                ['type' => TicketType::Bug, 'scope' => 'any'],
                ['type' => TicketType::Feature, 'scope' => 'frontend'],
                ['type' => TicketType::Feature, 'scope' => 'backend'],
                ['type' => TicketType::Feature, 'scope' => 'both'],
                ['type' => TicketType::Feature, 'scope' => 'any'],
                ['type' => TicketType::NewModule, 'scope' => 'any'],

                // ★ DevOps scope (2026-07-21) — a row per type so the admin can
                // set the point distribution for a purely-devops ticket.
                ['type' => TicketType::Inquiry, 'scope' => 'devops'],
                ['type' => TicketType::Bug, 'scope' => 'devops'],
                ['type' => TicketType::Feature, 'scope' => 'devops'],
                ['type' => TicketType::NewModule, 'scope' => 'devops'],
            ],
            'sides' => PointSide::cases(),
            'scopeLabels' => [
                'any' => 'أي',
                'frontend' => 'فرونت',
                'backend' => 'باك',
                'both' => 'فرونت وباك',
                'devops' => 'ديف أوبس',
            ],
            'users' => User::active()->orderBy('name')->get(['id', 'name']),
            'corrections' => PointTransaction::query()
                ->where('type', 'correction')
                ->with(['user:id,name', 'ticket:id,ticket_number,title', 'correctedBy:id,name'])
                ->latest('created_at')
                ->limit(20)
                ->get(),
            // F06 role-assignment extension: one row per (ticket type, role)
            // opted into ticket assignment — same idea as the fixed matrix
            // above, just keyed by role instead of scope/side.
            'roles' => $roles,
            'roleTypes' => TicketType::cases(),
            'roleRules' => PointRule::whereNotNull('role_id')->get()->keyBy(
                fn (PointRule $r) => "{$r->ticket_type->value}|{$r->role_id}"
            ),
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

    public function update(Request $request, ActivityLogger $logger): JsonResponse
    {
        abort_unless($request->user()->hasPermission('points.rules.manage'), 403);

        $data = $request->validate([
            'ticket_type' => ['required', 'string'],
            'scope' => ['required', 'in:any,frontend,backend,both,devops'],
            'side' => ['required', 'string'],
            'points' => ['required', 'numeric', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ]);

        $rule = PointRule::firstOrNew([
            'ticket_type' => $data['ticket_type'],
            'scope' => $data['scope'],
            'side' => $data['side'],
        ]);

        $before = $rule->exists ? ['points' => $rule->points, 'is_active' => $rule->is_active] : null;

        $rule->fill([
            'points' => $data['points'],
            'is_active' => $data['is_active'],
            'updated_by' => $request->user()->id,
        ])->save();

        $logger->log(
            action: 'point_rule.updated',
            userId: $request->user()->id,
            subject: $rule,
            changes: [
                'rule' => "{$data['ticket_type']}|{$data['scope']}|{$data['side']}",
                'from' => $before,
                'to' => ['points' => $rule->points, 'is_active' => $rule->is_active],
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        // F18: this never touches points already awarded. Say so, so nobody
        // assumes editing the matrix rewrites history.
        return response()->json([
            'ok' => true,
            'points' => (float) $rule->points,
            'note' => 'التعديل ده بيسري على الي هيتحل من دلوقتي — النقاط المصروفة قبل كده مش بتتغير.',
        ]);
    }

    /**
     * F06 role-assignment extension: the same inline-save cell, keyed by
     * (ticket_type, role_id) instead of (scope, side) — no scope involved,
     * one rule per role.
     */
    public function updateRole(Request $request, ActivityLogger $logger): JsonResponse
    {
        abort_unless($request->user()->hasPermission('points.rules.manage'), 403);

        $data = $request->validate([
            'ticket_type' => ['required', 'string'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'points' => ['required', 'numeric', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ]);

        $rule = PointRule::firstOrNew([
            'ticket_type' => $data['ticket_type'],
            'role_id' => $data['role_id'],
        ]);

        // scope carries no meaning for a role rule — 'any' is the closest
        // existing value, and the column stays NOT NULL.
        if (! $rule->exists) {
            $rule->scope = 'any';
        }

        $before = $rule->exists ? ['points' => $rule->points, 'is_active' => $rule->is_active] : null;

        $rule->fill([
            'points' => $data['points'],
            'is_active' => $data['is_active'],
            'updated_by' => $request->user()->id,
        ])->save();

        $logger->log(
            action: 'point_rule.updated',
            userId: $request->user()->id,
            subject: $rule,
            changes: [
                'rule' => "{$data['ticket_type']}|role:{$data['role_id']}",
                'from' => $before,
                'to' => ['points' => $rule->points, 'is_active' => $rule->is_active],
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'ok' => true,
            'points' => (float) $rule->points,
            'note' => 'التعديل ده بيسري على الي هيتحل من دلوقتي — النقاط المصروفة قبل كده مش بتتغير.',
        ]);
    }
}
