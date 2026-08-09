<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorklogCompletionWaiver;
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
                ->select(['id', 'name', 'email', 'role_id', 'is_active', 'must_change_password', 'last_login_at', 'avatar_path'])
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

        // No permission picker on the create form: the role isn't saved yet, so
        // there is no baseline to diff exceptions against. A new user starts on
        // exactly their role's set and is edited afterwards if they need one.
        // Waiver fields excluded here too. The create form does not render that
        // card, so they should never arrive — but "should never arrive" and
        // "cannot reach fill()" are different guarantees, and only the second
        // one survives a hand-made POST.
        $user = User::create($request->safe()->except([
            'password_confirmation', 'permissions',
            'waivers_present', 'waiver_all', 'waivers',
        ]));

        $logger->log(
            action: 'user.created',
            userId: $request->user()->id,
            subject: $user,
            changes: ['to' => $user->only('name', 'email', 'role_id', 'is_active')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.users.index')->with('status', "تم إضافة «{$user->name}».");
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $rolePermissionIds = $user->role?->permissions()->pluck('permissions.id')->all() ?? [];

        return view('admin.users.edit', $this->formData() + [
            'user' => $user,
            'groups' => Permission::orderBy('id')->get()->groupBy('group'),
            // What the role gives, so the picker can mark those "من الدور".
            'rolePermissionIds' => $rolePermissionIds,
            // What this person can actually do today — role baseline with their
            // own exceptions already applied. The admin edits this, and
            // update() turns the difference back into exception rows.
            'effectivePermissionIds' => $this->effectivePermissionIds($user, $rolePermissionIds),
            // F07 waivers: who this person may keep waiting. A null counterpart
            // row is the "with everyone" wildcard.
            'waiverAll' => $user->completionWaivers()->whereNull('counterpart_user_id')->exists(),
            'waiverIds' => $user->completionWaivers()
                ->whereNotNull('counterpart_user_id')
                ->pluck('counterpart_user_id')->all(),
            // Everyone but themselves — a waiver against yourself is a no-op the
            // model drops anyway, so it should not be on the menu.
            'waiverCandidates' => User::active()->without('role')
                ->whereKeyNot($user->id)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * @param  array<int, int>  $rolePermissionIds
     * @return array<int, int>
     */
    private function effectivePermissionIds(User $user, array $rolePermissionIds): array
    {
        $overrides = $user->permissionOverrides()->pluck('granted', 'permissions.id');

        return Permission::orderBy('id')->pluck('id')
            ->filter(fn (int $id) => $overrides->has($id)
                ? (bool) $overrides[$id]
                : in_array($id, $rolePermissionIds, true))
            ->values()->all();
    }

    /**
     * A readable line for activity_logs. Letting one person keep another
     * waiting is a workflow decision with a name on it, so it belongs in the
     * audit trail next to the permission changes (§ 5).
     */
    private function waiverSummary(User $user): string
    {
        $rows = $user->completionWaivers()->with('counterpart:id,name')->get();

        if ($rows->isEmpty()) {
            return 'مفيش';
        }

        if ($rows->contains(fn ($r) => $r->counterpart_user_id === null)) {
            return 'مع الكل';
        }

        return $rows->map(fn ($r) => $r->counterpart?->name)->filter()->implode('، ');
    }

    public function update(UserRequest $request, User $user, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $user);

        // Deactivating happens here too, so the self-lockout guard applies.
        if (! $request->boolean('is_active') && $user->is_active) {
            $this->authorize('deactivate', $user);
        }

        $before = $user->only('name', 'email', 'role_id', 'is_active')
            + ['permissions' => $this->overrideSummary($user)]
            + ['waivers' => $this->waiverSummary($user)];

        // An edit never silently changes a password — that's resetPassword's job.
        // The waiver fields are excluded for the same reason `permissions` is:
        // they are not columns on users, so letting them reach fill() trips
        // mass-assignment protection ($fillable is doing its job, § 5). They are
        // written to their own table below.
        $user->update($request->safe()->except([
            'password', 'password_confirmation', 'permissions',
            'waivers_present', 'waiver_all', 'waivers',
        ]));

        // Absent (the create form, or any caller that doesn't render the picker)
        // means "don't touch the exceptions" — NOT "revoke everything".
        if ($request->has('permissions')) {
            $this->syncOverrides($user->refresh(), $request->validated('permissions') ?? []);
        }

        // Same contract as the permissions above: the waiver card only renders
        // on edit, so its absence means "this caller doesn't manage waivers",
        // never "clear them". waiver_all is a checkbox, and an unticked checkbox
        // posts nothing — so the card announces itself with a hidden marker.
        if ($request->has('waivers_present')) {
            WorklogCompletionWaiver::syncFor(
                $user->id,
                $request->boolean('waiver_all'),
                $request->validated('waivers') ?? [],
            );
        }

        $logger->log(
            action: 'user.updated',
            userId: $request->user()->id,
            subject: $user,
            changes: [
                'from' => $before,
                'to' => $user->only('name', 'email', 'role_id', 'is_active')
                    + ['permissions' => $this->overrideSummary($user->refresh())]
                    + ['waivers' => $this->waiverSummary($user)],
            ],
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

    /**
     * Turns the effective set the admin ticked back into exception rows: only
     * the permissions that DISAGREE with the role are stored. Ticking something
     * the role already grants writes nothing, and un-ticking it writes a revoke.
     *
     * The diff is recomputed against the role the user has AFTER this save, so
     * changing someone's role and their permissions in one submit lands on the
     * baseline the admin was actually looking at.
     *
     * @param  array<int, int|string>  $effectiveIds
     */
    private function syncOverrides(User $user, array $effectiveIds): void
    {
        $effectiveIds = array_map('intval', $effectiveIds);
        $roleIds = $user->role?->permissions()->pluck('permissions.id')->all() ?? [];

        $verdicts = [];

        foreach (Permission::orderBy('id')->pluck('id') as $id) {
            $wanted = in_array($id, $effectiveIds, true);

            if ($wanted !== in_array($id, $roleIds, true)) {
                $verdicts[$id] = $wanted;
            }
        }

        $user->syncPermissionOverrides($verdicts);
    }

    /**
     * The exceptions in a form the audit log can show: permission changes are a
     * sensitive edit and have to be traceable to whoever made them (§ 5).
     *
     * @return array<string, string>
     */
    private function overrideSummary(User $user): array
    {
        return $user->permissionOverrides()->get()
            ->mapWithKeys(fn (Permission $p) => [$p->key => $p->pivot->granted ? 'مزوّدة' : 'متشالة'])
            ->all();
    }

    private function formData(): array
    {
        return [
            'roles' => Role::orderBy('id')->get(['id', 'name_ar', 'key']),
        ];
    }
}
