<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PointValueRequest;
use App\Models\TicketTypeDefinition;
use App\Services\ActivityLogger;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ★ (2026-08-19) F18.3 — the rate card, and what it comes to.
 *
 * Two halves of one question, deliberately on one screen. The top half sets
 * what a point is worth per ticket type; the bottom half shows what that makes
 * everyone's month. Splitting them across two pages would mean editing a rate
 * and then navigating somewhere else to find out what it did — and these are
 * salary numbers, so seeing the consequence next to the control is the point.
 *
 * Gated on settings.manage, the same permission as every other rate-shaped
 * setting (SLA hours, working days). It is deliberately NOT points.rules.manage:
 * that one is about correcting individual ledger rows, which is a different
 * authority from setting the price of everyone's work.
 */
class PointValueController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        $period = $this->period($request);

        return view('admin.point-values.index', [
            'types' => TicketTypeDefinition::orderBy('position')->get(),
            'period' => $period,
            'months' => $this->months(),
        ] + $this->reports->moneyReport($period));
    }

    /**
     * Rates arrive as one map, type id => price, and are saved together.
     *
     * One form and one submit rather than a save button per row: a rate card is
     * read across its rows — «الفيتشر ضعف البج» is a statement about two numbers
     * — and saving them one at a time leaves the card in states nobody meant,
     * where half the types are repriced and half are not.
     */
    public function update(PointValueRequest $request, ActivityLogger $log): RedirectResponse
    {
        $values = $request->validated()['values'];
        $types = TicketTypeDefinition::whereKey(array_keys($values))->get();
        $changes = [];

        foreach ($types as $type) {
            $new = (float) $values[$type->id];
            $old = (float) $type->point_value;

            // Compared as floats, not strings: the column is decimal:2, so an
            // untouched field posts back "20" against a stored "20.00" and a
            // string comparison would log a change on every save.
            if ($old === $new) {
                continue;
            }

            $type->update(['point_value' => $new]);
            $changes[$type->key] = ['from' => $old, 'to' => $new];
        }

        if ($changes !== []) {
            // These numbers decide what people are paid, so a change to one is
            // exactly the kind of edit CLAUDE.md § 5 requires an audit row for.
            $log->log(
                action: 'settings.point_values.updated',
                userId: $request->user()->id,
                changes: $changes,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return redirect()
            ->route('admin.point-values.index', $request->only('period'))
            ->with('status', $changes === []
                ? 'مفيش حاجة اتغيّرت.'
                : 'اتحفظ سعر النقطة لـ ' . count($changes) . ' نوع.');
    }

    /** The month being reported on. Defaults to this one; anything malformed falls back to it too. */
    private function period(Request $request): string
    {
        $period = (string) $request->query('period', '');

        return preg_match('/^\d{4}-\d{2}$/', $period) === 1
            ? $period
            : CarbonImmutable::now()->format('Y-m');
    }

    /**
     * The last twelve months for the picker — the same window every other
     * points screen offers, so switching between them keeps the same choices.
     *
     * @return array<string, string>
     */
    private function months(): array
    {
        $months = [];
        $cursor = CarbonImmutable::now();

        for ($i = 0; $i < 12; $i++) {
            $months[$cursor->format('Y-m')] = $cursor->translatedFormat('F Y');
            $cursor = $cursor->subMonth();
        }

        return $months;
    }
}
