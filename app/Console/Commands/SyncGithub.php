<?php

namespace App\Console\Commands;

use App\Models\GithubRepository;
use App\Services\GitHubSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F27 — read every repository and write down which branches exist.
 *
 * Scheduled nightly, ahead of the points sweep, so that by the time anything
 * asks "does this ticket have code behind it" the answer is from this morning
 * rather than from whenever somebody last opened a screen.
 *
 * Runs the four repositories in sequence rather than through a queue. There is
 * no delivery here that could half-happen and no ordering to protect — a read
 * that fails is simply a read that happens again tomorrow — so the isolation a
 * queue buys the Discord integration buys nothing here, and a command that
 * prints its results is easier to run by hand when something looks wrong.
 *
 * One repository failing does not stop the others: a token that lost access to
 * one repo should not blind the system to the other three.
 */
class SyncGithub extends Command
{
    protected $signature = 'github:sync {--repo= : owner/repo, to sync just one}';

    protected $description = 'قراءة البرانشات والـ PRs من جيت هب وربطها بالتذاكر';

    public function handle(GitHubSyncService $sync): int
    {
        if (! config('github.enabled') || blank(config('github.token'))) {
            // Not an error. "Not configured" has to mean "silently off", or a
            // cron on an installation that does not use GitHub mails a failure
            // every single night.
            $this->comment('تكامل جيت هب مقفول (GITHUB_ENABLED). مفيش حاجة اتعملت.');

            return self::SUCCESS;
        }

        $repositories = GithubRepository::query()
            ->active()
            ->when($this->option('repo'), fn ($q, string $full) => $q
                ->where('owner', str($full)->before('/')->toString())
                ->where('repo', str($full)->after('/')->toString()))
            ->orderBy('position')
            ->get();

        if ($repositories->isEmpty()) {
            $this->warn('مفيش ريبوز مفعّلة.');

            return self::SUCCESS;
        }

        $rows = [];
        $failed = 0;

        foreach ($repositories as $repository) {
            try {
                $stats = $sync->syncRepository($repository);

                $rows[] = [
                    $repository->fullName(),
                    $stats['branches'] ?? 0,
                    $stats['new'] ?? 0,
                    $stats['updated'] ?? 0,
                    $stats['gone'] ?? 0,
                    $stats['pulls'] ?? 0,
                    'تمام',
                ];
            } catch (Throwable $e) {
                $failed++;

                // Logged as well as printed: the nightly run has no audience.
                Log::warning('github:sync failed', [
                    'repository' => $repository->fullName(),
                    'message' => $e->getMessage(),
                ]);

                $rows[] = [$repository->fullName(), '—', '—', '—', '—', '—', $e->getMessage()];
            }
        }

        $this->table(
            ['الريبو', 'برانشات', 'جديد', 'اتغيّر', 'اختفى', 'PRs', 'الحالة'],
            $rows
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
