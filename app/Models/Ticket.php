<?php

namespace App\Models;

use App\Casts\PriorityCast;
use App\Casts\TicketStatusCast;
use App\Casts\TicketTypeCast;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number', 'company_id', 'requested_by', 'contact_id', 'reporter_name', 'reporter_erp_id',
        'title', 'description', 'type', 'priority', 'status', 'module',
        // How to reach the problem: the client's login code and the page it happens on.
        'client_user_code', 'page_url',
        // F26 — set only on a ticket the error reporter opened. The fingerprint
        // is how a later report of the same error finds this ticket again.
        'exception_fingerprint', 'exception_count', 'exception_server',
        'exception_source_file', 'exception_source_line', 'exception_culprit_login',
        'exception_culprit_name', 'exception_culprit_id', 'exception_attribution_reason',
        'created_by',
        'approval_status', 'approved_by', 'approved_at',
        'reported_at', 'first_response_at', 'sla_due_at', 'resolved_at',
        'resolution_note', 'client_notified_at', 'client_notified_by', 'closed_at',
        'points_awarded_at', 'start_date', 'due_date', 'original_estimate_hours',
    ];

    protected function casts(): array
    {
        return [
            'type' => TicketTypeCast::class,
            'priority' => PriorityCast::class,
            'status' => TicketStatusCast::class,
            'reported_at' => 'datetime',
            'first_response_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'client_notified_at' => 'datetime',
            'closed_at' => 'datetime',
            'approved_at' => 'datetime',
            'points_awarded_at' => 'datetime',
            'start_date' => 'date',
            'due_date' => 'date',
            'original_estimate_hours' => 'decimal:2',
            'exception_count' => 'integer',
            'exception_source_line' => 'integer',
            'spent_hours' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'contact_id');
    }

    /** The colleague who raised an internal ticket. Null on a client ticket. */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** The system user attributed as the likely cause of an exception ticket. */
    public function exceptionCulprit(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exception_culprit_id');
    }

    /**
     * Internal work — raised by the team, not owed to a customer.
     *
     * Derived from the absence of a company rather than stored as a flag: one
     * fact in one column cannot disagree with itself.
     */
    public function isInternal(): bool
    {
        return $this->company_id === null;
    }

    /**
     * Who this ticket is for, as one line — a company name, or the colleague
     * who asked for it.
     *
     * Every screen that used to print $ticket->company->name goes through here
     * instead, so "what does a ticket with no company show?" is answered once.
     */
    public function originLabel(): string
    {
        return $this->isInternal()
            ? ($this->requester?->name ?? 'داخلية')
            : ($this->company?->name ?? '—');
    }

    /** Internal tickets, or client tickets. F25 */
    public function scopeInternal(Builder $query, bool $internal = true): Builder
    {
        return $internal ? $query->whereNull('company_id') : $query->whereNotNull('company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /** One per committed side. The decision layer. F07 */
    public function workLogs(): HasMany
    {
        return $this->hasMany(TicketWorkLog::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)->orderBy('created_at');
    }

    /** The planning layer. Optional — a ticket may have none. F08 */
    public function subtasks(): HasMany
    {
        return $this->hasMany(TicketSubtask::class)->orderBy('position');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** F17 */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /** F18 — the ledger rows this ticket paid. */
    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    /**
     * F06 — who holds which role on this ticket. Since the four fixed columns
     * were dropped (2026-07-24) this is the ONE place assignment lives; every
     * role, built-in or custom, is a row here.
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(TicketRoleAssignment::class);
    }

    /** Every Discord message this ticket has caused, sent or not. */
    public function discordMessages(): HasMany
    {
        return $this->hasMany(TicketDiscordMessage::class);
    }

    /**
     * The user assigned to this ticket under a given role, or null. Reads the
     * eager-loaded relation when it's there (the ticket page loads it), and
     * falls back to a scoped query otherwise — an explicit query, so
     * preventLazyLoading never trips.
     */
    public function assigneeIdForRole(int $roleId): ?int
    {
        if ($this->relationLoaded('roleAssignments')) {
            return $this->roleAssignments->firstWhere('role_id', $roleId)?->user_id;
        }

        return $this->roleAssignments()->where('role_id', $roleId)->value('user_id');
    }

    /**
     * Every user assigned to this ticket, in any role — the role-based
     * replacement for "the frontend/backend/tester/devops columns".
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function assigneeIds(): \Illuminate\Support\Collection
    {
        return $this->relationLoaded('roleAssignments')
            ? $this->roleAssignments->pluck('user_id')->filter()->unique()->values()
            : $this->roleAssignments()->distinct()->pluck('user_id');
    }

    /** True when this user holds any role on the ticket. Query-safe. */
    public function isAssignee(int $userId): bool
    {
        return $this->relationLoaded('roleAssignments')
            ? $this->roleAssignments->contains('user_id', $userId)
            : $this->roleAssignments()->where('user_id', $userId)->exists();
    }

    /** Tickets this user holds any role on (F03 visibility, reports, board). */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->whereHas('roleAssignments', fn (Builder $q) => $q->where('user_id', $userId));
    }

    /** The ways a person can be attached to a ticket, for the people filter. */
    public const RELATIONS = [
        'any' => 'أي علاقة',
        'assigned' => 'مسندة له',
        'created' => 'هو اللي فتحها',
        'subtask' => 'عنده صب تاسك فيها',
    ];

    /**
     * ★ (2026-08-02) "Tickets this person is involved in" — which is three
     * different questions, and the old filter only answered one of them.
     *
     * Holding a role, opening the ticket, and owning a subtask on it are
     * genuinely different attachments: a support agent opens tickets they never
     * work on, and a developer picks up subtasks on tickets assigned to someone
     * else. Filtering by "المسؤول" alone hid both of those people.
     *
     * 'any' is the default because it is what someone picking a name off a list
     * means: show me everything this person touches.
     */
    public function scopeInvolving(Builder $query, int $userId, ?string $relation = null): Builder
    {
        $relation = array_key_exists((string) $relation, self::RELATIONS) ? $relation : 'any';

        return match ($relation) {
            'assigned' => $query->assignedTo($userId),
            'created' => $query->where('created_by', $userId),
            'subtask' => $query->whereHas('subtasks', fn (Builder $q) => $q->where('assignee_id', $userId)),
            default => $query->where(fn (Builder $q) => $q
                ->whereHas('roleAssignments', fn (Builder $r) => $r->where('user_id', $userId))
                ->orWhere('created_by', $userId)
                ->orWhereHas('subtasks', fn (Builder $s) => $s->where('assignee_id', $userId))),
        };
    }

    public function labels(): BelongsToMany
    {
        // Table named explicitly: Laravel's convention would be label_ticket
        // (alphabetical), but PLAN.md § 4.5 specifies ticket_label.
        return $this->belongsToMany(Label::class, 'ticket_label');
    }

    public function watchers(): BelongsToMany
    {
        // false for updated_at: the pivot only has created_at (PLAN.md § 4.5),
        // and a plain withTimestamps() would select a column that isn't there.
        return $this->belongsToMany(User::class, 'ticket_watchers')
            ->withTimestamps(null, false);
    }

    /** Links this ticket declared. F10 */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(TicketLink::class, 'from_ticket_id');
    }

    /** Links other tickets declared about this one — the inverse direction. F10 */
    public function incomingLinks(): HasMany
    {
        return $this->hasMany(TicketLink::class, 'to_ticket_id');
    }

    /**
     * F27: the branches carrying this ticket's code — the evidence that the
     * work exists, as opposed to somebody's word that it does.
     *
     * Deliberately unordered by state: a merged-and-removed branch still counts
     * as proof, so nothing here filters state='deleted' out.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(TicketBranch::class);
    }

    /** F27. Matched through the pull request's head branch, not through a link. */
    public function pullRequests(): HasMany
    {
        return $this->hasMany(TicketPullRequest::class);
    }

    /**
     * F27: no code was ever found for this ticket.
     *
     * Reads the counter, never a subquery — this runs over the whole ticket
     * table on the audit screen and on every filtered list (CLAUDE.md § 4.6).
     */
    public function scopeWithoutBranch(Builder $query): Builder
    {
        return $query->where('branches_count', 0);
    }

    /**
     * F10: something else must land before this can. Shown as a marker in the
     * list and on the board.
     */
    public function isBlocked(): bool
    {
        if (! $this->relationLoaded('incomingLinks')) {
            return false;
        }

        return $this->incomingLinks
            ->filter(fn (TicketLink $link) => $link->type->isBlocks())
            ->contains(fn (TicketLink $link) => $link->fromTicket?->status->isOpen() ?? false);
    }

    /** F09: green within estimate, amber over, red past double. */
    public function estimateVariant(): string
    {
        $estimate = (float) ($this->original_estimate_hours ?? 0);

        if ($estimate == 0.0) {
            return 'low';
        }

        $ratio = (float) $this->spent_hours / $estimate;

        return match (true) {
            $ratio > 2.0 => 'urgent',
            $ratio > 1.0 => 'high',
            default => 'resolved',
        };
    }

    public function progressPercent(): int
    {
        if ($this->subtasks_total === 0) {
            return 0;
        }

        return (int) round($this->subtasks_done / $this->subtasks_total * 100);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /** Files attached to the ticket body rather than to a comment. F04.2 */
    public function bodyAttachments(): HasMany
    {
        return $this->attachments()->whereNull('comment_id');
    }

    /**
     * The one image a board card shows — the first picture attached to the
     * ticket itself.
     *
     * ofMany() rather than "load them all and take the first": it resolves to a
     * single subquery-joined row per ticket, so a 300-card board still costs one
     * query and returns 300 rows, not every image on every ticket.
     *
     * Comment attachments are excluded on purpose. A screenshot somebody pasted
     * halfway down a thread is a reply, not what the ticket is about; the cover
     * should be the picture the reporter opened with. thumbnail_name is only
     * ever written for images (AttachmentService), so it doubles as the
     * is-an-image test and guarantees there is something small to serve.
     */
    public function coverImage(): HasOne
    {
        return $this->hasOne(TicketAttachment::class)->ofMany(
            ['id' => 'min'],
            fn (Builder $query) => $query->whereNull('comment_id')->whereNotNull('thumbnail_name'),
        );
    }

    /**
     * F24: (re)generates the client portal password. Only the hash is ever
     * stored — the plaintext returned here is the only time it's readable;
     * staff must relay it to the client immediately or regenerate later.
     */
    public function generatePortalPassword(): string
    {
        $plaintext = Str::password(10, symbols: false);

        $this->forceFill(['portal_password_hash' => Hash::make($plaintext)])->saveQuietly();

        return $plaintext;
    }

    public function checkPortalPassword(string $password): bool
    {
        return $this->portal_password_hash !== null && Hash::check($password, $this->portal_password_hash);
    }

    /**
     * Age for open tickets, resolution time for closed ones — both as
     * "3 أيام و 4 ساعات" rather than a raw number. F03.1
     */
    public function ageLabel(): string
    {
        $end = $this->status->isOpen() ? now() : ($this->resolved_at ?? $this->updated_at);

        return $this->humanInterval($this->reported_at->diffAsCarbonInterval($end));
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->sla_due_at !== null
            && $this->sla_due_at->isPast();
    }

    /**
     * A resolved/closed ticket is frozen: the only move left on it is the one
     * that reopens it. `rejected` is deliberately excluded — it's also
     * `is_open = false`, but it was never worked, so there is nothing to
     * freeze.
     */
    public function isLocked(): bool
    {
        return in_array($this->status->value, ['resolved', 'closed'], true);
    }

    /**
     * One plain-text line of the description, for a list card that needs to say
     * what the ticket is about without opening it.
     *
     * Reads `description_excerpt` when the query selected a bounded slice
     * instead of the whole LONGTEXT column (§ 4.3), and falls back to the full
     * column on screens that already loaded it.
     */
    public function descriptionExcerpt(int $length = 180): string
    {
        $source = $this->description_excerpt ?? $this->attributes['description'] ?? '';

        // The stored HTML is already purified; this strips it back to prose so
        // a truncated tag can never reach the page.
        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($source))), $length);
    }

    private function humanInterval(CarbonInterval $interval): string
    {
        $days = (int) $interval->totalDays;
        $hours = $interval->hours;

        if ($days >= 1) {
            $d = $days === 1 ? 'يوم' : ($days === 2 ? 'يومين' : ($days <= 10 ? "{$days} أيام" : "{$days} يوم"));

            return $hours > 0 ? "{$d} و {$hours} ساعة" : $d;
        }

        if ($interval->totalHours >= 1) {
            $h = (int) $interval->totalHours;

            return $h === 1 ? 'ساعة' : ($h === 2 ? 'ساعتين' : ($h <= 10 ? "{$h} ساعات" : "{$h} ساعة"));
        }

        $m = max(1, (int) $interval->totalMinutes);

        return "{$m} دقيقة";
    }

    /**
     * What belongs on a board (F12): live work, plus the finished work of one
     * chosen month.
     *
     * The bound matters. Without it the closed column pulled every ticket ever
     * resolved — 4,268 rows at 5,000 tickets, and a 6.7s page. A board is a
     * picture of now, not an archive; the archive is /tickets.
     *
     * ★ (2026-09-08) The bound was «updated_at within 14 days», which answered
     * "touched lately" rather than "finished lately": one comment on a ticket
     * closed months ago dragged it back into the column, and there was no way
     * to ask for a specific month. It reads resolved_at now — the same basis
     * every report uses, and the only one whose meaning is fixed once the work
     * is done.
     *
     * Consequence, deliberately not papered over: a resolved/closed row whose
     * resolved_at is NULL (imported, or written straight to the column by a
     * seeder) belongs to no month and appears in none. whereBetween excludes
     * NULL on its own.
     *
     * @param  array{0: string, 1: string}  $resolvedBetween  from ReportService::periodBounds()
     */
    public function scopeOnBoard(Builder $query, array $resolvedBetween): Builder
    {
        return $query->where(function (Builder $q) use ($resolvedBetween) {
            $q->whereIn('status', ['assigned', 'reopened', 'in_progress', 'dev_done', 'testing'])
                ->orWhere(fn (Builder $w) => $w
                    ->whereIn('status', ['resolved', 'closed'])
                    ->whereBetween('resolved_at', $resolvedBetween));
        });
    }

    /**
     * Default order: highest-priority first (by the admin-defined position on
     * `priorities`, not a hardcoded list), then oldest first. F03.1
     */
    public function scopeDefaultOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('(SELECT position FROM priorities WHERE priorities.key = tickets.priority)')
            ->orderBy('reported_at');
    }

    /** The date columns a date-range filter may run against. */
    public const DATE_BASES = [
        'reported_at' => 'تاريخ الفتح',
        'resolved_at' => 'تاريخ الحل',
        'closed_at' => 'تاريخ الإغلاق',
        'due_date' => 'تاريخ التسليم',
        'sla_due_at' => 'مهلة الـ SLA',
        'updated_at' => 'آخر تحديث',
    ];

    /**
     * ★ (2026-10-05) Every query-string key the list filter reads, in one place.
     *
     * /tickets, its export and the ready-to-close queue all used to repeat the
     * list by hand, and a key added to one was silently missing from the next —
     * the export of a filtered screen then answered a different question than
     * the screen. Read this constant instead of retyping it.
     */
    public const FILTER_KEYS = [
        'q', 'status', 'type', 'priority', 'company', 'assignee', 'relation', 'culprit',
        'from', 'to', 'date_basis', 'branch', 'label', 'module', 'origin', 'approval',
        'creator', 'subtasks', 'late', 'sort',
    ];

    /** Where the ticket came from — a customer, or the team itself (F25). */
    public const ORIGINS = [
        'client' => 'من عميل',
        'internal' => 'داخلية',
    ];

    /** F15 — the approval column's four states, labelled for a filter. */
    public const APPROVALS = [
        'not_required' => 'مش محتاجة موافقة',
        'pending' => 'مستنية موافقة',
        'approved' => 'اتوافق عليها',
        'rejected' => 'اترفضت',
    ];

    /** The subtask counters, read as three states a human asks about (F08/F30). */
    public const SUBTASK_STATES = [
        'none' => 'من غير صب تاسكس',
        'open' => 'فيها صب تاسكس مفتوحة',
        'done' => 'كل صب تاسكاتها خلصت',
    ];

    /** Missed a deadline or not — see scopeLate() for the exact rule. */
    public const LATENESS = [
        'late' => 'المتأخرة بس',
        'on_time' => 'اللي في معادها بس',
    ];

    /** What missedDeadlines() can name, labelled for the row marker. */
    public const DEADLINE_LABELS = [
        'sla' => 'مهلة الـ SLA',
        'due' => 'تاريخ التسليم',
    ];

    /** The orders a list may be asked for. 'default' is scopeDefaultOrder(). */
    public const SORTS = [
        'default' => 'الأولوية ثم الأقدم',
        'newest' => 'الأحدث فتحاً',
        'oldest' => 'الأقدم فتحاً',
        'updated' => 'آخر تحديث',
        'due' => 'أقرب تسليم',
        'sla' => 'أقرب مهلة SLA',
        'resolved' => 'آخر ما اتحل',
    ];

    /**
     * The list filters (F03.1). Query logic, so it lives on the model rather
     * than in the controller (CLAUDE.md § 3).
     *
     * date_basis picks which column from/to run against; it defaults to
     * reported_at so /tickets — which never sends it — keeps behaving exactly
     * as it did before this was added.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $dateBasis = array_key_exists($filters['date_basis'] ?? null, self::DATE_BASES)
            ? $filters['date_basis']
            : 'reported_at';

        return $query
            // "open" and "resolved" are groupings a human thinks in; the rest
            // are the raw states.
            ->when(($filters['status'] ?? null) === 'open',
                fn (Builder $q) => $q->whereNotIn('status', ['resolved', 'closed', 'rejected']))
            ->when(($filters['status'] ?? null) === 'resolved',
                fn (Builder $q) => $q->whereIn('status', ['resolved', 'closed']))
            ->when(
                ($filters['status'] ?? null) && ! in_array($filters['status'], ['open', 'resolved'], true),
                fn (Builder $q) => $q->where('status', $filters['status'])
            )
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->where('type', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('priority', $v))
            ->when($filters['company'] ?? null, fn (Builder $q, $v) => $q->where('company_id', $v))
            ->when($filters['assignee'] ?? null,
                fn (Builder $q, $v) => $q->involving((int) $v, $filters['relation'] ?? null))
            ->when($filters['culprit'] ?? null,
                fn (Builder $q, $v) => $q->where('exception_culprit_id', (int) $v))
            // F27. Two states only, both from the counter column: "no code was
            // ever found" and "some was". Anything finer belongs on the ticket
            // page, where the branches themselves are listed.
            ->when(($filters['branch'] ?? null) === 'none', fn (Builder $q) => $q->where('branches_count', 0))
            ->when(($filters['branch'] ?? null) === 'has', fn (Builder $q) => $q->where('branches_count', '>', 0))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate($dateBasis, '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate($dateBasis, '<=', $v))
            // ★ (2026-10-05) The second row of the filter bar. Each one is a
            // question the list could not answer before without opening rows.
            ->when($filters['label'] ?? null,
                fn (Builder $q, $v) => $q->whereHas('labels', fn (Builder $l) => $l->where('labels.id', (int) $v)))
            ->when($filters['module'] ?? null, fn (Builder $q, $v) => $q->where('module', 'like', '%' . $v . '%'))
            ->when(($filters['origin'] ?? null) === 'internal', fn (Builder $q) => $q->internal())
            ->when(($filters['origin'] ?? null) === 'client', fn (Builder $q) => $q->internal(false))
            ->when(array_key_exists($filters['approval'] ?? '', self::APPROVALS),
                fn (Builder $q) => $q->where('approval_status', $filters['approval']))
            ->when($filters['creator'] ?? null, fn (Builder $q, $v) => $q->where('created_by', (int) $v))
            // Off the two counters SubtaskService maintains — never a COUNT()
            // over ticket_subtasks per row (§ 4.6).
            ->when(($filters['subtasks'] ?? null) === 'none', fn (Builder $q) => $q->where('subtasks_total', 0))
            ->when(($filters['subtasks'] ?? null) === 'open',
                fn (Builder $q) => $q->whereColumn('subtasks_done', '<', 'subtasks_total'))
            ->when(($filters['subtasks'] ?? null) === 'done',
                fn (Builder $q) => $q->where('subtasks_total', '>', 0)->whereColumn('subtasks_done', '>=', 'subtasks_total'))
            ->when(($filters['late'] ?? null) === 'late', fn (Builder $q) => $q->late())
            ->when(($filters['late'] ?? null) === 'on_time', fn (Builder $q) => $q->late(false))
            ->when($filters['q'] ?? null, fn (Builder $q, $term) => $q->search($term));
    }

    /**
     * ★ (2026-10-05) "Did this ticket miss its deadline?" — one rule, in SQL,
     * so the list filter and the row marker cannot disagree (missedDeadlines()
     * below is the same rule read off a loaded row).
     *
     * A ticket carries up to two promises: the SLA (`sla_due_at`, a moment) and
     * the delivery date (`due_date`, a day — late once that day is over). It is
     * late when it is still open past either, or when it was resolved after
     * either. A rejected ticket was never worked and is never late; a ticket
     * with no promise at all cannot miss one, so it counts as on time.
     *
     * "Open" is read off ticket_statuses.is_open rather than a key list, the
     * way TicketSubtask::scopeOnLiveTicket does, so a status the admin adds is
     * covered without a code change.
     *
     * Both branches are spelled out rather than one being NOT the other: a
     * NULL deadline inside NOT(...) is NULL in SQL, and the row would silently
     * drop out of both answers.
     */
    public function scopeLate(Builder $query, bool $late = true): Builder
    {
        $open = fn ($s) => $s->select('key')->from('ticket_statuses')->where('is_open', true);
        $now = now();
        $today = today()->toDateString();

        if ($late) {
            return $query->where(fn (Builder $w) => $w
                ->where(fn (Builder $o) => $o
                    ->whereIn('status', $open)
                    ->where(fn (Builder $d) => $d
                        ->where('sla_due_at', '<', $now)
                        ->orWhere('due_date', '<', $today)))
                ->orWhere(fn (Builder $r) => $r
                    ->whereNotIn('status', $open)
                    ->whereNotNull('resolved_at')
                    ->where(fn (Builder $d) => $d
                        ->whereColumn('resolved_at', '>', 'sla_due_at')
                        ->orWhereRaw('DATE(resolved_at) > due_date'))));
        }

        return $query->where(fn (Builder $w) => $w
            ->where(fn (Builder $o) => $o
                ->whereIn('status', $open)
                ->where(fn (Builder $d) => $d->whereNull('sla_due_at')->orWhere('sla_due_at', '>=', $now))
                ->where(fn (Builder $d) => $d->whereNull('due_date')->orWhere('due_date', '>=', $today)))
            ->orWhere(fn (Builder $r) => $r
                ->whereNotIn('status', $open)
                ->whereNotNull('resolved_at')
                ->where(fn (Builder $d) => $d->whereNull('sla_due_at')->orWhereColumn('resolved_at', '<=', 'sla_due_at'))
                ->where(fn (Builder $d) => $d->whereNull('due_date')->orWhereRaw('DATE(resolved_at) <= due_date')))
            // Settled without ever being resolved (rejected): nothing was
            // promised and nothing was missed.
            ->orWhere(fn (Builder $r) => $r->whereNotIn('status', $open)->whereNull('resolved_at')));
    }

    /**
     * The deadlines this ticket has missed, as the row marker reads them —
     * the same rule as scopeLate(), on a loaded row. 'sla', 'due', or both;
     * empty when it is on time or carries no promise.
     *
     * For an open ticket the clock is now; for a resolved one it stopped at
     * resolved_at, so a ticket that was late stays late — the entire point of
     * the marker on a resolved row.
     *
     * @return array<int, string>
     */
    public function missedDeadlines(): array
    {
        $end = $this->status->isOpen() ? now() : $this->resolved_at;

        if ($end === null) {
            return [];
        }

        $missed = [];

        if ($this->sla_due_at !== null && $end->gt($this->sla_due_at)) {
            $missed[] = 'sla';
        }

        // ->copy(): the date cast hands back the cached Carbon, and endOfDay()
        // on it would move due_date for whoever reads it next.
        if ($this->due_date !== null && $end->gt($this->due_date->copy()->endOfDay())) {
            $missed[] = 'due';
        }

        return $missed;
    }

    /**
     * ★ (2026-10-05) How late, as words: «3 أيام و 4 ساعات» measured from the
     * FIRST promise it broke (the earlier of the two deadlines) to now for an
     * open ticket, or to resolved_at for a finished one. Null when it is on
     * time. The marker reads this beside the badge, so "late" always comes
     * with "by how much".
     */
    public function lateByLabel(): ?string
    {
        $missed = $this->missedDeadlines();

        if ($missed === []) {
            return null;
        }

        $end = $this->status->isOpen() ? now() : $this->resolved_at;

        $deadlines = collect($missed)->map(fn (string $k) => $k === 'sla'
            ? $this->sla_due_at
            : $this->due_date->copy()->endOfDay());

        return $this->humanInterval($deadlines->min()->diffAsCarbonInterval($end));
    }

    /** True when it has a delivery date or an SLA at all — the marker's "—" case. */
    public function hasDeadline(): bool
    {
        return $this->sla_due_at !== null || $this->due_date !== null;
    }

    /**
     * ★ (2026-10-05) The order the list was asked for. Anything not in SORTS
     * lands on the default, so a hand-edited url degrades rather than throws.
     *
     * The NULLS-last expressions carry no user input — the key is matched
     * against SORTS before any SQL is built.
     */
    public function scopeSortBy(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'newest' => $query->orderByDesc('reported_at'),
            'oldest' => $query->orderBy('reported_at'),
            'updated' => $query->orderByDesc('updated_at'),
            'due' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('reported_at'),
            'sla' => $query->orderByRaw('sla_due_at IS NULL')->orderBy('sla_due_at')->orderBy('reported_at'),
            'resolved' => $query->orderByRaw('resolved_at IS NULL')->orderByDesc('resolved_at'),
            default => $query->defaultOrder(),
        };
    }

    /**
     * A ticket number is an exact handle — jump straight to it. Everything else
     * goes through FULLTEXT, never LIKE %...% (F03.1, F21).
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if (preg_match('/^TK-\d{4}-\d+$/i', $term) === 1) {
            return $query->where('ticket_number', $term);
        }

        return $query->whereFullText(['title', 'description'], $term);
    }

    /**
     * Row-level visibility (CLAUDE.md § 5). A user who can only see what's
     * assigned to them never has other tickets in their result set at all.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('tickets.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereHas('roleAssignments', fn (Builder $r) => $r->where('user_id', $user->id));

            if ($user->hasPermission('tickets.view.own')) {
                $q->orWhere('created_by', $user->id);
            }
        });
    }
}
