<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F27 — "اتقفلت من غير برانش".
 *
 * The screen this whole feature was asked for. Every ticket somebody marked
 * resolved or closed for which no branch was ever found, in any repository —
 * the difference between the work being done and the work being reported done.
 *
 * Read-only, and row-filtered like everything else: github.audit says you may
 * ask the question, visibleTo() still decides which tickets you may ask it
 * about. A permission is not a bypass for the ticket scope (CLAUDE.md § 5).
 *
 * Runs off tickets.branches_count, so the whole screen is one indexed WHERE
 * rather than an EXISTS per row.
 */
class GithubAuditController extends Controller
{
    /** Closed-enough to expect code behind it. */
    private const SETTLED = ['resolved', 'closed'];

    public function index(Request $request): View
    {
        $filters = $request->only('from', 'to', 'q');

        $base = fn () => Ticket::query()
            ->whereIn('status', self::SETTLED)
            ->visibleTo($request->user())
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('resolved_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('resolved_at', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->search($v));

        $tickets = $base()
            // No description: LONGTEXT on a 25-row list (CLAUDE.md § 4.3).
            ->select([
                'id', 'ticket_number', 'company_id', 'title', 'type', 'priority',
                'status', 'resolved_at', 'closed_at', 'branches_count',
            ])
            ->with([
                'company:id,name',
                'roleAssignments.user:id,name,avatar_path,is_active',
            ])
            ->withoutBranch()
            ->orderByDesc('resolved_at')
            ->paginate(25)
            ->withQueryString();

        // Two counts over the same filtered set, so the header can say "31 من
        // 402" instead of a number with nothing to measure it against.
        $settled = $base()->count();

        return view('github.missing', [
            'tickets' => $tickets,
            'filters' => $filters,
            'settledCount' => $settled,
            'missingCount' => $tickets->total(),
        ]);
    }
}
