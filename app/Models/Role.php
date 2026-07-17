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

    protected $fillable = ['key', 'name_ar', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Permissions are read on every authorization check, so the map is
        // cached forever and busted on write (CLAUDE.md § 4.7).
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::ID_MAP_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::ID_MAP_KEY);
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

    public function scopeDeletable($query)
    {
        return $query->where('is_system', false);
    }
}
