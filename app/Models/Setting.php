<?php

namespace App\Models;

use App\Support\RequestMemo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const CACHE_KEY = 'settings.all';

    protected $fillable = ['key', 'value', 'type'];

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    /**
     * Whole table, cached forever, cast to native types.
     *
     * @return array<string, mixed>
     */
    public static function all_cached(): array
    {
        // Held for the request too (RequestMemo): read on nearly every screen,
        // in places once per calendar cell.
        return app(RequestMemo::class)->remember(self::CACHE_KEY, fn () => Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()
                ->get(['key', 'value', 'type'])
                ->mapWithKeys(fn (self $s) => [$s->key => $s->typedValue()])
                ->all();
        }));
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        app(RequestMemo::class)->forget(self::CACHE_KEY);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_cached()[$key] ?? $default;
    }

    protected function typedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'int' => (int) $this->value,
            'float' => (float) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
