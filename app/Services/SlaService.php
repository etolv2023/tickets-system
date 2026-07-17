<?php

namespace App\Services;

use App\Enums\Priority;
use App\Models\Holiday;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * SLA deadlines in WORKING hours (F14).
 *
 * The naive version — reportedAt + N hours — promises the customer things the
 * team was never going to deliver: F14's own example is an urgent ticket raised
 * Thursday at 4pm, whose deadline must not land while the office is shut.
 *
 * So the clock only runs inside working hours on working days, and pauses
 * overnight, at the weekend and on holidays.
 *
 * On the two settings this needs: F22.2 lists working DAYS but not the hours of
 * the day, and F14 cannot be honest without them — a 4-hour deadline set at 4pm
 * has to know when the day ends. work_day_start / work_day_end were added for
 * exactly that, and default to 09:00–17:00.
 *
 * They are distinct from daily_capacity_hours: capacity is how much PRODUCTIVE
 * work fits in a day (6h, for planning), while these are the hours the clock
 * runs for a customer promise (8h). Conflating them would either overstate
 * capacity or understate the deadline.
 */
class SlaService
{
    private const DEFAULT_START = '09:00';

    private const DEFAULT_END = '17:00';

    public function hoursFor(Priority $priority): int
    {
        return (int) Setting::get($priority->slaSettingKey(), $priority->defaultSlaHours());
    }

    public function dueAt(Priority $priority, CarbonImmutable $reportedAt): CarbonImmutable
    {
        return $this->addWorkingHours($reportedAt, $this->hoursFor($priority));
    }

    /**
     * Walks the calendar spending the budget only inside working hours.
     */
    public function addWorkingHours(CarbonImmutable $from, float $hours): CarbonImmutable
    {
        $remaining = $hours;
        $cursor = $this->nextWorkingMoment($from);

        // Guards against a settings mistake (every day off) becoming an endless
        // walk. 500 days is far past any real SLA.
        $guard = 0;

        while ($remaining > 0 && $guard < 500) {
            $guard++;

            $endOfDay = $this->endOfWorkingDay($cursor);
            // Minutes, not floatDiffInHours: that helper is deprecated in this
            // Carbon and emits a notice on every call.
            $availableToday = $endOfDay->diffInMinutes($cursor, absolute: true) / 60;

            if ($remaining <= $availableToday) {
                return $cursor->addMinutes((int) round($remaining * 60));
            }

            $remaining -= $availableToday;
            $cursor = $this->nextWorkingMoment($endOfDay->addSecond());
        }

        return $cursor;
    }

    /**
     * The first instant at or after $moment when the clock is actually running.
     * Before opening it's opening time; after closing it's the next working
     * day's opening; on a weekend or holiday it's the next working day.
     */
    private function nextWorkingMoment(CarbonImmutable $moment): CarbonImmutable
    {
        $guard = 0;

        while ($guard < 500) {
            $guard++;

            if (! $this->isWorkingDay($moment)) {
                $moment = $this->openingOf($moment->addDay());

                continue;
            }

            $opening = $this->openingOf($moment);
            $closing = $this->closingOf($moment);

            if ($moment->lt($opening)) {
                return $opening;
            }

            if ($moment->gte($closing)) {
                $moment = $this->openingOf($moment->addDay());

                continue;
            }

            return $moment;
        }

        return $moment;
    }

    private function endOfWorkingDay(CarbonImmutable $moment): CarbonImmutable
    {
        return $this->closingOf($moment);
    }

    private function openingOf(CarbonImmutable $day): CarbonImmutable
    {
        [$h, $m] = $this->parseTime(Setting::get('work_day_start', self::DEFAULT_START), self::DEFAULT_START);

        return $day->setTime($h, $m);
    }

    private function closingOf(CarbonImmutable $day): CarbonImmutable
    {
        [$h, $m] = $this->parseTime(Setting::get('work_day_end', self::DEFAULT_END), self::DEFAULT_END);

        return $day->setTime($h, $m);
    }

    /** @return array{0: int, 1: int} */
    private function parseTime(mixed $value, string $fallback): array
    {
        $time = is_string($value) && preg_match('/^\d{1,2}:\d{2}$/', $value) === 1 ? $value : $fallback;

        [$h, $m] = explode(':', $time);

        return [(int) $h, (int) $m];
    }

    /** @return array<int, int> */
    private function workDays(): array
    {
        return array_map('intval', (array) Setting::get('work_days', [0, 1, 2, 3, 4]));
    }

    public function isWorkingDay(CarbonImmutable $day): bool
    {
        return Holiday::nameFor($day) === null
            && in_array($day->dayOfWeek, $this->workDays(), true);
    }
}
