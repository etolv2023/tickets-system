<?php

namespace App\Support;

/**
 * F19.3 — /reports/team-activity sends one filter bar over two tables, so its
 * query-string names are not the names either model's scope uses: `person`
 * means `assignee` on a ticket, and `subtask_status` means `status` on a
 * subtask while plain `status` means the parent ticket's.
 *
 * The screen and its export both need that translation, and a screen whose
 * export answers a slightly different question is the exact failure this
 * feature exists to avoid — so the translation is written once, here.
 */
class TeamActivityFilters
{
    /**
     * ★ (2026-10-05) Every key the screen reads, so the controller and the
     * export cannot drift by one forgotten name.
     */
    public const KEYS = [
        'person', 'from', 'to', 'ticket_date_basis', 'subtask_date_basis',
        'type', 'priority', 'status', 'company', 'role', 'subtask_status',
        'ticket_late', 'subtask_overdue', 'q',
    ];

    /**
     * @param  array<string, mixed>  $filters  the raw query string
     * @return array<string, mixed>  keys Ticket::scopeFilter understands
     */
    public static function forTickets(array $filters): array
    {
        return [
            'assignee' => $filters['person'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'date_basis' => $filters['ticket_date_basis'] ?? null,
            'type' => $filters['type'] ?? null,
            'priority' => $filters['priority'] ?? null,
            'status' => $filters['status'] ?? null,
            'company' => $filters['company'] ?? null,
            // ★ (2026-10-05) Missed its SLA or delivery date (Ticket::scopeLate).
            'late' => $filters['ticket_late'] ?? null,
            'q' => $filters['q'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters  the raw query string
     * @return array<string, mixed>  keys TicketSubtask::scopeFilter understands
     */
    public static function forSubtasks(array $filters): array
    {
        return [
            'person' => $filters['person'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'date_basis' => $filters['subtask_date_basis'] ?? null,
            'role' => $filters['role'] ?? null,
            'status' => $filters['subtask_status'] ?? null,
            'type' => $filters['type'] ?? null,
            'company' => $filters['company'] ?? null,
            // ★ (2026-10-05) Still open past its date, or not.
            'overdue' => $filters['subtask_overdue'] ?? null,
            'q' => $filters['q'] ?? null,
        ];
    }
}
