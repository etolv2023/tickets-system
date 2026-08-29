<?php

namespace App\Jobs;

use App\Models\GithubRepository;
use App\Services\GitHubSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * F27 — the "زامن دلوقتي" button, off the request thread.
 *
 * The nightly `github:sync` command does not use this: four repositories in
 * sequence from a cron is exactly what a cron is for. This exists only so a
 * person clicking a button does not sit on a spinner through however many API
 * calls a first sync of a repository takes, and does not hit a PHP timeout
 * doing it.
 *
 * tries = 1. A failed read is not worth retrying — the nightly run is a few
 * hours away and the error is already written to the repository row, where the
 * admin screen shows it.
 */
class SyncGithubRepository implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly int $repositoryId)
    {
    }

    public function handle(GitHubSyncService $sync): void
    {
        $repository = GithubRepository::find($this->repositoryId);

        if ($repository === null || ! $repository->is_active) {
            return;
        }

        $sync->syncRepository($repository);
    }

    /**
     * syncRepository() already wrote the message onto the repository row before
     * rethrowing, so there is nothing to record here. Declared anyway so the
     * failure is not mistaken for something that needs handling elsewhere.
     */
    public function failed(Throwable $e): void
    {
    }
}
