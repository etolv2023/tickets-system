<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketSubtask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F21 — one box, grouped results.
 *
 * Everything here respects visibility: a developer searching must never get a
 * hit on a ticket they can't open, or the search itself leaks what exists.
 */
class SearchController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $term = trim((string) $request->query('q', ''));
        $user = $request->user();

        // F21: a ticket number is an exact handle — go straight there.
        if (preg_match('/^TK-\d{4}-\d+$/i', $term) === 1) {
            $ticket = Ticket::where('ticket_number', $term)->first();

            if ($ticket !== null && $user->can('view', $ticket)) {
                return redirect()->route('tickets.show', $ticket);
            }
        }

        if (mb_strlen($term) < 3) {
            return view('search.index', [
                'term' => $term,
                'results' => null,
                // MySQL's fulltext index ignores tokens under 3 characters, so
                // a shorter term genuinely cannot be answered.
                'tooShort' => $term !== '',
            ]);
        }

        return view('search.index', [
            'term' => $term,
            'tooShort' => false,
            'results' => [
                'tickets' => $this->tickets($term, $user),
                'subtasks' => $this->subtasks($term, $user),
                'comments' => $this->comments($term, $user),
                'companies' => $this->companies($term, $user),
            ],
        ]);
    }

    private function tickets(string $term, $user)
    {
        return Ticket::query()
            ->select(['id', 'ticket_number', 'title', 'company_id', 'requested_by', 'type', 'priority', 'status'])
            ->with('company:id,name', 'requester:id,name')
            // FULLTEXT, never LIKE %...% — F21 asks for < 200ms.
            ->whereFullText(['title', 'description'], $term)
            ->visibleTo($user)
            ->limit(20)
            ->get();
    }

    private function subtasks(string $term, $user)
    {
        return TicketSubtask::query()
            ->select(['id', 'ticket_id', 'title', 'status', 'side'])
            ->with('ticket:id,ticket_number,title')
            ->where('title', 'like', "%{$term}%")
            // Subtasks have no fulltext index of their own; the visibility gate
            // is what matters, and the row count here is small.
            ->whereHas('ticket', fn ($q) => $q->visibleTo($user))
            ->limit(15)
            ->get();
    }

    private function comments(string $term, $user)
    {
        return TicketComment::query()
            ->select(['id', 'ticket_id', 'user_id', 'contact_id', 'body', 'is_internal', 'created_at'])
            ->with(['ticket:id,ticket_number,title', 'user:id,name', 'contact:id,name'])
            ->where('body', 'like', "%{$term}%")
            ->whereHas('ticket', fn ($q) => $q->visibleTo($user))
            ->unless($user->hasPermission('comments.internal'), fn ($q) => $q->where('is_internal', false))
            ->limit(15)
            ->get();
    }

    private function companies(string $term, $user)
    {
        if (! $user->hasPermission('companies.manage')) {
            return collect();
        }

        return Company::search($term)->limit(10)->get(['id', 'name', 'code', 'is_active']);
    }
}
