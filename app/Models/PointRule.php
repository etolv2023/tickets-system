<?php

namespace App\Models;

use App\Enums\PointSide;
use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PointRule extends Model
{
    public const CACHE_KEY = 'point_rules.map';

    protected $fillable = ['ticket_type', 'scope', 'side', 'points', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return [
            'ticket_type' => TicketType::class,
            'side' => PointSide::class,
            'points' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * "type|scope|side" => rule, cached. The engine resolves a rule per
     * participant; a query each would be four per resolved ticket (§ 4.7).
     *
     * @return array<string, self>
     */
    public static function map(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::all()->keyBy(
                fn (self $r) => "{$r->ticket_type->value}|{$r->scope}|{$r->side->value}"
            )->all()
        );
    }
}
