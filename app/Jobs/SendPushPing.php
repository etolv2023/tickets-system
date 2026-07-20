<?php

namespace App\Jobs;

use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * F20.2 — nudges one person's browsers to go and look.
 *
 * Queued because a push is an outbound HTTP request per registered browser,
 * and a status change that notifies five people would otherwise add seconds to
 * the request that triggered it (CLAUDE.md § 4.9).
 *
 * Carries a user id and nothing else: the ping has no payload, so there is no
 * message to serialise, and a job row sitting in the queue never contains
 * anything about a ticket.
 */
class SendPushPing implements ShouldQueue
{
    use Queueable;

    /** A push nobody received in a minute is stale; the bell will catch up. */
    public int $tries = 2;

    public function __construct(public readonly int $userId)
    {
    }

    public function handle(WebPushService $push): void
    {
        $push->pingUser($this->userId);
    }
}
