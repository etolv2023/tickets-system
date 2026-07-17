<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            // withCount avoids a COUNT() per row in the view (CLAUDE.md § 4).
            'roles' => Role::query()
                ->withCount(['users', 'permissions'])
                ->orderByDesc('is_system')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', [
            'groups' => $this->permissionGroups(),
            'selected' => [],
        ]);
    }

    public function store(RoleRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = Role::create([
            'key' => $request->validated('key'),
            'name_ar' => $request->validated('name_ar'),
            'is_system' => false,
        ]);

        $role->syncPermissions($request->validated('permissions') ?? []);

        $logger->log(
            action: 'role.created',
            userId: $request->user()->id,
            subject: $role,
            changes: ['to' => $role->only('key', 'name_ar')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.roles.index')->with('status', "تم إنشاء دور «{$role->name_ar}».");
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role,
            'groups' => $this->permissionGroups(),
            'selected' => $role->permissions()->pluck('permissions.id')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('update', $role);

        $before = [
            'name_ar' => $role->name_ar,
            'permissions' => $role->permissions()->pluck('permissions.key')->sort()->values()->all(),
        ];

        // A system role's key is referenced in code paths and seeders; its name
        // and permissions stay editable.
        $role->update($role->is_system
            ? ['name_ar' => $request->validated('name_ar')]
            : $request->safe()->only('key', 'name_ar'));

        $role->syncPermissions($request->validated('permissions') ?? []);

        $after = [
            'name_ar' => $role->name_ar,
            'permissions' => $role->permissions()->pluck('permissions.key')->sort()->values()->all(),
        ];

        $logger->log(
            action: 'role.updated',
            userId: $request->user()->id,
            subject: $role,
            changes: ['from' => $before, 'to' => $after],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('admin.roles.index')->with('status', "تم تحديث دور «{$role->name_ar}».");
    }

    public function destroy(Request $request, Role $role, ActivityLogger $logger): RedirectResponse
    {
        $this->authorize('delete', $role);

        $name = $role->name_ar;

        $logger->log(
            action: 'role.deleted',
            userId: $request->user()->id,
            subject: $role,
            changes: ['from' => $role->only('key', 'name_ar')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', "تم حذف دور «{$name}».");
    }

    /** @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Permission>> */
    private function permissionGroups()
    {
        return Permission::orderBy('id')->get()->groupBy('group');
    }
}
