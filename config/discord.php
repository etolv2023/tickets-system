<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Discord ticket notifications
    |--------------------------------------------------------------------------
    |
    | Two audiences, one bot. A general channel carries every ticket as a feed,
    | and each person whose assignment actually changed gets a direct message.
    |
    | Off unless enabled AND a token and channel are present. "Not configured"
    | has to mean "silently off" — the ticket system predates this integration
    | and must keep working identically without it, so nothing here is allowed
    | to throw into an assignment or a status change.
    |
    | This is REST-only. The bot never opens a gateway connection, so no
    | privileged intents are required.
    |
    */

    'enabled' => (bool) env('DISCORD_ENABLED', false),

    /*
     * Bot token from the Discord developer portal. Secret — never logged, never
     * rendered, never returned by any route.
     */
    'bot_token' => env('DISCORD_BOT_TOKEN'),

    /*
     * The server. Not needed to post — a channel id is enough — but discord:check
     * uses it to confirm the bot is actually in the server and that a given
     * discord_user_id belongs to somebody who is too.
     */
    'guild_id' => env('DISCORD_GUILD_ID'),

    /*
     * The channel every ticket is announced in.
     */
    'tickets_channel_id' => env('DISCORD_TICKETS_CHANNEL_ID'),

    'api_base' => env('DISCORD_API_BASE', 'https://discord.com/api/v10'),

    'timeout' => (int) env('DISCORD_TIMEOUT', 10),

    /*
     * Each ticket's later updates go into a thread hanging off its own
     * announcement, so the channel stays a list of tickets rather than a wall of
     * status lines. Turn off and updates become plain channel messages.
     */
    'use_threads' => (bool) env('DISCORD_USE_THREADS', true),

    /*
     * Status changes post into the ticket's thread. Assignment events ignore
     * this — they are the point of the integration.
     */
    'announce_status' => (bool) env('DISCORD_ANNOUNCE_STATUS', true),

    /*
     * Mention assignees in the general channel. Off by default: the assignee
     * already gets a DM, so a mention pings the same person twice for one event.
     * When on, allowed_mentions is restricted to exactly those user ids.
     */
    'mention_assignees' => (bool) env('DISCORD_MENTION_ASSIGNEES', false),

    /*
     * Render and log the exact payload instead of calling the API. Lets the whole
     * flow — gates, ledger, thread routing, idempotency — be exercised without
     * credentials.
     */
    'dry_run' => (bool) env('DISCORD_DRY_RUN', false),

    /*
    |--------------------------------------------------------------------------
    | Recovering an ambiguous send
    |--------------------------------------------------------------------------
    |
    | A worker can claim a row, have Discord accept the message, and die before
    | writing the id back. The row is then stale and nobody can tell from the
    | database whether the message exists.
    |
    | Every row carries a deterministic nonce sent with enforce_nonce, so a
    | prompt retry is de-duplicated by Discord itself. But that guarantee is
    | time-bounded — Discord only documents it as "the past few minutes" — so it
    | cannot be leaned on for a row that has been stuck for hours.
    |
    | reclaim_after is therefore short: long enough that a live worker is not
    | robbed mid-flight, short enough that recovery still happens while both the
    | nonce window and a recent-message scan are effective.
    |
    | Past recovery_max_age the ambiguity is no longer resolvable. Such a row is
    | marked 'unverified' and NOT resent: a notification that may be missing is
    | better than one that may arrive twice, and the row stays visible in the
    | ledger and in discord:check for a human to settle.
    |
    */

    'reclaim_after' => (int) env('DISCORD_RECLAIM_AFTER', 120),

    'recovery_max_age' => (int) env('DISCORD_RECOVERY_MAX_AGE', 3600),

];
