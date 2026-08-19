<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * ★ (2026-08-05) The late-delivery sweep (F18 extension).
 *
 * withoutOverlapping because with «تراكم التأخير» on this touches money: a run
 * that is somehow still going when the next fires must not have a second one
 * walking the same rows beside it. The unique index would refuse the duplicate
 * anyway — this just means it never has to.
 *
 * Needs a system cron calling `php artisan schedule:run` every minute; without
 * it nothing here fires. The command is safe to run by hand meanwhile.
 *
 * ★ (2026-08-19) Hourly, was dailyAt('06:00').
 *
 * The old cadence was right while every deadline was a whole day: nothing can
 * become late between 06:00 and midnight if lateness only starts when a day
 * ends, so checking once each morning saw everything. F26 broke that
 * assumption — an exception subtask is due four working hours after the error,
 * so it can go late at 14:00 and a once-a-day sweep would not notice until the
 * following morning, sixteen hours after the fact.
 *
 * Safe to run twenty-four times a day because the charge is keyed by DAY, not
 * by run: UNIQUE(subtask_id, 'penalty:YYYY-MM-DD') means the second sweep of
 * any given day is refused by the database. So a whole-day subtask is still
 * docked exactly once per day with accumulation on, and exactly once ever with
 * it off — identical to the behaviour verified on 2026-08-05. All the extra
 * runs buy is that an exact deadline is caught within the hour.
 */
Schedule::command('points:charge-late')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
