<?php

namespace App\Http\Controllers\Export;

use App\Exports\EmployeeProfileExport;
use App\Exports\ReportsExport;
use App\Exports\TeamActivityExport;
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

        $this->logExport($request, 'export.reports', ['period' => $period]);

        return (new ReportsExport($period, $from, $to))->download("reports-{$period}.xlsx");
    }

    /** F19.3 — /reports/team-activity */
    public function teamActivity(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $show = in_array($request->query('show'), ['tickets', 'subtasks'], true)
            ? $request->query('show')
            : 'both';

        $filters = $request->only([
            'person', 'from', 'to', 'ticket_date_basis', 'subtask_date_basis',
            'type', 'priority', 'status', 'company', 'role', 'subtask_status',
        ]);

        $this->logExport($request, 'export.team_activity', $filters + ['show' => $show]);

        return (new TeamActivityExport($filters, $show))->download($this->filename('team-activity'));
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
