<?php

namespace App\Models;

use App\Support\RequestMemo;
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
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
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
        // Held for the request too (RequestMemo): the calendar asks once per day.
        return app(RequestMemo::class)->remember(self::CACHE_KEY, fn () => Cache::rememberForever(self::CACHE_KEY, fn () => static::all()
            ->mapWithKeys(fn (self $h) => [
                $h->is_recurring ? $h->date->format('m-d') : $h->date->toDateString() => $h->name,
            ])
            ->all()));
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        app(RequestMemo::class)->forget(self::CACHE_KEY);
    }

    public static function nameFor(\Carbon\CarbonInterface $date): ?string
    {
        $map = static::map();

        return $map[$date->toDateString()] ?? $map[$date->format('m-d')] ?? null;
    }
}
