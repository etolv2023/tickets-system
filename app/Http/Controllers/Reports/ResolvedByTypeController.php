<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PriorityDefinition;
use App\Models\Ticket;
use App\Models\TicketTypeDefinition;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ★ (2026-10-05) F19.5 — /reports/resolved-by-type: who resolved how many of
 * which type, over a date range. Its own controller rather than one more
 * action on ReportController, which is already past the 150-line mark
 * CLAUDE.md § 3 sets.
 *
 * The numbers come from ReportService::resolvedByType; this only reads the
 * query string, settles the defaults and hands the view what it prints.
 */
class ResolvedByTypeController extends Controller
{
    /** The query-string keys this screen and its export both read. */
    public const FILTER_KEYS = ['person', 'relation', 'from', 'to', 'type', 'company', 'priority'];

    /** Shortcuts under the date range — the ranges a manager actually asks for. */
    public const QUICK_RANGES = [
        'this_month' => 'الشهر ده',
        'last_month' => 'الشهر اللي فات',
        'quarter' => 'آخر ٣ شهور',
        'year' => 'السنة دي',
    ];

    public function __construct(private readonly ReportService $reports)
    {
    }

    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = self::resolve($request->only(self::FILTER_KEYS));

        return view('reports.resolved-by-type', [
            'filters' => $filters,
            'data' => $this->reports->resolvedByType($filters),
            'relations' => array_diff_key(Ticket::RELATIONS, ['any' => true]),
            'types' => TicketTypeDefinition::options(),
            'priorities' => PriorityDefinition::options(),
            'quickRanges' => self::quickRanges(),
            'selectedPerson' => filled($filters['person'])
                ? User::whereKey($filters['person'])->value('name')
                : null,
            'selectedCompany' => filled($filters['company'])
                ? Company::whereKey($filters['company'])->value('name')
                : null,
        ]);
    }

    /**
     * The raw query string with its dates settled: a missing or malformed date
     * lands on the current month rather than on an exception, and a range
     * typed backwards is turned the right way round instead of answering with
     * an empty table that reads as "nobody resolved anything".
     *
     * Shared with the export so the file always covers the screen's range.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function resolve(array $filters): array
    {
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) === 1 ? (string) $v : null;

        $from = $date($filters['from'] ?? null);
        $to = $date($filters['to'] ?? null);

        if ($from === null && $to === null) {
            $now = CarbonImmutable::now();
            $from = $now->startOfMonth()->toDateString();
            $to = $now->endOfMonth()->toDateString();
        }

        $from ??= $to;
        $to ??= $from;

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'person' => $filters['person'] ?? null,
            'relation' => $filters['relation'] ?? 'assigned',
            'from' => $from,
            'to' => $to,
            'type' => $filters['type'] ?? null,
            'company' => $filters['company'] ?? null,
            'priority' => $filters['priority'] ?? null,
        ];
    }

    /** @return array<string, array{label: string, from: string, to: string}> */
    private static function quickRanges(): array
    {
        $now = CarbonImmutable::now();

        $bounds = [
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()],
            'quarter' => [$now->subMonths(2)->startOfMonth(), $now->endOfMonth()],
            'year' => [$now->startOfYear(), $now->endOfYear()],
        ];

        $ranges = [];

        foreach (self::QUICK_RANGES as $key => $label) {
            $ranges[$key] = [
                'label' => $label,
                'from' => $bounds[$key][0]->toDateString(),
                'to' => $bounds[$key][1]->toDateString(),
            ];
        }

        return $ranges;
    }
}
