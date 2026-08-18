<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hosts that are internal by definition
    |--------------------------------------------------------------------------
    |
    | A ticket's page link is checked against DNS so a typo'd or dead domain is
    | caught while the person who knows the answer is still looking at the form.
    | That check is wrong for a customer whose system is not on the public
    | internet: an ERP on 192.168.1.50, or erp.company.local, resolves perfectly
    | well from inside their network and not at all from this server.
    |
    | Any host whose last label is listed here skips the DNS lookup. IP literals
    | and single-label hosts (http://erp/) skip it too, without needing an entry
    | — see App\Rules\ResolvableHost.
    |
    | Add a customer's own private suffix here rather than turning the check off.
    |
    */

    'internal_host_suffixes' => [
        // mDNS / Bonjour, and the classic Windows domain suffixes.
        'local',
        'localhost',
        'lan',
        'home',
        'corp',
        'intranet',
        'internal',
        'private',
        // RFC 2606 / 6761 reserved — these can never resolve publicly by design.
        'test',
        'invalid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception intake (F26)
    |--------------------------------------------------------------------------
    |
    | The other systems report their uncaught errors here and this turns each
    | one into work: a ticket of type «اكسبشن», assigned to a back-end
    | developer, due in four working hours.
    |
    | It is off unless a secret is configured. That is deliberate — an intake
    | endpoint with no shared secret is an open door for anybody who can reach
    | the host to create tickets and assign work, so "not configured" has to
    | mean "closed", never "unauthenticated".
    |
    */

    'intake' => [

        /*
         * The shared HMAC secret. MUST be the same string as TICKETS_WEBHOOK_SECRET
         * on every sending server. Empty disables the endpoint entirely (503).
         */
        'secret' => env('EXCEPTION_WEBHOOK_SECRET', ''),

        /*
         * How stale a signed request may be before it is refused, in seconds.
         * This is what makes a captured request unusable tomorrow; the
         * timestamp is inside the signature, so it cannot be edited to refresh
         * it. Five minutes is wide enough to survive ordinary clock drift
         * between two servers and narrow enough to be worth having.
         */
        'max_skew_seconds' => (int) env('EXCEPTION_WEBHOOK_MAX_SKEW', 300),

        /*
         * The ticket type an incoming error opens. A key from ticket_types —
         * the seeded `exception` row is a system row, so it is always there.
         */
        'type' => env('EXCEPTION_TICKET_TYPE', 'exception'),

        /*
         * The priority it opens at. Only decides the ticket's own SLA clock and
         * its colour on the board; the four-hour developer deadline below is a
         * separate promise and does not come from here.
         */
        'priority' => env('EXCEPTION_TICKET_PRIORITY', 'urgent'),

        /*
         * WORKING hours until the assigned subtask is due — counted through
         * SlaService, so the clock pauses overnight, at the weekend and on
         * holidays. An error at 4pm is therefore due mid-morning tomorrow
         * rather than at 8pm tonight.
         */
        'due_working_hours' => (float) env('EXCEPTION_DUE_HOURS', 4),

        /*
         * The role key that gets assigned. Whoever is picked is drawn from the
         * active users holding this role.
         */
        'assign_role' => env('EXCEPTION_ASSIGN_ROLE', 'backend'),
    ],

];
