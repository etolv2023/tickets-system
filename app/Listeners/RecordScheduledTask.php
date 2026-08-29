<?php

namespace App\Listeners;

use App\Models\ScheduledTask;
use App\Support\ScheduledTaskRegistry;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\DB;

/**
 * ★ (2026-08-29) Writes the run log onto the task's row.
 *
 * The screen's whole value is answering "did it actually run, and did it work" —
 * a schedule you cannot verify is a promise, not a fact. Laravel already
 * announces every scheduled run; this just writes down what it says.
 *
 * The event carries the task's `description`, which routes/console.php sets to
 * the registry key. Anything whose description is not a known key — the
 * heartbeat closure, most obviously — is ignored, which is why the registry
 * check is here and not merely an optimisation.
 *
 * Every write goes through the query builder, NOT the model: this runs inside
 * `schedule:run` a few times an hour, it must never fire a model event, and it
 * must never be the reason a scheduled job fails. A missing row is a no-op.
 */
class RecordScheduledTask
{
    public function starting(ScheduledTaskStarting $event): void
    {
        $this->write($event->task->description, [
            'last_started_at' => now(),
            // Cleared so a run in progress cannot be read as the last finished
            // one — "started 14:00, finished 09:00" is worse than a blank.
            'last_finished_at' => null,
            'last_exit_code' => null,
            'last_duration_ms' => null,
        ]);
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        $this->write($event->task->description, [
            'last_finished_at' => now(),
            'last_exit_code' => (int) ($event->task->exitCode ?? 0),
            // Laravel measures the runtime in seconds, as a float.
            'last_duration_ms' => (int) round($event->runtime * 1000),
        ]);
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $this->write($event->task->description, [
            'last_finished_at' => now(),
            // The exit code is whatever the command returned; if it threw
            // instead, there is none, and 1 is the honest stand-in for "not 0".
            'last_exit_code' => (int) ($event->task->exitCode ?: 1),
            'last_output' => mb_substr($event->exception->getMessage(), 0, 2000),
        ]);
    }

    /** @param array<string, mixed> $values */
    private function write(?string $key, array $values): void
    {
        if ($key === null || ! ScheduledTaskRegistry::has($key)) {
            return;
        }

        DB::table('scheduled_tasks')->where('key', $key)->update($values + ['updated_at' => now()]);
    }
}
