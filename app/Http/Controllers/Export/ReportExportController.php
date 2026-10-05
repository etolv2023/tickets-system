<?php

namespace App\Http\Controllers\Export;

use App\Exports\EmployeeProfileExport;
use App\Exports\ReportsExport;
use App\Exports\ResolvedByTypeExport;
use App\Exports\TeamActivityExport;
use App\Http\Controllers\Reports\ResolvedByTypeController;
use App\Exports\TimesheetExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Export\Concerns\LogsExport;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** F19 — the report exports, plus one person's own timesheet. */
class ReportExportController extends Controller
{
    use LogsExport;

    public function __construct(private readonly ReportService $reports)
    {
    }

    /** F19.3 — /reports */
    public function reports(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $period = $this->period($request);
        [$from, $to] = $this->reports->periodBounds($period);
        $filters = $request->only(['company', 'type', 'priority', 'person']);

        $this->logExport($request, 'export.reports', ['period' => $period] + $filters);

        return (new ReportsExport($period, $from, $to, $filters))->download("reports-{$period}.xlsx");
    }

    /** F19.3 — /reports/team-activity */
    public function teamActivity(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $show = in_array($request->query('show'), ['tickets', 'subtasks'], true)
            ? $request->query('show')
            : 'both';

        $filters = $request->only(\App\Support\TeamActivityFilters::KEYS);

        $this->logExport($request, 'export.team_activity', $filters + ['show' => $show]);

        return (new TeamActivityExport($filters, $show))->download($this->filename('team-activity'));
    }

    /** ★ (2026-10-05) F19.5 — /reports/resolved-by-type */
    public function resolvedByType(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        // Settled exactly as the screen settles them, so the file covers the
        // range the page showed — including the default month when none given.
        $filters = ResolvedByTypeController::resolve($request->only(ResolvedByTypeController::FILTER_KEYS));

        $this->logExport($request, 'export.resolved_by_type', $filters);

        return (new ResolvedByTypeExport($filters))->download($this->filename('resolved-by-type'));
    }

    /** F19.1 — /employees/{user} */
    public function employee(Request $request, User $user): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $period = $this->period($request);

        $this->logExport($request, 'export.employee', ['user' => $user->id, 'period' => $period]);

        return (new EmployeeProfileExport($user, $period))
            ->download("employee-{$user->id}-{$period}.xlsx");
    }

    /**
     * F09 — /my-timesheet. Bound to the authenticated user: the week is a
     * parameter, whose week is not.
     */
    public function timesheet(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('time.log'), 403);

        // Same week boundary the screen uses, from settings (F13/F22.2).
        $weekStart = (int) Setting::get('week_start_day', 6);

        $anchor = $request->query('week')
            ? CarbonImmutable::parse($request->query('week'))
            : CarbonImmutable::now();

        $from = $anchor->startOfWeek($weekStart);
        $to = $from->addDays(6);

        $this->logExport($request, 'export.timesheet', ['week' => $from->toDateString()]);

        return (new TimesheetExport($request->user(), $from, $to))
            ->download('timesheet-' . $from->toDateString() . '.xlsx');
    }
}
