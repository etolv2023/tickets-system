<?php

namespace App\Http\Controllers\Export;

use App\Exports\MyPointsExport;
use App\Exports\PointsExport;
use App\Exports\PointsLedgerExport;
use App\Exports\PointsSummaryExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Export\Concerns\LogsExport;
use App\Models\TicketTypeDefinition;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * F18/F19.2 — the points exports.
 *
 * These turn into money at the end of the month, so the gates are strict:
 * everything that shows other people's rows needs points.view.all, and "my
 * points" is bound to the authenticated user rather than to a parameter.
 */
class PointsExportController extends Controller
{
    use LogsExport;

    /** F19.2 — /leaderboard */
    public function leaderboard(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('points.view.all'), 403);

        $period = $this->period($request);
        $filters = $request->only('person', 'assignee');

        $this->logExport($request, 'export.points', $filters + ['period' => $period]);

        return (new PointsExport($period, $filters))->download("points-{$period}.xlsx");
    }

    /** F18 — /points-report */
    public function summary(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('points.view.all'), 403);

        $period = $this->period($request);

        // Same guard the screen uses: an unknown type would silently empty the
        // whole workbook and read as a quiet month rather than as a typo.
        $type = (string) $request->query('type', '');
        $type = array_key_exists($type, TicketTypeDefinition::options()) ? $type : null;

        $this->logExport($request, 'export.points_summary', ['period' => $period, 'type' => $type]);

        return (new PointsSummaryExport($period, $type))->download("points-summary-{$period}.xlsx");
    }

    /** F18/F19.3 — /points-report/detail, the ledger row by row. */
    public function ledger(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('points.view.all'), 403);

        $filters = $request->only(['person', 'period', 'from', 'to', 'role', 'type', 'kind', 'company', 'q']);

        $this->logExport($request, 'export.points_ledger', $filters);

        return (new PointsLedgerExport($filters))->download($this->filename('points-ledger'));
    }

    /** /my-points — your own rows, never anybody else's. */
    public function mine(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('points.view.own'), 403);

        // The screen's ?period=all mode sums the whole ledger; a null period
        // here means exactly that, and anything malformed falls back to a month.
        $isAll = $request->query('period') === 'all';
        $period = $isAll ? null : $this->period($request);

        $this->logExport($request, 'export.my_points', ['period' => $period ?? 'all']);

        return (new MyPointsExport($request->user(), $period))
            ->download('my-points-' . ($period ?? 'all') . '.xlsx');
    }
}
