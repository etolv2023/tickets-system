<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail (CLAUDE.md § 5). Read by /admin/audit in phase 8.
 *
 * Takes the actor and request metadata as arguments rather than reaching for
 * auth() or request(): a service must stay callable from a command or a queue
 * worker (CLAUDE.md § 3).
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $changes  before/after pairs
     */
    public function log(
        string $action,
        ?int $userId = null,
        ?Model $subject = null,
        array $changes = [],
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : $changes,
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ]);
    }
}
