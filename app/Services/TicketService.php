<?php

namespace App\Services;

use App\Casts\PriorityValue;
use App\Casts\TicketStatusValue;
use App\Casts\TicketTypeValue;
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
            $type = TicketTypeValue::for($data['type']);
            $priority = PriorityValue::for($data['priority']);

            // F25: internal work has no customer — the origin is a colleague.
            $internal = blank($data['company_id'] ?? null);

            $ticket = Ticket::create([
                'ticket_number' => $this->numbers->next((int) $reportedAt->format('Y')),
                'company_id' => $data['company_id'] ?? null,
                'requested_by' => $internal ? ($data['requested_by'] ?? null) : null,
                'contact_id' => $contact?->id,
                // Frozen now. Editing the contact later must not rewrite history. F03
                'reporter_name' => $contact?->name ?? ($data['reporter_name'] ?? null),
                'reporter_erp_id' => $contact?->erp_employee_id,
                'title' => $data['title'],
                'description' => $this->clean($data['description'] ?? null),
                'type' => $type,
                'priority' => $priority,
                'module' => $data['module'] ?? null,
                // A feature can't be worked on before an admin says yes. F15
                'status' => $type->needsApproval() ? TicketStatusValue::for('pending_approval') : TicketStatusValue::for('new'),
                'approval_status' => $type->needsApproval() ? 'pending' : 'not_required',
                'created_by' => $createdBy,
                // Automatic — never typed in by a human. F03
                'reported_at' => $reportedAt,
                // An internal ticket is a promise to the team lead who asked
                // for it, and it runs on the same clock as everything else.
                'sla_due_at' => $this->sla->dueAt($priority, $reportedAt),
            ]);

            // F24: every ticket gets a portal password from the start — staff
            // relay it to whoever needs the link.
            $ticket->generatePortalPassword();

            return $ticket;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data): Ticket
    {
        $priority = PriorityValue::for($data['priority']);

        $attributes = [
            'title' => $data['title'],
            'description' => $this->clean($data['description'] ?? null),
            'type' => $data['type'],
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
     * F24: a client-portal comment. Always public (a client can't write an
     * internal note) and always attributed to the ticket's own contact —
     * there's no session user to attribute it to.
     */
    public function addPortalComment(Ticket $ticket, string $body): TicketComment
    {
        // Not staff replying, so first_response_at (F05's "time to first
        // reply" clock) never starts here — only the client's own message.
        return $ticket->comments()->create([
            'contact_id' => $ticket->contact_id,
            'body' => $this->clean($body),
            'is_internal' => false,
        ]);
    }

    /**
     * F03 — a soft delete, so the ticket number is never reused
     * (TicketNumberService already counts trashed rows).
     */
    public function delete(Ticket $ticket): void
    {
        DB::transaction(function () use ($ticket) {
            // ticket_subtasks' FK is cascadeOnDelete — a database-level rule
            // that never fires on a soft delete. Without this line the ticket
            // disappears from every list while its subtasks stay live on the
            // calendar, which queries ticket_subtasks in its own right.
            $ticket->subtasks()->delete();
            $ticket->delete();
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
