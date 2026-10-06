<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\User;
use App\Services\TicketReportService;
use App\Support\DateBounds;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TicketReportController extends Controller
{
    public function __construct(private TicketReportService $reports) {}

    public function userPerformance(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        [$basis, $from, $to, $filters] = $this->parameters($request);
        $user = User::findOrFail($request->integer('user') ?: $request->user()->id);
        $report = $this->reports->userPerformance($user, $basis, $from, $to, $filters);

        return view('reports.performance', $this->data($request, $basis, $from, $to, $filters) + compact('user', 'report'));
    }

    public function comparison(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        [$basis, $from, $to, $filters] = $this->parameters($request);
        $rows = $this->reports->usersComparison($basis, $from, $to, $filters);

        return view('reports.comparison', $this->data($request, $basis, $from, $to, $filters) + compact('rows'));
    }

    public function summary(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        [$basis, $from, $to, $filters] = $this->parameters($request);
        $by = in_array($request->query('by'), ['type', 'status', 'priority', 'module'], true) ? $request->query('by') : 'type';
        $method = $by . 'Summary';
        $rows = $this->reports->{$method}($basis, $from, $to, $filters);

        return view('reports.summary', $this->data($request, $basis, $from, $to, $filters) + compact('by', 'rows'));
    }

    public function aging(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        [$basis, $from, $to, $filters] = $this->parameters($request);
        $report = $this->reports->agingReport($basis, $from, $to, $filters);

        return view('reports.aging', $this->data($request, $basis, $from, $to, $filters) + compact('report'));
    }

    public function deadline(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);
        [$basis, $from, $to, $filters] = $this->parameters($request);
        $report = $this->reports->deadlineReport($basis, $from, $to, $filters);

        return view('reports.deadline', $this->data($request, $basis, $from, $to, $filters) + compact('report'));
    }

    private function parameters(Request $request): array
    {
        $basis = array_key_exists($request->query('date_basis'), Ticket::DATE_BASES) ? $request->query('date_basis') : 'resolved_at';
        $month = CarbonImmutable::now(config('app.display_timezone'))->format('Y-m');
        [$start, $end] = DateBounds::month($month);
        $from = $request->query('from', CarbonImmutable::parse($start)->setTimezone(config('app.display_timezone'))->toDateString());
        $to = $request->query('to', CarbonImmutable::parse($end)->setTimezone(config('app.display_timezone'))->toDateString());
        $filters = $request->only(array_diff(Ticket::FILTER_KEYS, ['date_basis', 'from', 'to']));

        return [$basis, $from, $to, array_filter($filters, fn ($value) => $value !== null && $value !== '')];
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
