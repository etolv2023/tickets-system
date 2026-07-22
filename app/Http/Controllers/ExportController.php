<?php

namespace App\Http\Controllers;

use App\Exports\PointsExport;
use App\Exports\TicketsExport;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * F19.3 — the exports. Every one is scoped to what the requester may see, and
 * every cell goes through the formula guard (CLAUDE.md § 5).
 */
class ExportController extends Controller
{
    public function tickets(Request $request, ActivityLogger $logger): BinaryFileResponse
    {
        $this->authorize('viewAny', \App\Models\Ticket::class);

        $filters = $request->only('q', 'status', 'type', 'priority', 'company', 'assignee', 'from', 'to');

        $logger->log(
            action: 'export.tickets',
            userId: $request->user()->id,
            changes: ['filters' => array_filter($filters)],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return (new TicketsExport($request->user(), $filters))
            ->download('tickets-' . CarbonImmutable::now()->format('Y-m-d') . '.xlsx');
    }

    public function points(Request $request, ActivityLogger $logger): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('points.view.all'), 403);

        $period = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('period')) === 1
            ? $request->query('period')
            : CarbonImmutable::now()->format('Y-m');

        $logger->log(
            action: 'export.points',
            userId: $request->user()->id,
            changes: ['period' => $period],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return (new PointsExport($period))->download("points-{$period}.xlsx");
    }
}
