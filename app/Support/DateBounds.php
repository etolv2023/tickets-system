<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A date a person picks is a calendar day where they are (display timezone),
 * but every timestamp column is stored in UTC. `whereDate(col, ...)` compared
 * the UTC day instead — a ticket reported at 01:00 Cairo time landed on the
 * previous day — and wrapping the column in DATE() also kept MySQL off its
 * index.
 *
 * So the day is turned into the UTC instants that bound it, once, here, and
 * callers compare the raw column against them.
 *
 * DATE columns (due_date, start_date) hold a calendar day with no timezone at
 * all; they compare against day() rather than range().
 */
final class DateBounds
{
    /**
     * UTC bounds of a display-timezone day range: the first instant of `from`
     * and the last second of `to`. A side that is empty or not a `Y-m-d` day
     * comes back null, so a hand-edited url drops that side of the filter
     * instead of throwing.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function range(?string $from, ?string $to): array
    {
        return [
            self::parse($from)?->startOfDay()->utc()->toDateTimeString(),
            self::parse($to)?->endOfDay()->utc()->toDateTimeString(),
        ];
    }

    /**
     * UTC bounds of a display-timezone calendar month (`Y-m`).
     *
     * @return array{0: string, 1: string}
     */
    public static function month(string $period): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $period, config('app.display_timezone'));

        return [
            $start->utc()->toDateTimeString(),
            $start->endOfMonth()->utc()->toDateTimeString(),
        ];
    }

    /** A `Y-m-d` day as-is for a DATE column, or null when it is not one. */
    public static function day(?string $value): ?string
    {
        return self::parse($value)?->toDateString();
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.display_timezone'));

        // createFromFormat rolls 2026-02-31 over into March; refuse it instead.
        return $day !== false && $day->toDateString() === $value ? $day : null;
    }
}
