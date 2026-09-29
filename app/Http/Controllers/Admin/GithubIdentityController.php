<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGithubRepository;
use App\Models\GithubRepository;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\GithubIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GithubIdentityController extends Controller
{
    public function index(GithubIdentityService $identities): View
    {
        return view('admin.github-identities.index', [
            'mappings' => User::query()->without('role')->whereNotNull('github_login')
                ->orderBy('name')->get(['id', 'name', 'email', 'github_login', 'is_active']),
            'availableUsers' => User::query()->without('role')->active()->whereNull('github_login')
                ->orderBy('name')->get(['id', 'name', 'email']),
            'availableLogins' => $identities->availableLogins(),
        ]);
    }

    public function store(Request $request, GithubIdentityService $identities, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate($this->rules($identities));
        $user = $this->saveMapping((int) $data['user_id'], (string) $data['github_login']);

        $activity->log('github.identity.linked', $request->user()->id, $user,
            ['github_login' => ['before' => null, 'after' => $user->github_login]],
            $request->ip(), $request->userAgent());

        return back()->with('status', 'اتحفظ ربط حساب GitHub بالمستخدم.');
    }

    public function update(
        Request $request,
        User $user,
        GithubIdentityService $identities,
        ActivityLogger $activity,
    ): RedirectResponse {
        $data = $request->validate([
            'github_login' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9-]+$/', Rule::in($identities->availableLogins($user))],
        ]);
        $before = $user->github_login;
        $updated = $this->saveMapping($user->id, (string) $data['github_login'], allowExistingUser: true);

        $activity->log('github.identity.updated', $request->user()->id, $updated,
            ['github_login' => ['before' => $before, 'after' => $updated->github_login]],
            $request->ip(), $request->userAgent());

        return back()->with('status', 'اتعدل الربط.');
    }

    public function destroy(Request $request, User $user, ActivityLogger $activity): RedirectResponse
    {
        $before = $user->github_login;
        $user->forceFill(['github_login' => null])->save();

        $activity->log('github.identity.unlinked', $request->user()->id, $user,
            ['github_login' => ['before' => $before, 'after' => null]],
            $request->ip(), $request->userAgent());

        return back()->with('status', 'اتفك ربط حساب GitHub من المستخدم.');
    }

    public function sync(): RedirectResponse
    {
        if (! config('github.enabled') || blank(config('github.token'))) {
            return back()->withErrors(['sync' => 'تكامل GitHub مقفول أو توكن القراءة ناقص.']);
        }

        foreach (GithubRepository::activeList() as $repository) {
            SyncGithubRepository::dispatch($repository->id);
        }

        return back()->with('status', 'مزامنة GitHub اتحطت في الطابور. حدّث الصفحة بعد ما الـ worker يخلص.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(GithubIdentityService $identities): array
    {
        return [
            'github_login' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9-]+$/', Rule::in($identities->availableLogins())],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('github_login')],
        ];
    }

    private function saveMapping(int $userId, string $login, bool $allowExistingUser = false): User
    {
        return DB::transaction(function () use ($userId, $login, $allowExistingUser): User {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            if (! $allowExistingUser && filled($user->github_login)) {
                throw ValidationException::withMessages(['user_id' => 'الشخص ده مربوط بحساب GitHub بالفعل.']);
            }
            if (User::query()->where('id', '!=', $user->id)
                ->whereRaw('LOWER(github_login) = ?', [mb_strtolower($login)])->exists()) {
                throw ValidationException::withMessages(['github_login' => 'حساب GitHub ده مربوط بشخص بالفعل.']);
            }

            $user->forceFill(['github_login' => trim($login)])->save();
            return $user;
        });
    }
}
