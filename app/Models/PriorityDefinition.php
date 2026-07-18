<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The admin-manageable priority list that replaced the fixed Priority enum.
 * Same pattern as TicketStatusDefinition — cached forever, busted on write.
 */
class PriorityDefinition extends Model
{
    protected $table = 'priorities';

    public const CACHE_KEY = 'priorities.map';

    /** @var array<string, string> the semantic palette a priority colour may come from (§ 6). */
    public const COLORS = Label::COLORS;

    protected $fillable = ['key', 'name_ar', 'color', 'sla_hours', 'is_system', 'position'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(self::CACHE_KEY);

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
        return array_map(fn (self $p) => $p->name_ar, static::map());
    }
}
