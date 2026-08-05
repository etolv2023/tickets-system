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
 * 06:00 so the ledger is settled before anyone starts work — whoever opens the
 * system finds yesterday already accounted for, and nobody is docked for a day
 * they were still in the middle of.
 *
 * withoutOverlapping because with «تراكم التأخير» on this touches money: a run
 * that is somehow still going when the next fires must not have a second one
 * walking the same rows beside it. The unique index would refuse the duplicate
 * anyway — this just means it never has to.
 *
 * Needs a system cron calling `php artisan schedule:run` every minute; without
 * it nothing here fires. The command is safe to run by hand meanwhile.
 */
Schedule::command('points:charge-late')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();
