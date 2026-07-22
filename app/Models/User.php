<?php

namespace App\Models;

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
        'daily_capacity_hours',
        'must_change_password',
        'avatar_path',
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
}
