<?php

namespace App\Http\Controllers;

use App\Models\GithubRepository;
use App\Models\Ticket;
use App\Models\User;
use App\Services\BranchNamingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ticket-page panels fetched when their tab is first opened (lazy-panel.js).
 * Their rows matter only to someone who looks, and loading them with the page
 * kept it over § 4's query budget. Each one is guarded exactly like the page:
 * TicketPolicy::view, plus whatever the panel itself needs.
 */
class TicketPanelController extends Controller
{
    /** F27 — the code tab. */
    public function code(Request $request, Ticket $ticket): View
    {
        $ticket->load('roleAssignments');
        $this->authorize('view', $ticket);
        abort_unless($request->user()->hasPermission('github.view'), 403);

        $ticket->load(self::codeRelations());

        return view('tickets.partials._github', ['ticket' => $ticket, ...self::codeData($ticket, $request->user())]);
    }

    /** F09 — "my time on this ticket", under the time form. */
    public function time(Request $request, Ticket $ticket): View
    {
        $ticket->load('roleAssignments');
        $this->authorize('view', $ticket);
        abort_unless($request->user()->hasPermission('time.log'), 403);

        // The titles beside each entry come from these, not a per-entry load.
        $ticket->load('subtasks:id,ticket_id,title');

        return view('tickets.partials._my-time', [
            'ticket' => $ticket,
            // Someone else's hours aren't their business, and the totals are
            // already rolled up on the ticket.
            'myTimeEntries' => $ticket->timeEntries()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('spent_on')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * The repository each row belongs to is NOT eager-loaded: there are a
     * handful of them, they are cached forever, and TicketBranch::repo() reads
     * them from there.
     *
     * @return array<string, \Closure>
     */
    public static function codeRelations(): array
    {
        return [
            'branches' => fn ($q) => $q->orderBy('github_repository_id')->orderByDesc('id'),
            'pullRequests' => fn ($q) => $q->orderByDesc('number'),
        ];
    }

    /** @return array{githubRepositories: array, suggestedBranch: string} */
    public static function codeData(Ticket $ticket, User $user): array
    {
        return [
            // The manual-link picker's options, from the cached map — no
            // query. Empty for anyone who cannot link, so the panel does not
            // render a form nobody may submit.
            'githubRepositories' => $user->hasPermission('github.audit') ? GithubRepository::activeList() : [],
            // The name the convention says this ticket's branch should have —
            // a business rule, so not computed in the view (CLAUDE.md § 3).
            'suggestedBranch' => app(BranchNamingService::class)->suggest($ticket->ticket_number, $ticket->title),
        ];
    }
}
