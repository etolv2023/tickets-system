<?php

namespace App\Models;

use App\Enums\UserSkill;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'skills',
        'daily_capacity_hours',
        'must_change_password',
        'avatar_path',
        'is_active',
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
            'skills' => UserSkill::class,
            'daily_capacity_hours' => 'decimal:2',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Tickets this person owns the frontend side of. F19 */
    public function assignedFrontend(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_frontend_id');
    }

    public function assignedBackend(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_backend_id');
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
     */
    public function hasPermission(string $key): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return in_array($key, Role::permissionMap()[$this->role_id] ?? [], true);
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

    /** Users who can take frontend work — driven by skills, not role. F00.3 */
    public function scopeFrontendCapable(Builder $query): Builder
    {
        return $query->whereIn('skills', [UserSkill::Frontend->value, UserSkill::Both->value]);
    }

    public function scopeBackendCapable(Builder $query): Builder
    {
        return $query->whereIn('skills', [UserSkill::Backend->value, UserSkill::Both->value]);
    }
}
