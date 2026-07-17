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
     * Two guards, both refusals a human can act on: system roles are permanent
     * (F22.3), and a role still holding users would orphan them.
     */
    public function delete(User $user, Role $role): Response
    {
        if (! $user->hasPermission('users.manage')) {
            return Response::deny('ليس لديك صلاحية لحذف الأدوار.');
        }

        if ($role->is_system) {
            return Response::deny('ده دور أساسي في النظام — مينفعش يتحذف.');
        }

        if ($role->users()->exists()) {
            return Response::deny('فيه مستخدمين على الدور ده. انقلهم لدور تاني الأول.');
        }

        return Response::allow();
    }
}
