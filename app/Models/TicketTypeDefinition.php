<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The admin-manageable ticket-type list that replaced the fixed TicketType enum.
 * Same pattern as PriorityDefinition / TicketStatusDefinition — cached forever,
 * busted on write.
 */
class TicketTypeDefinition extends Model
{
    protected $table = 'ticket_types';

    public const CACHE_KEY = 'ticket_types.map';

    /** @var array<string, string> the semantic palette a type colour may come from (§ 6). */
    public const COLORS = Label::COLORS;

    /**
     * The icons a type may use — every one already drawn in the <x-icon>
     * component. Kept to a curated set so an admin can't pick a name that
     * renders nothing.
     *
     * @var array<string, string> icon name => Arabic label
     */
    public const ICONS = [
        'bug' => 'حشرة',
        'inquiry' => 'استفسار',
        'feature' => 'نجمة',
        'module' => 'مكعب',
        'undefined' => 'غير محدد',
        'ticket' => 'تذكرة',
        'alert' => 'تنبيه',
        'flag' => 'علم',
        'clock' => 'ساعة',
        'link' => 'رابط',
    ];

    protected $fillable = ['key', 'name_ar', 'color', 'icon', 'needs_approval', 'is_system', 'position'];

    protected function casts(): array
    {
        return ['needs_approval' => 'boolean', 'is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        $bust = function () {
            Cache::forget(self::CACHE_KEY);
            \App\Casts\TicketTypeValue::forget();
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
