<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PointSide;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Models\PointRule;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F18 — /admin/point-rules. Inline editing, saved over ajax. */
class PointRuleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.rules.manage'), 403);

        return view('admin.point-rules.index', [
            'rules' => PointRule::all()->keyBy(
                fn (PointRule $r) => "{$r->ticket_type->value}|{$r->scope}|{$r->side->value}"
            ),
            // The combinations F18's matrix actually defines. Anything not here
            // has no rule by design, and the engine awards zero for it.
            'rows' => [
                ['type' => TicketType::Inquiry, 'scope' => 'any'],
                ['type' => TicketType::Bug, 'scope' => 'frontend'],
                ['type' => TicketType::Bug, 'scope' => 'backend'],
                ['type' => TicketType::Bug, 'scope' => 'both'],
                ['type' => TicketType::Feature, 'scope' => 'frontend'],
                ['type' => TicketType::Feature, 'scope' => 'backend'],
                ['type' => TicketType::Feature, 'scope' => 'both'],
                ['type' => TicketType::NewModule, 'scope' => 'any'],
            ],
            'sides' => PointSide::cases(),
            'scopeLabels' => [
                'any' => 'أي',
                'frontend' => 'فرونت',
                'backend' => 'باك',
                'both' => 'فرونت وباك',
            ],
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): JsonResponse
    {
        abort_unless($request->user()->hasPermission('points.rules.manage'), 403);

        $data = $request->validate([
            'ticket_type' => ['required', 'string'],
            'scope' => ['required', 'in:any,frontend,backend,both'],
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
}
