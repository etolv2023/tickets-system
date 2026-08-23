<?php

namespace App\Services;

use App\Jobs\SendDiscordMessage;
use App\Models\Ticket;
use App\Models\TicketDiscordMessage;
use App\Models\TicketSubtask;
use App\Casts\TicketStatusValue;
use App\Models\Role;
use App\Models\User;
use App\Support\DiscordPresenter as P;
use Illuminate\Support\Facades\Log;

/**
 * What Discord is told about a ticket, and — more importantly — what it is not.
 *
 * The counterpart of NotificationService: callers say what happened, this
 * decides whether it leaves the building. Nothing here speaks HTTP; that is
 * DiscordService.
 *
 * ★ Three gates, kept separate on purpose.
 *
 *   blocked()    Nothing at all, not even a ledger row. A ticket awaiting
 *                approval does not exist as far as Discord is concerned: its
 *                planned distribution can be typed, retyped and handed between
 *                three people, and none of it is real work yet (F15).
 *
 *   activated()  The ticket has an announcement in the channel. Only lifecycle
 *                posts — threads, status, reassignment — wait for this.
 *
 *   ...and DIRECT MESSAGES check neither of the two beyond blocked(). This is
 *   the subtle one. approve() applies the final assignments BEFORE the
 *   announcement is created, so a blanket "return unless activated" at the top
 *   of assignment handling would silently swallow every initial DM at exactly
 *   the moment the ticket comes alive. The DM does not need the announcement;
 *   the thread post does.
 *
 * Every method is wrapped so a fault here can never reach the assignment that
 * called it. A ticket must be assignable with Discord broken (CLAUDE.md § 4.9,
 * same rule WebPushService follows).
 */
class DiscordNotificationService
{
    /**
     * Badge hues → embed colours. The palette is Label::COLORS plus the blue the
     * statuses use, so a custom status an admin invents still gets its own
     * colour rather than falling to grey.
     */
    private const PALETTE = [
        'red' => 'b4433a', 'rose' => 'b04a63', 'orange' => 'a95f2a', 'amber' => 'a9720f',
        'yellow' => '967c12', 'lime' => '5f7d2a', 'green' => '3d7a52', 'teal' => '2f7468',
        'cyan' => '2d6f7d', 'blue' => '3a6ea5', 'indigo' => '5257a8', 'violet' => '7a51a5',
        'plum' => '90467f', 'brown' => '7d5a3c', 'slate' => '5b6570',
    ];

    /**
     * A distribution change that a subtask assignment caused. The subtask layer
     * owns the messaging for it; see assignmentsChanged().
     */
    public const SOURCE_SUBTASK = 'subtask';

    public function __construct(private readonly DiscordService $discord)
    {
    }

    /**
     * The ticket's one announcement in the general channel.
     *
     * Called at creation for an ordinary ticket, and at the END of approve() for
     * one that needed approving — never both, and never twice, because the
     * dedupe key is unique and the insert ignores a collision.
     */
    public function announceCreated(Ticket $ticket): void
    {
        $this->guard($ticket, function () use ($ticket) {
            // The day the ticket became real, in the business timezone — not the
            // worker's. A ticket approved at 1am Cairo belongs to that Cairo
            // date, whatever UTC thinks, and it belongs to it permanently: this
            // is stamped once and every later event reads the ids off this row
            // rather than asking what today is.
            $businessDate = $this->forumMode()
                ? now()->timezone(config('app.display_timezone'))->toDateString()
                : null;

            if ($businessDate !== null) {
                $this->ensureDailyPost($businessDate);
            }

            $this->record([
                'ticket_id' => $ticket->id,
                'type' => TicketDiscordMessage::TYPE_CREATED_GENERAL,
                'dedupe_key' => 'created:' . $ticket->id,
                'payload' => [
                    'ticket' => $this->snapshot($ticket),
                    'business_date' => $businessDate,
                ],
            ]);
        });
    }

    /** Forum mode groups tickets into one post per day; off, it is one thread per ticket. */
    public function forumMode(): bool
    {
        return (bool) config('discord.forum_mode');
    }

    /**
     * Makes sure the day's forum post exists, exactly once.
     *
     * The unique dedupe key does the real work: three tickets activating in the
     * same second all try to insert `daily-post:2026-08-23` and only one row
     * survives, so only one job runs and only one post is opened. No lock, no
     * second table — the constraint that already stops duplicate announcements
     * stops duplicate days too.
     */
    public function ensureDailyPost(string $businessDate): void
    {
        $this->record([
            'ticket_id' => null,
            'type' => TicketDiscordMessage::TYPE_DAILY_FORUM_POST,
            'dedupe_key' => TicketDiscordMessage::dailyPostKey($businessDate),
            'payload' => ['business_date' => $businessDate],
        ]);
    }

    /**
     * Queues a rewrite of the ticket's card so it shows where the ticket
     * actually stands.
     *
     * The card is the ticket's current state; the messages under it are its
     * history. So a status move or a change of hands edits the card AND leaves a
     * line beneath it — the two say different things on purpose.
     *
     * Coalesced rather than appended: if an edit is already waiting, its payload
     * is replaced with the newer snapshot. Five changes in a minute are one
     * PATCH of the final state, not five PATCHes racing to be last.
     */
    /**
     * @param  array<string, mixed>|null  $snapshot  a snapshot the caller has
     *         already taken. Building one walks the ticket's company, creator and
     *         whole distribution, so the status and assignment paths — which have
     *         just built one for their own message — hand it over rather than
     *         paying for it twice on the same request.
     */
    public function syncRoot(Ticket $ticket, ?array $snapshot = null): void
    {
        $this->guard($ticket, function () use ($ticket, $snapshot) {
            $root = $this->rootRecord($ticket);

            if ($root === null) {
                return;
            }

            $payload = ['ticket' => $snapshot ?? $this->snapshot($ticket)];

            // Nothing on the card moved, so there is nothing to rewrite. This is
            // what keeps a repeated approval — which rebuilds the same
            // assignments from scratch — from queueing an edit that would set
            // the card to exactly what it already says.
            if ($this->cardMatches($ticket, $payload)) {
                return;
            }

            $pending = TicketDiscordMessage::where('ticket_id', $ticket->id)
                ->where('type', TicketDiscordMessage::TYPE_ROOT_SYNC)
                ->whereIn('status', [TicketDiscordMessage::STATUS_PENDING])
                ->latest('id')
                ->first();

            if ($pending !== null) {
                $pending->forceFill(['payload' => $payload])->saveQuietly();

                return;
            }

            $this->record([
                'ticket_id' => $ticket->id,
                'type' => TicketDiscordMessage::TYPE_ROOT_SYNC,
                'payload' => $payload,
            ]);

            // The day's header counts open versus done, so it goes stale for the
            // same reasons the card does.
            $this->refreshDailySummary($root->payload['business_date'] ?? null);
        });
    }

    /**
     * Queues a rewrite of a day's header.
     *
     * Coalesced per date: twenty tickets moving in a busy hour are one edit of
     * the final counts, not twenty racing PATCHes of the same message.
     */
    public function refreshDailySummary(?string $businessDate): void
    {
        if ($businessDate === null || ! $this->forumMode()) {
            return;
        }

        $exists = TicketDiscordMessage::where('type', TicketDiscordMessage::TYPE_DAILY_SUMMARY)
            ->where('status', TicketDiscordMessage::STATUS_PENDING)
            ->whereJsonContains('payload->business_date', $businessDate)
            ->exists();

        if ($exists) {
            return;
        }

        $this->record([
            'ticket_id' => null,
            'type' => TicketDiscordMessage::TYPE_DAILY_SUMMARY,
            'payload' => ['business_date' => $businessDate],
        ]);
    }

    /**
     * Whether the card already shows this exact state.
     *
     * Compares against the newest rewrite if one is queued or done, else the
     * announcement itself — whichever last described the card.
     *
     * @param  array<string, mixed>  $payload
     */
    private function cardMatches(Ticket $ticket, array $payload): bool
    {
        $latest = TicketDiscordMessage::where('ticket_id', $ticket->id)
            ->whereIn('type', [TicketDiscordMessage::TYPE_ROOT_SYNC, TicketDiscordMessage::TYPE_CREATED_GENERAL])
            ->whereIn('status', [
                TicketDiscordMessage::STATUS_PENDING,
                TicketDiscordMessage::STATUS_PROCESSING,
                TicketDiscordMessage::STATUS_SENT,
            ])
            ->latest('id')
            ->first();

        if ($latest === null) {
            return false;
        }

        // business_date rides along on the announcement and is not part of what
        // the card displays, so it is left out of the comparison.
        return ($latest->payload['ticket'] ?? null) == ($payload['ticket'] ?? null);
    }

    /** The ticket's card row, whatever state it is in. */
    public function rootRecord(Ticket $ticket): ?TicketDiscordMessage
    {
        return TicketDiscordMessage::where('ticket_id', $ticket->id)->root()->first();
    }

    /**
     * One or more roles changed hands.
     *
     * @param  array<int, array{role_id: int, role_name: string, from: int|null, to: int|null}>  $diffs
     *         Only real changes — assignRoles() has already dropped the no-ops,
     *         which is why re-saving the same person sends nothing.
     * @param  bool  $activation  true when these are a ticket's first assignments
     *         (approval, creation, exception intake). Passed explicitly rather
     *         than guessed from "no announcement yet": a ticket that predates
     *         this integration also has no announcement, and inferring would
     *         wrongly de-duplicate a genuine re-assignment on it.
     * @param  string|null  $source  where the change came from. 'subtask' means a
     *         subtask hand-off dragged the distribution along with it, and the
     *         subtask layer is already telling both people about it — so this
     *         layer stays quiet rather than sending everybody a second, vaguer
     *         DM about the same click. Only Discord is suppressed: the bell, the
     *         work log and the activity log all still happen, because those are
     *         records rather than interruptions.
     */
    public function assignmentsChanged(Ticket $ticket, array $diffs, ?int $actorId, bool $activation = false, ?string $source = null): void
    {
        if ($diffs === [] || $source === self::SOURCE_SUBTASK) {
            return;
        }

        $this->guard($ticket, function () use ($ticket, $diffs, $actorId, $activation) {
            $snapshot = $this->snapshot($ticket);
            $names = $this->names($diffs, $actorId);
            $activated = $this->activated($ticket);

            foreach ($diffs as $diff) {
                $from = $diff['from'];
                $to = $diff['to'];

                if ($to !== null) {
                    $this->record([
                        'ticket_id' => $ticket->id,
                        'user_id' => $to,
                        'role_id' => $diff['role_id'],
                        'type' => TicketDiscordMessage::TYPE_ASSIGNED,
                        // Only the activation DMs are keyed. An ordinary hand-off
                        // must stay unkeyed: giving a role back to whoever held it
                        // last week genuinely owes them a second message, and a
                        // deterministic key would eat it.
                        'dedupe_key' => $activation
                            ? "activation-assignment:{$ticket->id}:{$diff['role_id']}:{$to}"
                            : null,
                        'payload' => [
                            'ticket' => $snapshot,
                            'role' => $diff['role_name'],
                            'previous' => $from === null ? null : ($names[$from] ?? null),
                            'initial' => $activation,
                        ],
                    ]);
                }

                if ($from !== null && $from !== $to) {
                    $this->record([
                        'ticket_id' => $ticket->id,
                        'user_id' => $from,
                        'role_id' => $diff['role_id'],
                        'type' => TicketDiscordMessage::TYPE_UNASSIGNED,
                        'dedupe_key' => null,
                        'payload' => [
                            'ticket' => $snapshot,
                            'role' => $diff['role_name'],
                            'successor' => $to === null ? null : ($names[$to] ?? null),
                        ],
                    ]);
                }

                // The channel hears about it only once the ticket has an
                // announcement to hang the thread on — and never during
                // activation, because the announcement itself is about to name
                // every one of these people anyway.
                //
                // Both halves of that condition earn their place. On a first
                // approval $activated is false; on a REPEATED one it is true,
                // and without the $activation half the re-run would post a
                // "مفيش ← أحمد" update about a hand-off that never happened —
                // approve() rebuilds the assignments from scratch each time, so
                // every role looks new to the loop above.
                // The card is current state, so it is rewritten whenever the
                // team on it changes — including during activation, where the
                // card is about to be written for the first time anyway and
                // syncRoot simply finds nothing to do.
                $this->syncRoot($ticket, $snapshot);

                if ($activated && ! $activation) {
                    $this->record([
                        'ticket_id' => $ticket->id,
                        'role_id' => $diff['role_id'],
                        'type' => TicketDiscordMessage::TYPE_REASSIGNED_GENERAL,
                        'payload' => [
                            'ticket' => $snapshot,
                            'role' => $diff['role_name'],
                            'role_key' => $diff['role_key'] ?? null,
                            'from' => $from === null ? null : ($names[$from] ?? null),
                            'to' => $to === null ? null : ($names[$to] ?? null),
                            'actor' => $actorId === null ? null : ($names[$actorId] ?? null),
                        ],
                    ]);
                }
            }
        });
    }

    /** The status moved. Thread only — this never earns a post in the channel itself. */
    public function statusChanged(Ticket $ticket, TicketStatusValue $from, TicketStatusValue $to, ?int $actorId, ?string $note = null): void
    {
        if (! config('discord.announce_status')) {
            return;
        }

        $this->guard($ticket, function () use ($ticket, $from, $to, $actorId, $note) {
            if (! $this->activated($ticket)) {
                return;
            }

            $snapshot = $this->snapshot($ticket);

            // Same reason as the assignment path: the card shows where the
            // ticket stands now, the message under it records that it moved.
            $this->syncRoot($ticket, $snapshot);

            $resolved = ! $ticket->status->isOpen();

            $this->record([
                'ticket_id' => $ticket->id,
                'type' => $resolved
                    ? TicketDiscordMessage::TYPE_RESOLVED
                    : TicketDiscordMessage::TYPE_STATUS_CHANGED,
                'payload' => [
                    'ticket' => $snapshot,
                    'from' => $from->label(),
                    'from_key' => $from->value,
                    'from_variant' => $from->variant(),
                    'from_open' => $from->isOpen(),
                    'to' => $to->label(),
                    'to_key' => $to->value,
                    'to_variant' => $to->variant(),
                    'to_open' => $to->isOpen(),
                    'note' => $note,
                    'actor' => $actorId === null ? null : (User::find($actorId)?->name),
                ],
            ]);
        });
    }

    /**
     * A subtask changed hands.
     *
     * Called only for a REAL change — the caller has already compared the two
     * ids as integers and returned early if they match, which is what makes
     * re-saving a form with the same owner produce nothing at all.
     *
     * @param  bool  $created  the subtask is brand new, so the thread post reads
     *         as "a step was added" rather than "a step moved".
     * @param  bool  $synced   the parent ticket's distribution was dragged along
     *         by this change; the thread post says so, which is the only place
     *         that fact is announced now that the ticket layer is suppressed.
     */
    public function subtaskAssignmentChanged(
        Ticket $ticket,
        TicketSubtask $subtask,
        ?int $from,
        ?int $to,
        ?int $actorId,
        bool $created = false,
        bool $synced = false,
    ): void {
        $this->guard($ticket, function () use ($ticket, $subtask, $from, $to, $actorId, $created, $synced) {
            // Explicit: preventLazyLoading is on in development.
            $subtask->loadMissing('role:id,key,name_ar');

            $names = $this->names([['from' => $from, 'to' => $to]], $actorId);

            $context = [
                'ticket' => $this->snapshot($ticket),
                'subtask' => [
                    'id' => $subtask->id,
                    'title' => $subtask->title,
                    'role' => $subtask->role?->name_ar,
                    'role_key' => $subtask->role?->key,
                ],
                'from' => $from === null ? null : ($names[$from] ?? null),
                'to' => $to === null ? null : ($names[$to] ?? null),
                'actor' => $actorId === null ? null : ($names[$actorId] ?? null),
                'created' => $created,
                'synced' => $synced,
            ];

            if ($to !== null) {
                $this->record([
                    'ticket_id' => $ticket->id,
                    'user_id' => $to,
                    'role_id' => $subtask->role_id,
                    'type' => TicketDiscordMessage::TYPE_SUBTASK_ASSIGNED,
                    'payload' => $context,
                ]);
            }

            if ($from !== null && $from !== $to) {
                $this->record([
                    'ticket_id' => $ticket->id,
                    'user_id' => $from,
                    'role_id' => $subtask->role_id,
                    'type' => TicketDiscordMessage::TYPE_SUBTASK_UNASSIGNED,
                    'payload' => $context,
                ]);
            }

            // Same rule as every other lifecycle post: the thread only exists
            // once the ticket has been announced. A subtask must never fall back
            // to the main channel — that is a feed of tickets, not of steps.
            if ($this->activated($ticket)) {
                $this->record([
                    'ticket_id' => $ticket->id,
                    'role_id' => $subtask->role_id,
                    'type' => $created
                        ? TicketDiscordMessage::TYPE_SUBTASK_CREATED
                        : TicketDiscordMessage::TYPE_SUBTASK_REASSIGNED_GENERAL,
                    'payload' => $context,
                ]);
            }
        });
    }

    /**
     * A step moved along.
     *
     * Timeline only, and deliberately so: a status change moves work, not
     * ownership, and the people who need to know are the ones reading the
     * ticket. DMing an assignee because their own subtask went to «جاري العمل»
     * — usually because they clicked it — is the definition of noise.
     */
    public function subtaskStatusChanged(Ticket $ticket, TicketSubtask $subtask, string $fromKey, string $fromLabel, ?int $actorId): void
    {
        if (! config('discord.announce_status')) {
            return;
        }

        $this->guard($ticket, function () use ($ticket, $subtask, $fromKey, $fromLabel, $actorId) {
            if (! $this->activated($ticket)) {
                return;
            }

            $subtask->loadMissing('role:id,key,name_ar');

            $this->record([
                'ticket_id' => $ticket->id,
                'role_id' => $subtask->role_id,
                'type' => TicketDiscordMessage::TYPE_SUBTASK_STATUS,
                'payload' => [
                    'ticket' => $this->snapshot($ticket),
                    'subtask' => [
                        'id' => $subtask->id,
                        'title' => $subtask->title,
                        'role' => $subtask->role?->name_ar,
                        'role_key' => $subtask->role?->key,
                    ],
                    'from' => $fromLabel,
                    'from_key' => $fromKey,
                    'to' => $subtask->status->label(),
                    'to_key' => $subtask->status->value,
                    'to_variant' => $subtask->status->variant(),
                    'actor' => $actorId === null ? null : (User::whereKey($actorId)->value('name')),
                ],
            ]);
        });
    }

    /**
     * Turns a stored record into the body Discord is posted.
     *
     * Lives here rather than in the job because everything about how a message
     * reads belongs in one file; the job only decides whether and where to send.
     *
     * @return array<string, mixed>
     */
    public function renderBody(TicketDiscordMessage $record): array
    {
        $payload = $record->payload ?? [];
        $ticket = $payload['ticket'] ?? [];

        return match ($record->type) {
            TicketDiscordMessage::TYPE_DAILY_FORUM_POST,
            TicketDiscordMessage::TYPE_DAILY_SUMMARY => [
                'embeds' => [$this->dailyHeader($payload['business_date'] ?? '')],
            ],
            // A card rewrite renders exactly like the card, because that is what
            // it is replacing.
            TicketDiscordMessage::TYPE_ROOT_SYNC,
            TicketDiscordMessage::TYPE_CREATED_GENERAL => $this->announcement($ticket),
            TicketDiscordMessage::TYPE_ASSIGNED => [
                'embeds' => [$this->ticketEmbed(
                    $ticket,
                    ($payload['initial'] ?? false) ? '🎯 التذكرة دي بقت معاك' : '🎯 اتعملك أساين على التذكرة دي',
                    $this->assignedNote($payload),
                )],
            ],
            TicketDiscordMessage::TYPE_UNASSIGNED => [
                'embeds' => [$this->plainEmbed(
                    $ticket,
                    '👤 التذكرة دي مبقتش معاك',
                    $this->unassignedNote($payload),
                    'slate',
                )],
            ],
            TicketDiscordMessage::TYPE_REASSIGNED_GENERAL => [
                'embeds' => [$this->plainEmbed(
                    $ticket,
                    '🔄 تغيير توزيع',
                    $this->reassignedNote($payload),
                    'blue',
                )],
            ],
            TicketDiscordMessage::TYPE_STATUS_CHANGED => [
                'embeds' => [$this->plainEmbed(
                    $ticket, '🔵 تغيرت الحالة', $this->statusNote($payload), $ticket['variant'] ?? 'slate',
                )],
            ],
            TicketDiscordMessage::TYPE_RESOLVED => [
                'embeds' => [$this->plainEmbed(
                    $ticket, '✅ تم إنهاء التذكرة', $this->statusNote($payload), $ticket['variant'] ?? 'green',
                )],
            ],
            TicketDiscordMessage::TYPE_SUBTASK_ASSIGNED => [
                'embeds' => [$this->plainEmbed(
                    $ticket,
                    ($payload['created'] ?? false) ? '🧩 صب تاسك جديدة معاك' : '🧩 صب تاسك بقت معاك',
                    $this->subtaskNote($payload, forOwner: true),
                    $ticket['variant'] ?? 'slate',
                )],
            ],
            TicketDiscordMessage::TYPE_SUBTASK_UNASSIGNED => [
                'embeds' => [$this->plainEmbed(
                    $ticket,
                    '👤 الصب تاسك دي مبقتش معاك',
                    $this->subtaskNote($payload, forOwner: false),
                    'slate',
                )],
            ],
            TicketDiscordMessage::TYPE_SUBTASK_STATUS => [
                'embeds' => [$this->plainEmbed(
                    $ticket, '🧩 تحديث صب تاسك', $this->subtaskStatusNote($payload),
                    $payload['to_variant'] ?? 'slate',
                )],
            ],
            TicketDiscordMessage::TYPE_SUBTASK_CREATED => [
                'embeds' => [$this->plainEmbed(
                    $ticket, '🧩 صب تاسك جديدة', $this->subtaskThreadNote($payload), 'indigo',
                )],
            ],
            TicketDiscordMessage::TYPE_SUBTASK_REASSIGNED_GENERAL => [
                'embeds' => [$this->plainEmbed(
                    $ticket,
                    ($payload['to'] ?? null) === null ? '👤 إلغاء توزيع صب تاسك' : '🔄 نقل صب تاسك',
                    $this->subtaskThreadNote($payload),
                    'slate',
                )],
            ],
            default => ['content' => $ticket['url'] ?? ''],
        };
    }

    /**
     * The announcement, plus an optional mention line.
     *
     * Mentions are off by default. The assignee already gets a DM, so switching
     * this on pings the same person twice for the same event — worth it only for
     * a team that lives in the channel and wants the ping there instead.
     *
     * allowed_mentions is pinned to exactly those ids, so nothing else in the
     * message can ping anybody: a ticket titled "@everyone broken" stays text.
     *
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    private function announcement(array $ticket): array
    {
        $body = ['embeds' => [$this->ticketEmbed($ticket, 'تذكرة جديدة')]];

        if (! config('discord.mention_assignees')) {
            return $body;
        }

        $ids = array_values(array_filter(array_map(
            fn ($row) => $row['discord_id'] ?? null,
            $ticket['assignees'] ?? []
        )));

        if ($ids === []) {
            return $body;
        }

        $body['content'] = implode(' ', array_map(fn ($id) => "<@{$id}>", $ids));
        $body['allowed_mentions'] = ['parse' => [], 'users' => $ids];

        return $body;
    }

    /**
     * The first message of a day's post — the day itself, and how it went.
     *
     * The counts are read when the message is rendered, which happens on the
     * worker, so a busy day costs the person clicking «غيّر الحالة» nothing.
     * Open versus done comes from ticket_statuses.is_open, the same flag the
     * rest of the system uses, rather than a list of status names that would go
     * stale the moment an admin adds one.
     *
     * @return array<string, mixed>
     */
    private function dailyHeader(string $businessDate): array
    {
        $counts = ['total' => 0, 'open' => 0, 'done' => 0];

        if ($businessDate !== '') {
            $ids = TicketDiscordMessage::query()
                ->root()
                ->whereNotNull('ticket_id')
                ->get(['ticket_id', 'payload'])
                ->filter(fn ($r) => ($r->payload['business_date'] ?? null) === $businessDate)
                ->pluck('ticket_id');

            if ($ids->isNotEmpty()) {
                $open = Ticket::query()
                    ->whereIn('id', $ids)
                    ->whereIn('status', fn ($q) => $q->select('key')->from('ticket_statuses')->where('is_open', true))
                    ->count();

                $counts = ['total' => $ids->count(), 'open' => $open, 'done' => $ids->count() - $open];
            }
        }

        $day = $businessDate === '' ? '' : $this->arabicDate($businessDate);

        return array_filter([
            'title' => $this->titleFor($businessDate),
            'description' => "📅 **{$day}**",
            'color' => $this->color('indigo'),
            'fields' => [
                ['name' => '🎫 التذاكر', 'value' => (string) $counts['total'], 'inline' => true],
                ['name' => '🔵 شغّالة', 'value' => (string) $counts['open'], 'inline' => true],
                ['name' => '✅ خلصت', 'value' => (string) $counts['done'], 'inline' => true],
            ],
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** The name a day's forum post carries — deterministic, so it can be found again. */
    public function dailyPostName(string $businessDate): string
    {
        return $businessDate . ' · نظام التذاكر';
    }

    private function titleFor(string $businessDate): string
    {
        return $businessDate === '' ? 'نظام التذاكر' : $this->dailyPostName($businessDate);
    }

    /** «الأحد، 23 أغسطس 2026» — read on the worker, never in a web request. */
    private function arabicDate(string $businessDate): string
    {
        $months = [1=>'يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
        $days = ['Sunday'=>'الأحد','Monday'=>'الإتنين','Tuesday'=>'التلات','Wednesday'=>'الأربع','Thursday'=>'الخميس','Friday'=>'الجمعة','Saturday'=>'السبت'];

        try {
            $d = \Carbon\CarbonImmutable::parse($businessDate);
        } catch (\Throwable) {
            return $businessDate;
        }

        return ($days[$d->format('l')] ?? '') . '، ' . $d->day . ' ' . ($months[(int) $d->month] ?? '') . ' ' . $d->year;
    }

    /** The thread name a ticket's updates collect under. */
    public function threadName(TicketDiscordMessage $root): string
    {
        $ticket = $root->payload['ticket'] ?? [];

        return trim(($ticket['number'] ?? '') . ' — ' . ($ticket['title'] ?? ''));
    }

    // ---------------------------------------------------------------- gates

    /**
     * (A) Nothing Discord-related may happen for this ticket.
     *
     * The condition is the workflow's own — the same expression assign() and
     * transition() already refuse on — so there is exactly one definition of
     * "approved" in the system and Discord is not allowed a second opinion.
     */
    private function blocked(Ticket $ticket): bool
    {
        return ! $this->discord->configured()
            || ($ticket->type->needsApproval() && $ticket->approval_status !== 'approved');
    }

    /** (C) The ticket has an announcement, so a thread can hang off it. */
    private function activated(Ticket $ticket): bool
    {
        return TicketDiscordMessage::query()
            ->where('ticket_id', $ticket->id)
            ->root()
            ->exists();
    }

    /**
     * Runs the body unless the ticket is gated, and never lets it escape.
     *
     * Discord being down, misconfigured or slow is not the assigning user's
     * problem and must not roll back their assignment.
     */
    private function guard(Ticket $ticket, callable $body): void
    {
        if ($this->blocked($ticket)) {
            return;
        }

        try {
            $body();
        } catch (\Throwable $e) {
            Log::warning('discord announcement failed to queue', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ---------------------------------------------------------------- ledger

    /**
     * Writes one ledger row and queues it.
     *
     * A keyed row that already exists is not an error and not a second message —
     * it is a repeated approval, and the whole point of the key is that it does
     * nothing the second time.
     *
     * No nonce is written here. It is a pure function of the row's id
     * (TicketDiscordMessage::nonceValue), so every attempt derives the same
     * value without this path paying for a second write per message — which on a
     * three-role hand-off is three UPDATEs saved on the request that assigns.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): void
    {
        $key = $attributes['dedupe_key'] ?? null;

        if ($key !== null && TicketDiscordMessage::where('dedupe_key', $key)->exists()) {
            return;
        }

        try {
            $record = TicketDiscordMessage::create($attributes + [
                'status' => TicketDiscordMessage::STATUS_PENDING,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Another request won the race on the same dedupe key. Correct
            // outcome: exactly one message, and it is not ours.
            return;
        }

        // onQueue: Discord waits on somebody else's server — rate limits,
        // back-offs, timeouts, recovery scans. None of that may ever hold up the
        // default queue, so these jobs live on their own and are drained by
        // their own worker.
        //
        // afterCommit: the row and the job both belong to the assignment's
        // transaction. A rolled-back assignment must not leave a worker holding
        // a message about something that never happened.
        SendDiscordMessage::dispatch($record->id)
            ->onQueue(config('discord.queue', 'discord'))
            ->afterCommit();
    }

    // ------------------------------------------------------------- snapshots

    /**
     * The ticket as it is right now, frozen.
     *
     * Only the fields agreed for external delivery. No description, no comments,
     * no attachments, no SLA — those stay behind the login.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Ticket $ticket): array
    {
        // Explicit, because preventLazyLoading is on in development and
        // originLabel() reaches for a relation. Only one of the two, though:
        // a ticket is either a customer's or a colleague's, never both, so
        // loading the other is a query bought for nothing.
        $ticket->loadMissing(array_values(array_filter([
            $ticket->isInternal() ? 'requester:id,name' : 'company:id,name',
            'creator:id,name',
        ])));

        // ★ load(), not loadMissing(), for the distribution.
        //
        // The team is the one part of the card that changes under us. approve()
        // deletes the role assignments and rebuilds them, and assignRoles writes
        // its rows through the model rather than the relation — so a copy loaded
        // earlier in the same request is stale by the time the card is rendered,
        // and loadMissing would keep it. That is not a slightly-out-of-date
        // label: the ticket had just had its rows deleted, so the cached
        // collection was EMPTY and the card would have announced a ticket with
        // nobody on it.
        $ticket->load([
            'roleAssignments:id,ticket_id,role_id,user_id',
            'roleAssignments.user:id,name,discord_user_id',
            'roleAssignments.role:id,name_ar',
        ]);

        $held = $ticket->roleAssignments->keyBy('role_id');

        // Every role the admin opted into distribution, in their own order —
        // held ones named, the rest shown as open. "Who is on this?" and "what
        // is nobody on?" are the same question on a card that claims to be
        // current state.
        $team = Role::assignableList()
            // A role appears when somebody holds it, or when it is one of the
            // roles that actually deliver a ticket — the ones that log work or
            // do the testing. Without that second half the card could not say
            // «مبرمج باك → غير موزع», which is the useful half of a distribution
            // panel; with every assignable role it would also announce that
            // «مدير النظام» is unassigned on every ticket forever.
            ->filter(fn (Role $role) => $held->has($role->id) || $role->logsWork() || $role->isTester())
            ->map(function (Role $role) use ($held) {
                $assignment = $held->get($role->id);

                return [
                    'role_key' => $role->key,
                    'role' => $role->name_ar,
                    'user' => $assignment?->user?->name,
                    'discord_id' => $assignment?->user?->discord_user_id ?: null,
                ];
            })
            // Held first, so the eye lands on who IS on the ticket.
            ->sortBy(fn ($row) => ($row['user'] === null ? '1' : '0') . $row['role'])
            ->values()
            ->all();

        return [
            'id' => $ticket->id,
            'number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'origin' => $ticket->originLabel(),
            'type' => $ticket->type->label(),
            'priority' => $ticket->priority->label(),
            'priority_key' => (string) $ticket->priority,
            'priority_variant' => $ticket->priority->variant(),
            'status' => $ticket->status->label(),
            'status_key' => (string) $ticket->status,
            'status_open' => $ticket->status->isOpen(),
            'variant' => $ticket->status->variant(),
            'creator' => $ticket->creator?->name,
            'team' => $team,
            // Kept for the mention option, which only cares about who is on it.
            'assignees' => collect($team)->filter(fn ($r) => $r['user'] !== null)->values()->all(),
            'reported_at' => $ticket->reported_at?->toIso8601String(),
            'url' => route('tickets.show', $ticket),
        ];
    }

    /**
     * Names for every person mentioned by a batch of diffs, in one query.
     *
     * @param  array<int, array{from: int|null, to: int|null}>  $diffs
     * @return array<int, string>
     */
    private function names(array $diffs, ?int $actorId): array
    {
        $ids = [];

        foreach ($diffs as $diff) {
            $ids[] = $diff['from'];
            $ids[] = $diff['to'];
        }

        $ids[] = $actorId;
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? [] : User::whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    // ---------------------------------------------------------------- embeds

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    private function ticketEmbed(array $ticket, string $heading, ?string $note = null): array
    {
        // Three inline fields per row on Discord, so these land as two tidy rows
        // rather than a ragged column.
        $fields = [
            ['name' => '🏢 العميل', 'value' => P::isolate($ticket['origin'] ?? null), 'inline' => true],
            ['name' => '🏷️ النوع', 'value' => P::isolate($ticket['type'] ?? null), 'inline' => true],
            ['name' => '🚦 الأولوية', 'value' => P::priority(
                $ticket['priority_key'] ?? null, $ticket['priority'] ?? null, $ticket['priority_variant'] ?? null
            ), 'inline' => true],
            ['name' => '📌 الحالة', 'value' => P::status(
                $ticket['status_key'] ?? null, $ticket['status'] ?? null,
                $ticket['variant'] ?? null, $ticket['status_open'] ?? null
            ), 'inline' => true],
            ['name' => '👤 فتحها', 'value' => P::isolate($ticket['creator'] ?? null), 'inline' => true],
            ['name' => '🕒 اتفتحت', 'value' => P::timestamp($ticket['reported_at'] ?? null, 'R'), 'inline' => true],
        ];

        if ($team = $this->teamLines($ticket)) {
            $fields[] = ['name' => '👥 فريق العمل', 'value' => $team, 'inline' => false];
        }

        $description = trim(($note ? $note . "\n\n" : '')
            . '🔗 [فتح التذكرة](' . ($ticket['url'] ?? '') . ')');

        return array_filter([
            // The number is forced left-to-right so it never comes apart beside
            // an Arabic title; the title lays itself out by its own script.
            'title' => P::truncate(
                '🎫 ' . P::ltr($ticket['number'] ?? '') . ' — ' . P::isolate($ticket['title'] ?? ''),
                256
            ),
            'url' => $ticket['url'] ?? null,
            'description' => $description === '' ? null : $description,
            'color' => P::color($ticket['variant'] ?? 'slate'),
            'fields' => $fields,
            'timestamp' => $ticket['reported_at'] ?? null,
            'footer' => ['text' => $heading],
        ], fn ($v) => $v !== null);
    }

    /**
     * The current team, one line per assignable role.
     *
     * Unheld roles are listed too — a card that only names the people on a
     * ticket cannot answer "who is missing", which is half of what a
     * distribution panel is for.
     *
     * @param  array<string, mixed>  $ticket
     */
    private function teamLines(array $ticket): ?string
    {
        $rows = $ticket['team'] ?? $ticket['assignees'] ?? [];

        if ($rows === []) {
            return null;
        }

        $lines = array_map(
            fn ($row) => P::role($row['role_key'] ?? null, $row['role'] ?? null)
                . ' → ' . P::isolate($row['user'] ?? null ?: 'غير موزع'),
            $rows
        );

        return P::truncate(implode("\n", $lines), 1024);
    }

    /**
     * The shorter form used for updates — the full field grid belongs to the
     * announcement, and repeating it under every status change would bury the
     * one line that actually changed.
     *
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    private function plainEmbed(array $ticket, string $heading, string $note, string $variant): array
    {
        // Many tickets share one day's post, so the number leads every activity
        // line — that is what tells a reader which ticket moved.
        return array_filter([
            'title' => P::truncate(
                $heading . ' · ' . P::ltr($ticket['number'] ?? ''),
                256
            ),
            'url' => $ticket['url'] ?? null,
            'description' => P::truncate(
                P::isolate($ticket['title'] ?? '') . "\n" . $note,
                4000
            ),
            'color' => P::color($variant),
        ], fn ($v) => $v !== null);
    }

    /** @param array<string, mixed> $ticket */
    private function assigneeLines(array $ticket): ?string
    {
        $rows = $ticket['assignees'] ?? [];

        if ($rows === []) {
            return null;
        }

        $lines = array_map(
            fn ($row) => '• ' . ($row['role'] ?? '—') . ': ' . ($row['user'] ?? '—'),
            $rows
        );

        return $this->truncate(implode("\n", $lines), 1024);
    }

    /** @param array<string, mixed> $payload */
    private function assignedNote(array $payload): string
    {
        $note = 'دورك على التذكرة دي: **' . ($payload['role'] ?? '—') . '**';

        if (filled($payload['previous'] ?? null)) {
            $note .= "\nكانت مع: " . $payload['previous'];
        }

        return $note;
    }

    /** @param array<string, mixed> $payload */
    private function unassignedNote(array $payload): string
    {
        $note = 'التذكرة دي اتوزّعت من تاني، وبقت مش معاك.'
            . "\nالدور: **" . ($payload['role'] ?? '—') . '**';

        if (filled($payload['successor'] ?? null)) {
            $note .= "\nبقت مع: " . $payload['successor'];
        } else {
            $note .= "\nمحدش واخدها دلوقتي.";
        }

        return $note;
    }

    /** @param array<string, mixed> $payload */
    private function reassignedNote(array $payload): string
    {
        $from = $payload['from'] ?? null;
        $to = $payload['to'] ?? null;

        // "first assignment" reads as an assignment, not as a hand-off from
        // nobody — «مفيش → محمد» was noise pretending to be history.
        $note = P::role($payload['role_key'] ?? null, $payload['role'] ?? null) . "\n"
            . ($from === null
                ? '➡️ ' . P::isolate($to ?: 'غير موزع')
                : P::transition($from, $to));

        if (filled($payload['actor'] ?? null)) {
            $note .= "\n👤 غيّرها: " . P::isolate($payload['actor']);
        }

        return $note;
    }

    /** @param array<string, mixed> $payload */
    private function statusNote(array $payload): string
    {
        $note = P::ltr(
            P::status($payload['from_key'] ?? null, $payload['from'] ?? null, $payload['from_variant'] ?? null, $payload['from_open'] ?? null)
            . '  →  '
            . P::status($payload['to_key'] ?? null, $payload['to'] ?? null, $payload['to_variant'] ?? null, $payload['to_open'] ?? null)
        );

        if (filled($payload['actor'] ?? null)) {
            $note .= "\n👤 غيّرها: " . P::isolate($payload['actor']);
        }

        if (filled($payload['note'] ?? null)) {
            $note .= "\n" . $this->truncate($payload['note'], 500);
        }

        return $note;
    }

    /** @param array<string, mixed> $payload */
    private function subtaskNote(array $payload, bool $forOwner): string
    {
        $sub = $payload['subtask'] ?? [];

        $note = '🧩 ' . P::ltr('ST-' . ($sub['id'] ?? '?')) . ' — ' . P::isolate($sub['title'] ?? null);

        if (filled($sub['role'] ?? null)) {
            $note .= "\n" . P::role($sub['role_key'] ?? null, $sub['role']);
        }

        if ($forOwner) {
            if (filled($payload['from'] ?? null)) {
                $note .= "\n↩️ كانت مع: " . P::isolate($payload['from']);
            }
        } else {
            $note .= "\n" . (filled($payload['to'] ?? null)
                ? '➡️ بقت مع: ' . P::isolate($payload['to'])
                : '➡️ محدش واخدها دلوقتي.');
        }

        return $note;
    }

    /** @param array<string, mixed> $payload */
    private function subtaskStatusNote(array $payload): string
    {
        $sub = $payload['subtask'] ?? [];

        $note = '🧩 ' . P::ltr('ST-' . ($sub['id'] ?? '?')) . ' — ' . P::isolate($sub['title'] ?? null);

        if (filled($sub['role'] ?? null)) {
            $note .= "\n" . P::role($sub['role_key'] ?? null, $sub['role']);
        }

        $note .= "\n" . P::ltr(
            P::subtaskStatus($payload['from_key'] ?? null, $payload['from'] ?? null, null)
            . '  →  '
            . P::subtaskStatus($payload['to_key'] ?? null, $payload['to'] ?? null, $payload['to_variant'] ?? null)
        );

        if (filled($payload['actor'] ?? null)) {
            $note .= "\n👤 غيّرها: " . P::isolate($payload['actor']);
        }

        return $note;
    }

    /** @param array<string, mixed> $payload */
    private function subtaskThreadNote(array $payload): string
    {
        $sub = $payload['subtask'] ?? [];

        // The subtask's own identity first — a day's post carries many tickets
        // and each ticket many steps, so «which one?» has to be answerable from
        // the first line.
        $note = '🧩 ' . P::ltr('ST-' . ($sub['id'] ?? '?')) . ' — ' . P::isolate($sub['title'] ?? null);

        if (filled($sub['role'] ?? null)) {
            $note .= "\n" . P::role($sub['role_key'] ?? null, $sub['role']);
        }

        $from = $payload['from'] ?? null;
        $to = $payload['to'] ?? null;

        // A first assignment is not a hand-off from nobody.
        $note .= "\n" . ($from === null
            ? '👤 ' . P::isolate($to ?: 'غير موزع')
            : P::transition($from, $to));

        if ($payload['synced'] ?? false) {
            $note .= "\n↪️ وتوزيع التذكرة اتظبط على كده.";
        }

        if (filled($payload['actor'] ?? null)) {
            $note .= "\n👤 غيّرها: " . P::isolate($payload['actor']);
        }

        return $note;
    }

    private function color(string $variant): int
    {
        return hexdec(self::PALETTE[$variant] ?? self::PALETTE['slate']);
    }

    private function value(?string $value): string
    {
        return filled($value) ? $this->truncate($value, 1024) : '—';
    }

    private function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit - 1) . '…';
    }
}
