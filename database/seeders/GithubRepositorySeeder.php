<?php

namespace Database\Seeders;

use App\Enums\SubtaskSide;
use App\Models\GithubRepository;
use Illuminate\Database\Seeder;

/**
 * F27 — the four repositories the team actually works in.
 *
 * A system seeder, not a demo one: these are real addresses on github.com and
 * they are the same on every installation of this system. Seeding them means
 * nobody has to type an owner and a repo slug correctly on first run, which is
 * the one place a typo produces a permanently silent 404.
 *
 * Idempotent on (owner, repo) — the unique key — so re-running never duplicates
 * a row, and an administrator's edits to `name` or `side` survive a reseed.
 * Only the addressing fields are enforced.
 */
class GithubRepositorySeeder extends Seeder
{
    /** @var array<int, array{name: string, owner: string, repo: string, side: SubtaskSide, default_branch: string}> */
    private const REPOSITORIES = [
        [
            'name' => 'الباك اند — trioapi',
            'owner' => 'etolv2023',
            'repo' => 'trioapi',
            'side' => SubtaskSide::Backend,
            'default_branch' => 'production',
        ],
        [
            'name' => 'بورتال السفر v3',
            'owner' => 'etolv2023',
            'repo' => 'travel_portal_v3',
            'side' => SubtaskSide::Frontend,
            'default_branch' => 'production',
        ],
        [
            'name' => 'بورتال السفر v4',
            'owner' => 'etolv2023',
            'repo' => 'travel_portal_v4',
            'side' => SubtaskSide::Frontend,
            'default_branch' => 'production',
        ],
        [
            'name' => 'حسابات السفر',
            'owner' => 'etolv2023',
            'repo' => 'travel-accounting',
            'side' => SubtaskSide::Frontend,
            'default_branch' => 'production',
        ],
    ];

    public function run(): void
    {
        foreach (self::REPOSITORIES as $position => $repository) {
            GithubRepository::firstOrCreate(
                ['owner' => $repository['owner'], 'repo' => $repository['repo']],
                [
                    'name' => $repository['name'],
                    'side' => $repository['side']->value,
                    // ★ (2026-08-29) Only the value shown until the first sync
                    // — GitHubSyncService::refreshDefaultBranch() then replaces
                    // it with the truth. It was `main` here and every one of
                    // these repositories actually defaults to `production`,
                    // which is exactly why nothing should trust this guess.
                    'default_branch' => $repository['default_branch'],
                    'is_active' => true,
                    'position' => $position,
                ]
            );
        }
    }
}
