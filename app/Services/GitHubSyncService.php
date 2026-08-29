<?php

namespace App\Services;

use App\Enums\BranchState;
use App\Enums\PullRequestState;
use App\Models\GithubRepository;
use App\Models\Ticket;
use App\Models\TicketBranch;
use App\Models\TicketPullRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Reading GitHub and writing down what it says — F27.
 *
 * The rule that shapes everything here: THIS NEVER DELETES A ROW. A branch that
 * has disappeared from GitHub is marked deleted and kept, because a branch
 * usually disappears at the moment it is merged, and dropping the record then
 * would delete the proof at the instant the work was finished.
 *
 * Takes and returns data; never reads auth() or request(). The actor's id is a
 * parameter, so this stays callable from a scheduled command with no session
 * (CLAUDE.md § 3).
 */
class GitHubSyncService
{
    /**
     * How far back to re-read pull requests beyond the newest one already held.
     *
     * Not zero: a PR updated while the previous sync was mid-walk would sit
     * just behind the high-water mark and never be read again. A day of overlap
     * costs one extra page and closes that window.
     */
    private const PULL_OVERLAP_HOURS = 24;

    public function __construct(
        private readonly GitHubClient $github,
        private readonly BranchNamingService $naming,
    ) {
    }

    /**
     * Bring one repository's branches and pull requests up to date.
     *
     * @return array<string, int> what changed, for the command's output
     */
    public function syncRepository(GithubRepository $repo): array
    {
        try {
            $stats = $this->syncBranches($repo) + $this->syncPullRequests($repo);

            $repo->forceFill(['last_synced_at' => now(), 'last_sync_error' => null])->save();

            return $stats;
        } catch (Throwable $e) {
            // Recorded on the row, not just logged: the admin screen has to be
            // able to say WHY a repository stopped reporting, otherwise a
            // lapsed token looks exactly like a team that stopped branching.
            $repo->forceFill(['last_sync_error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            throw $e;
        }
    }

    /**
     * A person asserting "this branch is the work for this ticket".
     *
     * Three gates, all of them before anything is written:
     *   1. the name follows the convention and names THIS ticket
     *   2. the branch actually exists on GitHub — checked by asking GitHub
     *   3. it is not already claimed by a different ticket
     *
     * Gate 2 is the one that matters. Without it this is a text box that lets
     * anybody type a branch name to make a ticket look finished, which is
     * precisely the thing the feature exists to prevent.
     *
     * @throws RuntimeException with a message meant for the user
     */
    public function link(Ticket $ticket, GithubRepository $repo, string $name, int $userId): TicketBranch
    {
        $name = trim($name);

        if ($reason = $this->naming->rejectionReason($name, $ticket->ticket_number)) {
            throw new RuntimeException($reason);
        }

        $existing = TicketBranch::where('github_repository_id', $repo->id)
            ->where('name', $name)
            ->first();

        if ($existing && $existing->ticket_id !== $ticket->id) {
            throw new RuntimeException('البرانش ده مربوط بتذكرة تانية بالفعل.');
        }

        $remote = $this->github->branch($repo, $name);

        if ($remote === null) {
            throw new RuntimeException(
                'مفيش برانش بالاسم ده في ' . $repo->name . '. اتأكد إنك عملت push للبرانش الأول.'
            );
        }

        $branch = $existing ?? new TicketBranch([
            'ticket_id' => $ticket->id,
            'github_repository_id' => $repo->id,
            'name' => $name,
            'matched_by' => TicketBranch::MATCHED_MANUAL,
            'linked_by' => $userId,
            'first_seen_at' => now(),
        ]);

        $branch->fill($this->headFrom($remote) + [
            'state' => BranchState::Active->value,
            'last_seen_at' => now(),
            'deleted_detected_at' => null,
        ])->save();

        $this->refreshCounts([$ticket->id]);

        return $branch;
    }

    /**
     * Recompute tickets.branches_count for the given tickets.
     *
     * Grouped by the resulting count, so this is at most five UPDATE statements
     * no matter how many tickets moved — there are four repositories, so the
     * only possible counts are 0..4.
     *
     * Through the query builder rather than the model on purpose: Eloquent
     * would touch updated_at, and a nightly job that bumps every ticket's
     * updated_at silently reorders the ticket list every morning.
     *
     * @param  array<int, int>  $ticketIds
     */
    public function refreshCounts(array $ticketIds): void
    {
        $ticketIds = array_values(array_unique(array_filter($ticketIds)));

        if ($ticketIds === []) {
            return;
        }

        // Aliased away from branches_count: that is the name of the column
        // being written, and letting withCount() claim it too means the
        // subquery and the stored value collide in the same result row.
        // select('id') because the default is every column, description
        // included (CLAUDE.md § 4.3).
        $counts = Ticket::whereIn('id', $ticketIds)
            ->select('id')
            ->withCount(['branches as live_branches_count'])
            ->pluck('live_branches_count', 'id');

        // preserveKeys: true is load-bearing, not tidiness. groupBy() re-indexes
        // each group from zero by default, so $group->keys() would hand back
        // 0,1,2… — positions — and every ticket whose id happened to match a
        // position would be given some other ticket's count. Silent, and wrong
        // in exactly the direction that makes finished work look unfinished.
        foreach ($counts->groupBy(fn (int $count) => $count, preserveKeys: true) as $count => $group) {
            DB::table('tickets')
                ->whereIn('id', $group->keys()->all())
                ->update(['branches_count' => (int) $count]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function syncBranches(GithubRepository $repo): array
    {
        $remote = collect($this->github->branches($repo))
            ->filter(fn ($b) => filled($b['name'] ?? null))
            ->keyBy('name');

        // Which of them name a ticket at all. Everything else in the repository
        // — main, develop, someone's scratch branch — is simply not our
        // business and is neither stored nor reported as a problem.
        $claimed = $remote
            ->map(fn ($b, string $name) => $this->naming->ticketNumberIn($name))
            ->filter();

        $ticketIds = Ticket::whereIn('ticket_number', $claimed->values()->unique()->all())
            ->pluck('id', 'ticket_number');

        $rows = TicketBranch::where('github_repository_id', $repo->id)->get()->keyBy('name');

        $touched = [];
        $stats = ['branches' => $remote->count(), 'new' => 0, 'updated' => 0, 'gone' => 0];

        foreach ($claimed as $name => $ticketNumber) {
            $ticketId = $ticketIds[$ticketNumber] ?? null;

            // A branch naming a ticket that does not exist here. Not an error
            // worth failing the sync over — a typo in a branch name, or a
            // ticket from a system this one replaced.
            if ($ticketId === null) {
                continue;
            }

            $sha = $remote[$name]['commit']['sha'] ?? null;
            $row = $rows->get($name);

            if ($row === null) {
                $row = new TicketBranch([
                    'ticket_id' => $ticketId,
                    'github_repository_id' => $repo->id,
                    'name' => $name,
                    'matched_by' => TicketBranch::MATCHED_AUTO,
                    'first_seen_at' => now(),
                ]);

                $stats['new']++;
            } elseif ($row->head_sha !== $sha) {
                $stats['updated']++;
            }

            $head = ['head_sha' => $sha];

            // The branch list carries a sha and nothing else, so the author and
            // the commit date cost one extra request. Only spent when the head
            // actually moved — an unchanged branch is free forever after.
            if ($row->head_sha !== $sha || $row->last_commit_at === null) {
                // A null here means the branch was removed between the list and
                // this call. Keep the sha from the list rather than overwriting
                // what we know with three nulls.
                $detail = $this->github->branch($repo, $name);

                if ($detail !== null) {
                    $head = $this->headFrom($detail);
                }
            }

            $row->fill($head + [
                'state' => BranchState::Active->value,
                'last_seen_at' => now(),
                'deleted_detected_at' => null,
            ])->save();

            $touched[] = $ticketId;
        }

        // Gone from GitHub. Marked, never removed — see the class docblock.
        foreach ($rows as $name => $row) {
            if ($remote->has($name) || $row->state === BranchState::Deleted) {
                continue;
            }

            $row->forceFill([
                'state' => BranchState::Deleted->value,
                'deleted_detected_at' => now(),
            ])->save();

            $stats['gone']++;
        }

        $this->refreshCounts($touched);

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    private function syncPullRequests(GithubRepository $repo): array
    {
        $since = TicketPullRequest::where('github_repository_id', $repo->id)
            ->max('github_updated_at');

        $since = $since === null
            ? null
            : CarbonImmutable::parse($since)->subHours(self::PULL_OVERLAP_HOURS);

        $pulls = $this->github->pullRequests($repo, $since);

        if ($pulls === []) {
            return ['pulls' => 0];
        }

        $numbers = collect($pulls)
            ->map(fn ($p) => $this->naming->ticketNumberIn($p['head']['ref'] ?? ''))
            ->filter()
            ->unique()
            ->values();

        $ticketIds = $numbers->isEmpty()
            ? collect()
            : Ticket::whereIn('ticket_number', $numbers->all())->pluck('id', 'ticket_number');

        foreach ($pulls as $pull) {
            $head = $pull['head']['ref'] ?? '';
            $ticketNumber = $this->naming->ticketNumberIn($head);

            TicketPullRequest::updateOrCreate(
                [
                    'github_repository_id' => $repo->id,
                    'number' => (int) $pull['number'],
                ],
                [
                    'ticket_id' => $ticketNumber === null ? null : ($ticketIds[$ticketNumber] ?? null),
                    'title' => mb_substr((string) ($pull['title'] ?? ''), 0, 255),
                    'state' => PullRequestState::fromGitHub(
                        (string) ($pull['state'] ?? 'closed'),
                        $pull['merged_at'] ?? null
                    )->value,
                    'is_draft' => (bool) ($pull['draft'] ?? false),
                    'author_login' => $pull['user']['login'] ?? null,
                    'head_branch' => mb_substr($head, 0, 255),
                    'base_branch' => mb_substr((string) ($pull['base']['ref'] ?? ''), 0, 100),
                    'opened_at' => $pull['created_at'] ?? null,
                    'merged_at' => $pull['merged_at'] ?? null,
                    'closed_at' => $pull['closed_at'] ?? null,
                    'github_updated_at' => $pull['updated_at'] ?? null,
                ]
            );
        }

        return ['pulls' => count($pulls)];
    }

    /**
     * The three head-commit fields, dug out of GitHub's nesting.
     *
     * `commit.author` is the GitHub account (may be null — a commit whose email
     * matches no account), while `commit.commit.author.date` is the git author
     * date. Two different "author"s one level apart, which is exactly the sort
     * of thing worth pulling into one named place.
     *
     * @param  array<string, mixed>  $branch
     * @return array<string, mixed>
     */
    private function headFrom(array $branch): array
    {
        return [
            'head_sha' => $branch['commit']['sha'] ?? null,
            'author_login' => $branch['commit']['author']['login'] ?? null,
            'last_commit_at' => $branch['commit']['commit']['author']['date'] ?? null,
        ];
    }
}
