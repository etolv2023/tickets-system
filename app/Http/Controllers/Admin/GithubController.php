<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubtaskSide;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GithubRepositoryRequest;
use App\Jobs\SyncGithubRepository;
use App\Models\GithubRepository;
use App\Models\TicketBranch;
use App\Models\TicketPullRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F27 — the repository list, and the state of the connection.
 *
 * NOTE WHAT IS MISSING: there is no destroy(). A repository is switched off,
 * never removed, because every ticket_branches row points at one and those rows
 * are the evidence this feature exists to keep. Deactivating stops it being
 * synced and takes it out of the manual-link picker; the branches already
 * recorded against it stay readable forever.
 *
 * This screen deliberately makes no API calls while rendering. It shows the
 * last sync's outcome, which is a stored fact and costs nothing; verifying the
 * token and its scopes is `php artisan github:check`, which is slow and belongs
 * on a terminal rather than in a page load with a 300ms budget.
 */
class GithubController extends Controller
{
    public function index(): View
    {
        $repositories = GithubRepository::orderBy('position')->orderBy('id')->get();

        // Two grouped counts rather than a withCount per row: this table has
        // four rows and those two queries do not grow with it.
        $branchCounts = TicketBranch::query()
            ->selectRaw('github_repository_id, count(*) as total')
            ->groupBy('github_repository_id')
            ->pluck('total', 'github_repository_id');

        $pullCounts = TicketPullRequest::query()
            ->selectRaw('github_repository_id, count(*) as total')
            ->groupBy('github_repository_id')
            ->pluck('total', 'github_repository_id');

        return view('admin.github.index', [
            'repositories' => $repositories,
            'branchCounts' => $branchCounts,
            'pullCounts' => $pullCounts,
            'sides' => SubtaskSide::options(),
            'connected' => (bool) config('github.enabled') && filled(config('github.token')),
            // Branch names carrying a ticket number that matches no ticket
            // here. Usually a typo in the branch name, and invisible without
            // somewhere that says so.
            'unmatchedPulls' => TicketPullRequest::whereNull('ticket_id')->count(),
        ]);
    }

    public function store(GithubRepositoryRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $repository = GithubRepository::create($request->validated() + ['is_active' => true]);

        $logger->log(
            action: 'github.repository_added',
            userId: $request->user()->id,
            subject: $repository,
            changes: ['to' => $repository->only('name', 'owner', 'repo', 'side')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'اتضاف الريبو. شغّل مزامنة عشان يقرا البرانشات.');
    }

    public function update(
        GithubRepositoryRequest $request,
        GithubRepository $repository,
        ActivityLogger $logger,
    ): RedirectResponse {
        $before = $repository->only('name', 'owner', 'repo', 'side', 'default_branch', 'is_active');

        $repository->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        $logger->log(
            action: 'github.repository_updated',
            userId: $request->user()->id,
            subject: $repository,
            changes: ['from' => $before, 'to' => $repository->only(array_keys($before))],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'اتحفظ.');
    }

    /**
     * Read this repository now instead of waiting for 03:00.
     *
     * Queued rather than run inline: a first sync of a repository with hundreds
     * of branches is hundreds of API calls, and none of them belong inside a
     * request. Needs a worker on the default queue — without one the button
     * appears to do nothing, which is why the message says what it says.
     */
    public function sync(Request $request, GithubRepository $repository): RedirectResponse
    {
        if (! config('github.enabled') || blank(config('github.token'))) {
            return back()->withErrors(['sync' => 'التكامل مقفول أو التوكن ناقص — راجع GITHUB_ENABLED و GITHUB_TOKEN.']);
        }

        SyncGithubRepository::dispatch($repository->id);

        return back()->with('status', 'المزامنة اتحطّت في الطابور. النتيجة هتبان هنا بعد ما الـ worker يخلّصها.');
    }
}
