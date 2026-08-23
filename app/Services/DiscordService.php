<?php

namespace App\Services;

use App\Support\DiscordResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talking to Discord, and nothing else.
 *
 * Deliberately the same shape as WebPushService: a configured() guard, one
 * timeout, every failure caught and classified rather than thrown. Nothing in
 * here knows what a ticket is — that belongs to DiscordNotificationService, and
 * keeping the split means the retry rules can be reasoned about without also
 * reasoning about assignment.
 *
 * REST only. No gateway connection is ever opened, which is why the bot needs no
 * privileged intents.
 */
class DiscordService
{
    /** Discord caps a nonce at 25 characters. */
    public const NONCE_MAX = 25;

    /**
     * The permission bits this integration cares about.
     *
     * "Send Messages" is what Discord checks when a bot opens a forum post — the
     * UI calls it "Create Posts" there, but it is the same bit. Manage Channels
     * is listed only so discord:check can confirm it is NOT needed.
     */
    public const PERM = [
        'Administrator' => 1 << 3,
        'Manage Channels' => 1 << 4,
        'View Channels' => 1 << 10,
        'Send Messages' => 1 << 11,
        'Embed Links' => 1 << 14,
        'Read Message History' => 1 << 16,
        'Create Public Threads' => 1 << 35,
        'Send Messages in Threads' => 1 << 38,
    ];

    /** Discord's own type number for a forum channel. */
    public const CHANNEL_FORUM = 15;

    /** The widest a single scan looks back when recovering an ambiguous send. */
    private const SCAN_LIMIT = 100;

    public function configured(): bool
    {
        return config('discord.enabled')
            && filled(config('discord.bot_token'))
            && filled(config('discord.tickets_channel_id'));
    }

    public function dryRun(): bool
    {
        return (bool) config('discord.dry_run');
    }

    public function ticketsChannelId(): ?string
    {
        return config('discord.tickets_channel_id');
    }

    /**
     * Posts a message and returns its id.
     *
     * enforce_nonce is the important part. Discord will return the message it
     * already has rather than creating a second one when the same nonce comes
     * back, which turns an ordinary job retry from a duplicate risk into a
     * no-op. It is not a complete answer — Discord only honours a nonce for a
     * few minutes — so the caller still has findByNonce() for the slow cases.
     *
     * @param  array<string, mixed>  $body
     */
    public function postMessage(string $channelId, array $body, ?string $nonce = null): DiscordResult
    {
        if ($nonce !== null) {
            $body['nonce'] = $nonce;
            $body['enforce_nonce'] = true;
        }

        // Nothing may be mentioned unless it was named on purpose. Without this
        // an @everyone typed into a ticket title would ping the whole server.
        $body['allowed_mentions'] ??= ['parse' => []];

        if ($this->dryRun()) {
            Log::info('discord dry run', ['channel_id' => $channelId, 'body' => $body]);

            return DiscordResult::ok('dry-run-' . ($nonce ?? uniqid()));
        }

        return $this->request('POST', "/channels/{$channelId}/messages", $body, function (Response $response) {
            return DiscordResult::ok((string) $response->json('id'));
        });
    }

    /**
     * Edits a message we sent earlier.
     *
     * This is what keeps a ticket's card true. The card is the ticket's current
     * state, not a log line: when the status moves or the role changes hands, the
     * same message is rewritten rather than a second one appended, so a day's
     * post stays a list of tickets instead of a transcript of edits.
     *
     * @param  array<string, mixed>  $body
     */
    public function editMessage(string $channelId, string $messageId, array $body): DiscordResult
    {
        $body['allowed_mentions'] ??= ['parse' => []];

        if ($this->dryRun()) {
            Log::info('discord dry run (edit)', ['channel_id' => $channelId, 'message_id' => $messageId, 'body' => $body]);

            return DiscordResult::ok($messageId);
        }

        return $this->request(
            'PATCH',
            "/channels/{$channelId}/messages/{$messageId}",
            $body,
            fn (Response $response) => DiscordResult::ok((string) $response->json('id')),
        );
    }

    /**
     * Opens a forum post — a day's container for the tickets announced in it.
     *
     * A forum post is a thread whose first message is created with it, so this
     * is one call rather than post-then-thread. The id it returns is BOTH the
     * thread id and its starter message's id, which is what lets the daily
     * header be edited later through editMessage() using the same value twice.
     *
     * Needs Send Messages on the forum channel — Discord presents that as
     * "Create Posts" for forums. It does NOT need Manage Channels; the forum
     * itself already exists.
     *
     * @param  array<string, mixed>  $message  the starter message payload
     */
    public function createForumPost(string $forumChannelId, string $name, array $message): DiscordResult
    {
        $message['allowed_mentions'] ??= ['parse' => []];

        if ($this->dryRun()) {
            Log::info('discord dry run (forum post)', ['forum' => $forumChannelId, 'name' => $name, 'message' => $message]);

            return DiscordResult::ok('dry-run-post-' . substr(md5($name), 0, 12));
        }

        return $this->request(
            'POST',
            "/channels/{$forumChannelId}/threads",
            [
                'name' => $this->truncate($name, 100),
                'auto_archive_duration' => 10080,
                'message' => $message,
            ],
            fn (Response $response) => DiscordResult::ok((string) $response->json('id')),
        );
    }

    /**
     * Looks for a day's post that already exists, by exact name.
     *
     * The ledger's unique dedupe key is what normally stops a second post being
     * opened for a date. This is the belt to that braces: if a row was lost, or
     * somebody opened the post by hand, adopting it is better than starting a
     * rival. Active threads first — a day in progress is not archived — then the
     * public archive for a date that has gone quiet.
     */
    public function findForumPost(string $forumChannelId, string $name): ?string
    {
        if ($this->dryRun()) {
            return null;
        }

        foreach ([
            "/guilds/" . config('discord.guild_id') . "/threads/active",
            "/channels/{$forumChannelId}/threads/archived/public?limit=100",
        ] as $path) {
            try {
                $response = $this->client()->get($path);

                if (! $response->successful()) {
                    continue;
                }

                foreach ($response->json('threads') ?? [] as $thread) {
                    if (($thread['parent_id'] ?? null) === $forumChannelId && ($thread['name'] ?? null) === $name) {
                        return (string) $thread['id'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('discord forum post lookup failed', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * Finds a message we already sent, by its nonce.
     *
     * This is the recovery path for a worker that died between Discord accepting
     * a message and the row being updated. Discord echoes the nonce back on the
     * message object, so our own message is identifiable without having stored
     * its id — which is exactly the thing that went missing.
     *
     * Needs Read Message History on the channel.
     */
    public function findByNonce(string $channelId, string $nonce): ?string
    {
        if ($this->dryRun()) {
            return null;
        }

        try {
            $response = $this->client()->get("/channels/{$channelId}/messages", ['limit' => self::SCAN_LIMIT]);

            if (! $response->successful()) {
                Log::warning('discord nonce scan failed', [
                    'channel_id' => $channelId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            foreach ($response->json() ?? [] as $message) {
                if (($message['nonce'] ?? null) !== null && (string) $message['nonce'] === $nonce) {
                    return (string) $message['id'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('discord nonce scan errored', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Hangs a thread off a message, so a ticket's updates collect under it
     * instead of scrolling the channel apart.
     *
     * 10080 minutes is Discord's maximum (7 days). An archived thread un-archives
     * itself the moment something is posted into it, so a long-running ticket
     * does not need the window to cover its whole life.
     */
    public function startThread(string $channelId, string $messageId, string $name): ?string
    {
        if ($this->dryRun()) {
            return 'dry-run-thread-' . $messageId;
        }

        $result = $this->request(
            'POST',
            "/channels/{$channelId}/messages/{$messageId}/threads",
            ['name' => $this->truncate($name, 100), 'auto_archive_duration' => 10080],
            fn (Response $response) => DiscordResult::ok((string) $response->json('id')),
        );

        return $result->ok ? $result->messageId : null;
    }

    /**
     * The private channel between the bot and one person, creating it if needed.
     *
     * Discord returns the same channel every time, so this is safe to call on
     * every send — but the caller stores the id anyway, because a message that
     * may already have been sent has to be findable later.
     */
    public function openDm(string $discordUserId): DiscordResult
    {
        if ($this->dryRun()) {
            return DiscordResult::ok('dry-run-dm-' . $discordUserId);
        }

        return $this->request(
            'POST',
            '/users/@me/channels',
            ['recipient_id' => $discordUserId],
            fn (Response $response) => DiscordResult::ok((string) $response->json('id')),
        );
    }

    /** Whether this Discord id belongs to somebody in our server. */
    public function guildMemberExists(string $discordUserId): ?bool
    {
        $guild = config('discord.guild_id');

        if (blank($guild) || $this->dryRun()) {
            return null;
        }

        try {
            $response = $this->client()->get("/guilds/{$guild}/members/{$discordUserId}");

            if ($response->status() === 404) {
                return false;
            }

            return $response->successful() ? true : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Who the bot is, for discord:check. Null when the token is refused.
     *
     * @return array<string, mixed>|null
     */
    public function identity(): ?array
    {
        try {
            $response = $this->client()->get('/users/@me');

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * What the bot may actually do in a channel.
     *
     * Discord has no "what are my permissions here" endpoint outside an
     * interaction, so this computes it the way Discord does: the bot's roles
     * OR'd together, then the channel's overwrites applied in Discord's order —
     * @everyone, then roles, then the member. Administrator short-circuits.
     *
     * Worth doing rather than guessing: "nothing arrived" looks identical
     * whether the token is wrong, the channel is invisible, or one permission is
     * missing, and those are three different fixes.
     *
     * @return array<string, bool>|null  null when something could not be read
     */
    public function channelPermissions(string $channelId): ?array
    {
        $guild = config('discord.guild_id');

        if (blank($guild)) {
            return null;
        }

        try {
            $me = $this->client()->get('/users/@me');
            $roles = $this->client()->get("/guilds/{$guild}/roles");
            $member = $this->client()->get("/guilds/{$guild}/members/" . $me->json('id'));
            $channel = $this->client()->get("/channels/{$channelId}");

            if (! $me->successful() || ! $roles->successful() || ! $member->successful() || ! $channel->successful()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $botId = (string) $me->json('id');
        $byId = collect($roles->json())->keyBy('id');
        $mine = collect($member->json('roles') ?? [])->push($guild);   // @everyone's id is the guild's

        $perm = 0;

        foreach ($mine as $roleId) {
            $perm |= (int) ($byId[$roleId]['permissions'] ?? 0);
        }

        $admin = ($perm & self::PERM['Administrator']) === self::PERM['Administrator'];

        $overwrites = collect($channel->json('permission_overwrites') ?? []);

        if ($everyone = $overwrites->firstWhere('id', $guild)) {
            $perm &= ~((int) $everyone['deny']);
            $perm |= (int) $everyone['allow'];
        }

        $allow = 0;
        $deny = 0;

        foreach ($overwrites as $o) {
            if ((int) $o['type'] === 0 && $o['id'] !== $guild && $mine->contains($o['id'])) {
                $deny |= (int) $o['deny'];
                $allow |= (int) $o['allow'];
            }
        }

        $perm &= ~$deny;
        $perm |= $allow;

        if ($member = $overwrites->first(fn ($o) => (int) $o['type'] === 1 && $o['id'] === $botId)) {
            $perm &= ~((int) $member['deny']);
            $perm |= (int) $member['allow'];
        }

        $result = [];

        foreach (self::PERM as $label => $bit) {
            $result[$label] = $admin || (($perm & $bit) === $bit);
        }

        return $result;
    }

    /**
     * Whether the bot can actually see a channel. A token that works and a
     * channel it cannot read are two different problems and read identically
     * from the outside, so discord:check asks separately.
     *
     * @return array<string, mixed>|null
     */
    public function channel(string $channelId): ?array
    {
        try {
            $response = $this->client()->get("/channels/{$channelId}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * One place where an HTTP outcome becomes a decision.
     *
     * @param  array<string, mixed>  $body
     * @param  callable(Response): DiscordResult  $onSuccess
     */
    private function request(string $method, string $path, array $body, callable $onSuccess): DiscordResult
    {
        try {
            $response = $this->client()->send($method, $path, ['json' => $body]);
        } catch (ConnectionException $e) {
            // The network, not the request. Always worth another go.
            return DiscordResult::retryable('connection: ' . $e->getMessage());
        } catch (\Throwable $e) {
            return DiscordResult::retryable('transport: ' . $e->getMessage());
        }

        if ($response->successful()) {
            return $onSuccess($response);
        }

        $status = $response->status();

        if ($status === 429) {
            // Discord says how long to wait; obeying it is the difference between
            // backing off and being rate limited harder.
            $retryAfter = (int) ceil((float) ($response->json('retry_after') ?? $response->header('Retry-After') ?? 5));

            return DiscordResult::retryable('rate limited', $status, max($retryAfter, 1));
        }

        if ($status >= 500) {
            return DiscordResult::retryable('discord server error', $status);
        }

        // Everything else is us: a bad token, a channel we cannot post in, a
        // person who does not accept DMs (50007). Retrying changes nothing.
        return DiscordResult::terminal(
            (string) ($response->json('message') ?? 'HTTP ' . $status),
            $status,
            $response->json('code') === null ? null : (int) $response->json('code'),
        );
    }

    private function client()
    {
        return Http::withHeaders([
            // Discord's own scheme — NOT Bearer, which withToken() would send.
            'Authorization' => 'Bot ' . config('discord.bot_token'),
            'Content-Type' => 'application/json',
        ])
            ->baseUrl(rtrim(config('discord.api_base'), '/'))
            ->timeout((int) config('discord.timeout', 10))
            ->acceptJson();
    }

    private function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit - 1) . '…';
    }
}
