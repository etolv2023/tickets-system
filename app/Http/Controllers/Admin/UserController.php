<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserSkill;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.users.index', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'role_id', 'skills', 'is_active', 'must_change_password', 'last_login_at', 'avatar_path'])
                ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")))
                ->when($request->query('role'), fn ($q, $role) => $q->where('role_id', $role))
                ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
                ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'roles' => Role::orderBy('id')->get(['id', 'name_ar']),
            'filters' => $request->only('q', 'role', 'status'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', $this->formData());
    }

    public function store(UserRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = User::create($request->safe()->except('password_confirmation'));

        $logger->log(
            action: 'user.created',
            userId: $request->user()->id,
            subject: $user,
            changes: ['to' => $user->only('name', 'email', 'role_id', 'skills', 'is_active')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.users.index')->with('status', "تم إضافة «{$user->name}».");
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', $this->formData() + ['user' => $user]);
    }

    public function update(UserRequest $request, User $user, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $user);

        // Deactivating happens here too, so the self-lockout guard applies.
        if (! $request->boolean('is_active') && $user->is_active) {
            $this->authorize('deactivate', $user);
        }

        $before = $user->only('name', 'email', 'role_id', 'skills', 'is_active');

        // An edit never silently changes a password — that's resetPassword's job.
        $user->update($request->safe()->except(['password', 'password_confirmation']));

        $logger->log(
            action: 'user.updated',
            userId: $request->user()->id,
            subject: $user,
            changes: ['from' => $before, 'to' => $user->only('name', 'email', 'role_id', 'skills', 'is_active')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.users.index')->with('status', 'تم حفظ التعديلات.');
    }

    /**
     * F22.3: reset to a random password + force a change on next login. The new
     * password is shown once, here, and never stored anywhere readable.
     */
    public function resetPassword(Request $request, User $user, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $password = Str::password(14);

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        $logger->log(
            action: 'user.password_reset',
            userId: $request->user()->id,
            subject: $user,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('generated_password', [
            'user' => $user->name,
            'password' => $password,
        ]);
    }

    private function formData(): array
    {
        return [
            'roles' => Role::orderBy('id')->get(['id', 'name_ar', 'key']),
            'skills' => UserSkill::options(),
        ];
    }
}
