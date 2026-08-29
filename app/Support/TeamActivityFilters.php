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
        ];
    }
}
