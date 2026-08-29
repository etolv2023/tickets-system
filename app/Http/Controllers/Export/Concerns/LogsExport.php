<?php

namespace App\Http\Controllers\Export\Concerns;

use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Every export writes an audit row (CLAUDE.md § 5).
 *
 * An export is the one action that takes a whole screen's worth of data out of
 * the system in a file that then travels by email — so "who pulled what, with
 * which filter" is exactly the question the audit log exists to answer.
 */
trait LogsExport
{
    /** @param array<string, mixed> $context */
    protected function logExport(Request $request, string $action, array $context = []): void
    {
        app(ActivityLogger::class)->log(
            action: $action,
            userId: $request->user()->id,
            changes: ['filters' => array_filter($context, fn ($v) => $v !== null && $v !== '')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    /** A dated filename, so two pulls of the same screen don't overwrite. */
    protected function filename(string $stem): string
    {
        return $stem . '-' . CarbonImmutable::now()->format('Y-m-d') . '.xlsx';
    }

    /** A YYYY-MM from the query string, or this month. Anything else is ignored. */
    protected function period(Request $request): string
    {
        $period = (string) $request->query('period', '');

        return preg_match('/^\d{4}-\d{2}$/', $period) === 1
            ? $period
            : CarbonImmutable::now()->format('Y-m');
    }
}
