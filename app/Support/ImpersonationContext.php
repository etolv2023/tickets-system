<?php

namespace App\Support;

/**
 * ★ (2026-08-29) "Is this request being made by somebody wearing a face, and
 * whose session is it?" — F29.
 *
 * A container singleton, set once per request by TrackImpersonation middleware
 * and read by ActivityLogger.
 *
 * IT EXISTS SO THE LOGGER NEVER TOUCHES THE SESSION. CLAUDE.md § 3 says a
 * service takes and returns data and does not reach for session() or request(),
 * so that it stays callable from a queue worker or a console command — and
 * ActivityLogger is called from both. Injecting a context object keeps that
 * true: in a worker nothing ever sets it, so it is simply empty and every log
 * written there is an ordinary one.
 *
 * Deliberately tiny and deliberately not the source of truth. The session holds
 * the state; this is the copy the rest of the request may read.
 */
class ImpersonationContext
{
    private ?int $sessionId = null;

    public function set(?int $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function sessionId(): ?int
    {
        return $this->sessionId;
    }

    public function isActive(): bool
    {
        return $this->sessionId !== null;
    }
}
