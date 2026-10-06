<?php

namespace App\Http\Controllers\Export;

use App\Exports\TicketAgingExport;
use App\Exports\TicketComparisonExport;
use App\Exports\TicketDeadlineExport;
use App\Exports\TicketPerformanceExport;
use App\Exports\TicketSummaryExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Export\Concerns\LogsExport;
use App\Models\User;
use App\Services\TicketReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketReportExportController extends Controller
{
    use LogsExport;

    public function __construct(private readonly TicketReportService $reports) {}

    public function performance(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $parameters = $this->reports->parameters($request->query());
        $user = User::findOrFail($parameters['user'] ?: $request->user()->id);
        $report = $this->reports->userPerformance($user, $parameters['date_basis'], $parameters['from'], $parameters['to'], $parameters['filters']);
        $this->logExport($request, 'export.performance', $this->context($parameters) + ['user' => $user->id]);

        return (new TicketPerformanceExport($report))->download($this->filename('ticket-performance'));
    }

    public function comparison(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $parameters = $this->reports->parameters($request->query());
        $rows = $this->reports->usersComparison($parameters['date_basis'], $parameters['from'], $parameters['to'], $parameters['filters']);
        $this->logExport($request, 'export.comparison', $this->context($parameters));

        return (new TicketComparisonExport($rows))->download($this->filename('ticket-comparison'));
    }

    public function summary(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $parameters = $this->reports->parameters($request->query());
        $method = $parameters['by'] . 'Summary';
        $rows = $this->reports->{$method}($parameters['date_basis'], $parameters['from'], $parameters['to'], $parameters['filters']);
        $this->logExport($request, 'export.summary', $this->context($parameters) + ['by' => $parameters['by']]);

        return (new TicketSummaryExport($rows))->download($this->filename('ticket-summary'));
    }

    public function aging(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $parameters = $this->reports->parameters($request->query(), 'reported_at');
        $report = $this->reports->agingReport($parameters['date_basis'], $parameters['from'], $parameters['to'], $parameters['filters']);
        $this->logExport($request, 'export.aging', $this->context($parameters));

        return (new TicketAgingExport($report))->download($this->filename('ticket-aging'));
    }

    public function deadline(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        $parameters = $this->reports->parameters($request->query(), 'reported_at');
        $report = $this->reports->deadlineReport($parameters['date_basis'], $parameters['from'], $parameters['to'], $parameters['filters']);
        $this->logExport($request, 'export.deadline', $this->context($parameters));

        return (new TicketDeadlineExport($report))->download($this->filename('ticket-deadline'));
    }

    private function context(array $parameters): array
    {
        return $parameters['filters'] + [
            'date_basis' => $parameters['date_basis'],
            'from' => $parameters['from'],
            'to' => $parameters['to'],
        ];
    }
}
