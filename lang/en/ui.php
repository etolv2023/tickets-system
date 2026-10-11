<?php

return [
    'language' => 'العربية',
    'language_code' => 'ar',
    'workflow_title' => 'Ticket workflow at a glance',
    'workflow_intro' => 'The ticket status answers one question: where is the commitment now? “Start/Finish” tracks an individual’s work and is not the final ticket status.',
    'stages' => [
        'intake' => ['title' => '1. Intake and approval', 'body' => 'New → Awaiting approval when approval is required.'],
        'delivery' => ['title' => '2. Assignment and delivery', 'body' => 'Roles are assigned to people, then each owner starts their work.'],
        'verification' => ['title' => '3. Testing and verification', 'body' => 'Development complete does not mean resolved; the result must be verified.'],
        'closure' => ['title' => '4. Resolution and closure', 'body' => 'Resolved awards points; Closed means the client was notified and the commitment ended.'],
    ],
    'points_title' => 'How are points calculated?',
    'points_award' => 'Points are awarded once when the ticket becomes Resolved, based on completed subtasks and their owners.',
    'points_penalty' => 'A penalty is posted when a subtask finishes after its due date. The ledger shows the task, reason, and amount.',
    'points_correction' => 'A manual correction is a separate audited entry with an owner and reason; it does not rewrite the original award.',
];
