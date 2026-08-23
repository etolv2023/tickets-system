<?php

namespace App\Support;

/**
 * What one call to Discord came back with.
 *
 * The reason this exists rather than a bare status code: the only question the
 * job actually needs answered is "try again, or stop?", and getting that wrong
 * is expensive in both directions. Retrying a 403 burns the queue forever on a
 * permission that will never appear; giving up on a 429 drops a notification
 * because the bot was briefly busy. Classifying once, here, means every caller
 * gets the same answer.
 */
final class DiscordResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $messageId = null,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfter = null,
        public readonly ?int $httpStatus = null,
        public readonly ?string $error = null,
        public readonly ?int $discordCode = null,
    ) {
    }

    public static function ok(?string $messageId = null): self
    {
        return new self(ok: true, messageId: $messageId);
    }

    /** Worth another attempt: rate limit, server error, or the network. */
    public static function retryable(string $error, ?int $httpStatus = null, ?int $retryAfter = null): self
    {
        return new self(
            ok: false,
            retryable: true,
            retryAfter: $retryAfter,
            httpStatus: $httpStatus,
            error: $error,
        );
    }

    /** Will fail identically forever — bad token, missing permission, closed DMs. */
    public static function terminal(string $error, ?int $httpStatus = null, ?int $discordCode = null): self
    {
        return new self(
            ok: false,
            httpStatus: $httpStatus,
            error: $error,
            discordCode: $discordCode,
        );
    }
}
