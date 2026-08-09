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

];
