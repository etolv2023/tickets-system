<?php

namespace App\Models;

use App\Enums\PointSide;
use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class PointRule extends Model
{
    public const CACHE_KEY = 'point_rules.map';

    public const ROLE_CACHE_KEY = 'point_rules.role_map';

    protected $fillable = ['ticket_type', 'scope', 'side', 'role_id', 'points', 'is_active', 'updated_by'];

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
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::ROLE_CACHE_KEY);
        });
        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::ROLE_CACHE_KEY);
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * "type|scope|side" => rule, cached. The engine resolves a rule per
     * participant; a query each would be four per resolved ticket (§ 4.7).
     * Role-based rows (side null, role_id set) live in roleMap() instead —
     * `$r->side->value` would throw on a null side here.
     *
     * @return array<string, self>
     */
    public static function map(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::whereNotNull('side')->get()->keyBy(
                fn (self $r) => "{$r->ticket_type->value}|{$r->scope}|{$r->side->value}"
            )->all()
        );
    }

    /**
     * "type|role_id" => rule, cached. The role-assignment counterpart of
     * map() — one rule per (ticket type, role), no scope/side involved.
     *
     * @return array<string, self>
     */
    public static function roleMap(): array
    {
        return Cache::rememberForever(
            self::ROLE_CACHE_KEY,
            fn () => static::whereNotNull('role_id')->get()->keyBy(
                fn (self $r) => "{$r->ticket_type->value}|{$r->role_id}"
            )->all()
        );
    }
}
