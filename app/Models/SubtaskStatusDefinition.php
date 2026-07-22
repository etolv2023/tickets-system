<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The admin-manageable subtask-status list that replaced the fixed SubtaskStatus
 * enum. Same pattern as TicketTypeDefinition / TicketStatusDefinition — cached
 * forever, busted on write.
 *
 * `done`, `in_progress` and `blocked` keys carry code behaviour (points and
 * counters, started_at, the required reason), so they are seeded as system rows
 * with immutable keys. A custom status is a neutral open state.
 */
class SubtaskStatusDefinition extends Model
{
    protected $table = 'subtask_statuses';

    public const CACHE_KEY = 'subtask_statuses.map';

    /** @var array<string, string> the semantic palette a status colour may come from. */
    public const COLORS = Label::COLORS;

    protected $fillable = ['key', 'name_ar', 'color', 'needs_reason', 'is_system', 'position'];

    protected function casts(): array
    {
        return ['needs_reason' => 'boolean', 'is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        $bust = function () {
            Cache::forget(self::CACHE_KEY);
            \App\Casts\SubtaskStatusValue::forget();
        };

        static::saved($bust);
        static::deleted($bust);
    }

    /** @return array<string, self> */
    public static function map(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::orderBy('position')->get()->keyBy('key')->all()
        );
    }

    /** @return array<string, string> key => name_ar, ordered for a <select> */
    public static function options(): array
    {
        return array_map(fn (self $s) => $s->name_ar, static::map());
    }

    /**
     * The statuses a drag / quick-change may set — everything that doesn't need
     * a reason (a reason can't be typed while dragging a card). F08
     *
     * @return array<int, string>
     */
    public static function quickChangeKeys(): array
    {
        return array_values(array_map(
            fn (self $s) => $s->key,
            array_filter(static::map(), fn (self $s) => ! $s->needs_reason)
        ));
    }

    /**
     * Keys of the statuses that require a reason (blocked out of the box) — the
     * subtask form shows/requires its reason field for these. F08
     *
     * @return array<int, string>
     */
    public static function reasonKeys(): array
    {
        return array_values(array_map(
            fn (self $s) => $s->key,
            array_filter(static::map(), fn (self $s) => $s->needs_reason)
        ));
    }
}
