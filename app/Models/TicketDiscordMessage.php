<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempted Discord message about one ticket.
 *
 * The type constants are the vocabulary the rest of the integration speaks in;
 * the status constants are a small state machine:
 *
 *   pending ──claim──> processing ──> sent
 *                                 ├─> failed      Discord refused, permanently
 *                                 ├─> skipped     nothing to send to (no Discord id)
 *                                 └─> unverified  we cannot tell whether it arrived
 *
 * 'unverified' is the honest answer to a worker that died between the API call
 * and the response being stored. Resending would risk a duplicate; pretending it
 * sent would be a lie. It stays visible instead.
 *
 * OPERATIONALLY: unverified means DELIVERY MAY ALREADY HAVE HAPPENED, and the
 * automatic resend is deliberately blocked so no duplicate can be created. It is
 * terminal — the claim in SendDiscordMessage cannot pick it up, failed() cannot
 * overwrite it, and no scope treats it as pending. There is intentionally NO
 * force-resend path: the safe action is a person looking at the channel.
 * `php artisan discord:check` lists these rows so an administrator can decide.
 */
class TicketDiscordMessage extends Model
{
    /** The ticket's own announcement in the general channel. */
    public const TYPE_CREATED_GENERAL = 'ticket_created_general';

    /** DM: this ticket is now yours. */
    public const TYPE_ASSIGNED = 'ticket_assigned';

    /** DM: it is not yours any more. */
    public const TYPE_UNASSIGNED = 'ticket_unassigned';

    /** Thread: a role changed hands. */
    public const TYPE_REASSIGNED_GENERAL = 'ticket_reassigned_general';

    /** Thread: the status moved. */
    public const TYPE_STATUS_CHANGED = 'ticket_status_changed';

    /** Thread: the work is done. */
    public const TYPE_RESOLVED = 'ticket_resolved';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_UNVERIFIED = 'unverified';

    protected $fillable = [
        'ticket_id', 'user_id', 'role_id', 'type', 'dedupe_key', 'nonce',
        'channel_id', 'message_id', 'thread_id', 'reply_to_message_id',
        'payload', 'status', 'claimed_at', 'error', 'attempts', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The ticket's announcement. Its existence is what "this ticket is live on
     * Discord" means — thread updates are only allowed once it is there.
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CREATED_GENERAL);
    }

    /** Reached a state no worker will move it out of. */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_FAILED,
            self::STATUS_SKIPPED,
            self::STATUS_UNVERIFIED,
        ], true);
    }

    /**
     * Rows a person has to look at.
     *
     * unverified — delivery may already have happened; the automatic resend is
     * intentionally blocked. Nothing retries these on its own, and nothing
     * should be added that does.
     *
     * failed — Discord refused it for a reason that will not change by itself
     * (bad token, missing permission, closed DMs).
     *
     * Both are terminal; neither is ever treated as pending.
     */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_UNVERIFIED, self::STATUS_FAILED]);
    }

    /**
     * The nonce Discord de-duplicates on.
     *
     * Derived rather than stored so it costs nothing to insert and cannot drift:
     * every attempt at this row computes the same string, which is exactly the
     * property enforce_nonce needs. Comfortably inside Discord's 25-character
     * limit — a nine-digit ticket id and a nine-digit row id still fit.
     */
    public function nonceValue(): string
    {
        return 't' . $this->ticket_id . 'r' . $this->id;
    }

    /** A DM goes to a person; everything else goes to the channel or a thread. */
    public function isDirectMessage(): bool
    {
        return in_array($this->type, [self::TYPE_ASSIGNED, self::TYPE_UNASSIGNED], true);
    }
}
