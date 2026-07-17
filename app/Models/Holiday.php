<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Holiday extends Model
{
    public const CACHE_KEY = 'holidays.all';

    protected $fillable = ['date', 'name', 'is_recurring'];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_recurring' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * date-key => name, cached. A recurring holiday is stored once and matched
     * by month-day, so it lands every year without a row per year.
     *
     * Keys are 'MM-DD' for recurring entries and 'YYYY-MM-DD' for one-offs.
     *
     * @return array<string, string>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::all()
            ->mapWithKeys(fn (self $h) => [
                $h->is_recurring ? $h->date->format('m-d') : $h->date->toDateString() => $h->name,
            ])
            ->all());
    }

    public static function nameFor(\Carbon\CarbonInterface $date): ?string
    {
        $map = static::map();

        return $map[$date->toDateString()] ?? $map[$date->format('m-d')] ?? null;
    }
}
