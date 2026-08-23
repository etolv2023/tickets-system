<?php

namespace App\Services;

use App\Jobs\SendDiscordMessage;
use App\Models\Ticket;
use App\Models\TicketDiscordMessage;
use App\Models\User;
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
            $this->record([
                'ticket_id' => $ticket->id,
                'type' => TicketDiscordMessage::TYPE_CREATED_GENERAL,
                'dedupe_key' => 'created:' . $ticket->id,
                'payload' => ['ticket' => $this->snapshot($ticket)],
            ]);
        });
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
     */
    public function assignmentsChanged(Ticket $ticket, array $diffs, ?int $actorId, bool $activation = false): void
    {
        if ($diffs === []) {
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
                if ($activated && ! $activation) {
                    $this->record([
                        'ticket_id' => $ticket->id,
                        'role_id' => $diff['role_id'],
                        'type' => TicketDiscordMessage::TYPE_REASSIGNED_GENERAL,
                        'payload' => [
                            'ticket' => $snapshot,
                            'role' => $diff['role_name'],
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
    public function statusChanged(Ticket $ticket, string $fromLabel, string $toLabel, ?int $actorId, ?string $note = null): void
    {
        if (! config('discord.announce_status')) {
            return;
        }

        $this->guard($ticket, function () use ($ticket, $fromLabel, $toLabel, $actorId, $note) {
            if (! $this->activated($ticket)) {
                return;
            }

            $resolved = in_array($ticket->status->value, ['resolved', 'closed'], true);

            $this->record([
                'ticket_id' => $ticket->id,
                'type' => $resolved
                    ? TicketDiscordMessage::TYPE_RESOLVED
                    : TicketDiscordMessage::TYPE_STATUS_CHANGED,
                'payload' => [
                    'ticket' => $this->snapshot($ticket),
                    'from' => $fromLabel,
                    'to' => $toLabel,
                    'note' => $note,
                    'actor' => $actorId === null ? null : (User::find($actorId)?->name),
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
            TicketDiscordMessage::TYPE_CREATED_GENERAL => $this->announcement($ticket),
            TicketDiscordMessage::TYPE_ASSIGNED => [
                'embeds' => [$this->ticketEmbed(
                    $ticket,
                    ($payload['initial'] ?? false) ? 'التذكرة دي بقت معاك' : 'اتعملك أساين على التذكرة دي',
                    $this->assignedNote($payload),
                )],
            ],
            TicketDiscordMessage::TYPE_UNASSIGNED => [
                'embeds' => [$this->plainEmbed(
                    $ticket,
                    'التذكرة دي مبقتش معاك',
                    $this->unassignedNote($payload),
                    'slate',
                )],
            ],
            TicketDiscordMessage::TYPE_REASSIGNED_GENERAL => [
                'embeds' => [$this->plainEmbed(
                    $ticket,
                    'التوزيع اتغيّر',
                    $this->reassignedNote($payload),
                    'slate',
                )],
            ],
            TicketDiscordMessage::TYPE_STATUS_CHANGED, TicketDiscordMessage::TYPE_RESOLVED => [
                'embeds' => [$this->plainEmbed(
                    $ticket,
                    'الحالة اتغيّرت',
                    $this->statusNote($payload),
                    $ticket['variant'] ?? 'slate',
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

        // afterCommit: the row and the job both belong to the assignment's
        // transaction. A rolled-back assignment must not leave a worker holding
        // a message about something that never happened.
        SendDiscordMessage::dispatch($record->id)->afterCommit();
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
            'roleAssignments:id,ticket_id,role_id,user_id',
            'roleAssignments.user:id,name,discord_user_id',
            'roleAssignments.role:id,name_ar',
        ])));

        return [
            'id' => $ticket->id,
            'number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'origin' => $ticket->originLabel(),
            'type' => $ticket->type->label(),
            'priority' => $ticket->priority->label(),
            'status' => $ticket->status->label(),
            'variant' => $ticket->status->variant(),
            'creator' => $ticket->creator?->name,
            'assignees' => $ticket->roleAssignments
                ->map(fn ($a) => [
                    'role' => $a->role?->name_ar,
                    'user' => $a->user?->name,
                    'discord_id' => $a->user?->discord_user_id ?: null,
                ])
                ->filter(fn ($row) => $row['user'] !== null)
                ->values()
                ->all(),
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
        $fields = [
            ['name' => 'العميل', 'value' => $this->value($ticket['origin'] ?? null), 'inline' => true],
            ['name' => 'النوع', 'value' => $this->value($ticket['type'] ?? null), 'inline' => true],
            ['name' => 'الأولوية', 'value' => $this->value($ticket['priority'] ?? null), 'inline' => true],
            ['name' => 'الحالة', 'value' => $this->value($ticket['status'] ?? null), 'inline' => true],
            ['name' => 'فتحها', 'value' => $this->value($ticket['creator'] ?? null), 'inline' => true],
        ];

        $assignees = $this->assigneeLines($ticket);

        if ($assignees !== null) {
            $fields[] = ['name' => 'التوزيع', 'value' => $assignees, 'inline' => false];
        }

        return array_filter([
            'title' => $this->truncate(($ticket['number'] ?? '') . ' — ' . ($ticket['title'] ?? ''), 256),
            'url' => $ticket['url'] ?? null,
            'description' => $note,
            'color' => $this->color($ticket['variant'] ?? 'slate'),
            'fields' => $fields,
            'timestamp' => $ticket['reported_at'] ?? null,
            'author' => ['name' => $heading],
        ], fn ($v) => $v !== null);
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
        return array_filter([
            'title' => $this->truncate(($ticket['number'] ?? '') . ' — ' . ($ticket['title'] ?? ''), 256),
            'url' => $ticket['url'] ?? null,
            'description' => $note,
            'color' => $this->color($variant),
            'author' => ['name' => $heading],
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

        $note = '**' . ($payload['role'] ?? '—') . '**: '
            . ($from ?? 'مفيش') . ' ← ' . ($to ?? 'مفيش');

        if (filled($payload['actor'] ?? null)) {
            $note .= "\nغيّرها: " . $payload['actor'];
        }

        return $note;
    }

    /** @param array<string, mixed> $payload */
    private function statusNote(array $payload): string
    {
        $note = '**' . ($payload['from'] ?? '—') . '** ← **' . ($payload['to'] ?? '—') . '**';

        if (filled($payload['actor'] ?? null)) {
            $note .= "\nغيّرها: " . $payload['actor'];
        }

        if (filled($payload['note'] ?? null)) {
            $note .= "\n" . $this->truncate($payload['note'], 500);
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
