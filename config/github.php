<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub — evidence, not integration
    |--------------------------------------------------------------------------
    |
    | This exists to answer one question: does a ticket somebody marked resolved
    | actually have code behind it? Not "did the developer say so" — is there a
    | branch, in a repo, whose name carries the ticket number.
    |
    | It is therefore READ ONLY, all the way down. GitHubClient has exactly one
    | request method and it issues GET; there is no code path in this
    | application that can POST, PATCH or DELETE against GitHub, so no
    | configuration mistake and no future edit to a controller can turn this
    | into something that writes.
    |
    | The token should be a fine-grained PAT scoped to the listed repositories
    | with:
    |
    |     Contents:      Read-only
    |     Pull requests: Read-only
    |     Metadata:      Read-only        (mandatory, granted automatically)
    |
    | and nothing else. `php artisan github:check` verifies exactly that and
    | complains if the token turns out to hold more.
    |
    | Off unless enabled AND a token is present. "Not configured" has to mean
    | "silently off": the ticket system predates this and must keep working
    | identically without it. Nothing here throws into a ticket screen.
    |
    */

    'enabled' => (bool) env('GITHUB_ENABLED', false),

    /*
     * Fine-grained personal access token. Secret — never logged, never
     * rendered, never returned by any route. The admin screen shows only
     * whether it works, never the value.
     */
    'token' => env('GITHUB_TOKEN'),

    'api_base' => env('GITHUB_API_BASE', 'https://api.github.com'),

    /*
     * The web host, for building the links the buttons point at. Split from
     * api_base because they are different hosts on github.com and the same
     * host on GitHub Enterprise.
     */
    'web_base' => env('GITHUB_WEB_BASE', 'https://github.com'),

    'timeout' => (int) env('GITHUB_TIMEOUT', 15),

    /*
     * A sync walks pages of 100 until GitHub returns a short page. That is what
     * ends the walk — not this number.
     *
     * ★ (2026-08-29) This was 10, and the first real sync came back with EXACTLY
     * 1000 branches and EXACTLY 1000 pull requests on trioapi. That is not a
     * count, it is 10 × 100: the walk was cut off and the rest was never read.
     * A branch that is never read is a ticket reported as «ملهاش برانش» when it
     * has one — a false accusation produced by the feature built to prevent
     * false accusations.
     *
     * So this is a runaway guard against a broken pagination loop, and NOT a
     * working limit. 1000 pages is 100,000 rows in one repository; no real
     * repository reaches it, and one that does should be looked at rather than
     * quietly halved. Hitting it now raises instead of truncating — see
     * GitHubClient::pages().
     *
     * The limit that actually binds at scale is GitHub's, not this: a PAT gets
     * 5,000 requests an hour, which is ~500,000 rows. If a repository ever grows
     * past what one nightly pass can read inside that budget, the answer is
     * webhooks — being told what changed — not a bigger number here.
     */
    'max_pages' => (int) env('GITHUB_MAX_PAGES', 1000),

    /*
    |--------------------------------------------------------------------------
    | Naming
    |--------------------------------------------------------------------------
    |
    | Generation is strict and matching is tolerant, which is deliberate and not
    | a contradiction: every name this system HANDS OUT starts with the ticket
    | number, so `git branch` sorts by ticket. But branches created by hand
    | before any of this existed carry a Git-Flow prefix, and refusing to
    | recognise those would report work as missing when it is right there.
    |
    | A prefix is one segment and one segment only — `feature/TK-2026-00042` is
    | recognised, `team/feature/TK-2026-00042` is not. Deep nesting means the
    | ticket number stops being findable at a glance, which is the property the
    | whole convention exists to protect.
    |
    */

    'allow_type_prefix' => (bool) env('GITHUB_ALLOW_TYPE_PREFIX', true),

    'type_prefixes' => ['feature', 'feat', 'fix', 'hotfix', 'bugfix', 'chore', 'refactor', 'release'],

    /*
     * Warn this many days before the token expires. A fine-grained PAT lasts a
     * year at most, and the failure mode when it lapses is silent: the sync
     * simply stops finding branches, and every ticket starts looking like it
     * has no code. github:check surfaces it before that happens.
     */
    'expiry_warning_days' => (int) env('GITHUB_EXPIRY_WARNING_DAYS', 30),

];
