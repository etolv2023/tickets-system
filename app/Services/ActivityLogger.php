<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ImpersonationSession;
use App\Support\ImpersonationContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail (CLAUDE.md § 5). Read by /admin/audit in phase 8.
 *
 * Takes the actor and request metadata as arguments rather than reaching for
 * auth() or request(): a service must stay callable from a command or a queue
 * worker (CLAUDE.md § 3).
 *
 * ★ (2026-08-29) F29 — the one thing it does NOT take as an argument is whether
 * this action is being performed by somebody wearing another user's face. That
 * arrives through an injected ImpersonationContext, which the middleware fills
 * from the session and nothing fills in a queue worker.
 *
 * It is not a parameter because it must not be forgettable. There are around
 * thirty log() calls in this application; a thirty-first written next year, by
 * somebody who has never heard of impersonation, still has to be stamped. An
 * argument would be omitted exactly where it mattered most.
 *
 * user_id stays the IMPERSONATED user on purpose. The log has to agree with
 * every other screen about who resolved the ticket; impersonation_id is the
 * footnote that says whose hands were on it.
 */
class ActivityLogger
{
    public function __construct(private readonly ImpersonationContext $impersonation)
    {
    }

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
        $impersonationId = $this->impersonation->sessionId();

        ActivityLog::create([
            'user_id' => $userId,
            'impersonation_id' => $impersonationId,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : $changes,
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ]);

        // The list screen shows "did they actually DO anything" per session, and
        // wants it without counting rows for every line (CLAUDE.md § 4.6).
        if ($impersonationId !== null) {
            ImpersonationSession::whereKey($impersonationId)->increment('actions_count');
        }
    }
}
