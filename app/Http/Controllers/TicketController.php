<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\GithubRepository;
use App\Models\Label;
use App\Models\PriorityDefinition;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketLink;
use App\Models\TicketStatusDefinition;
use App\Models\TicketSubtask;
use App\Models\TicketTypeDefinition;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AttachmentService;
use App\Services\BranchNamingService;
use App\Services\DiscordNotificationService;
use App\Services\SubtaskService;
use App\Services\TicketService;
use App\Services\TicketWorkflowService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TicketController extends Controller
{
    /** Memos for activeUsers() / companyContacts(). Per-request, never cached across pages. */
    private ?EloquentCollection $activeUsers = null;

    private ?EloquentCollection $companyContacts = null;

    public function __construct(
        private readonly TicketService $tickets,
        private readonly AttachmentService $attachments,
        private readonly TicketWorkflowService $workflow,
        private readonly SubtaskService $subtasks,
        private readonly DiscordNotificationService $discord,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->only('q', 'status', 'type', 'priority', 'company', 'assignee', 'relation', 'from', 'to', 'branch');

        $tickets = Ticket::query()
            // Never select description here: it's LONGTEXT and this page shows
            // 25 rows of it that nobody reads (CLAUDE.md § 4.3).
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority',
                'status', 'reported_at', 'sla_due_at', 'resolved_at', 'updated_at', 'created_by',
                'subtasks_total', 'subtasks_done',
                // F27 — read by the "ملهاش برانش" marker below. A column, not
                // a subquery: 25 rows on a screen with a 300ms budget.
                'branches_count',
            ])
            // Role-based assignment (2026-07-24): the assignee avatars come from
            // the ticket's role assignments, not the four dropped columns.
            ->with(['company:id,name,code', 'requester:id,name', 'creator:id,name', 'roleAssignments.user:id,name,avatar_path,is_active', 'labels:id,name,color'])
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
            'selectedAssignee' => filled($filters['assignee'] ?? null)
                ? User::whereKey($filters['assignee'])->value('name')
                : null,
            // The "تذاكري" shortcut is only meaningful to someone whose list
            // holds other people's tickets in the first place. For a
            // view.assigned-only user every row is already theirs.
            'canSeeOthers' => $request->user()->hasPermission('tickets.view.all'),
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

        if ($failed = $this->storeAttachments($request, $ticket)) {
            return $failed;
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
        //
        // ★ (2026-08-02) Each row arrives carrying the role it belongs to, and
        // that role's person becomes its assignee. A subtask with no assignee
        // earns its owner nothing, forever (PointEngineService filters on
        // assignee_id) — TK-2026-00169 lost 7 subtasks' worth that way, so the
        // create form no longer has a path that produces one.
        //
        // Read from the request, not from ticket_role_assignments: on a
        // feature the distribution is still only a plan at this point and
        // nothing is written to that table until approval.
        $owners = $this->roleAssignmentsFromRequest($request);

        // ★ (2026-08-05) The create form's rows carry a due date too, and a due
        // date is now what decides whether its owner earns or loses the
        // subtask's points (PointEngineService::isLate). Same silent drop as
        // SubtaskController: the third write path can't be the open one.
        $maySchedule = $request->user()->can('schedule', TicketSubtask::class);

        foreach ($request->validated('subtasks') ?? [] as $row) {
            $roleId = $row['role_id'] ?? null;

            if (! $maySchedule) {
                unset($row['due_date']);
            }

            $this->subtasks->create($ticket, $row + [
                'status' => 'todo',
                'assignee_id' => $roleId === null ? null : ($owners[$roleId] ?? null),
            ], $request->user()->id);
        }

        $this->assignAtCreation($ticket, $request);

        // After the distribution, so the announcement names whoever it landed on.
        // A ticket that needs approving is silent here — it has not become real
        // work yet — and gets announced by approve() instead.
        $this->discord->announceCreated($ticket->refresh());

        return redirect()->route('tickets.show', $ticket)
            ->with('status', "تم فتح التذكرة {$ticket->ticket_number}.");
    }

    /**
     * Stores whatever the uploader posted and repoints the description's inline
     * images at the rows that now exist.
     *
     * ★ (2026-08-04) Shared by store() and update(). It used to live inline in
     * store() only, which is half of why editing a ticket could not save a
     * pasted picture: even once the edit form had an uploader, update() had no
     * code that looked at the files or at the placeholders in the text.
     *
     * The order matters and is the reverse of what it looks like it should be.
     * TicketService has already run Purifier over the description by the time we
     * get here, so this rewrites clean HTML — and it must, because the
     * placeholder has to survive the whitelist to still be there to rewrite.
     *
     * @return RedirectResponse|null a redirect when an upload was rejected, null
     *                               on success — the ticket is already saved
     *                               either way, so a bad file reports itself
     *                               rather than discarding the whole edit.
     */
    private function storeAttachments(Request $request, Ticket $ticket): ?RedirectResponse
    {
        if (! $request->hasFile('attachments')) {
            return null;
        }

        try {
            $saved = $this->attachments->attachMany($ticket, $request->file('attachments'), $request->user()->id);

            // An image pasted into the description was uploaded as an
            // attachment but is still pointing at the editor's placeholder.
            // Now that the rows exist, point it at the real file.
            $ticket->forceFill([
                'description' => $this->attachments->resolveInlineImages(
                    $ticket->description,
                    $saved,
                    $request->input('attachment_tokens', [])
                ),
            ])->saveQuietly();
        } catch (RuntimeException $e) {
            return redirect()->route('tickets.show', $ticket)
                ->withErrors(['attachments' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * The distribution the user picked on the create page, as role_id => user_id,
     * with the "— مفيش —" rows dropped. Used twice: to own the hand-written
     * subtasks, and to actually assign the ticket.
     *
     * @return array<int, int> role_id => user_id
     */
    private function roleAssignmentsFromRequest(StoreTicketRequest $request): array
    {
        if (! $request->user()->hasPermission('tickets.assign')) {
            return [];
        }

        return array_filter($request->input('role_assignments', []), fn ($v) => filled($v));
    }

    /**
     * The create-page distribution block. A normal ticket is assigned live and
     * seeds a starter subtask per role (F06.3). A feature/module ticket starts
     * pending_approval, so its distribution is SAVED as a plan (planAssignments,
     * F15) and activated the moment it's approved — the choice is no longer lost.
     *
     * ★ (2026-08-02) The hand-written plan no longer suppresses the starters.
     * assignRoles() skips a starter only for a role that already HAS a subtask,
     * so a role the creator didn't plan for still gets one — see the note there.
     */
    private function assignAtCreation(Ticket $ticket, StoreTicketRequest $request): void
    {
        $roleAssignments = $this->roleAssignmentsFromRequest($request);

        if ($roleAssignments === []) {
            return;
        }

        if ($ticket->type->needsApproval()) {
            $this->workflow->planAssignments($ticket, $roleAssignments, $request->user()->id);

            return;
        }

        $this->workflow->assign($ticket, $roleAssignments, $request->user()->id, activation: true);
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
        // Loaded before authorize(): TicketPolicy::isAssigned() reads
        // roleAssignments (F06 role-assignment extension), and it only
        // consults the relation when it's already eager-loaded.
        $ticket->load([
            'company:id,name,code',
            'workLogs',
            'statusHistory',
            'subtasks',
            'labels',
            'watchers:id',
            'roleAssignments',
        ]);

        $this->authorize('view', $ticket);

        /*
         * ★ (2026-08-29) F27 — the code panel, loaded only for somebody who can
         * see it. Two queries, and both are skipped entirely for a role without
         * github.view rather than fetched and thrown away.
         *
         * The repository each row belongs to is NOT eager-loaded: there are
         * four of them, they are cached forever, and TicketBranch::repo() reads
         * them from there (see that method for why).
         */
        if (auth()->user()->hasPermission('github.view')) {
            $ticket->load([
                'branches' => fn ($q) => $q->orderBy('github_repository_id')->orderByDesc('id'),
                'pullRequests' => fn ($q) => $q->orderByDesc('number'),
            ]);
        }

        // role names for work logs, subtasks and assignments — one cached
        // lookup instead of three eager loads on a dozen-row table.
        $this->hydrateRoles($ticket);

        // The reporter, from the same read the recipient dropdown uses.
        $ticket->setRelation('contact', $this->companyContacts($ticket)->get($ticket->contact_id));

        $comments = $ticket->comments()
            ->unless(auth()->user()->hasPermission('comments.internal'), fn ($q) => $q->where('is_internal', false))
            ->orderBy('created_at')
            ->get();

        $this->hydrateAttachments($ticket, $comments);
        $this->hydrateLinks($ticket);
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
            // F27. The manual-link picker's options, from the cached map — no
            // query. Empty array for anyone who cannot link, so the panel does
            // not render a form nobody may submit.
            'githubRepositories' => auth()->user()->hasPermission('github.audit')
                ? GithubRepository::activeList()
                : [],
            // The name the convention says this ticket's branch should have.
            // Computed here rather than in the view: a Blade file does not hold
            // business rules, and the naming convention is one (CLAUDE.md § 3).
            'suggestedBranch' => app(BranchNamingService::class)
                ->suggest($ticket->ticket_number, $ticket->title),
            // F17: only fetched for someone allowed to see or give them — and
            // ★ (2026-08-04) only for a ticket that can actually show them.
            // show.blade.php renders the panel for resolved/closed only, so on
            // every open ticket this was a query whose result went nowhere.
            'ratings' => in_array($ticket->status->value, ['resolved', 'closed'], true)
                && (auth()->user()->hasPermission('ratings.give')
                    || auth()->user()->hasPermission('ratings.view.all'))
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
        $before = $ticket->only('title', 'type', 'priority', 'module');

        $this->tickets->update($ticket, $request->validated());

        // Same pass as store(): files first, then the description's placeholders
        // get pointed at them. Without this an image pasted while editing was
        // uploaded and then dropped out of the text by the purifier.
        if ($failed = $this->storeAttachments($request, $ticket)) {
            return $failed;
        }

        $logger->log(
            action: 'ticket.updated',
            userId: $request->user()->id,
            subject: $ticket,
            changes: ['from' => $before, 'to' => $ticket->only('title', 'type', 'priority', 'module')],
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
            'types' => TicketTypeDefinition::options(),
            'priorities' => PriorityDefinition::map(),
            // F06 role-assignment extension: the create form's inline subtask
            // repeater offers the same optional "الرول" select.
            'assignableRoles' => Role::assignableList(),
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
    /**
     * The role behind every work log, subtask and assignment, from cache.
     *
     * ★ (2026-08-04) These were three eager loads — workLogs.role,
     * subtasks.role, roleAssignments.role — each a query against `roles`, a
     * table with under a dozen rows that an admin edits maybe twice a year.
     * Role::byId() is cached forever and busted on write like every other
     * reference list in this app (§ 4.7), so all three now cost nothing.
     */
    /**
     * Every contact of this ticket's company, keyed by id, read once.
     *
     * ★ (2026-08-04) The page asked this table up to three separate times: the
     * ticket's own reporter by id, the recipient dropdown's active list for the
     * company, and whoever a status change or a portal reply named. Same
     * company, overlapping rows.
     *
     * Not filtered to active here — the ticket's own reporter may have been
     * deactivated since, and the reporter's name still has to appear on the
     * ticket they opened. The dropdown does its own filtering below; a facts
     * row must not lose a name because someone left the customer.
     *
     * An internal ticket has no company, so it costs nothing at all.
     *
     * @return EloquentCollection<int, CompanyContact>
     */
    private function companyContacts(Ticket $ticket): EloquentCollection
    {
        if ($this->companyContacts !== null) {
            return $this->companyContacts;
        }

        if ($ticket->company_id === null) {
            return $this->companyContacts = new EloquentCollection();
        }

        return $this->companyContacts = CompanyContact::query()
            ->where('company_id', $ticket->company_id)
            ->get(['id', 'name', 'erp_employee_id', 'email', 'phone', 'is_active'])
            ->keyBy('id');
    }

    private function hydrateRoles(Ticket $ticket): void
    {
        $roles = Role::byId();

        $ticket->workLogs->each(fn ($log) => $log->setRelation('role', $roles->get($log->role_id)));
        $ticket->subtasks->each(fn ($s) => $s->setRelation('role', $roles->get($s->role_id)));
        $ticket->roleAssignments->each(fn ($a) => $a->setRelation('role', $roles->get($a->role_id)));
    }

    /**
     * Every file on the ticket in one read, then handed to the relations that
     * would each have fetched their own slice.
     *
     * ★ (2026-08-04) bodyAttachments (comment_id IS NULL) and the comments'
     * own attachments (comment_id IN …) are two queries against one table for
     * one ticket. Same table, same ticket_id, disjoint halves.
     *
     * Scoped to the comments actually being rendered rather than "everything on
     * the ticket": without that, a viewer without comments.internal would pull
     * the internal comments' files into memory. They were never rendered, but
     * not reading them is the version that stays true when someone later loops
     * over the wrong collection.
     */
    private function hydrateAttachments(Ticket $ticket, $comments): void
    {
        $visible = $comments->pluck('id');

        $files = $ticket->attachments()
            ->where(fn ($q) => $q->whereNull('comment_id')->orWhereIn('comment_id', $visible))
            ->get();

        $ticket->setRelation('bodyAttachments', $files->whereNull('comment_id')->values());

        $byComment = $files->whereNotNull('comment_id')->groupBy('comment_id');

        $comments->each(fn ($c) => $c->setRelation(
            'attachments',
            $byComment->get($c->id) ?? new EloquentCollection()
        ));
    }

    /**
     * Both link directions, and the tickets on the other end, in two reads.
     *
     * ★ (2026-08-04) This was four: a query per direction, then a query per
     * direction again for the related ticket rows. The columns wanted from the
     * far side are identical either way, so direction is a property of the link
     * row, not a reason to ask the database twice. F10
     *
     * Each link gets both sides set — including the one that resolves to null,
     * because it points back at this ticket. A relation left unset would be a
     * lazy load, and preventLazyLoading turns that into an exception.
     */
    private function hydrateLinks(Ticket $ticket): void
    {
        $links = TicketLink::query()
            ->where('from_ticket_id', $ticket->id)
            ->orWhere('to_ticket_id', $ticket->id)
            ->get();

        $otherIds = $links->pluck('from_ticket_id')
            ->merge($links->pluck('to_ticket_id'))
            ->reject(fn ($id) => (int) $id === (int) $ticket->id)
            ->unique();

        $others = $otherIds->isEmpty()
            ? new EloquentCollection()
            : Ticket::whereIn('id', $otherIds)
                ->get(['id', 'ticket_number', 'title', 'status', 'priority'])
                ->keyBy('id');

        $links->each(function (TicketLink $link) use ($others) {
            $link->setRelation('toTicket', $others->get($link->to_ticket_id));
            $link->setRelation('fromTicket', $others->get($link->from_ticket_id));
        });

        $ticket->setRelation('outgoingLinks', $links->where('from_ticket_id', $ticket->id)->values());
        $ticket->setRelation('incomingLinks', $links->where('to_ticket_id', $ticket->id)->values());
    }

    private function hydratePeople(Ticket $ticket, $comments): void
    {
        $ids = collect([$ticket->created_by])
            ->merge($ticket->subtasks->pluck('assignee_id'))
            ->merge($ticket->statusHistory->pluck('user_id'))
            ->merge($ticket->statusHistory->pluck('recipient_user_id'))
            ->merge($comments->pluck('user_id'))
            // ★ (2026-08-04) The assignees used to arrive via their own
            // roleAssignments.user eager load — a second query over the same
            // table, for people this one was already fetching.
            ->merge($ticket->roleAssignments->pluck('user_id'))
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

        // Anyone named here is a contact of this ticket's own company, so the
        // shared read already has them. The fallback covers the one case it
        // cannot: a company reassigned under a ticket that had already been
        // handed to one of the old company's people.
        $known = $this->companyContacts($ticket);
        $missing = $contactIds->reject(fn ($id) => $known->has($id));

        $contacts = $missing->isEmpty()
            ? $known
            : $known->merge(
                CompanyContact::whereIn('id', $missing)->get(['id', 'name'])->keyBy('id')
            );

        $ticket->setRelation('creator', $people->get($ticket->created_by));

        $ticket->roleAssignments->each(fn ($a) => $a->setRelation('user', $people->get($a->user_id)));
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
            'recipientTeam' => $this->activeUsers(),
            // Filtered in php off the shared read: naming a deactivated contact
            // as the recipient of a status change is not offered, even though
            // the same list still has to be able to print one who already was.
            'recipientContacts' => $this->companyContacts($ticket)
                ->where('is_active', true)
                ->sortBy('name')
                ->values(),
        ];
    }

    /**
     * Every active user, read once per request.
     *
     * ★ (2026-08-04) Three separate panels on the ticket page each wanted "the
     * active users" and each went and got them: the subtask assignee dropdown
     * (select *, so every column including the password hash), the status-change
     * recipient dropdown (id, name), and the assignment dropdowns (id, name,
     * role_id). Same rows, same order, up to three round trips — and the widest
     * of them broke § 4.3 for a list that only ever renders a name.
     *
     * One query with the union of the columns anyone actually uses. role_id is
     * in there for assignableUsers(), which partitions this list by role rather
     * than asking the database to do it again.
     *
     * without('role'): User eager-loads its role for the chrome, and that join
     * would be a query spent to learn something role_id already says.
     *
     * @return EloquentCollection<int, User>
     */
    private function activeUsers(): EloquentCollection
    {
        return $this->activeUsers ??= User::active()
            ->without('role')
            ->select(['id', 'name', 'role_id'])
            ->orderBy('name')
            ->get();
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
                'assignableRoles' => collect(),
                'labels' => $canLabel ? Label::pickerList() : collect(),
            ];
        }

        return [
            'assignable' => $canAssign ? $this->assignableUsers() : null,
            // F06 role-assignment extension: the subtask form's optional
            // "الرول" select — cached, so every row in the loop reuses the
            // same collection instead of a query each (CLAUDE.md § 4).
            'assignableRoles' => $canPlan ? Role::assignableList() : collect(),
            // A subtask may go to anyone — F08 puts no skills constraint on it.
            'assignableAll' => $canPlan ? $this->activeUsers() : collect(),
            'labels' => $canLabel ? Label::pickerList() : collect(),
        ];
    }

    /**
     * The assignment dropdowns (F06), one per role an admin opted into the
     * distribution panel (Role::assignable_on_tickets). Fully role-based since
     * the four fixed columns were dropped (2026-07-24): a role's candidates are
     * the active users who hold that role, and there is no longer a separate
     * skills-driven frontend/backend list — assignment follows the role.
     *
     * @return array{roles: \Illuminate\Support\Collection}
     */
    private function assignableUsers(): array
    {
        $users = $this->activeUsers();
        // The cached copy of exactly this query — it already exists for the
        // subtask form, and there is no reason this one paid for its own.
        $roles = Role::assignableList();

        return [
            'roles' => $roles->map(fn (Role $role) => [
                'role' => $role,
                'candidates' => $users->filter(fn (User $u) => $u->role_id === $role->id)->values(),
            ]),
        ];
    }
}
