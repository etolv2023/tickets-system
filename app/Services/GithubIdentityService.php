<?php

namespace App\Services;

use App\Models\GithubUnmatchedBranch;
use App\Models\TicketBranch;
use App\Models\TicketPullRequest;
use App\Models\User;

class GithubIdentityService
{
    /** @return array<int, string> */
    public function discoveredLogins(): array
    {
        return collect([
            GithubUnmatchedBranch::query()->whereNotNull('author_login')->distinct()->pluck('author_login'),
            TicketBranch::query()->whereNotNull('author_login')->distinct()->pluck('author_login'),
            TicketPullRequest::query()->whereNotNull('author_login')->distinct()->pluck('author_login'),
        ])->flatten()
            ->filter(fn ($login) => is_string($login) && $login !== '')
            ->unique(fn ($login) => mb_strtolower($login))
            ->sort(fn ($a, $b) => strcasecmp($a, $b))
            ->values()->all();
    }

    /** @return array<int, string> */
    public function availableLogins(?User $except = null): array
    {
        $linked = User::query()->whereNotNull('github_login')
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->pluck('github_login')
            ->map(fn ($login) => mb_strtolower((string) $login))
            ->flip();

        $available = collect($this->discoveredLogins())
            ->reject(fn ($login) => $linked->has(mb_strtolower($login)))
            ->values();

        if ($except?->github_login && ! $available->contains(fn ($login) => strcasecmp($login, $except->github_login) === 0)) {
            $available->prepend($except->github_login);
        }

        return $available->all();
    }
}
