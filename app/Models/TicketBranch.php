<?php

namespace App\Models;

use App\Enums\BranchState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One branch on GitHub, tied to one ticket — F27.
 *
 * A row here is the answer to "is there code behind this ticket, or did somebody
 * just say there was". It is created by the nightly sync when a branch's name
 * carries a ticket number, or by a person with github.audit asserting the link
 * — and in that second case only after the branch was confirmed to exist.
 *
 * NOTHING DELETES A ROW HERE. Not the sync, not a controller, not an admin
 * screen. A branch that vanishes from GitHub is marked deleted and stays.
 */
class TicketBranch extends Model
{
    public const MATCHED_AUTO = 'auto';

    public const MATCHED_MANUAL = 'manual';

    protected $fillable = [
        'ticket_id', 'github_repository_id', 'name', 'head_sha', 'state',
        'matched_by', 'linked_by', 'author_login', 'last_commit_at',
        'first_seen_at', 'last_seen_at', 'deleted_detected_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => BranchState::class,
            'last_commit_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'deleted_detected_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(GithubRepository::class, 'github_repository_id');
    }

    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    /**
     * The repository, from the cached map rather than the relation.
     *
     * Every screen that renders branches renders several, across at most four
     * repositories. Eager-loading the relation is a second query per screen for
     * rows that are already in memory; with Model::preventLazyLoading() on in
     * development, NOT eager-loading it is an exception. This sidesteps both.
     */
    public function repo(): ?GithubRepository
    {
        return GithubRepository::fromCache($this->github_repository_id);
    }

    public function url(): ?string
    {
        return $this->repo()?->branchUrl($this->name);
    }

    /** What to paste into a terminal to be on this branch. */
    public function checkoutCommand(): string
    {
        return 'git fetch origin && git checkout ' . $this->name;
    }

    /** Asserted by a person rather than recognised by the sync. */
    public function isManual(): bool
    {
        return $this->matched_by === self::MATCHED_MANUAL;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('state', BranchState::Active->value);
    }
}
