<?php

namespace App\Models;

use App\Enums\BranchState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GithubUnmatchedBranch extends Model
{
    protected $fillable = [
        'github_repository_id', 'name', 'head_sha', 'state', 'author_login',
        'last_commit_at', 'first_seen_at', 'last_seen_at', 'deleted_detected_at',
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

    public function repository(): BelongsTo
    {
        return $this->belongsTo(GithubRepository::class, 'github_repository_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_login', 'github_login');
    }

    public function url(): ?string
    {
        return $this->repository?->branchUrl($this->name);
    }
}
