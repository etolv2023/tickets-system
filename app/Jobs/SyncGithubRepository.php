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
        /*
         * ★ (2026-08-29) Bail before touching the repository row.
         *
         * Without this the client throws "التكامل مش مفعّل أو التوكن ناقص" from
         * deep inside syncRepository(), which catches it and STAMPS IT ON THE
         * ROW — so a global configuration problem is written onto all four
         * repositories as if each of them had failed, overwriting the result of
         * a sync that actually worked.
         *
         * It is not hypothetical: `queue:work` is a long-running process that
         * reads config once at boot. A worker started before GITHUB_TOKEN was
         * set keeps the old, empty value in memory, so `php artisan github:sync`
         * on the terminal succeeds while the queued sync of the same repository
         * fails a second later and marks every row red. Restarting the worker is
         * the fix; not defacing the rows meanwhile is this.
         */
        if (! config('github.enabled') || blank(config('github.token'))) {
            return;
        }

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
