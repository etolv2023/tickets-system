<?php

namespace Database\Seeders;

use App\Enums\BranchState;
use App\Enums\PullRequestState;
use App\Enums\SubtaskSide;
use App\Models\GithubRepository;
use App\Models\Ticket;
use App\Models\TicketBranch;
use App\Models\TicketPullRequest;
use App\Models\User;
use App\Services\GitHubSyncService;
use Illuminate\Database\Seeder;

/**
 * F27 demo data — what the nightly sync WOULD have written.
 *
 * Never reaches production (DatabaseSeeder gates it), and it makes no network
 * call: it writes the rows GitHubSyncService would have written, so every
 * screen can be opened and every state seen without a token.
 *
 * Deliberately leaves roughly a third of the settled tickets with no branch at
 * all. A demo where everything is fine cannot show the one screen this feature
 * was asked for.
 */
class DemoGithubSeeder extends Seeder
{
    public function run(GitHubSyncService $sync): void
    {
        $backend = GithubRepository::where('repo', 'trioapi')->first();
        $frontend = GithubRepository::where('repo', 'travel_portal_v4')->first();

        if ($backend === null || $frontend === null) {
            return;
        }

        // Give the developers GitHub handles, so a branch's author renders as a
        // person rather than as a login nobody recognises.
        $logins = ['frontend' => 'mo-front', 'backend' => 'sara-api', 'devops' => 'ops-hany'];

        foreach ($logins as $roleKey => $login) {
            User::whereHas('role', fn ($q) => $q->where('key', $roleKey))
                ->orderBy('id')
                ->limit(1)
                ->update(['github_login' => $login]);
        }

        $tickets = Ticket::whereIn('status', ['resolved', 'closed'])
            ->orderBy('id')
            ->get(['id', 'ticket_number', 'resolved_at']);

        $touched = [];
        $index = 0;

        foreach ($tickets as $ticket) {
            $index++;

            // Every third settled ticket is left bare — that is the population
            // /github/without-branch exists to list.
            if ($index % 3 === 0) {
                continue;
            }

            $commitAt = ($ticket->resolved_at ?? now())->copy()->subHours(6);

            // The backend branch. Every fifth one is recorded as gone from
            // GitHub, so the "اتمسح" state is visible somewhere.
            $this->branch($ticket, $backend, [
                'author_login' => 'sara-api',
                'last_commit_at' => $commitAt,
                'state' => $index % 5 === 0 ? BranchState::Deleted->value : BranchState::Active->value,
                'deleted_detected_at' => $index % 5 === 0 ? $commitAt->copy()->addDay() : null,
            ]);

            // Half of them also have frontend work — the same branch name in a
            // different repository, which is the case the schema exists for.
            if ($index % 2 === 0) {
                $this->branch($ticket, $frontend, [
                    'author_login' => 'mo-front',
                    'last_commit_at' => $commitAt->copy()->addHour(),
                    // One in eight was attached by a person rather than found
                    // by the sync, so the «اترّبط بإيد» marker has a row. Eight
                    // rather than six because six shares a factor with the
                    // every-third skip above — every sixth ticket is one this
                    // loop never reaches, so that branch was dead.
                    'matched_by' => $index % 8 === 0 ? TicketBranch::MATCHED_MANUAL : TicketBranch::MATCHED_AUTO,
                    'linked_by' => $index % 8 === 0
                        ? User::whereHas('role', fn ($q) => $q->where('key', 'manager'))->value('id')
                        : null,
                ]);

                $this->pull($ticket, $frontend, $index, $commitAt);
            }

            $touched[] = $ticket->id;
        }

        $sync->refreshCounts($touched);

        $this->command?->info(
            '  جيت هب: ' . TicketBranch::count() . ' برانش · ' . TicketPullRequest::count() . ' PR · '
            . Ticket::whereIn('status', ['resolved', 'closed'])->withoutBranch()->count()
            . ' تذكرة متقفلة من غير برانش.'
        );
    }

    /** @param array<string, mixed> $extra */
    private function branch(Ticket $ticket, GithubRepository $repository, array $extra): void
    {
        TicketBranch::updateOrCreate(
            ['github_repository_id' => $repository->id, 'name' => $ticket->ticket_number],
            $extra + [
                'ticket_id' => $ticket->id,
                // A plausible 40-character sha. Never used to address anything —
                // it is only ever displayed, seven characters at a time.
                'head_sha' => substr(hash('sha1', $ticket->ticket_number . $repository->id), 0, 40),
                'matched_by' => TicketBranch::MATCHED_AUTO,
                'state' => BranchState::Active->value,
                'first_seen_at' => $ticket->resolved_at ?? now(),
                'last_seen_at' => now(),
            ]
        );
    }

    private function pull(Ticket $ticket, GithubRepository $repository, int $number, \Carbon\CarbonInterface $at): void
    {
        $merged = $number % 4 !== 0;

        TicketPullRequest::updateOrCreate(
            ['github_repository_id' => $repository->id, 'number' => $number],
            [
                'ticket_id' => $ticket->id,
                'title' => $ticket->ticket_number . ' — ' . SubtaskSide::Frontend->label(),
                'state' => ($merged ? PullRequestState::Merged : PullRequestState::Open)->value,
                'is_draft' => ! $merged && $number % 8 === 0,
                'author_login' => 'mo-front',
                'head_branch' => $ticket->ticket_number,
                'base_branch' => $repository->default_branch,
                'opened_at' => $at,
                'merged_at' => $merged ? $at->copy()->addHours(3) : null,
                'github_updated_at' => $at->copy()->addHours(3),
            ]
        );
    }
}
