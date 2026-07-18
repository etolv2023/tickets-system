<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

/**
 * The admin-manageable status list that replaced the fixed TicketStatus enum.
 * Read on every ticket render, so it's cached forever and busted on write —
 * same pattern as Role and PointRule.
 */
class TicketStatusDefinition extends Model
{
    protected $table = 'ticket_statuses';

    public const CACHE_KEY = 'ticket_statuses.map';

    /**
     * The palette a status colour may come from — the same semantic tokens
     * Label::COLORS already uses. No free colour (CLAUDE.md § 6).
     */
    public const COLORS = Label::COLORS;

    protected $fillable = ['key', 'name_ar', 'color', 'is_system', 'is_open', 'position'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_open' => 'boolean'];
    }

    protected static function booted(): void
    {
        $bust = function () {
            Cache::forget(self::CACHE_KEY);
            // A deleted status cascades to delete its transitions too (FK
            // cascadeOnDelete) — that cached graph would otherwise still list
            // a status that no longer exists.
            Cache::forget(self::CACHE_KEY . '.transitions');
        };

        static::saved($bust);
        static::deleted($bust);
    }

    public function transitionsTo(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'ticket_status_transitions',
            'from_status_id',
            'to_status_id'
        )->withTimestamps();
    }

    /**
     * key => row, cached. Everything that used to be a TicketStatus enum
     * method (label, variant, isOpen) now reads from here via TicketStatusValue.
     *
     * @return array<string, self>
     */
    public static function map(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::orderBy('position')->get()->keyBy('key')->all()
        );
    }

    /**
     * key => [allowed target keys], cached. What TicketWorkflowService::TRANSITIONS
     * used to be as a hardcoded array.
     *
     * @return array<string, array<int, string>>
     */
    public static function transitionMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY . '.transitions', function () {
            $byId = static::all()->keyBy('id');

            return TicketStatusTransition::all()
                ->groupBy('from_status_id')
                ->map(fn ($rows) => $rows->map(fn ($r) => $byId[$r->to_status_id]?->key)->filter()->values()->all())
                ->mapWithKeys(fn ($tos, $fromId) => [$byId[$fromId]->key => $tos])
                ->all();
        });
    }

    /** @return array<string, string> key => name_ar, ordered for a <select> */
    public static function options(): array
    {
        return array_map(fn (self $s) => $s->name_ar, static::map());
    }
}
