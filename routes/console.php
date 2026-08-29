<?php

use App\Models\ScheduledTask;
use App\Support\ScheduledTaskRegistry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| The schedule
|--------------------------------------------------------------------------
|
| ★ (2026-08-29) Moved out of this file and into the database, so /admin
| /scheduled-tasks can turn a task off or move when it runs without a deploy.
|
| WHAT DID NOT MOVE IS THE COMMAND. A row holds a key, a cron expression and an
| on/off switch; App\Support\ScheduledTaskRegistry is what says which artisan
| command a key means, and it lives in code and is reviewed like code. A
| schedule table that stored the command string would be a remote shell behind
| a permission.
|
| Both entries below used to be written out here by hand:
|
|     points:charge-late   hourly     (F18 — the late-delivery sweep)
|     github:sync          03:00      (F27 — read the repositories)
|
| Their reasoning now lives on the registry entries, next to what they do.
|
| ScheduledTask::activeSchedule() CANNOT THROW — see its docblock. This file is
| loaded on every artisan invocation, including `migrate` against a schema that
| has no tables and installer calls made before .env has database credentials.
|
| withoutOverlapping and onOneServer on everything: the points sweep touches
| money and must never have a second copy of itself walking the same rows, and
| the GitHub sync would just spend the same API calls twice.
|
*/
foreach (ScheduledTask::activeSchedule() as $key => $cron) {
    Schedule::command($key)
        // The name is how RecordScheduledTask maps a scheduler event back to
        // its row — Laravel's events carry the description, not the key.
        ->name($key)
        ->cron($cron)
        ->withoutOverlapping()
        ->onOneServer();
}

/*
 * ★ (2026-08-29) Proof that anything is running the schedule at all.
 *
 * This is the failure the screen would otherwise hide, and it is the likeliest
 * one: if the system cron is not calling `php artisan schedule:run`, every task
 * still shows its cron and its "المرة الجاية" and none of them ever fire.
 * Nothing on the page would look wrong. A missed heartbeat is what makes it
 * look wrong.
 *
 * A cache write once a minute, nothing more. Deliberately not a scheduled
 * COMMAND — it must not appear on the screen as a task somebody can switch off,
 * because switching it off would only break the alarm, never the thing it is
 * watching.
 */
Schedule::call(fn () => Cache::put(ScheduledTask::HEARTBEAT_KEY, now()->toIso8601String(), now()->addHours(6)))
    ->name('schedule-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * A guard against the registry and the table drifting apart: a task added to
 * the registry but never seeded has no row, so it is never scheduled and never
 * appears on the screen — silently. `schedule:list` would not show it either.
 * This makes the gap visible on the one command an administrator runs when
 * something is missing.
 */
Artisan::command('schedule:audit', function () {
    $rows = ScheduledTask::pluck('key')->all();

    $missing = array_diff(ScheduledTaskRegistry::keys(), $rows);
    $orphans = array_diff($rows, ScheduledTaskRegistry::keys());

    foreach ($missing as $key) {
        $this->warn("في الكود ومفيش ليه صف: {$key} — شغّل db:seed --class=ScheduledTaskSeeder");
    }

    foreach ($orphans as $key) {
        $this->warn("صف في الداتابيز مش موجود في الكود (بيتجاهل): {$key}");
    }

    if ($missing === [] && $orphans === []) {
        $this->info('المهام المجدولة متطابقة مع الكود.');
    }
})->purpose('يقارن المهام المجدولة في الداتابيز بالقائمة البيضاء في الكود');
