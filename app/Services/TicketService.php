<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\CompanyContact;
use App\Models\Ticket;
use App\Models\TicketComment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

/**
 * Creating and editing a ticket (F03, F05). Status transitions are
 * TicketWorkflowService's job in phase 3.
 */
class TicketService
{
    public function __construct(
        private readonly TicketNumberService $numbers,
        private readonly SlaService $sla,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $createdBy): Ticket
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $contact = isset($data['contact_id'])
                ? CompanyContact::find($data['contact_id'])
                : null;

            $reportedAt = CarbonImmutable::now();
            $type = TicketType::from($data['type']);
            $priority = \App\Enums\Priority::from($data['priority']);

            return Ticket::create([
                'ticket_number' => $this->numbers->next((int) $reportedAt->format('Y')),
                'company_id' => $data['company_id'],
                'contact_id' => $contact?->id,
                // Frozen now. Editing the contact later must not rewrite history. F03
                'reporter_name' => $contact?->name ?? $data['reporter_name'],
                'reporter_erp_id' => $contact?->erp_employee_id,
                'title' => $data['title'],
                'description' => $this->clean($data['description'] ?? null),
                'type' => $type,
                'scope' => $data['scope'],
                'priority' => $priority,
                'module' => $data['module'] ?? null,
                // A feature can't be worked on before an admin says yes. F15
                'status' => $type->needsApproval() ? TicketStatus::PendingApproval : TicketStatus::New,
                'approval_status' => $type->needsApproval() ? 'pending' : 'not_required',
                'created_by' => $createdBy,
                // Automatic — never typed in by a human. F03
                'reported_at' => $reportedAt,
                'sla_due_at' => $this->sla->dueAt($priority, $reportedAt),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data): Ticket
    {
        $priority = \App\Enums\Priority::from($data['priority']);

        $attributes = [
            'title' => $data['title'],
            'description' => $this->clean($data['description'] ?? null),
            'type' => $data['type'],
            'scope' => $data['scope'],
            'priority' => $priority,
            'module' => $data['module'] ?? null,
        ];

        // Re-prioritising moves the deadline; reported_at never changes.
        if ($ticket->priority !== $priority) {
            $attributes['sla_due_at'] = $this->sla->dueAt(
                $priority,
                CarbonImmutable::parse($ticket->reported_at)
            );
        }

        $ticket->update($attributes);

        return $ticket;
    }

    public function addComment(Ticket $ticket, string $body, int $userId, bool $isInternal): TicketComment
    {
        return DB::transaction(function () use ($ticket, $body, $userId, $isInternal) {
            $comment = $ticket->comments()->create([
                'user_id' => $userId,
                'body' => $this->clean($body),
                'is_internal' => $isInternal,
            ]);

            // First reply from anyone other than the reporter starts the clock. F05
            if ($ticket->first_response_at === null && $ticket->created_by !== $userId) {
                $ticket->forceFill(['first_response_at' => now()])->save();
            }

            return $comment;
        });
    }

    /**
     * Every piece of editor HTML passes through here before it is stored — on
     * the server, never in the browser (F04.1, CLAUDE.md § 5).
     */
    private function clean(?string $html): ?string
    {
        if ($html === null || trim(strip_tags($html)) === '' && ! str_contains($html, '<img')) {
            return null;
        }

        return Purifier::clean($html);
    }
}
