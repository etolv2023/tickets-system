<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ★ (2026-08-29) One stretch of somebody acting as somebody else — F29.
 *
 * The actions themselves live in activity_logs under the impersonated user's
 * name, which is what keeps every other screen truthful. This row is the only
 * place that knows whose hands were on the keyboard.
 */
class ImpersonationSession extends Model
{
    protected $fillable = [
        'impersonator_id', 'impersonated_id', 'started_at', 'ended_at', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'actions_count' => 'integer',
        ];
    }

    /** Who borrowed the face. */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /** Whose face was borrowed. */
    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_id');
    }

    /** Everything logged while it was running. */
    public function actions(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'impersonation_id');
    }

    /**
     * Still running.
     *
     * A session with no end is usually a closed browser rather than somebody
     * still in there — the session cookie is gone but nothing told us. Shown as
     * "لسه مفتوحة" instead of being quietly closed, because guessing an end
     * time would be inventing a fact about who did what and when.
     */
    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function durationLabel(): string
    {
        if ($this->isOpen()) {
            return 'لسه مفتوحة';
        }

        return $this->started_at->diffForHumans($this->ended_at, syntax: true, parts: 2);
    }
}
