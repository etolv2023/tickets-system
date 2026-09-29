<?php

namespace App\Http\Controllers;

use App\Enums\BranchState;
use App\Models\GithubRepository;
use App\Models\GithubUnmatchedBranch;
use App\Models\TicketBranch;
use App\Models\TicketPullRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\GitHubWriteClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'availableUsers' => User::query()->without('role')->active()
                ->whereNull('github_login')->orderBy('name')->get(['id', 'name']),
            'selectedAuthor' => $selectedAuthor,
            'githubLogins' => $this->availableGithubLogins(),
            'writeConfigured' => app(GitHubWriteClient::class)->configured(),
        ]);
    }

    public function linkAccount(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate([
            'github_login' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9-]+$/', Rule::in($this->discoveredLogins())],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
        ]);

        $login = trim((string) $data['github_login']);
        $user = DB::transaction(function () use ($data, $login): User {
            $user = User::query()->whereKey((int) $data['user_id'])->lockForUpdate()->firstOrFail();

            if (filled($user->github_login)) {
                throw ValidationException::withMessages([
                    'user_id' => 'الشخص ده مربوط بحساب GitHub بالفعل. فك الربط القديم الأول.',
                ]);
            }

            if (User::query()->whereRaw('LOWER(github_login) = ?', [mb_strtolower($login)])->exists()) {
                throw ValidationException::withMessages([
                    'github_login' => 'حساب GitHub ده مربوط بشخص بالفعل.',
                ]);
            }

            $user->forceFill(['github_login' => $login])->save();

            return $user;
        });

        $activity->log(
            'github.account.linked',
            $request->user()->id,
            $user,
            ['github_login' => ['before' => null, 'after' => $login]],
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('status', "اتربط @{$login} بـ {$user->name}. الربط هيستخدم في البرانشات والاكسبشنات.");
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
            return back()->withErrors(['delete_branch' => 'اسم البرانش المكتوب لا يطابق البرانش المطلوب حذفه.']);
        }

        $branch->loadMissing('repository');
        $repository = $branch->repository;

        if ($repository === null || $branch->state !== BranchState::Active) {
            return back()->withErrors(['delete_branch' => 'البرانش غير موجود أو اتمسح قبل كده.']);
        }

        if ($branch->name === $repository->default_branch) {
            return back()->withErrors(['delete_branch' => 'مينفعش تمسح الـ default branch من نظام التذاكر.']);
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

        return back()->with('status', "اتمسح البرانش {$branch->name} من GitHub.");
    }

    /** @return array<int, string> */
    private function discoveredLogins(): array
    {
        return collect([
            GithubUnmatchedBranch::query()->whereNotNull('author_login')->distinct()->pluck('author_login'),
            TicketBranch::query()->whereNotNull('author_login')->distinct()->pluck('author_login'),
            TicketPullRequest::query()->whereNotNull('author_login')->distinct()->pluck('author_login'),
        ])->flatten()
            ->filter(fn ($login) => is_string($login) && $login !== '')
            ->unique(fn ($login) => mb_strtolower($login))
            ->sort(fn ($a, $b) => strcasecmp($a, $b))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function availableGithubLogins(): array
    {
        $linked = User::query()->whereNotNull('github_login')->pluck('github_login')
            ->map(fn ($login) => mb_strtolower((string) $login))
            ->flip();

        return collect($this->discoveredLogins())
            ->reject(fn ($login) => $linked->has(mb_strtolower($login)))
            ->values()
            ->all();
    }
}
