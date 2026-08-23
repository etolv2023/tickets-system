<?php

namespace App\Jobs;

use App\Models\TicketDiscordMessage;
use App\Models\User;
use App\Services\DiscordNotificationService;
use App\Services\DiscordService;
use App\Support\DiscordResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one row of ticket_discord_messages.
 *
 * Queued because a ticket handed to three people is three outbound HTTP calls,
 * and the person clicking «توزيع» must not wait for Discord (CLAUDE.md § 4.9).
 *
 * Carries only a row id. Everything the message says was frozen into the row
 * when the assignment happened, so a job sitting in the queue holds no ticket
 * data and renders identically however long it waits.
 *
 * ★ The delivery guarantee, stated honestly.
 *
 * Three separate things can produce a duplicate, and they need three answers:
 *
 *   Two workers grabbing the same row   → the atomic claim below. One UPDATE
 *                                         decides the owner; the loser returns.
 *   A retry after Discord accepted it   → the nonce, sent with enforce_nonce.
 *                                         Discord hands back the message it
 *                                         already has instead of making another.
 *   A worker that died mid-call         → neither of the above is enough. The
 *                                         row says "processing" and nobody knows
 *                                         whether the message exists.
 *
 * That last case is handled by looking: the nonce comes back on our own
 * messages, so the channel is scanned for it before anything is resent. And
 * when the row is too old for either the nonce window or the scan to be
 * trustworthy, it is marked 'unverified' and left alone. A notification that
 * might be missing beats one that might arrive twice.
 */
class SendDiscordMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $recordId)
    {
    }

    public function handle(DiscordService $discord, DiscordNotificationService $notifier): void
    {
        $before = TicketDiscordMessage::find($this->recordId);

        if ($before === null || $before->isTerminal()) {
            return;
        }

        // A previous attempt already resolved a channel, which means it may also
        // have reached Discord. Remember that before the claim overwrites it.
        $ambiguous = filled($before->channel_id);

        if (! $this->claim()) {
            return;
        }

        $record = TicketDiscordMessage::find($this->recordId);

        if ($record === null) {
            return;
        }

        if (! $discord->configured()) {
            $this->finish($record, TicketDiscordMessage::STATUS_SKIPPED, 'التكامل مقفول');

            return;
        }

        $channelId = $this->resolveChannel($record, $discord);

        if ($channelId === null) {
            return; // resolveChannel has already settled or released the row
        }

        // Written BEFORE the send, so a crash on the next line still leaves
        // enough behind for the scan above to find the message.
        $record->forceFill(['channel_id' => $channelId])->saveQuietly();

        if ($ambiguous && $this->recover($record, $discord, $channelId)) {
            return;
        }

        $body = $notifier->renderBody($record);

        if ($reference = $this->replyReference($record)) {
            $body['message_reference'] = $reference;
        }

        $result = $discord->postMessage($channelId, $body, $record->nonceValue());

        if (! $result->ok) {
            $this->handleFailure($record, $result);

            return;
        }

        $record->forceFill([
            'message_id' => $result->messageId,
            // Stored now, on the write that was happening anyway, so the ledger
            // and the recovery scan have it without an insert-time UPDATE.
            'nonce' => $record->nonceValue(),
            'status' => TicketDiscordMessage::STATUS_SENT,
            'sent_at' => now(),
            'error' => null,
        ])->saveQuietly();

        $this->openThread($record, $discord, $notifier, $channelId);
    }

    public function failed(\Throwable $e): void
    {
        TicketDiscordMessage::where('id', $this->recordId)
            ->whereIn('status', [TicketDiscordMessage::STATUS_PENDING, TicketDiscordMessage::STATUS_PROCESSING])
            ->update([
                'status' => TicketDiscordMessage::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);

        Log::warning('discord message job failed', [
            'record_id' => $this->recordId,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Takes ownership of the row, or returns false.
     *
     * One conditional UPDATE, so the database decides the winner. A row left in
     * 'processing' by a worker that died becomes claimable again after
     * reclaim_after — deliberately a short window, because the recovery that
     * follows is only reliable while the message is still recent.
     */
    private function claim(): bool
    {
        $staleBefore = now()->subSeconds((int) config('discord.reclaim_after', 120));

        return TicketDiscordMessage::where('id', $this->recordId)
            ->where(fn ($q) => $q
                ->where('status', TicketDiscordMessage::STATUS_PENDING)
                ->orWhere(fn ($s) => $s
                    ->where('status', TicketDiscordMessage::STATUS_PROCESSING)
                    ->where('claimed_at', '<', $staleBefore)))
            ->update([
                'status' => TicketDiscordMessage::STATUS_PROCESSING,
                'claimed_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
            ]) > 0;
    }

    /**
     * Where this message goes: a person's DM, the ticket's thread, or the
     * channel itself.
     *
     * Returns null when the row has been settled or put back for later, in which
     * case the caller must stop.
     */
    private function resolveChannel(TicketDiscordMessage $record, DiscordService $discord): ?string
    {
        if ($record->isDirectMessage()) {
            $discordUserId = User::whereKey($record->user_id)->value('discord_user_id');

            if (blank($discordUserId)) {
                // Not a failure. Nobody is obliged to hand over a Discord id, and
                // the assignment itself worked; this records why the DM did not.
                $this->finish($record, TicketDiscordMessage::STATUS_SKIPPED, 'المستخدم مالوش Discord ID');

                return null;
            }

            $dm = $discord->openDm((string) $discordUserId);

            if (! $dm->ok) {
                $this->handleFailure($record, $dm);

                return null;
            }

            return $dm->messageId;
        }

        $root = TicketDiscordMessage::where('ticket_id', $record->ticket_id)->root()->first();

        if ($record->type === TicketDiscordMessage::TYPE_CREATED_GENERAL) {
            return $discord->ticketsChannelId();
        }

        if ($root === null) {
            // Only reachable if the announcement was deleted or never made. The
            // update has nothing to attach to and no channel of its own.
            $this->finish($record, TicketDiscordMessage::STATUS_SKIPPED, 'التذكرة ملهاش رسالة أصلية');

            return null;
        }

        if ($root->status === TicketDiscordMessage::STATUS_PENDING || $root->status === TicketDiscordMessage::STATUS_PROCESSING) {
            // The announcement is still on its way. Wait for it rather than
            // posting this update loose in the channel.
            $record->forceFill([
                'status' => TicketDiscordMessage::STATUS_PENDING,
                'claimed_at' => null,
            ])->saveQuietly();

            $this->release(5);

            return null;
        }

        if ($root->status !== TicketDiscordMessage::STATUS_SENT) {
            // The announcement failed, or we never established whether it
            // arrived. Either way the ticket has no anchor: there is no thread,
            // and dropping this into the main channel would strand an orphan
            // "الحالة اتغيّرت" among unrelated tickets. Recorded with the reason,
            // which discord:check surfaces alongside the root's own row.
            $this->finish(
                $record,
                TicketDiscordMessage::STATUS_SKIPPED,
                "الرسالة الأصلية للتذكرة حالتها «{$root->status}» — مفيش ثريد نعلّق عليه"
            );

            return null;
        }

        return config('discord.use_threads') && filled($root->thread_id)
            ? $root->thread_id
            : $discord->ticketsChannelId();
    }

    /**
     * Looks for a message we may already have sent.
     *
     * Returns true when the row has been settled and the caller must not send.
     */
    private function recover(TicketDiscordMessage $record, DiscordService $discord, string $channelId): bool
    {
        $existing = $discord->findByNonce($channelId, $record->nonceValue());

        if ($existing !== null) {
            $record->forceFill([
                'message_id' => $existing,
                'nonce' => $record->nonceValue(),
                'status' => TicketDiscordMessage::STATUS_SENT,
                'sent_at' => now(),
                'error' => 'اتأكدنا إنها اتبعتت قبل كده (nonce)',
            ])->saveQuietly();

            Log::info('discord message recovered by nonce', [
                'record_id' => $record->id,
                'ticket_id' => $record->ticket_id,
                'user_id' => $record->user_id,
                'type' => $record->type,
                'message_id' => $existing,
            ]);

            return true;
        }

        $maxAge = (int) config('discord.recovery_max_age', 3600);

        if ($record->created_at !== null && $record->created_at->lt(now()->subSeconds($maxAge))) {
            // Too old for the nonce window to protect a resend, and too old for
            // the scan to have covered it. Sending again could duplicate; we
            // decline to guess.
            $this->finish(
                $record,
                TicketDiscordMessage::STATUS_UNVERIFIED,
                'مش متأكدين إذا كانت وصلت ولا لأ — مبعتناهاش تاني'
            );

            // Everything an administrator needs to settle it by hand: which
            // ticket, which ledger row, which notification, who it was for, and
            // where to look.
            Log::warning('discord message left unverified — delivery unknown, deliberately NOT resent', [
                'record_id' => $record->id,
                'ticket_id' => $record->ticket_id,
                'user_id' => $record->user_id,
                'type' => $record->type,
                'channel_id' => $record->channel_id,
                'nonce' => $record->nonceValue(),
                'age_seconds' => $record->created_at?->diffInSeconds(now()),
            ]);

            return true;
        }

        return false;
    }

    /**
     * The DM this one answers.
     *
     * Discord has no threads in a DM, so "this is not yours any more" points
     * back at the message that said it was. fail_if_not_exists keeps a deleted
     * original from turning the whole send into a 400 — it just arrives on its
     * own instead.
     *
     * @return array<string, mixed>|null
     */
    private function replyReference(TicketDiscordMessage $record): ?array
    {
        if ($record->type !== TicketDiscordMessage::TYPE_UNASSIGNED) {
            return null;
        }

        $original = TicketDiscordMessage::where('ticket_id', $record->ticket_id)
            ->where('user_id', $record->user_id)
            ->where('type', TicketDiscordMessage::TYPE_ASSIGNED)
            ->where('status', TicketDiscordMessage::STATUS_SENT)
            ->whereNotNull('message_id')
            ->latest('id')
            ->first();

        if ($original === null || $original->channel_id !== $record->channel_id) {
            return null;
        }

        return [
            'message_id' => $original->message_id,
            'channel_id' => $original->channel_id,
            'fail_if_not_exists' => false,
        ];
    }

    /**
     * Gives the ticket its own thread, so every later update collects under the
     * announcement instead of scattering down the channel.
     */
    private function openThread(
        TicketDiscordMessage $record,
        DiscordService $discord,
        DiscordNotificationService $notifier,
        string $channelId,
    ): void {
        if ($record->type !== TicketDiscordMessage::TYPE_CREATED_GENERAL
            || ! config('discord.use_threads')
            || filled($record->thread_id)) {
            return;
        }

        $threadId = $discord->startThread($channelId, (string) $record->message_id, $notifier->threadName($record));

        if ($threadId === null) {
            // Not fatal. Updates fall back to plain channel messages, which is
            // exactly what use_threads=false does on purpose.
            Log::warning('discord thread creation failed', ['record_id' => $record->id]);

            return;
        }

        $record->forceFill(['thread_id' => $threadId])->saveQuietly();
    }

    private function handleFailure(TicketDiscordMessage $record, DiscordResult $result): void
    {
        if (! $result->retryable) {
            $this->finish(
                $record,
                TicketDiscordMessage::STATUS_FAILED,
                trim(($result->discordCode ? "[{$result->discordCode}] " : '') . $result->error)
            );

            Log::warning('discord message rejected', [
                'record_id' => $record->id,
                'ticket_id' => $record->ticket_id,
                'user_id' => $record->user_id,
                'type' => $record->type,
                'http_status' => $result->httpStatus,
                'discord_code' => $result->discordCode,
                'error' => $result->error,
            ]);

            return;
        }

        // Back to pending, or the claim window would lock the row out of its own
        // retry for reclaim_after seconds.
        $record->forceFill([
            'status' => TicketDiscordMessage::STATUS_PENDING,
            'claimed_at' => null,
            'error' => mb_substr((string) $result->error, 0, 500),
        ])->saveQuietly();

        Log::info('discord message deferred', [
            'record_id' => $record->id,
            'http_status' => $result->httpStatus,
            'retry_after' => $result->retryAfter,
        ]);

        $this->release($result->retryAfter ?? 10);
    }

    private function finish(TicketDiscordMessage $record, string $status, ?string $error = null): void
    {
        $record->forceFill([
            'status' => $status,
            'error' => $error === null ? null : mb_substr($error, 0, 500),
        ])->saveQuietly();
    }
}
