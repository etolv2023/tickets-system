<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The admin-manageable ticket-link-type list that replaced the fixed LinkType
 * enum. Same pattern as TicketTypeDefinition — cached forever, busted on write.
 *
 * `blocks` carries code behaviour (the blocked marker), so it's a system row
 * with an immutable key; the rest are plain relationships.
 */
class LinkTypeDefinition extends Model
{
    protected $table = 'link_types';

    public const CACHE_KEY = 'link_types.map';

    /** @var array<string, string> the semantic palette a link colour may come from. */
    public const COLORS = Label::COLORS;

    protected $fillable = ['key', 'name_ar', 'inverse_label_ar', 'color', 'is_system', 'position'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        $bust = function () {
            Cache::forget(self::CACHE_KEY);
            \App\Casts\LinkTypeValue::forget();
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
        return array_map(fn (self $t) => $t->name_ar, static::map());
    }
}
