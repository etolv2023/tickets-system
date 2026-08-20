<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Every user's permission exceptions in one cached array — see overrideMap(). */
    public const OVERRIDES_CACHE_KEY = 'users.permission_overrides';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'daily_capacity_hours',
        'must_change_password',
        'avatar_path',
        // F26.1 — the numeric Discord snowflake, so an exception ticket can
        // actually ping the person it landed on. Set by the person themselves
        // at /profile, or by an admin on the user form.
        'discord_user_id',
        'is_active',
        'email_notifications',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The role is shown next to the user's name on every screen, and roles are
     * a 6-row table. One eager query beats a lazy-load violation everywhere.
     */
    protected $with = ['role'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'daily_capacity_hours' => 'decimal:2',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'email_notifications' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The override rows are cascadeOnDelete, so deleting a user silently drops
     * their exceptions — the cached map has to go with them or it would keep
     * answering for an id that no longer exists (and could be reused).
     */
    protected static function booted(): void
    {
        static::deleted(fn () => Cache::forget(self::OVERRIDES_CACHE_KEY));
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Tickets this person holds any role on (F19). Role-based since the fixed
     * assignment columns were dropped (2026-07-24): one relation through
     * ticket_role_assignments replaces the per-side hasMany's.
     */
    public function assignedTickets(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_role_assignments')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /** Role assignments this person holds across all tickets. */
    public function ticketRoleAssignments(): HasMany
    {
        return $this->hasMany(TicketRoleAssignment::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(TicketSubtask::class, 'assignee_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(UserLeave::class);
    }

    /** F19: this month's points, for the profile header and "نقاطي". */
    public function pointsFor(string $period): float
    {
        return (float) $this->pointTransactions()->forPeriod($period)->sum('points');
    }

    /**
     * Reads the cached role => permissions map keyed by role_id, so an
     * authorization check costs neither a query nor a relation load.
     *
     * ★ (2026-08-02) Then applies this user's own exceptions on top. The role is
     * still the baseline — the override table only ever holds deliberate
     * departures from it, so a user with no exceptions behaves exactly as
     * before and the lookup below is a miss on an empty array.
     *
     * Both layers are cached forever and busted on write, because this runs on
     * every authorization check on every page. Adding a query here would put one
     * on each of the ~20 policy calls a ticket page makes (§ 4).
     */
    public function hasPermission(string $key): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $overrides = self::overrideMap()[$this->id] ?? [];

        // An exception wins over the role in both directions: it can add a key
        // the role lacks, and take away one the role grants.
        if (array_key_exists($key, $overrides)) {
            return $overrides[$key];
        }

        return in_array($key, Role::permissionMap()[$this->role_id] ?? [], true);
    }

    /** F07: who this person is allowed to keep waiting — see WorklogCompletionWaiver. */
    public function completionWaivers(): HasMany
    {
        return $this->hasMany(WorklogCompletionWaiver::class);
    }

    /** Deliberate exceptions to a user's role permissions (grant or revoke). */
    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withPivot('granted')->withTimestamps();
    }

    /**
     * user id => [permission key => granted]. One cached array for the whole
     * table: it holds only exceptions, so it stays a handful of rows even with
     * hundreds of users — far cheaper to cache whole than per user, and one
     * bust key instead of one per person.
     *
     * @return array<int, array<string, bool>>
     */
    public static function overrideMap(): array
    {
        return Cache::rememberForever(self::OVERRIDES_CACHE_KEY, function () {
            return DB::table('permission_user')
                ->join('permissions', 'permissions.id', '=', 'permission_user.permission_id')
                ->get(['permission_user.user_id', 'permissions.key', 'permission_user.granted'])
                ->groupBy('user_id')
                ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [$row->key => (bool) $row->granted])->all())
                ->all();
        });
    }

    /**
     * Always change overrides through here — a direct sync() on the relation
     * writes the pivot without firing a model event, so the cache above would
     * survive the change and the user would keep their old permissions.
     *
     * @param  array<string, bool>  $verdicts  permission id => granted
     */
    public function syncPermissionOverrides(array $verdicts): void
    {
        $this->permissionOverrides()->sync(
            collect($verdicts)->map(fn (bool $granted) => ['granted' => $granted])->all()
        );

        Cache::forget(self::OVERRIDES_CACHE_KEY);
    }

    /** Initials for the avatar fallback — first letter of the first two words. */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return Str::upper(mb_substr($words[0] ?? '؟', 0, 1) . mb_substr($words[1] ?? '', 0, 1));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
