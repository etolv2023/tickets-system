<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Gate answers "can this role manage roles at all"; this answers "may it do
 * this to THIS role" (F00.3).
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('users.manage');
    }

    /**
     * ★ (2026-07-22) Any role — system included — is deletable once it holds
     * no users, by explicit request. A system role's key still can't be
     * renamed (RoleController::update()), and code that looks one up by key
     * (Role::idByKey('tester')/('devops') for the assignment dropdowns) just
     * finds nothing if it's gone — the same as any other role nobody uses.
     */
    public function delete(User $user, Role $role): Response
    {
        if (! $user->hasPermission('users.manage')) {
            return Response::deny('ليس لديك صلاحية لحذف الأدوار.');
        }

        if ($role->users()->exists()) {
            return Response::deny('فيه مستخدمين على الدور ده. انقلهم لدور تاني الأول.');
        }

        return Response::allow();
    }
}
