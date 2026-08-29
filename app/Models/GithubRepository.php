<?php

namespace App\Models;

use App\Enums\SubtaskSide;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * One repository the team's work lives in — F27.
 *
 * Cached forever and busted on write, the same pattern as LinkTypeDefinition:
 * there are four rows, they change roughly never, and every ticket page and
 * every list row needs to resolve a repository id to a name and a URL. Reading
 * them per request would be a query on a screen that has none to spare.
 *
 * There is deliberately no delete. Deactivating a repository stops it being
 * synced and stops it appearing in the picker; the row itself has to survive
 * because ticket_branches rows point at it, and those are evidence.
 */
class GithubRepository extends Model
{
    public const CACHE_KEY = 'github.repositories';

    protected $fillable = [
        'name', 'owner', 'repo', 'side', 'default_branch', 'is_active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'side' => SubtaskSide::class,
            'is_active' => 'boolean',
            'position' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(self::CACHE_KEY);

        static::saved($bust);
        static::deleted($bust);
    }

    /**
     * Every repository, keyed by id.
     *
     * @return array<int, self>
     */
    public static function lookup(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::orderBy('position')->orderBy('id')->get()->keyBy('id')->all()
        );
    }

    /**
     * The ones a branch can currently be found in.
     *
     * @return array<int, self>
     */
    public static function activeList(): array
    {
        return array_filter(self::lookup(), fn (self $r) => $r->is_active);
    }

    /**
     * One repository from the cached map, or null if the id is unknown.
     *
     * Not called find() — that name belongs to Eloquent and means "go to the
     * database". This one never does.
     */
    public static function fromCache(int $id): ?self
    {
        return self::lookup()[$id] ?? null;
    }

    public function branches(): HasMany
    {
        return $this->hasMany(TicketBranch::class);
    }

    public function pullRequests(): HasMany
    {
        return $this->hasMany(TicketPullRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** owner/repo — how GitHub addresses it, and how every API path starts. */
    public function fullName(): string
    {
        return $this->owner . '/' . $this->repo;
    }

    public function webUrl(): string
    {
        return rtrim(config('github.web_base'), '/') . '/' . $this->fullName();
    }

    /**
     * A branch name can contain a slash, and the slashes are path separators in
     * the tree URL rather than something to escape — rawurlencode() on the
     * whole name would produce a 404 on every `feature/…` branch. Each segment
     * is encoded on its own instead.
     */
    public function branchUrl(string $branch): string
    {
        $path = implode('/', array_map('rawurlencode', explode('/', $branch)));

        return $this->webUrl() . '/tree/' . $path;
    }

    public function pullUrl(int $number): string
    {
        return $this->webUrl() . '/pull/' . $number;
    }
}
