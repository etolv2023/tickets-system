<?php

namespace App\Http\Controllers;

use App\Enums\BranchState;
use App\Models\GithubRepository;
use App\Models\GithubUnmatchedBranch;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\GitHubWriteClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class GithubUnmatchedBranchController extends Controller
{
    public function index(Request $request): View
    {
        $selectedAuthor = $request->filled('author_user')
            ? User::query()->find($request->integer('author_user'))
            : null;

        $branches = GithubUnmatchedBranch::query()
            ->with(['repository:id,name,owner,repo', 'author:id,name,github_login,avatar_path'])
            ->when($request->filled('repo'), fn ($q) => $q->where('github_repository_id', $request->integer('repo')))
            ->when($selectedAuthor !== null, fn ($q) => filled($selectedAuthor->github_login)
                ? $q->whereRaw('LOWER(author_login) = ?', [mb_strtolower($selectedAuthor->github_login)])
                : $q->whereRaw('1 = 0'))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->string('q')->trim() . '%'))
            ->where('state', $request->input('state', 'active'))
            ->orderByDesc('last_commit_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('github.unmatched', [
            'branches' => $branches,
            'repositories' => GithubRepository::activeList(),
            'filterUsers' => User::query()->without('role')->active()
                ->whereNotNull('github_login')->orderBy('name')->get(['id', 'name', 'github_login']),
            'selectedAuthor' => $selectedAuthor,
            'writeConfigured' => app(GitHubWriteClient::class)->configured(),
        ]);
    }

    public function destroy(
        Request $request,
        GithubUnmatchedBranch $branch,
        GitHubWriteClient $github,
        ActivityLogger $activity,
    ): RedirectResponse {
        $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        if (! hash_equals($branch->name, (string) $request->input('confirmation'))) {
            return back()->withErrors(['delete_branch' => __('اسم الفرع المُدخل لا يطابق الفرع المطلوب حذفه.')]);
        }

        $branch->loadMissing('repository');
        $repository = $branch->repository;

        if ($repository === null || $branch->state !== BranchState::Active) {
            return back()->withErrors(['delete_branch' => __('الفرع غير موجود أو حُذف سابقًا.')]);
        }

        if ($branch->name === $repository->default_branch) {
            return back()->withErrors(['delete_branch' => __('لا يمكن حذف الفرع الافتراضي من نظام التذاكر.')]);
        }

        try {
            $github->deleteBranch($repository, $branch->name);
        } catch (RuntimeException $e) {
            return back()->withErrors(['delete_branch' => $e->getMessage()]);
        }

        $branch->forceFill([
            'state' => BranchState::Deleted,
            'deleted_detected_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        $activity->log(
            'github.branch.deleted',
            $request->user()->id,
            $branch,
            ['repository' => $repository->fullName(), 'branch' => $branch->name],
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('status', __('تم حذف الفرع :name من GitHub.', ['name' => $branch->name]));
    }
}
