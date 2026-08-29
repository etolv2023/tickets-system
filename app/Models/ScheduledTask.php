<?php

namespace App\Models;

use App\Support\ScheduledTaskRegistry;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * ★ (2026-08-29) One scheduled task, as configured rather than as coded.
 *
 * The row never holds a command — only a registry key. Everything the row does
 * not know (what the command is, what it does, whether it touches money) comes
 * from ScheduledTaskRegistry, which lives in code. See that class.
 */
class ScheduledTask extends Model
{
    /**
     * How the schedule screen knows the system cron is alive.
     *
     * `schedule:run` writes this every minute. Without it the screen would show
     * a beautifully configured list of tasks and no way to tell that nothing is
     * executing any of them — which is the failure this feature is most likely
     * to hide, because it looks exactly like everything working.
     */
    public const HEARTBEAT_KEY = 'schedule.heartbeat';

    /** Past this with no heartbeat, the cron is treated as down. */
    public const HEARTBEAT_STALE_MINUTES = 15;

    protected $fillable = ['key', 'cron', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_started_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'last_exit_code' => 'integer',
            'last_duration_ms' => 'integer',
        ];
    }

    /**
     * What routes/console.php registers: enabled tasks that the registry still
     * recognises, as key => cron.
     *
     * ══════════════════════════════════════════════════════════════════════
     *  THIS RUNS ON EVERY SINGLE ARTISAN COMMAND, INCLUDING `migrate` ON A
     *  DATABASE THAT DOES NOT EXIST YET. IT MUST NEVER THROW.
     * ══════════════════════════════════════════════════════════════════════
     *
     * routes/console.php is loaded whenever the console kernel boots. On a
     * fresh install that is `php artisan migrate` against an empty schema, and
     * during the install wizard it is artisan calls made before .env even has
     * database credentials. A query here that throws takes the installer down
     * with an error about a table nobody has heard of.
     *
     * Uncached on purpose. The alternative is one cache entry that has to be
     * busted from a web request and read by a separate `schedule:run` process a
     * minute later — a whole class of "I changed the time and it kept running
     * at the old one" bugs, to save a two-row query once a minute.
     *
     * @return array<string, string>
     */
    public static function activeSchedule(): array
    {
        try {
            if (! Schema::hasTable('scheduled_tasks')) {
                return [];
            }

            return static::query()
                ->where('is_enabled', true)
                ->whereIn('key', ScheduledTaskRegistry::keys())
                ->pluck('cron', 'key')
                ->all();
        } catch (Throwable) {
            // No database, no credentials, mid-migration. The scheduler simply
            // registers nothing this run.
            return [];
        }
    }

    /** The registry entry behind this row, or null if the key was retired. */
    public function definition(): ?array
    {
        return ScheduledTaskRegistry::get($this->key);
    }

    public function name(): string
    {
        return $this->definition()['name'] ?? $this->key;
    }

    public function touchesPoints(): bool
    {
        return (bool) ($this->definition()['touches_points'] ?? false);
    }

    /**
     * When this will next run, or null if it is off or its cron is unusable.
     *
     * Computed, never stored: a stored next_run_at is wrong the moment the
     * expression changes and there is nothing to keep the two honest.
     */
    public function nextRunAt(): ?CarbonImmutable
    {
        if (! $this->is_enabled || ! CronExpression::isValidExpression((string) $this->cron)) {
            return null;
        }

        try {
            return CarbonImmutable::instance(
                (new CronExpression($this->cron))->getNextRunDate()
            );
        } catch (Throwable) {
            return null;
        }
    }

    /** Ran, and the last run finished cleanly. */
    public function lastRunSucceeded(): bool
    {
        return $this->last_exit_code === 0;
    }

    public function hasRun(): bool
    {
        return $this->last_started_at !== null;
    }

    /**
     * Is anything actually executing the schedule?
     *
     * A false here means every "المرة الجاية" on the screen is fiction.
     */
    public static function cronIsAlive(): bool
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);

        return $beat !== null
            && CarbonImmutable::parse($beat)->greaterThan(now()->subMinutes(self::HEARTBEAT_STALE_MINUTES));
    }

    public static function lastHeartbeat(): ?CarbonImmutable
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);

        return $beat === null ? null : CarbonImmutable::parse($beat);
    }
}
