<?php

namespace App\Http\Controllers;

use App\Enums\TicketScope;
use App\Enums\TicketType;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Label;
use App\Models\PriorityDefinition;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AttachmentService;
use App\Services\SubtaskService;
use App\Services\TicketService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly AttachmentService $attachments,
        private readonly TicketWorkflowService $workflow,
        private readonly SubtaskService $subtasks,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->only('q', 'status', 'type', 'scope', 'priority', 'company', 'assignee', 'from', 'to');

        $tickets = Ticket::query()
            // Never select description here: it's LONGTEXT and this page shows
            // 25 rows of it that nobody reads (CLAUDE.md § 4.3).
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'scope', 'priority',
                'status', 'reported_at', 'sla_due_at', 'resolved_at', 'updated_at',
                'assigned_frontend_id', 'assigned_backend_id', 'tester_id', 'created_by',
                'subtasks_total', 'subtasks_done',
            ])
            ->with(['company:id,name,code', 'requester:id,name', 'frontend:id,name,avatar_path,is_active', 'backend:id,name,avatar_path,is_active'])
            ->visibleTo($request->user())
            ->filter($filters)
            ->defaultOrder()
            ->paginate(25)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            // Just the one that is selected, so the box can show its name.
            // The rest arrive from /lookup as the user types.
            'selectedCompany' => filled($filters['company'] ?? null)
                ? Company::whereKey($filters['company'])->value('name')
                : null,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Ticket::class);

        return view('tickets.create', $this->formData() + [
            // The same distribution block as the ticket page — assigning
            // right away means the starter subtask (F06.3) exists from the
            // first moment, never a ticket sitting unassigned by omission.
            'assignable' => auth()->user()->hasPermission('tickets.assign') ? $this->assignableUsers() : null,
        ]);
    }

    public function store(StoreTicketRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $ticket = $this->tickets->create($request->validated(), $request->user()->id);

        if ($request->hasFile('attachments')) {
            try {
                $this->attachments->attachMany($ticket, $request->file('attachments'), $request->user()->id);
            } catch (RuntimeException $e) {
                // The ticket itself is already saved; say what didn't make it
                // rather than throwing the whole thing away.
                return redirect()->route('tickets.show', $ticket)
                    ->withErrors(['attachments' => $e->getMessage()]);
            }
        }

        $logger->log(
            action: 'ticket.created',
            userId: $request->user()->id,
            subject: $ticket,
            changes: ['to' => $ticket->only('ticket_number', 'title', 'type', 'priority', 'status')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        // Before assignAtCreation, not after: assign() seeds a starter subtask
        // for any side that has none, and it must be able to see the plan the
        // user typed here or it would add a duplicate on top of it.
        foreach ($request->validated('subtasks') ?? [] as $row) {
            $this->subtasks->create($ticket, $row + ['status' => 'todo'], $request->user()->id);
        }

        $this->assignAtCreation($ticket, $request);

        return redirect()->route('tickets.show', $ticket)
            ->with('status', "تم فتح التذكرة {$ticket->ticket_number}.");
    }

    /**
     * The create-page distribution block reuses TicketWorkflowService::assign()
     * as-is — same starter-subtask behaviour as assigning from the ticket page
     * (F06.3), no separate code path. A feature/module ticket starts
     * pending_approval and assign() refuses it before approval (F15); the
     * fields are simply ignored for those until someone approves and assigns
     * from the ticket page instead.
     */
    private function assignAtCreation(Ticket $ticket, StoreTicketRequest $request): void
    {
        if (! auth()->user()->hasPermission('tickets.assign') || $ticket->type->needsApproval()) {
            return;
        }

        $assignees = $request->only('assigned_frontend_id', 'assigned_backend_id', 'tester_id');

        if (collect($assignees)->filter()->isEmpty()) {
            return;
        }

        $this->workflow->assign($ticket, $assignees, $request->user()->id);
    }

    /**
     * The ticket detail page is where every layer of the system converges:
     * comments, subtasks, time, links, labels, watchers, ratings, assignment
     * and history all render on one screen. That is ~18 queries for a viewer and
     * ~21 for an admin (who also gets the assignment/label panels) — above § 4's
     * 15-query guideline, and deliberately so. Every one is a single load per
     * relation, not an N+1 (preventLazyLoading would throw on an N+1), and the
     * lot runs in ~36ms. Splitting this into lazy-loaded tabs to hit 15 would
     * trade one fast request for several round-trips; the number is worse read
     * as one page. This is the one screen exempt from the guideline, on purpose.
     */
    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'company:id,name,code',
            'contact:id,name,erp_employee_id,email,phone',
            // Neither bodyAttachments.uploader nor workLogs.user is rendered —
            // eager-loading them was two queries paid for nothing.
            'bodyAttachments',
            'workLogs',
            'statusHistory',
            'subtasks',
            'labels',
            'watchers:id',
            // Both directions in one query each; the related-ticket columns are
            // shared, so a single constrained load covers the pair (F10).
            'outgoingLinks.toTicket:id,ticket_number,title,status,priority',
            'incomingLinks.fromTicket:id,ticket_number,title,status,priority',
        ]);

        $comments = $ticket->comments()
            ->with('attachments')
            ->unless(auth()->user()->hasPermission('comments.internal'), fn ($q) => $q->where('is_internal', false))
            ->orderBy('created_at')
            ->get();

        $this->hydratePeople($ticket, $comments);

        return view('tickets.show', [
            'ticket' => $ticket,
            'comments' => $comments,
            // The dropdown lists every panel needs, fetched once and shared.
            // Separately they were four queries over the same users table.
            ...$this->panelData($ticket),
            ...$this->statusChangeData($ticket),
            // F09: this person's own entries. Someone else's hours aren't their
            // business, and the totals are already rolled up on the ticket.
            // No subtask eager-load: the titles come from the subtasks already
            // on the ticket.
            'myTimeEntries' => $ticket->timeEntries()
                ->where('user_id', auth()->id())
                ->orderByDesc('spent_on')
                ->limit(10)
                ->get(),
            'isWatching' => $ticket->watchers->contains('id', auth()->id()),
            // F17: only fetched for someone allowed to see or give them.
            'ratings' => auth()->user()->hasPermission('ratings.give')
                || auth()->user()->hasPermission('ratings.view.all')
                ? $ticket->ratings()->get()
                : collect(),
        ]);
    }

    public function edit(Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        return view('tickets.edit', $this->formData() + ['ticket' => $ticket]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        $before = $ticket->only('title', 'type', 'scope', 'priority', 'module');

        $this->tickets->update($ticket, $request->validated());

        $logger->log(
            action: 'ticket.updated',
            userId: $request->user()->id,
            subject: $ticket,
            changes: ['from' => $before, 'to' => $ticket->only('title', 'type', 'scope', 'priority', 'module')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('tickets.show', $ticket)->with('status', 'تم حفظ التعديلات.');
    }

    public function destroy(Request $request, Ticket $ticket, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('delete', $ticket);

        $number = $ticket->ticket_number;

        // Logged before the delete, so the row still reads as it was.
        $logger->log(
            action: 'ticket.deleted',
            userId: $request->user()->id,
            subject: $ticket,
            changes: ['from' => $ticket->only('ticket_number', 'title', 'status')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $this->tickets->delete($ticket);

        return redirect()->route('tickets.index')->with('status', "تم حذف التذكرة {$number}.");
    }

    /** F11 — labels on a ticket. */
    public function syncLabels(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'labels' => ['nullable', 'array'],
            'labels.*' => ['integer', 'exists:labels,id'],
        ], [], ['labels' => 'اللابلز']);

        $ticket->labels()->sync($data['labels'] ?? []);

        return back()->with('status', 'تم حفظ اللابلز.');
    }

    private function formData(): array
    {
        // Companies and contacts are NOT sent to the view any more. They come
        // from /lookup as the user types, so the page no longer carries the
        // whole customer table — two queries and a JSON blob that grew with
        // the database are now zero of both.
        return [
            'types' => TicketType::options(),
            'scopes' => TicketScope::options(),
            'priorities' => PriorityDefinition::map(),
        ];
    }

    /**
     * Fills every user relation on the page from a single query.
     *
     * The creator, the two developers, the tester, each subtask's assignee, each
     * history entry's author and each comment's author are all the same handful
     * of colleagues. Eager-loading them relation by relation asked the users
     * table for those same rows nine separate times. This asks once and hands
     * the results out.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\TicketComment>  $comments
     */
    private function hydratePeople(Ticket $ticket, $comments): void
    {
        $ids = collect([
            $ticket->created_by,
            $ticket->assigned_frontend_id,
            $ticket->assigned_backend_id,
            $ticket->tester_id,
        ])
            ->merge($ticket->subtasks->pluck('assignee_id'))
            ->merge($ticket->statusHistory->pluck('user_id'))
            ->merge($ticket->statusHistory->pluck('recipient_user_id'))
            ->merge($comments->pluck('user_id'))
            ->filter()
            ->unique();

        $people = $ids->isEmpty()
            ? collect()
            : User::without('role')
                ->whereIn('id', $ids)
                ->get(['id', 'name', 'avatar_path', 'is_active'])
                ->keyBy('id');

        $contactIds = $ticket->statusHistory->pluck('recipient_contact_id')
            ->merge($comments->pluck('contact_id'))
            ->filter()
            ->unique();

        $contacts = $contactIds->isEmpty()
            ? collect()
            : CompanyContact::whereIn('id', $contactIds)->get(['id', 'name'])->keyBy('id');

        $ticket->setRelation('creator', $people->get($ticket->created_by));
        $ticket->setRelation('frontend', $people->get($ticket->assigned_frontend_id));
        $ticket->setRelation('backend', $people->get($ticket->assigned_backend_id));
        $ticket->setRelation('tester', $people->get($ticket->tester_id));

        $ticket->subtasks->each(fn ($s) => $s->setRelation('assignee', $people->get($s->assignee_id)));
        $ticket->statusHistory->each(function ($h) use ($people, $contacts) {
            $h->setRelation('user', $people->get($h->user_id));
            $h->setRelation('recipientUser', $people->get($h->recipient_user_id));
            $h->setRelation('recipientContact', $contacts->get($h->recipient_contact_id));
        });
        $comments->each(function ($c) use ($people, $contacts) {
            $c->setRelation('user', $people->get($c->user_id));
            $c->setRelation('contact', $contacts->get($c->contact_id));
        });
    }

    /**
     * The manual "غيّر الحالة" panel's own data (F06): the statuses this ticket
     * may actually move to from here, and who could be named as the recipient
     * — a teammate or one of the ticket's own company's contacts.
     *
     * @return array<string, mixed>
     */
    private function statusChangeData(Ticket $ticket): array
    {
        if (! auth()->user()->can('changeStatus', $ticket)) {
            return ['nextStatuses' => collect(), 'recipientTeam' => collect(), 'recipientContacts' => collect()];
        }

        $statuses = TicketStatusDefinition::map();
        $allowed = TicketStatusDefinition::transitionMap()[$ticket->status->value] ?? [];

        // "جاري العمل" and "تم التطوير" are computed from ticket_work_logs and
        // belong to the بدأت / خلصت buttons. Offering them here let the badge
        // and the work logs drift apart — the ticket would claim to be in
        // progress while every side still said pending.
        $allowed = array_diff($allowed, TicketWorkflowService::COMPUTED_STATUSES);

        return [
            'nextStatuses' => collect($allowed)->map(fn ($key) => $statuses[$key])->filter(),
            'recipientTeam' => User::active()->orderBy('name')->get(['id', 'name']),
            'recipientContacts' => CompanyContact::where('company_id', $ticket->company_id)
                ->active()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * The lists the side panels need, fetched once.
     *
     * The assignment dropdowns and the subtask assignee dropdown all want active
     * users; separately that was four queries over one small table. This is one
     * query, partitioned in php, and each list is only built for someone who can
     * actually see that panel.
     *
     * @return array<string, mixed>
     */
    private function panelData(Ticket $ticket): array
    {
        $user = auth()->user();
        $canAssign = $user->can('assign', $ticket);
        $canPlan = $user->hasPermission('subtasks.manage');
        $canLabel = $user->can('update', $ticket);

        if (! $canAssign && ! $canPlan) {
            return [
                'assignable' => null,
                'assignableAll' => collect(),
                'labels' => $canLabel ? Label::orderBy('name')->get(['id', 'name', 'color']) : collect(),
            ];
        }

        return [
            'assignable' => $canAssign ? $this->assignableUsers() : null,
            // A subtask may go to anyone — F08 puts no skills constraint on it.
            'assignableAll' => $canPlan ? User::active()->without('role')->orderBy('name')->get() : collect(),
            'labels' => $canLabel ? Label::orderBy('name')->get(['id', 'name', 'color']) : collect(),
        ];
    }

    /**
     * The three assignment dropdowns' lists (F06), driven by skills rather
     * than role — a "both" developer belongs in both lists (F00.3). Shared by
     * the ticket page's distribution panel and the create-page block (F06.3).
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function assignableUsers(): array
    {
        // without('role'): User eager-loads its role for the chrome, but the
        // role id here comes from the cached map — that join would be a query
        // spent to learn something already in memory.
        $users = User::active()
            ->without('role')
            ->select(['id', 'name', 'skills', 'role_id'])
            ->orderBy('name')
            ->get();

        $testerRoleId = Role::idByKey('tester');

        return [
            'frontend' => $users->filter(fn (User $u) => $u->skills->coversFrontend())->values(),
            'backend' => $users->filter(fn (User $u) => $u->skills->coversBackend())->values(),
            'testers' => $users->filter(fn (User $u) => $u->role_id === $testerRoleId)->values(),
        ];
    }
}
