<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'roles.permissions.map';

    public const ID_MAP_KEY = 'roles.id.map';

    public const ASSIGNABLE_CACHE_KEY = 'roles.assignable_on_tickets';

    public const WORK_LOGGING_CACHE_KEY = 'roles.logs_work';

    public const TESTER_CACHE_KEY = 'roles.is_tester';

    protected $fillable = ['key', 'name_ar', 'is_system', 'assignable_on_tickets', 'logs_work', 'is_tester'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'assignable_on_tickets' => 'boolean',
            'logs_work' => 'boolean',
            'is_tester' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Permissions are read on every authorization check, so the map is
        // cached forever and busted on write (CLAUDE.md § 4.7).
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::ID_MAP_KEY);
            Cache::forget(self::ASSIGNABLE_CACHE_KEY);
            Cache::forget(self::WORK_LOGGING_CACHE_KEY);
            Cache::forget(self::TESTER_CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::ID_MAP_KEY);
            Cache::forget(self::ASSIGNABLE_CACHE_KEY);
            Cache::forget(self::WORK_LOGGING_CACHE_KEY);
            Cache::forget(self::TESTER_CACHE_KEY);
        });
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Always change permissions through here. sync() writes the pivot directly
     * and fires no model event, so the cached map would otherwise survive the
     * change and every user would keep their old permissions.
     *
     * @param  array<int, int>  $permissionIds
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);

        Cache::forget(self::CACHE_KEY);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * role id => [permission keys]. Cached, so an authorization check costs no
     * query. Keyed by id rather than key so it never depends on the role
     * relation being loaded — which preventLazyLoading would refuse anyway.
     *
     * @return array<int, array<int, string>>
     */
    public static function permissionMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()
                ->with('permissions:id,key')
                ->get(['id'])
                ->mapWithKeys(fn (self $role) => [
                    $role->id => $role->permissions->pluck('key')->all(),
                ])
                ->all();
        });
    }

    /**
     * key => id, cached. Lets a query filter by role without joining or
     * eager-loading the relation just to read one key.
     *
     * @return array<string, int>
     */
    public static function idMap(): array
    {
        return Cache::rememberForever(self::ID_MAP_KEY, fn () => static::pluck('id', 'key')->all());
    }

    public static function idByKey(string $key): ?int
    {
        return static::idMap()[$key] ?? null;
    }

    /**
     * F06 role-assignment extension: the roles the ticket panel offers a
     * dropdown for. A plain attribute of the role, not a permission — it
     * answers "does this role show up as an assignment target", which is a
     * different question from "what can this role's members do".
     */
    public function scopeAssignableOnTickets($query)
    {
        return $query->where('assignable_on_tickets', true);
    }

    public function isAssignableOnTickets(): bool
    {
        return (bool) $this->assignable_on_tickets;
    }

    /**
     * An assignment of this role gets a بدأت/خلصت work log (F07). Replaces the
     * old hardcoded "frontend + backend columns log work" rule — the admin now
     * flags any role from /admin/roles.
     */
    public function logsWork(): bool
    {
        return (bool) $this->logs_work;
    }

    /**
     * A ticket assigned to this role shows in the testing queue (F16). Replaces
     * the old hardcoded tester_id column.
     */
    public function isTester(): bool
    {
        return (bool) $this->is_tester;
    }

    /** @return \Illuminate\Support\Collection<int, self> ids of roles that log work */
    public static function workLoggingRoleIds()
    {
        return Cache::rememberForever(
            self::WORK_LOGGING_CACHE_KEY,
            fn () => static::where('logs_work', true)->pluck('id')
        );
    }

    /** @return \Illuminate\Support\Collection<int, int> ids of tester roles */
    public static function testerRoleIds()
    {
        return Cache::rememberForever(
            self::TESTER_CACHE_KEY,
            fn () => static::where('is_tester', true)->pluck('id')
        );
    }

    /**
     * Cached, read-only list of assignable-on-tickets roles — the subtask
     * form renders one per row, so this must not cost a query per row.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function assignableList()
    {
        return Cache::rememberForever(
            self::ASSIGNABLE_CACHE_KEY,
            fn () => static::assignableOnTickets()->orderBy('name_ar')->get(['id', 'name_ar', 'logs_work', 'is_tester'])
        );
    }
}
