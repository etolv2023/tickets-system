<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. F23 exposes it read-only; nothing in the app updates or
 * deletes a row.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'impersonation_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ★ (2026-08-29) F29. Set only when this action was performed by somebody
     * acting as `user`. Null on the overwhelming majority of rows.
     */
    public function impersonation(): BelongsTo
    {
        return $this->belongsTo(ImpersonationSession::class, 'impersonation_id');
    }

    /** Somebody else's hands did this. */
    public function wasImpersonated(): bool
    {
        return $this->impersonation_id !== null;
    }
}
