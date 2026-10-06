<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\User;
use App\Services\TicketReportService;
use Illuminate\Http\Request;

class TicketReportController extends Controller
{
    public function __construct(private TicketReportService $reports) {}

    public function userPerformance(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        ['date_basis' => $basis, 'from' => $from, 'to' => $to, 'filters' => $filters, 'user' => $userId] = $this->reports->parameters($request->query());
        $user = User::findOrFail($userId ?: $request->user()->id);
        $report = $this->reports->userPerformance($user, $basis, $from, $to, $filters);

        return view('reports.performance', $this->data($request, $basis, $from, $to, $filters) + compact('user', 'report'));
    }

    public function comparison(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        ['date_basis' => $basis, 'from' => $from, 'to' => $to, 'filters' => $filters] = $this->reports->parameters($request->query());
        $rows = $this->reports->usersComparison($basis, $from, $to, $filters);

        return view('reports.comparison', $this->data($request, $basis, $from, $to, $filters) + compact('rows'));
    }

    public function summary(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        ['date_basis' => $basis, 'from' => $from, 'to' => $to, 'filters' => $filters, 'by' => $by] = $this->reports->parameters($request->query());
        $method = $by . 'Summary';
        $rows = $this->reports->{$method}($basis, $from, $to, $filters);

        return view('reports.summary', $this->data($request, $basis, $from, $to, $filters) + compact('by', 'rows'));
    }

    public function aging(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        ['date_basis' => $basis, 'from' => $from, 'to' => $to, 'filters' => $filters] = $this->reports->parameters($request->query(), 'reported_at');
        $report = $this->reports->agingReport($basis, $from, $to, $filters);

        return view('reports.aging', $this->data($request, $basis, $from, $to, $filters) + compact('report'));
    }

    public function deadline(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        ['date_basis' => $basis, 'from' => $from, 'to' => $to, 'filters' => $filters] = $this->reports->parameters($request->query(), 'reported_at');
        $report = $this->reports->deadlineReport($basis, $from, $to, $filters);

        return view('reports.deadline', $this->data($request, $basis, $from, $to, $filters) + compact('report'));
    }

    private function data(Request $request, string $basis, string $from, string $to, array $filters): array
    {
        return ['dateBasis' => $basis, 'from' => $from, 'to' => $to, 'filters' => $filters,
            'dateBases' => Ticket::DATE_BASES, 'deadlineFilters' => Ticket::DEADLINE_FILTERS,
            'drilldown' => array_merge($filters, ['date_basis' => $basis, 'from' => $from, 'to' => $to]),
            'openStatusKeys' => TicketStatusDefinition::openKeys(),
            'resolvedStatusKeys' => TicketStatusDefinition::resolvedKeys(),
            'selectedAssignee' => User::find($request->query('assignee'))];
    }
}
