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
