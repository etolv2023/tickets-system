<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TicketScope;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number', 'company_id', 'contact_id', 'reporter_name', 'reporter_erp_id',
        'title', 'description', 'type', 'scope', 'priority', 'status', 'module',
        'created_by', 'assigned_frontend_id', 'assigned_backend_id', 'tester_id',
        'approval_status', 'approved_by', 'approved_at',
        'reported_at', 'first_response_at', 'sla_due_at', 'resolved_at',
        'resolution_note', 'client_notified_at', 'client_notified_by', 'closed_at',
        'points_awarded_at', 'start_date', 'due_date', 'original_estimate_hours',
    ];

    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
            'scope' => TicketScope::class,
            'priority' => Priority::class,
            'status' => TicketStatus::class,
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function frontend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_frontend_id');
    }

    public function backend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_backend_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
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
     * F10: something else must land before this can. Shown as a marker in the
     * list and on the board.
     */
    public function isBlocked(): bool
    {
        if (! $this->relationLoaded('incomingLinks')) {
            return false;
        }

        return $this->incomingLinks
            ->where('type', \App\Enums\LinkType::Blocks)
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
     * What belongs on a board (F12): live work, plus a short tail of recently
     * closed so you can see what just landed.
     *
     * The tail matters. Without the date bound the closed column pulled every
     * ticket ever resolved — 4,268 rows at 5,000 tickets, and a 6.7s page. A
     * board is a picture of now, not an archive; the archive is /tickets.
     */
    public function scopeOnBoard(Builder $query, int $closedWithinDays = 14): Builder
    {
        return $query->where(function (Builder $q) use ($closedWithinDays) {
            $q->whereIn('status', ['assigned', 'reopened', 'in_progress', 'dev_done', 'testing'])
                ->orWhere(fn (Builder $w) => $w
                    ->whereIn('status', ['resolved', 'closed'])
                    ->where('updated_at', '>=', now()->subDays($closedWithinDays)));
        });
    }

    /** Default order: urgent first, then oldest first. F03.1 */
    public function scopeDefaultOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('reported_at');
    }

    /**
     * The list filters (F03.1). Query logic, so it lives on the model rather
     * than in the controller (CLAUDE.md § 3).
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
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
            ->when($filters['scope'] ?? null, fn (Builder $q, $v) => $q->where('scope', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('priority', $v))
            ->when($filters['company'] ?? null, fn (Builder $q, $v) => $q->where('company_id', $v))
            ->when($filters['assignee'] ?? null, fn (Builder $q, $v) => $q->where(fn (Builder $w) => $w
                ->where('assigned_frontend_id', $v)
                ->orWhere('assigned_backend_id', $v)
                ->orWhere('tester_id', $v)))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('reported_at', '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('reported_at', '<=', $v))
            ->when($filters['q'] ?? null, fn (Builder $q, $term) => $q->search($term));
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
            $q->where('assigned_frontend_id', $user->id)
                ->orWhere('assigned_backend_id', $user->id)
                ->orWhere('tester_id', $user->id);

            if ($user->hasPermission('tickets.view.own')) {
                $q->orWhere('created_by', $user->id);
            }
        });
    }
}
