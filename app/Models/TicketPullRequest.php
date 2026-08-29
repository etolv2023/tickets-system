<?php

namespace App\Models;

use App\Enums\PullRequestState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pull request, matched to a ticket through its head branch — F27.
 *
 * Read-only in the strictest sense: this application has no way to open, close,
 * merge or comment on a pull request. A row is a copy of something GitHub
 * already decided, kept because it answers "did this work reach the default
 * branch" long after the branch itself is gone.
 */
class TicketPullRequest extends Model
{
    protected $fillable = [
        'ticket_id', 'github_repository_id', 'number', 'title', 'state', 'is_draft',
        'author_login', 'head_branch', 'base_branch',
        'opened_at', 'merged_at', 'closed_at', 'github_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => PullRequestState::class,
            'is_draft' => 'boolean',
            'number' => 'integer',
            'opened_at' => 'datetime',
            'merged_at' => 'datetime',
            'closed_at' => 'datetime',
            'github_updated_at' => 'datetime',
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

    /** From the cached map — see TicketBranch::repo() for why. */
    public function repo(): ?GithubRepository
    {
        return GithubRepository::fromCache($this->github_repository_id);
    }

    public function url(): ?string
    {
        return $this->repo()?->pullUrl($this->number);
    }
}
