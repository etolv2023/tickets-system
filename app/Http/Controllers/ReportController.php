<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F19 — the numbers. You come here on purpose; they're never pushed at you. */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        [$from, $to] = $this->bounds($request);

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'period' => $this->period($request),
            'distribution' => $this->reports->ticketDistribution($from, $to),
            'companies' => $this->reports->companyPerformance($from, $to),
            'resolution' => $this->reports->resolutionTimes($from, $to),
            'breaches' => $this->reports->slaBreaches($from, $to),
            'load' => $this->reports->teamLoad(),
            'time' => $this->reports->timeReport($from, $to),
            'months' => $this->months(),
        ]);
    }

    /** F19.1 */
    public function employee(Request $request, User $user): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $period = $this->period($request);

        return view('reports.employee', [
            'employee' => $user,
            'period' => $period,
            'months' => $this->months(),
            'data' => $this->reports->employeeProfile($user, $period),
        ]);
    }

    /** F19.2 */
    public function leaderboard(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.view.all'), 403);

        $period = $this->period($request);

        return view('reports.leaderboard', [
            'period' => $period,
            'months' => $this->months(),
            'rows' => $this->reports->leaderboard($period),
        ]);
    }

    /** "نقاطي" — everyone can see their own. F18 */
    public function myPoints(Request $request): View
    {
        abort_unless($request->user()->hasPermission('points.view.own'), 403);

        $period = $this->period($request);

        return view('reports.my-points', [
            'period' => $period,
            'months' => $this->months(),
            'total' => $request->user()->pointsFor($period),
            'transactions' => $request->user()->pointTransactions()
                ->with('ticket:id,ticket_number,title,type')
                ->forPeriod($period)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    private function period(Request $request): string
    {
        $period = (string) $request->query('period', '');

        return preg_match('/^\d{4}-\d{2}$/', $period) === 1
            ? $period
            : CarbonImmutable::now()->format('Y-m');
    }

    /** @return array{0: string, 1: string} */
    private function bounds(Request $request): array
    {
        return $this->reports->periodBounds($this->period($request));
    }

    /** @return array<string, string> the last 12 months, for the picker */
    private function months(): array
    {
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $month = CarbonImmutable::now()->subMonths($i);
            $months[$month->format('Y-m')] = $month->translatedFormat('F Y');
        }

        return $months;
    }
}
