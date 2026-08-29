<?php

namespace App\Jobs;

use App\Support\ScheduledTaskRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ★ (2026-08-29) "شغّل دلوقتي" — one scheduled task, off the request thread.
 *
 * ══════════════════════════════════════════════════════════════════════════
 *  THE KEY IS RE-CHECKED AGAINST THE REGISTRY HERE, NOT ONLY IN THE FORM.
 * ══════════════════════════════════════════════════════════════════════════
 *
 * The controller validates it too. This second check is not redundant: a job
 * payload is a row in the `jobs` table, and anything that can write there —
 * a bug, an injection, a hand-edited row — would otherwise be handing this
 * class a command name to execute. Artisan::call() on unvalidated input is a
 * shell; the check is what stops it being one.
 *
 * Queued rather than inline because these are the commands that take minutes
 * (a points sweep over a month, a first sync of four repositories) and none of
 * them belong inside a request.
 *
 * tries = 1. A person pressed a button; if it failed they can look at why and
 * press it again. Silently running a points sweep three more times is not a
 * retry policy anybody wants.
 */
class RunScheduledTask implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly string $key)
    {
    }

    public function handle(): void
    {
        if (! ScheduledTaskRegistry::has($this->key)) {
            return;
        }

        $startedAt = microtime(true);

        DB::table('scheduled_tasks')->where('key', $this->key)->update([
            'last_started_at' => now(),
            'last_finished_at' => null,
            'last_exit_code' => null,
            'last_duration_ms' => null,
            'last_output' => null,
            'updated_at' => now(),
        ]);

        try {
            $exitCode = Artisan::call($this->key);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $exitCode = 1;
            $output = $e->getMessage();
        }

        $this->record($exitCode, $output, $startedAt);
    }

    /**
     * A job that dies outright — killed worker, timeout — still has to leave
     * the row saying so, or the screen shows a task stuck "بيشتغل" forever.
     */
    public function failed(Throwable $e): void
    {
        DB::table('scheduled_tasks')->where('key', $this->key)->update([
            'last_finished_at' => now(),
            'last_exit_code' => 1,
            'last_output' => mb_substr($e->getMessage(), 0, 2000),
            'updated_at' => now(),
        ]);
    }

    private function record(int $exitCode, string $output, float $startedAt): void
    {
        DB::table('scheduled_tasks')->where('key', $this->key)->update([
            'last_finished_at' => now(),
            'last_exit_code' => $exitCode,
            'last_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            // Trimmed: `github:sync` prints a table and a points sweep can print
            // a line per subtask. The last 2000 characters are the part that
            // says how it ended, which is the part anyone reads.
            'last_output' => mb_substr(trim($output), -2000) ?: null,
            'updated_at' => now(),
        ]);
    }
}
