<?php

namespace App\Services;

use App\Models\PriorityDefinition;
use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use Illuminate\Database\Eloquent\Builder;

/**
 * F28 — the read-only view of tickets other internal systems are allowed to see.
 *
 * It returns a FIXED, small set of fields on purpose. A caller that holds the
 * integration secret can ask for any ticket, so what comes back is limited to
 * what is needed to pick one and open it: number, title, status, priority,
 * company and the link. Never the description, comments, attachments,
 * assignees or the reporter's name and ERP id — those are the customer's, and
 * the other system has no business holding a copy.
 */
class TicketLookupService
{
    /**
     * Newest first, so an empty search is a useful "recent tickets" list.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $limit): array
    {
        $query = $this->base()->orderByDesc('reported_at')->orderByDesc('id')->limit($limit);

        if ($term !== '') {
            $this->applyTerm($query, $term);
        }

        return $this->present($query->get());
    }

    /** @return array<string, mixed>|null */
    public function find(string $ticketNumber): ?array
    {
        $ticket = $this->base()->where('ticket_number', $ticketNumber)->first();

        return $ticket === null ? null : $this->present(collect([$ticket]))[0];
    }

    private function base(): Builder
    {
        // Only the columns that are returned, and the company by id+name —
        // never the LONGTEXT description (CLAUDE.md § 4). SoftDeletes keeps
        // deleted tickets out without being asked.
        return Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'status', 'priority', 'company_id', 'reported_at'])
            ->with('company:id,name');
    }

    /**
     * A ticket number (or the start of one) is matched on the unique index.
     * Anything else goes through the same FULLTEXT search the rest of the app
     * uses, never LIKE %...% (F03.1).
     */
    private function applyTerm(Builder $query, string $term): void
    {
        if (preg_match('/^TK-/i', $term) === 1) {
            // Prefix match on an indexed column; the term is escaped so a
            // pasted % or _ matches itself instead of acting as a wildcard.
            $escaped = addcslashes($term, '\\%_');
            $query->where('ticket_number', 'like', $escaped . '%');

            return;
        }

        $query->search($term);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Ticket>  $tickets
     * @return list<array<string, mixed>>
     */
    private function present($tickets): array
    {
        // Both maps are cached (rememberForever, busted on save), so labelling
        // costs no queries here.
        $statuses = TicketStatusDefinition::options();
        $priorities = PriorityDefinition::options();

        return $tickets->map(function (Ticket $ticket) use ($statuses, $priorities) {
            // The raw column, not the attribute: status and priority are
            // custom casts, and the key is what the caller needs.
            $status = (string) $ticket->getRawOriginal('status');
            $priority = (string) $ticket->getRawOriginal('priority');

            return [
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'status' => $status,
                'status_label' => $statuses[$status] ?? $status,
                'priority' => $priority,
                'priority_label' => $priorities[$priority] ?? $priority,
                'company' => $ticket->company?->name,
                'url' => route('tickets.show', $ticket->id),
                'reported_at' => $ticket->reported_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
