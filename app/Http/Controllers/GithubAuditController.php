<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGithubRepository;
use App\Models\Company;
use App\Models\GithubRepository;
use App\Models\PriorityDefinition;
use App\Models\Ticket;
use App\Models\TicketStatusDefinition;
use App\Models\TicketTypeDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * F27 — التذاكر اللي مفيش كود وراها.
 *
 * The screen this whole feature was asked for: every ticket for which no branch
 * was ever found — the difference between the work being done and the work
 * being reported done.
 *
 * ★ (2026-08-29) Opened up from "resolved and closed only" to the full ticket
 * filter set, because the question is asked in more than one shape: "مين قفل من
 * غير كود" is the headline, but "الباك اند عمل برانش والفرونت لأ على الشغل
 * المفتوح" is the one you ask before it becomes a problem. The default is still
 * the settled tickets — that is the accusation-shaped question and it should
 * not need a click.
 *
 * Read-only, and row-filtered like everything else: github.audit says you may
 * ask the question, visibleTo() still decides which tickets you may ask it
 * about. A permission is not a bypass for the ticket scope (CLAUDE.md § 5).
 */
class GithubAuditController extends Controller
{
    /** Closed-enough to expect code behind it. The default view. */
    private const SETTLED = ['resolved', 'closed'];

    /** The status filter value that means "do not narrow by status at all". */
    private const STATUS_ALL = 'all';

    /**
     * ★ (2026-08-29) The screen answers three questions now, not one.
     *
     * It started as «اتقفلت من غير برانش» — the accusation-shaped question, and
     * still the default. But «أنهي تذاكر ليها برانش فعلاً» is the same data read
     * the other way, and «الكل» is how you see the ratio. One column, one WHERE.
     */
    public const BRANCH_MODES = [
        'none' => 'ملهاش برانش',
        'has' => 'ليها برانش',
        'all' => 'الكل — ببرانش ومن غيره',
    ];

    public function index(Request $request): View
    {
        $filters = $request->only(
            'q', 'status', 'type', 'priority', 'company', 'assignee', 'relation',
            'from', 'to', 'date_basis', 'repo', 'branch',
        );

        // Blank means «ملهاش برانش»: the blank state of this screen stays the
        // question it was built to answer.
        $mode = array_key_exists($filters['branch'] ?? '', self::BRANCH_MODES)
            ? $filters['branch']
            : 'none';

        // resolved_at rather than reported_at: on this screen a date range means
        // "work delivered in this window", not "tickets opened in it".
        $filters['date_basis'] ??= 'resolved_at';

        $repo = $this->repositoryFilter($filters['repo'] ?? null);

        /*
         * The population being measured, before the "has no branch" cut. Built
         * as a closure rather than a variable because it is consumed twice —
         * once narrowed to the missing rows and once whole — and an Eloquent
         * builder is mutable, so sharing one instance would let the first use
         * quietly change the second.
         */
        $population = fn () => Ticket::query()
            ->visibleTo($request->user())
            ->tap(fn (Builder $q) => $this->applyStatus($q, $filters['status'] ?? null))
            // status is applied above; everything else is the same filter set
            // /tickets uses, so the two screens cannot drift apart.
            ->filter(Arr::except($filters, ['status', 'repo', 'branch']));

        $tickets = $population()
            // No description: LONGTEXT on a 25-row list (CLAUDE.md § 4.3).
            ->select([
                'id', 'ticket_number', 'company_id', 'title', 'type', 'priority',
                'status', 'reported_at', 'resolved_at', 'closed_at', 'branches_count',
            ])
            ->with([
                'company:id,name',
                'roleAssignments.user:id,name,avatar_path,is_active',
            ])
            ->tap(fn (Builder $q) => $this->applyBranchMode($q, $repo, $mode))
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('github.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'mode' => $mode,
            'modeLabel' => self::BRANCH_MODES[$mode],
            'branchModes' => self::BRANCH_MODES,
            // The denominator. "31" alone says nothing; "31 من 402" says
            // whether this is a habit or a slip. Both follow the filters, so
            // they always describe the same rows.
            'totalCount' => $population()->count(),
            'matchedCount' => $tickets->total(),
            'repositories' => GithubRepository::activeList(),
            'selectedRepo' => $repo?->name,
            'statuses' => TicketStatusDefinition::options(),
            'types' => TicketTypeDefinition::options(),
            'priorities' => PriorityDefinition::options(),
            'selectedCompany' => filled($filters['company'] ?? null)
                ? Company::whereKey($filters['company'])->value('name')
                : null,
            'selectedAssignee' => filled($filters['assignee'] ?? null)
                ? User::whereKey($filters['assignee'])->value('name')
                : null,
            'connected' => (bool) config('github.enabled') && filled(config('github.token')),
            'lastSyncedAt' => collect(GithubRepository::activeList())
                ->pluck('last_synced_at')->filter()->min(),
        ]);
    }

    /**
     * ★ (2026-08-29) "زامن دلوقتي" — read GitHub now instead of waiting for
     * 03:00.
     *
     * On github.audit rather than settings.manage: the person who has just been
     * shown a list of unproven work is the person who needs to know the list is
     * current, and a read against a read-only token changes nothing anywhere.
     *
     * Queued, like the button on the admin screen — a first sync of a repository
     * is hundreds of API calls and none of them belong inside a request.
     */
    public function sync(Request $request): RedirectResponse
    {
        if (! config('github.enabled') || blank(config('github.token'))) {
            return back()->withErrors(['sync' => 'التكامل مقفول أو التوكن ناقص — راجع GITHUB_ENABLED و GITHUB_TOKEN.']);
        }

        $repositories = GithubRepository::activeList();

        if ($repositories === []) {
            return back()->withErrors(['sync' => 'مفيش ريبوز مفعّلة.']);
        }

        foreach ($repositories as $repository) {
            SyncGithubRepository::dispatch($repository->id);
        }

        return back()->with('status', count($repositories)
            . ' ريبو اتحطوا في طابور المزامنة. حدّث الصفحة بعد شوية.');
    }

    /**
     * Which tickets count as "should have code by now".
     *
     * Empty means the default — settled only. That is deliberate: the blank
     * state of this screen is the question it exists to answer, not "every
     * ticket in the system", most of which have no branch yet for the entirely
     * ordinary reason that nobody has started them.
     */
    private function applyStatus(Builder $query, ?string $status): void
    {
        match (true) {
            blank($status) => $query->whereIn('status', self::SETTLED),
            $status === self::STATUS_ALL => null,
            $status === 'open' => $query->whereNotIn('status', ['resolved', 'closed', 'rejected']),
            default => $query->where('status', $status),
        };
    }

    /**
     * Has a branch, has none, or do not ask — optionally scoped to one repo.
     *
     * Without a repository the "none" case reads the counter column, which is
     * why the unfiltered screen is one indexed WHERE over the whole ticket table
     * (CLAUDE.md § 4.6). With one, it has to be an EXISTS, because
     * branches_count cannot say WHICH repository the branches were in — that is
     * the price of the counter, and it is only paid on a narrowed view.
     */
    private function applyBranchMode(Builder $query, ?GithubRepository $repo, string $mode): void
    {
        if ($mode === 'all') {
            return;
        }

        if ($repo === null) {
            $mode === 'has'
                ? $query->where('branches_count', '>', 0)
                : $query->withoutBranch();

            return;
        }

        $inRepo = fn ($b) => $b->where('github_repository_id', $repo->id);

        $mode === 'has'
            ? $query->whereHas('branches', $inRepo)
            : $query->whereDoesntHave('branches', $inRepo);
    }

    /** The chosen repository, from the cached map — never a query. */
    private function repositoryFilter(mixed $id): ?GithubRepository
    {
        return filled($id) ? GithubRepository::fromCache((int) $id) : null;
    }
}
