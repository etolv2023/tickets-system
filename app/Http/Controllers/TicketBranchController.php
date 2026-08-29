<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tickets\LinkBranchRequest;
use App\Models\GithubRepository;
use App\Models\Ticket;
use App\Services\ActivityLogger;
use App\Services\GitHubSyncService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * F27 — attaching a branch to a ticket by hand.
 *
 * The one write path this feature has, and it writes to THIS database, never to
 * GitHub: it records that a branch which already exists belongs to a ticket.
 * There is no counterpart that removes the record, and that is deliberate —
 * the three gates in front of this make a wrong link close to impossible (the
 * name must carry this ticket's number, and the branch must exist), so the
 * ability to erase evidence would buy nothing and cost the guarantee.
 */
class TicketBranchController extends Controller
{
    public function store(
        LinkBranchRequest $request,
        Ticket $ticket,
        GitHubSyncService $sync,
        ActivityLogger $logger,
    ): RedirectResponse {
        // The permission says you may link branches; the policy says you may
        // see THIS ticket. Both, always — a permission is not a row filter.
        $this->authorize('view', $ticket);

        $repository = GithubRepository::fromCache((int) $request->validated('github_repository_id'));

        if ($repository === null || ! $repository->is_active) {
            return back()->withInput()->withErrors(['github_repository_id' => 'الريبو ده مش مفعّل.']);
        }

        try {
            $branch = $sync->link(
                $ticket,
                $repository,
                (string) $request->validated('branch_name'),
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            // Everything link() throws is written for a person to read — a
            // failed existence check, a branch already claimed, a naming rule.
            return back()->withInput()->withErrors(['branch_name' => $e->getMessage()]);
        }

        // CLAUDE.md § 5: this is an assertion that somebody's work exists, and
        // it feeds a report about who delivered what. It is logged.
        $logger->log(
            action: 'ticket.branch_linked',
            userId: $request->user()->id,
            subject: $ticket,
            changes: ['to' => [
                'repository' => $repository->fullName(),
                'branch' => $branch->name,
            ]],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'اتربط البرانش «' . $branch->name . '» بالتذكرة.');
    }
}
