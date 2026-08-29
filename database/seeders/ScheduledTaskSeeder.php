<?php

namespace Database\Seeders;

use App\Models\ScheduledTask;
use App\Support\ScheduledTaskRegistry;
use Illuminate\Database\Seeder;

/**
 * ★ (2026-08-29) A row for every task in the registry.
 *
 * A system seeder, not a demo one: a task with no row is never registered with
 * the scheduler, so without this a fresh install has a working points engine
 * that never charges anybody for being late.
 *
 * firstOrCreate on the key — the one thing that must never happen here is
 * re-seeding an existing installation back to the default schedule, silently
 * undoing whatever an administrator set from the screen. New keys get a row at
 * the registry's suggested cadence; existing rows are left exactly alone,
 * including a task somebody deliberately switched off.
 */
class ScheduledTaskSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ScheduledTaskRegistry::TASKS as $key => $definition) {
            ScheduledTask::firstOrCreate(
                ['key' => $key],
                ['cron' => $definition['cron'], 'is_enabled' => true],
            );
        }
    }
}
