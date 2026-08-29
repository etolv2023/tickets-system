<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-29) The schedule, moved out of routes/console.php.
 *
 * NOTE WHAT IS NOT HERE: there is no `command` column. The row carries a `key`
 * that must match an entry in App\Support\ScheduledTaskRegistry, and the
 * registry is what says which artisan command that key means. A schedule screen
 * whose database holds the command string is a remote shell behind a
 * permission; this one can only turn known tasks on and off and move when they
 * run.
 *
 * The last_* columns are a run log flattened onto the row rather than a history
 * table. What anyone actually asks of this screen is "did it run, when, and did
 * it work" — the last answer, not all of them. A history table would need its
 * own retention policy to stop a per-hour task filling it forever, which is a
 * lot of machinery for a question nobody asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->id();

            // The registry key — 'points:charge-late', 'github:sync'. Unique
            // because a task runs on one schedule; two rows for one key would
            // register it twice and run it twice.
            $table->string('key', 100)->unique();

            // A five-field cron expression. Validated against
            // Cron\CronExpression before it is ever stored, so the scheduler
            // never has to survive a malformed one.
            $table->string('cron', 100);

            $table->boolean('is_enabled')->default(true);

            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();

            // null = never run · 0 = finished cleanly · anything else = failed.
            $table->unsignedSmallInteger('last_exit_code')->nullable();
            $table->unsignedInteger('last_duration_ms')->nullable();

            // Captured for a manual "run now" only. A scheduled run's output
            // goes wherever the system cron sends it; Laravel's scheduler
            // events carry the runtime and the failure, not the text.
            $table->text('last_output')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};
